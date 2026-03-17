<?php

namespace Webkul\BagistoApi\Tests\Feature\GraphQL;

use Illuminate\Support\Facades\DB;
use Webkul\BagistoApi\Tests\GraphQLTestCase;

class AddToCartConfigurableProductTest extends GraphQLTestCase
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

    /**
     * Get guest cart token from the createCart mutation response.
     */
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

    /**
     * Find a configurable product + in-stock variant and build the payload expected by the GraphQL mutation.
     *
     * @return array{productId:int,selectedConfigurableOption:int,superAttribute:array<int,array{key:string,value:int}>}
     */
    private function createConfigurableProductPayload(): array
    {
        $this->seedRequiredData();

        $attributes = \Webkul\Attribute\Models\Attribute::query()
            ->where('is_configurable', 1)
            ->where('type', 'select')
            ->orderBy('id')
            ->limit(2)
            ->get();

        if ($attributes->isEmpty()) {
            $this->markTestSkipped('No configurable select attributes found. Run Bagisto seeders for attributes like color/size.');
        }

        $parent = $this->createBaseProduct('configurable', [
            'sku' => 'TEST-CONFIG-PARENT-'.uniqid(),
        ]);
        $this->ensureInventory($parent, 50);
        $this->upsertProductAttributeValue($parent->id, 'weight', 1.5, null, 'default');

        $child = $this->createBaseProduct('simple', [
            'sku'       => 'TEST-CONFIG-CHILD-'.uniqid(),
            'parent_id' => $parent->id,
        ]);
        $this->ensureInventory($child, 50);

        // Disable manage stock for the child product so inventory check passes
        $this->upsertProductAttributeValue($child->id, 'manage_stock', 0, null, 'default');
        $this->upsertProductAttributeValue($child->id, 'weight', 1.5, null, 'default');

        DB::table('product_relations')->insert([
            'parent_id' => $parent->id,
            'child_id'  => $child->id,
        ]);

        $superAttribute = [];

        foreach ($attributes as $attribute) {
            $attributeId = (int) $attribute->id;
            $optionId = $this->createAttributeOption($attributeId, 'Opt-'.$child->sku);

            DB::table('product_super_attributes')->insert([
                'product_id'   => $parent->id,
                'attribute_id' => $attributeId,
            ]);

            $this->upsertProductAttributeValue($child->id, (string) $attribute->code, $optionId, null, 'default');

            $superAttribute[] = [
                'key'   => (string) $attributeId,
                'value' => (int) $optionId,
            ];
        }

        return [
            'productId'                 => (int) $parent->id,
            'selectedConfigurableOption' => (int) $child->id,
            'superAttribute'            => $superAttribute,
        ];
    }

    /**
     * Add Configurable Product In Cart (Guest)
     */
    public function test_create_add_configurable_product_in_cart_as_guest(): void
    {
        $token = $this->getGuestCartToken();
        $headers = $this->guestHeaders($token);

        $payload = $this->createConfigurableProductPayload();

        $mutation = <<<'GQL'
            mutation createAddProductInCart(
              $productId: Int!
              $quantity: Int!
              $selectedConfigurableOption: Int!
              $superAttribute: Iterable
            ) {
              createAddProductInCart(
                input: {
                  productId: $productId
                  quantity: $quantity
                  selectedConfigurableOption: $selectedConfigurableOption
                  superAttribute: $superAttribute
                }
              ) {
                addProductInCart {
                  id
                  _id
                  cartToken
                  success
                  message
                  isGuest
                  itemsQty
                  itemsCount
                  haveStockableItems
                  items {
                    totalCount
                    edges {
                      node {
                        id
                        productId
                        sku
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
            'productId'                 => $payload['productId'],
            'quantity'                  => 1,
            'selectedConfigurableOption' => $payload['selectedConfigurableOption'],
            'superAttribute'            => $payload['superAttribute'],
        ], $headers);

        $response->assertSuccessful();

        $json = $response->json();
        if (isset($json['errors'])) {
            $this->fail('GraphQL returned errors while adding configurable product to cart: '.json_encode($json['errors']));
        }

        $data = $response->json('data.createAddProductInCart.addProductInCart');

        $this->assertNotNull($data);
        $this->assertTrue((bool) ($data['success'] ?? false));
        $this->assertTrue((bool) ($data['isGuest'] ?? false));
        $this->assertGreaterThan(0, (int) ($data['itemsCount'] ?? 0));

        $firstItem = $data['items']['edges'][0]['node'] ?? null;
        $this->assertNotNull($firstItem, 'Cart item node is missing');
        $this->assertSame(1, (int) ($firstItem['quantity'] ?? 0));

        $productId = (int) ($firstItem['productId'] ?? 0);
        $this->assertTrue(
            in_array($productId, [(int) $payload['productId'], (int) $payload['selectedConfigurableOption']], true),
            'Cart item productId did not match either the configurable parent or selected variant.'
        );
    }

    public function test_create_add_configurable_product_in_cart_as_customer(): void
    {
        $token = $this->loginCustomerAndGetToken();
        $headers = $this->customerHeaders($token);

        $payload = $this->createConfigurableProductPayload();

        $mutation = <<<'GQL'
            mutation createAddProductInCart(
              $productId: Int!
              $quantity: Int!
              $selectedConfigurableOption: Int!
              $superAttribute: Iterable
            ) {
              createAddProductInCart(
                input: {
                  productId: $productId
                  quantity: $quantity
                  selectedConfigurableOption: $selectedConfigurableOption
                  superAttribute: $superAttribute
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
            'productId'                 => $payload['productId'],
            'quantity'                  => 1,
            'selectedConfigurableOption' => $payload['selectedConfigurableOption'],
            'superAttribute'            => $payload['superAttribute'],
        ], $headers);

        $response->assertSuccessful();

        $json = $response->json();
        if (isset($json['errors'])) {
            $this->fail('GraphQL returned errors while adding configurable product to cart as customer: '.json_encode($json['errors']));
        }

        $data = $response->json('data.createAddProductInCart.addProductInCart');

        $this->assertNotNull($data);
        $this->assertTrue((bool) ($data['success'] ?? false));
        $this->assertFalse((bool) ($data['isGuest'] ?? true));
        $this->assertNotNull($data['customerId'] ?? null);
        $this->assertGreaterThan(0, (int) ($data['itemsCount'] ?? 0));
    }
}
