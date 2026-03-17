<?php

namespace Webkul\BagistoApi\Dto;

use Symfony\Component\Serializer\Annotation\Groups;

/**
 * LogoutInput DTO
 *
 * Contains input fields for logout mutation.
 * The token is extracted from the Authorization header, but deviceToken can be passed to remove it from the device tokens table.
 */
class LogoutInput
{
    #[Groups(['mutation'])]
    public ?string $deviceToken = null;

    public function __construct(
        ?string $deviceToken = null,
    ) {
        $this->deviceToken = $deviceToken;
    }
}
