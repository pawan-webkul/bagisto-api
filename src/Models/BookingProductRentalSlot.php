<?php

namespace Webkul\BagistoApi\Models;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use Webkul\BookingProduct\Models\BookingProductRentalSlot as BaseModel;

#[ApiResource(routePrefix: '/api/shop', operations: [], graphQlOperations: [])]
class BookingProductRentalSlot extends BaseModel
{
    /**
     * Override to prevent API Platform from using Laravel's auto-generated getter for 'slots'
     */
    protected $casts = [];

    #[ApiProperty(writable: false, readable: true, required: false)]
    public function getRentingType()
    {
        return $this->renting_type;
    }

    #[ApiProperty(writable: false, readable: true, required: false)]
    public function getDailyPrice()
    {
        return $this->daily_price;
    }

    #[ApiProperty(writable: false, readable: true, required: false)]
    public function getHourlyPrice()
    {
        return $this->hourly_price;
    }

    #[ApiProperty(writable: false, readable: true, required: false)]
    public function getSameSlotAllDays()
    {
        return $this->same_slot_all_days;
    }

    #[ApiProperty(writable: false, readable: true, required: false)]
    public function getSlots()
    {
        return $this->slots;
    }

    #[ApiProperty(writable: false, readable: true, required: false)]
    public function getBookingProductId()
    {
        return $this->booking_product_id;
    }
}
