<?php

namespace Webkul\BagistoApi\Tests\Feature\GraphQL;

use Webkul\BagistoApi\Tests\GraphQLTestCase;

class AddToCartBundleProductTest extends GraphQLTestCase
{
    private function loginCustomerAndGetToken(): string
    {
        $customerData = $this->createTestCustomer();

        return $customerData['token'];
    }

    private function customerHeaders(string $token): array
    {
        return [
            'Authorization' => 'Bearer '.$token,
        ];
    }

    private function getGuestCartToken(): string
    {
        $mutation = <<<'GQL'
            mutation createCart {
              createCartToken(input: {}) {
                cartToken {
                  cartToken
                  success
                }
              }
            }
        GQL;

        $response = $this->graphQL($mutation);
        $response->assertSuccessful();

        $data = $response->json('data.createCartToken.cartToken');

        $this->assertNotNull($data, 'cartToken response is null');
        $this->assertTrue((bool) ($data['success'] ?? false));

        $token = $data['cartToken'] ?? null;
        $this->assertNotEmpty($token, 'guest cart token is missing');

        return $token;
    }

    private function guestHeaders(string $token): array
    {
        return [
            'Authorization' => 'Bearer '.$token,
        ];
    }

    private function createBundleProductPayload(): array
    {
        $bundle = $this->createBaseProduct('bundle');

        // Ensure inventory exists for the bundle product
        $this->ensureInventory($bundle, 50);

        // Disable manage stock for the bundle product so inventory check passes
        $this->upsertProductAttributeValue($bundle->id, 'manage_stock', 0, null, 'default');

        // Refresh bundle from database to get updated attribute values
        $bundle = \Webkul\Product\Models\Product::find($bundle->id);

        $optionId = (int) \Illuminate\Support\Facades\DB::table('product_bundle_options')->insertGetId([
            'product_id'   => $bundle->id,
            'type'         => 'checkbox',
            'is_required'  => 1,
            'sort_order'   => 1,
        ]);

        $optionProduct = $this->createBaseProduct('simple', [
            'sku' => 'TEST-BUNDLE-OPT-'.$bundle->id.'-1',
        ]);

        // Ensure inventory exists for the option product
        $this->ensureInventory($optionProduct, 50);

        // Disable manage stock for the option product so inventory check passes
        $this->upsertProductAttributeValue($optionProduct->id, 'manage_stock', 0, null, 'default');

        // Refresh option product from database to get updated attribute values
        $optionProduct = \Webkul\Product\Models\Product::find($optionProduct->id);

        // Also set price for the option product
        $this->upsertProductAttributeValue($optionProduct->id, 'price', 10.00, null, 'default');

        $bundleOptionProductId = \Illuminate\Support\Facades\DB::table('product_bundle_option_products')->insertGetId([
            'product_id'               => $optionProduct->id,
            'product_bundle_option_id' => $optionId,
            'qty'                      => 1,
            'is_user_defined'          => 1,
            'is_default'               => 1,
            'sort_order'               => 1,
        ]);

        $bundleOptions = [
            (string) $optionId => [(int) $bundleOptionProductId],
        ];

        $bundleOptionQty = [
            (string) $optionId => 1,
        ];

        return [
            'productId'       => (int) $bundle->id,
            'bundleOptions'   => json_encode($bundleOptions, JSON_UNESCAPED_SLASHES),
            'bundleOptionQty' => json_encode($bundleOptionQty, JSON_UNESCAPED_SLASHES),
        ];
    }

    public function test_create_add_bundle_product_in_cart_as_guest(): void
    {
        $token = $this->getGuestCartToken();
        $headers = $this->guestHeaders($token);

        $payload = $this->createBundleProductPayload();

        $mutation = <<<'GQL'
            mutation createAddProductInCart(
              $productId: Int!
              $quantity: Int!
              $bundleOptions: String
              $bundleOptionQty: String
            ) {
              createAddProductInCart(
                input: {
                  productId: $productId
                  quantity: $quantity
                  bundleOptions: $bundleOptions
                  bundleOptionQty: $bundleOptionQty
                }
              ) {
                addProductInCart {
                  id
                  cartToken
                  success
                  isGuest
                  itemsCount
                  items {
                    edges {
                      node {
                        id
                        productId
                        quantity
                        type
                      }
                    }
                  }
                }
              }
            }
        GQL;

        $response = $this->graphQL($mutation, [
            'productId'       => $payload['productId'],
            'quantity'        => 1,
            'bundleOptions'   => $payload['bundleOptions'],
            'bundleOptionQty' => $payload['bundleOptionQty'],
        ], $headers);

        $response->assertSuccessful();

        $json = $response->json();
        if (isset($json['errors'])) {
            $this->fail('GraphQL returned errors while adding bundle product to cart: '.json_encode($json['errors']));
        }

        $data = $response->json('data.createAddProductInCart.addProductInCart');

        $this->assertNotNull($data);
        $this->assertTrue((bool) ($data['success'] ?? false));
        $this->assertTrue((bool) ($data['isGuest'] ?? false));
        $this->assertGreaterThan(0, (int) ($data['itemsCount'] ?? 0));
    }

    public function test_create_add_bundle_product_in_cart_as_customer(): void
    {
        $token = $this->loginCustomerAndGetToken();
        $headers = $this->customerHeaders($token);

        $payload = $this->createBundleProductPayload();

        $mutation = <<<'GQL'
            mutation createAddProductInCart(
              $productId: Int!
              $quantity: Int!
              $bundleOptions: String
              $bundleOptionQty: String
            ) {
              createAddProductInCart(
                input: {
                  productId: $productId
                  quantity: $quantity
                  bundleOptions: $bundleOptions
                  bundleOptionQty: $bundleOptionQty
                }
              ) {
                addProductInCart {
                  id
                  customerId
                  success
                  isGuest
                  itemsCount
                  items {
                    edges {
                      node {
                        id
                        productId
                        quantity
                        type
                      }
                    }
                  }
                }
              }
            }
        GQL;

        $response = $this->graphQL($mutation, [
            'productId'       => $payload['productId'],
            'quantity'        => 1,
            'bundleOptions'   => $payload['bundleOptions'],
            'bundleOptionQty' => $payload['bundleOptionQty'],
        ], $headers);

        $response->assertSuccessful();

        $json = $response->json();
        if (isset($json['errors'])) {
            $this->fail('GraphQL returned errors while adding bundle product to cart as customer: '.json_encode($json['errors']));
        }

        $data = $response->json('data.createAddProductInCart.addProductInCart');

        $this->assertNotNull($data);
        $this->assertTrue((bool) ($data['success'] ?? false));
        $this->assertFalse((bool) ($data['isGuest'] ?? true));
        $this->assertNotNull($data['customerId'] ?? null);
        $this->assertGreaterThan(0, (int) ($data['itemsCount'] ?? 0));
    }
}
