<?php

namespace Webkul\BagistoApi\Dto;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Move Wishlist to Cart Output DTO
 */
class MoveWishlistToCartOutput
{
    #[ApiProperty(identifier: true)]
    public ?int $id = null;

    #[Groups(['query', 'mutation'])]
    #[ApiProperty(description: 'Whether the operation was successful')]
    public ?bool $success = null;

    #[Groups(['query', 'mutation'])]
    #[ApiProperty(description: 'Message describing the result')]
    public ?string $message = null;

    #[Groups(['query', 'mutation'])]
    #[ApiProperty(description: 'ID of the wishlist item that was moved')]
    public ?int $wishlistItemId = null;
}
