<?php

namespace Webkul\BagistoApi\Tests\Feature\GraphQL;

use Webkul\BagistoApi\Tests\GraphQLTestCase;

class AddToCartGroupedProductTest extends GraphQLTestCase
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

    private function createGroupedProductPayload(int $associatedCount = 2): array
    {
        $grouped = $this->createBaseProduct('grouped');
        $this->ensureInventory($grouped, 50);

        $qtyMap = [];

        for ($i = 1; $i <= $associatedCount; $i++) {
            $associated = $this->createBaseProduct('simple', [
                'sku' => 'TEST-GROUPED-ASSOC-'.$grouped->id.'-'.$i,
            ]);
            $this->ensureInventory($associated, 50);

            // Disable manage stock for the associated product so inventory check passes
            $this->upsertProductAttributeValue($associated->id, 'manage_stock', 0, null, 'default');

            \Illuminate\Support\Facades\DB::table('product_grouped_products')->insert([
                'product_id'            => $grouped->id,
                'associated_product_id' => $associated->id,
                'qty'                   => 1,
                'sort_order'            => $i,
            ]);

            $qtyMap[(string) $associated->id] = 1;
        }

        return [
            'productId'  => (int) $grouped->id,
            'groupedQty' => json_encode($qtyMap, JSON_UNESCAPED_SLASHES),
        ];
    }

    public function test_create_add_grouped_product_in_cart_as_guest(): void
    {
        $token = $this->getGuestCartToken();
        $headers = $this->guestHeaders($token);

        $payload = $this->createGroupedProductPayload();

        $mutation = <<<'GQL'
            mutation createAddProductInCart(
              $productId: Int!
              $quantity: Int!
              $groupedQty: String
            ) {
              createAddProductInCart(
                input: {
                  productId: $productId
                  quantity: $quantity
                  groupedQty: $groupedQty
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
            'productId'  => $payload['productId'],
            'quantity'   => 1,
            'groupedQty' => $payload['groupedQty'],
        ], $headers);

        $response->assertSuccessful();

        $json = $response->json();
        if (isset($json['errors'])) {
            $this->fail('GraphQL returned errors while adding grouped product to cart: '.json_encode($json['errors']));
        }

        $data = $response->json('data.createAddProductInCart.addProductInCart');

        $this->assertNotNull($data);
        $this->assertTrue((bool) ($data['success'] ?? false));
        $this->assertTrue((bool) ($data['isGuest'] ?? false));
        $this->assertGreaterThan(0, (int) ($data['itemsCount'] ?? 0));
    }

    public function test_create_add_grouped_product_in_cart_as_customer(): void
    {
        $token = $this->loginCustomerAndGetToken();
        $headers = $this->customerHeaders($token);

        $payload = $this->createGroupedProductPayload();

        $mutation = <<<'GQL'
            mutation createAddProductInCart(
              $productId: Int!
              $quantity: Int!
              $groupedQty: String
            ) {
              createAddProductInCart(
                input: {
                  productId: $productId
                  quantity: $quantity
                  groupedQty: $groupedQty
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
            'productId'  => $payload['productId'],
            'quantity'   => 1,
            'groupedQty' => $payload['groupedQty'],
        ], $headers);

        $response->assertSuccessful();

        $json = $response->json();
        if (isset($json['errors'])) {
            $this->fail('GraphQL returned errors while adding grouped product to cart as customer: '.json_encode($json['errors']));
        }

        $data = $response->json('data.createAddProductInCart.addProductInCart');

        $this->assertNotNull($data);
        $this->assertTrue((bool) ($data['success'] ?? false));
        $this->assertFalse((bool) ($data['isGuest'] ?? true));
        $this->assertNotNull($data['customerId'] ?? null);
        $this->assertGreaterThan(0, (int) ($data['itemsCount'] ?? 0));
    }
}
