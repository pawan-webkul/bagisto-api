# Changelog

All notable changes to `bagisto/bagisto-api` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.3] - 2026-04-13

### Added
- Customer account API resources: orders, invoices, wishlists, reviews, compare items.
- REST APIs for Locale, Category, and Theme Customization.
- Merge Cart API with support for configurable product type.
- Push Notification integration.
- Product type mutations for Booking and Event Booking products.
- Query fields on the product API to support dynamic currency.
- `ClearApiPlatformCacheCommand` for clearing the API Platform cache.
- Extensive Pest feature test coverage: GraphQL product/cart/checkout/customer/wishlist/compare, REST customer orders/invoices/reviews/downloadable/CMS pages, customer auth and address flows.

### Changed
- More precise product search by title.
- Cart price conversion now respects the active currency.
- Translation fallback for products and product variants based on active status.
- Translations extended for Event Booking product type.
- Shipping rates now expose `formattedPrice`.
- `InstallApiPlatformCommand` now publishes vendor config.
- `api-platform` / `api-platform-graphql` pinned to specific versions in `composer.json`.
- OpenAPI `info.version` bumped to `1.0.3` in `config/api-platform.php` and `config/api-platform-vendor.php`.

### Fixed
- Disabled products can no longer be added to the wishlist.
- Moving a wishlist item to the cart now increments the cart quantity when the same product is moved again.
- `attributeValues` key resolved correctly in product query data.
- `formattedPrice` field for downloadable and event booking product types.
- Rate limit handling for storefront endpoints.
- Cart merge behaviour for configurable products.
- `PageByUrlKeyResolver` tagged as a collection query resolver in GraphQL.
- Order, customer, and wishlist issue fixes.

[1.0.3]: https://github.com/bagisto/bagisto-api/compare/v1.0.2...v1.0.3
[1.0.2]: https://github.com/bagisto/bagisto-api/compare/v1.0.1...v1.0.2
[1.0.1]: https://github.com/bagisto/bagisto-api/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/bagisto/bagisto-api/releases/tag/v1.0.0
