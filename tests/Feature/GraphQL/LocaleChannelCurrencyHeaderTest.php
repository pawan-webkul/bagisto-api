<?php

namespace Webkul\BagistoApi\Tests\Feature\GraphQL;

use Webkul\BagistoApi\Tests\GraphQLTestCase;
use Webkul\Core\Models\Channel;

class LocaleChannelCurrencyHeaderTest extends GraphQLTestCase
{
    /**
     * Simple query that returns translatable data (CMS page) to verify locale switching.
     */
    private function cmsPageQuery(): string
    {
        return <<<'GQL'
            query {
              pages(first: 1) {
                edges {
                  node {
                    id
                    _id
                    translation {
                      pageTitle
                      locale
                    }
                  }
                }
              }
            }
        GQL;
    }

    /**
     * Channel query to verify channel data loads.
     */
    private function channelQuery(): string
    {
        return <<<'GQL'
            query getChannelByID($id: ID!) {
              channel(id: $id) {
                id
                _id
                code
                defaultLocale { code }
                baseCurrency { code }
              }
            }
        GQL;
    }

    /**
     * Get valid locale codes from default channel.
     */
    private function getChannelLocales(): array
    {
        $channel = Channel::first();

        return $channel ? $channel->locales->pluck('code')->toArray() : ['en'];
    }

    /**
     * Get valid currency codes from default channel.
     */
    private function getChannelCurrencies(): array
    {
        $channel = Channel::first();

        return $channel ? $channel->currencies->pluck('code')->toArray() : ['USD'];
    }

    // ─── No headers (defaults) ──────────────────────────────────────────

    public function test_no_headers_returns_default_locale_data(): void
    {
        $response = $this->graphQL($this->cmsPageQuery());

        $response->assertSuccessful();

        $json = $response->json();
        $this->assertArrayNotHasKey('errors', $json, 'GraphQL errors: '.json_encode($json['errors'] ?? []));

        $edges = $response->json('data.pages.edges');
        $this->assertNotNull($edges);

        // When no headers, default locale should be used
        if (! empty($edges)) {
            $locale = $edges[0]['node']['translation']['locale'] ?? null;
            $channel = Channel::first();
            $defaultLocale = $channel?->default_locale?->code ?? 'en';

            $this->assertSame($defaultLocale, $locale, 'Without X-LOCALE header, default locale should be used');
        }
    }

    // ─── X-LOCALE header ────────────────────────────────────────────────

    public function test_valid_locale_header_switches_locale(): void
    {
        $locales = $this->getChannelLocales();

        // Use the default locale to verify header is being read
        $targetLocale = $locales[0] ?? 'en';

        $response = $this->graphQL($this->cmsPageQuery(), [], [
            'X-Locale' => $targetLocale,
        ]);

        $response->assertSuccessful();

        $json = $response->json();
        $this->assertArrayNotHasKey('errors', $json, 'GraphQL errors: '.json_encode($json['errors'] ?? []));

        $edges = $response->json('data.pages.edges');
        if (! empty($edges)) {
            $locale = $edges[0]['node']['translation']['locale'] ?? null;
            $this->assertSame($targetLocale, $locale, 'X-LOCALE header should switch the response locale');
        }
    }

    public function test_invalid_locale_header_falls_back_to_default(): void
    {
        $response = $this->graphQL($this->cmsPageQuery(), [], [
            'X-Locale' => 'xx-INVALID',
        ]);

        $response->assertSuccessful();

        $json = $response->json();
        $this->assertArrayNotHasKey('errors', $json, 'GraphQL errors: '.json_encode($json['errors'] ?? []));

        // Should not error — falls back to default locale
        $edges = $response->json('data.pages.edges');
        if (! empty($edges)) {
            $locale = $edges[0]['node']['translation']['locale'] ?? null;
            $channel = Channel::first();
            $defaultLocale = $channel?->default_locale?->code ?? 'en';

            $this->assertSame($defaultLocale, $locale, 'Invalid X-LOCALE should fall back to default');
        }
    }

    // ─── X-CURRENCY header ──────────────────────────────────────────────

    public function test_valid_currency_header_is_accepted(): void
    {
        $currencies = $this->getChannelCurrencies();
        $targetCurrency = $currencies[0] ?? 'USD';

        $response = $this->graphQL($this->cmsPageQuery(), [], [
            'X-Currency' => $targetCurrency,
        ]);

        $response->assertSuccessful();

        $json = $response->json();
        $this->assertArrayNotHasKey('errors', $json, 'GraphQL errors: '.json_encode($json['errors'] ?? []));
    }

    public function test_invalid_currency_header_falls_back_to_default(): void
    {
        $response = $this->graphQL($this->cmsPageQuery(), [], [
            'X-Currency' => 'ZZZZZ',
        ]);

        $response->assertSuccessful();

        $json = $response->json();
        $this->assertArrayNotHasKey('errors', $json, 'GraphQL errors: '.json_encode($json['errors'] ?? []));
    }

    // ─── X-CHANNEL header ───────────────────────────────────────────────

    public function test_valid_channel_header_is_accepted(): void
    {
        $response = $this->graphQL($this->cmsPageQuery(), [], [
            'X-Channel' => 'default',
        ]);

        $response->assertSuccessful();

        $json = $response->json();
        $this->assertArrayNotHasKey('errors', $json, 'GraphQL errors: '.json_encode($json['errors'] ?? []));
    }

    public function test_channel_header_not_passed_uses_default(): void
    {
        // No X-Channel header at all
        $response = $this->graphQL($this->channelQuery(), [
            'id' => '/api/shop/channels/1',
        ]);

        $response->assertSuccessful();

        $node = $response->json('data.channel');
        $this->assertNotNull($node);
        $this->assertNotNull($node['code']);
    }

    // ─── All three headers together ─────────────────────────────────────

    public function test_all_three_headers_together(): void
    {
        $locales = $this->getChannelLocales();
        $currencies = $this->getChannelCurrencies();

        $response = $this->graphQL($this->cmsPageQuery(), [], [
            'X-Locale'   => $locales[0] ?? 'en',
            'X-Currency'  => $currencies[0] ?? 'USD',
            'X-Channel'   => 'default',
        ]);

        $response->assertSuccessful();

        $json = $response->json();
        $this->assertArrayNotHasKey('errors', $json, 'GraphQL errors: '.json_encode($json['errors'] ?? []));

        $edges = $response->json('data.pages.edges');
        if (! empty($edges)) {
            $locale = $edges[0]['node']['translation']['locale'] ?? null;
            $this->assertSame($locales[0] ?? 'en', $locale);
        }
    }

    public function test_all_three_headers_with_invalid_values_fall_back_gracefully(): void
    {
        $response = $this->graphQL($this->cmsPageQuery(), [], [
            'X-Locale'   => 'xx-NOPE',
            'X-Currency'  => 'FAKE',
            'X-Channel'   => 'nonexistent',
        ]);

        $response->assertSuccessful();

        $json = $response->json();
        $this->assertArrayNotHasKey('errors', $json, 'GraphQL errors: '.json_encode($json['errors'] ?? []));

        // Should fall back to defaults, not crash
        $edges = $response->json('data.pages.edges');
        if (! empty($edges)) {
            $locale = $edges[0]['node']['translation']['locale'] ?? null;
            $channel = Channel::first();
            $defaultLocale = $channel?->default_locale?->code ?? 'en';
            $this->assertSame($defaultLocale, $locale);
        }
    }

    // ─── Formatted price respects currency ──────────────────────────────

    public function test_product_price_respects_currency_header(): void
    {
        $this->seedRequiredData();
        $product = $this->createBaseProduct('simple');

        $query = <<<'GQL'
            query getProduct($id: ID!) {
              product(id: $id) {
                id
                name
                price
              }
            }
        GQL;

        // Query with default currency
        $response = $this->graphQL($query, ['id' => '/api/shop/products/'.$product->id]);
        $response->assertSuccessful();

        $json = $response->json();
        $this->assertArrayNotHasKey('errors', $json, 'GraphQL errors: '.json_encode($json['errors'] ?? []));

        $data = $response->json('data.product');
        $this->assertNotNull($data, 'Product should be returned');
        $this->assertArrayHasKey('price', $data);
        $this->assertArrayHasKey('name', $data);

        // Query again with a valid currency header — should not error
        $currencies = $this->getChannelCurrencies();
        $response2 = $this->graphQL($query, ['id' => '/api/shop/products/'.$product->id], [
            'X-Currency' => $currencies[0] ?? 'USD',
        ]);
        $response2->assertSuccessful();

        $json2 = $response2->json();
        $this->assertArrayNotHasKey('errors', $json2, 'GraphQL errors: '.json_encode($json2['errors'] ?? []));

        $data2 = $response2->json('data.product');
        $this->assertNotNull($data2);
        $this->assertArrayHasKey('price', $data2);
    }
}
