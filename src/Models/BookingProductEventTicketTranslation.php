<?php

namespace Webkul\BagistoApi\Models;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use Webkul\BookingProduct\Models\BookingProductEventTicketTranslation as BaseModel;

#[ApiResource(routePrefix: '/api/shop', operations: [], graphQlOperations: [])]
class BookingProductEventTicketTranslation extends BaseModel
{
    #[ApiProperty(writable: false, readable: true, required: false)]
    public function getName()
    {
        return $this->name;
    }

    #[ApiProperty(writable: false, readable: true, required: false)]
    public function getDescription()
    {
        return $this->description;
    }

    #[ApiProperty(writable: false, readable: true, required: false)]
    public function getLocale()
    {
        return $this->locale;
    }
}
