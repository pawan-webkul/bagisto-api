<?php

namespace Webkul\BagistoApi\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Webkul\BagistoApi\Dto\CartData;
use Webkul\BagistoApi\Dto\MoveWishlistToCartInput;
use Webkul\BagistoApi\Dto\MoveWishlistToCartOutput;
use Webkul\BagistoApi\Exception\AuthorizationException;
use Webkul\BagistoApi\Exception\InvalidInputException;
use Webkul\BagistoApi\Exception\ResourceNotFoundException;
use Webkul\BagistoApi\Models\Wishlist;
use Webkul\Checkout\Facades\Cart;
use Webkul\Checkout\Models\Cart as CartModel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;

/**
 * MoveWishlistToCartProcessor - Handles moving wishlist items to cart
 */
class MoveWishlistToCartProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly ProcessorInterface $persistProcessor,
    ) {}

    /**
     * Process move to cart operation
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof MoveWishlistToCartInput) {
            /**
             * REST fallback: the serializer's name converter may not populate camelCase DTO
             * properties from snake_case JSON keys. Populate from request if needed.
             */
            if ($data->wishlistItemId === null) {
                $data->wishlistItemId = (int) (request()->input('wishlist_item_id') ?? request()->input('wishlistItemId'));
                $data->quantity = (int) (request()->input('quantity') ?? 1);
            }

            return $this->handleMoveToCart($data);
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }

    /**
     * Handle move to cart operation for wishlist items
     * Returns cart data similar to handleAddProduct in CartTokenProcessor
     */
    private function handleMoveToCart(MoveWishlistToCartInput $input): array
    {
        $user = Auth::guard('sanctum')->user();

        if (! $user) {
            throw new AuthorizationException(__('bagistoapi::app.graphql.logout.unauthenticated'));
        }

        if ($input->quantity < 1) {
            throw new InvalidInputException(__('bagistoapi::app.graphql.wishlist.invalid-quantity'));
        }

        // Eager load product relationship - required for Cart::moveToCart() to work
        $wishlistItem = Wishlist::with('product')
            ->where('id', $input->wishlistItemId)
            ->where('customer_id', $user->id)
            ->first();

        if (! $wishlistItem) {
            throw new ResourceNotFoundException(__('bagistoapi::app.graphql.wishlist.not-found'));
        }

        try {
            // Get the current cart first (if exists)
            $cart = Cart::getCart();

            // If no cart exists, create one
            if (! $cart) {
                $channel = core()->getCurrentChannel();
                $cartRepository = app('Webkul\Checkout\Repositories\CartRepository');
                $cart = $cartRepository->create([
                    'customer_id' => $user->id,
                    'channel_id'  => $channel->id,
                    'is_active'   => 1,
                ]);
            }

            // Set the cart on the facade before moving
            Cart::setCart($cart);

            Event::dispatch('customer.wishlist.move-to-cart.before', $input->wishlistItemId);

            // Move the wishlist item to cart
            $result = Cart::moveToCart($wishlistItem, $input->quantity);

            Event::dispatch('customer.wishlist.move-to-cart.after', $input->wishlistItemId);

            if (! $result) {
                throw new InvalidInputException(__('bagistoapi::app.graphql.wishlist.move-to-cart-missing-options'));
            }

            // Collect totals to update cart calculations
            Cart::collectTotals();

            // Get the updated cart
            $updatedCart = Cart::getCart();

            if (! $updatedCart) {
                throw new ResourceNotFoundException(__('bagistoapi::app.graphql.cart.cart-not-found'));
            }

            // Build the response like handleAddProduct does
            $responseData = CartData::fromModel($updatedCart);
            $responseData->success = true;
            $responseData->message = __('bagistoapi::app.graphql.wishlist.moved-to-cart-success');

            return (array) $responseData;
        } catch (\Exception $exception) {
            throw new InvalidInputException($exception->getMessage());
        }
    }
}
