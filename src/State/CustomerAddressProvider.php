<?php

namespace Webkul\BagistoApi\State;

use ApiPlatform\Laravel\Eloquent\Paginator;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\Pagination;
use ApiPlatform\State\ProviderInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;
use Webkul\BagistoApi\Exception\AuthenticationException;
use Webkul\BagistoApi\Facades\TokenHeaderFacade;
use Webkul\BagistoApi\Models\CustomerAddress;
use Webkul\Customer\Models\Customer;

/**
 * Provides customer addresses filtered by authenticated user token.
 */
class CustomerAddressProvider implements ProviderInterface
{
    public function __construct(private readonly Pagination $pagination) {}

    /**
     * Provide paginated customer addresses for the authenticated user.
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $request = Request::instance() ?? ($context['request'] ?? null);

        // Extract Bearer token from Authorization header
        $token = $request ? TokenHeaderFacade::getAuthorizationBearerToken($request) : null;

        if (! $token) {
            throw new AuthenticationException(__('bagistoapi::app.graphql.auth.token-required'));
        }

        $authenticatedCustomerId = $this->getCustomerIdFromToken($token);

        if ($authenticatedCustomerId === null) {
            throw new AuthenticationException(__('bagistoapi::app.graphql.customer-addresses.invalid-or-expired-token'));
        }

        $args = $context['args'] ?? [];

        $first  = isset($args['first']) ? (int) $args['first'] : null;
        $last   = isset($args['last']) ? (int) $args['last'] : null;
        $after  = $args['after'] ?? null;
        $before = $args['before'] ?? null;

        $perPage = $first ?? $last ?? 10;
        $offset  = 0;

        if ($after) {
            $decoded = base64_decode($after, true);
            $offset  = ctype_digit((string) $decoded) ? ((int) $decoded + 1) : 0;
        }

        if ($before) {
            $decoded = base64_decode($before, true);
            $cursor  = ctype_digit((string) $decoded) ? (int) $decoded : 0;
            $offset  = max(0, $cursor - $perPage);
        }

        $query = CustomerAddress::where('customer_id', $authenticatedCustomerId)
            ->orderBy('id', 'asc');

        $total = (clone $query)->count();

        if ($offset > $total) {
            $offset = max(0, $total - $perPage);
        }

        $items = $query
            ->offset($offset)
            ->limit($perPage)
            ->get();

        $currentPage = $total > 0 ? (int) floor($offset / $perPage) + 1 : 1;

        return new Paginator(
            new LengthAwarePaginator(
                $items,
                $total,
                $perPage,
                $currentPage,
                ['path' => request()->url()]
            )
        );
    }

    /**
     * Validate token and extract customer ID.
     */
    private function getCustomerIdFromToken(string $token): ?int
    {
        try {
            $tokenParts = explode('|', $token);

            if (count($tokenParts) !== 2) {
                return null;
            }

            $tokenId = $tokenParts[0];

            $personalAccessToken = DB::table('personal_access_tokens')
                ->where('id', $tokenId)
                ->where('tokenable_type', Customer::class)
                ->where(function ($query) {
                    $query->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                })
                ->first();

            if (! $personalAccessToken) {
                return null;
            }

            $customer = Customer::find($personalAccessToken->tokenable_id);

            if (! $customer || $customer->is_suspended) {
                return null;
            }

            return $customer->id;
        } catch (\Exception) {
            return null;
        }
    }
}
