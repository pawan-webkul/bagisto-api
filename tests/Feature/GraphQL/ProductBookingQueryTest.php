<?php

namespace Webkul\BagistoApi\Tests\Feature\GraphQL;

use Illuminate\Support\Facades\DB;
use Webkul\BagistoApi\Tests\GraphQLTestCase;
use Webkul\BookingProduct\Models\BookingProduct;
use Webkul\BookingProduct\Models\BookingProductDefaultSlot;
use Webkul\BookingProduct\Models\BookingProductAppointmentSlot;
use Webkul\BookingProduct\Models\BookingProductTableSlot;
use Webkul\BookingProduct\Models\BookingProductRentalSlot;
use Webkul\BookingProduct\Models\BookingProductEventTicket;
use Carbon\Carbon;

class ProductBookingQueryTest extends GraphQLTestCase
{
    /**
     * Test querying appointment booking product
     */
    public function test_get_appointment_booking_product(): void
    {
        $bookingData = $this->createBookingProductFixture('appointment');

        $query = <<<'GQL'
            query getProduct($id: ID!) {
              product(id: $id) {
                id
                name
                sku
                urlKey
                price
                bookingProducts {
                  edges {
                    node {
                      _id
                      type
                      appointmentSlot {
                        id
                        _id
                        bookingProductId
                        duration
                        breakTime
                        sameSlotAllDays
                        slots
                      }
                    }
                  }
                }
              }
            }
        GQL;

        $response = $this->graphQL($query, [
            'id' => (string) $bookingData['product']->id,
        ]);

        $response->assertSuccessful();

        $data = $response->json('data.product');

        $this->assertNotNull($data);
        $this->assertArrayHasKey('bookingProducts', $data);
    }

    /**
     * Test querying rental booking product
     */
    public function test_get_rental_booking_product(): void
    {
        $bookingData = $this->createBookingProductFixture('rental');

        $query = <<<'GQL'
            query getProduct($id: ID!) {
              product(id: $id) {
                id
                name
                sku
                urlKey
                price
                bookingProducts {
                  edges {
                    node {
                      _id
                      type
                      rentalSlot {
                        id
                        _id
                        bookingProductId
                        rentingType
                        dailyPrice
                        hourlyPrice
                        sameSlotAllDays
                        slots
                      }
                    }
                  }
                }
              }
            }
        GQL;

        $response = $this->graphQL($query, [
            'id' => (string) $bookingData['product']->id,
        ]);

        $response->assertSuccessful();

        $data = $response->json('data.product');

        $this->assertNotNull($data);
        $this->assertArrayHasKey('bookingProducts', $data);
    }

    /**
     * Test querying default booking product
     */
    public function test_get_default_booking_product(): void
    {
        $bookingData = $this->createBookingProductFixture('default');

        $query = <<<'GQL'
            query getProduct($id: ID!) {
              product(id: $id) {
                id
                name
                sku
                urlKey
                price
                bookingProducts {
                  edges {
                    node {
                      _id
                      type
                      defaultSlot {
                        id
                        _id
                        bookingType
                        duration
                        breakTime
                        slots
                      }
                    }
                  }
                }
              }
            }
        GQL;

        $response = $this->graphQL($query, [
            'id' => (string) $bookingData['product']->id,
        ]);

        $response->assertSuccessful();

        $data = $response->json('data.product');

        $this->assertNotNull($data);
        $this->assertArrayHasKey('bookingProducts', $data);
    }

    /**
     * Test querying table booking product
     */
    public function test_get_table_booking_product(): void
    {
        $bookingData = $this->createBookingProductFixture('table');

        $query = <<<'GQL'
            query getProduct($id: ID!) {
              product(id: $id) {
                id
                name
                sku
                urlKey
                price
                bookingProducts {
                  edges {
                    node {
                      _id
                      type
                      tableSlot {
                        id
                        _id
                        bookingProductId
                        priceType
                        guestLimit
                        duration
                        breakTime
                        preventSchedulingBefore
                        sameSlotAllDays
                        slots
                      }
                    }
                  }
                }
              }
            }
        GQL;

        $response = $this->graphQL($query, [
            'id' => (string) $bookingData['product']->id,
        ]);

        $response->assertSuccessful();

        $data = $response->json('data.product');

        $this->assertNotNull($data);
        $this->assertArrayHasKey('bookingProducts', $data);
    }

    /**
     * Test querying event booking product
     */
    public function test_get_event_booking_product(): void
    {
        $bookingData = $this->createBookingProductFixture('event');

        $query = <<<'GQL'
            query getProduct($id: ID!) {
              product(id: $id) {
                id
                name
                sku
                urlKey
                price
                bookingProducts {
                  edges {
                    node {
                      _id
                      type
                      eventTickets {
                        edges {
                          node {
                            id
                            _id
                            bookingProductId
                            price
                            qty
                            specialPrice
                            specialPriceFrom
                            specialPriceTo
                          }
                        }
                      }
                    }
                  }
                }
              }
            }
        GQL;

        $response = $this->graphQL($query, [
            'id' => (string) $bookingData['product']->id,
        ]);

        $response->assertSuccessful();

        $data = $response->json('data.product');

        $this->assertNotNull($data);
        $this->assertArrayHasKey('bookingProducts', $data);
    }

    /**
     * Helper method to create booking product fixtures
     */
    private function createBookingProductFixture(string $bookingType): array
    {
        $product = $this->createBaseProduct('booking', [
            'sku' => 'TEST-BOOKING-QUERY-'.$bookingType.'-'.uniqid(),
        ]);

        $this->ensureInventory($product, 100);

        $booking = BookingProduct::query()->create([
            'product_id'           => $product->id,
            'type'                 => $bookingType,
            'qty'                  => 100,
            'available_every_week' => 1,
            'available_from'       => $bookingType === 'event' ? Carbon::now()->addDay()->format('Y-m-d H:i:s') : null,
            'available_to'         => $bookingType === 'event' ? Carbon::now()->addMonth()->format('Y-m-d H:i:s') : null,
        ]);

        $tomorrow = Carbon::now()->addDay()->format('Y-m-d');
        $weekday = (int) Carbon::parse($tomorrow)->format('w');

        if ($bookingType === 'default') {
            BookingProductDefaultSlot::query()->create([
                'booking_product_id' => $booking->id,
                'booking_type'       => 'many',
                'duration'           => 30,
                'break_time'         => 0,
                'slots'              => [
                    (string) $weekday => [
                        ['from' => '09:00', 'to' => '10:00', 'qty' => 10, 'status' => 1],
                    ],
                ],
            ]);
        } elseif ($bookingType === 'appointment') {
            BookingProductAppointmentSlot::query()->create([
                'booking_product_id' => $booking->id,
                'duration'           => 30,
                'break_time'         => 0,
                'same_slot_all_days' => 1,
                'slots'              => [
                    ['from' => '09:00', 'to' => '10:00', 'qty' => 10, 'status' => 1],
                ],
            ]);
        } elseif ($bookingType === 'table') {
            BookingProductTableSlot::query()->create([
                'booking_product_id'        => $booking->id,
                'price_type'                => 'table',
                'guest_limit'               => 1,
                'duration'                  => 30,
                'break_time'                => 0,
                'prevent_scheduling_before' => 0,
                'same_slot_all_days'        => 1,
                'slots'                     => [
                    ['from' => '09:00', 'to' => '10:00', 'qty' => 10, 'status' => 1],
                ],
            ]);
        } elseif ($bookingType === 'rental') {
            BookingProductRentalSlot::query()->create([
                'booking_product_id' => $booking->id,
                'renting_type'       => 'daily',
                'daily_price'        => 10,
                'hourly_price'       => 0,
                'same_slot_all_days' => 1,
                'slots'              => [],
            ]);
        } elseif ($bookingType === 'event') {
            /** @var BookingProductEventTicket $ticket */
            $ticket = BookingProductEventTicket::query()->create([
                'booking_product_id'   => $booking->id,
                'price'                => 10,
                'qty'                  => 100,
                'special_price_from'   => Carbon::now()->subDay()->format('Y-m-d H:i:s'),
                'special_price_to'     => Carbon::now()->addMonth()->format('Y-m-d H:i:s'),
            ]);

            DB::table('booking_product_event_ticket_translations')->insert([
                'booking_product_event_ticket_id' => $ticket->id,
                'locale'                          => 'en',
                'name'                            => 'Test Ticket',
                'description'                     => 'Test Ticket Description',
            ]);
        }

        return [
            'product'      => $product,
            'booking'      => $booking,
            'tomorrowDate' => $tomorrow,
        ];
    }
}
