# CIRITAS — Modern PHP frontend + transition admin

This package is a staged modernization of the existing CIRITAS PHP/MySQL site. It is designed to work against the existing production database without importing the old SQL dump over production data.

## What is included

- Modern public site UI.
- Product catalog without public group headings.
- Existing product and download URL patterns retained (`product.php?id=...`, `download.php?id=...`).
- Modern transition admin under `/admin-modern/`.
- Product, group, order, news, FAQ, partner, page and settings screens.
- Product assets screen for downloads, prices and screenshots.
- PDO-based MySQL access.
- Legacy Windows-1251 conversion support.
- v4 order flow with legacy-compatible `cart`, `cart_items` and `csOrders` integration.
- Multiple products/licenses in one order, quantity editing and cart checkout.
- Legacy order type (`zType`): organization or private person.
- Legacy delivery options: Russian Post (free) or courier service (+1000 RUB).
- Legacy additional products/services from active `csPriceItems` records in `PriceSectionID=7`.

## Local configuration

Copy `config/local.example.php` to `config/local.php` on the server and fill in the real database/admin settings there. `config/local.php` is intentionally excluded from Git.

Do not commit passwords, database credentials, uploaded program files or screenshots.

## Uploads and photos

The repository intentionally does not include the large historical `Uploads` / `uploads` and `photos` directories. Keep the existing server copies and configure their paths in the local configuration.

## v4 order compatibility

The modern order flow deliberately preserves the old database model:

- active cart is stored in `cart` and identified by `cartID` / `cartCode` cookies;
- selected licenses are stored in `cart_items`;
- checkout creates one `csOrders` record with `ProductID=0`, matching the historical composite-order convention;
- `cart.IsOrdered` is set to the new `csOrders.ID`;
- `cart.zType`, `cart.Delivery`, `cart.ExtraProduct` and `cart.TotalSum` retain the historical checkout semantics;
- courier delivery adds 1000 RUB to the order total;
- the optional extra product/service is selected from `csPriceItems` where `PriceSectionID=7` and `IsActive=1`;
- `OrderDate` remains a Unix timestamp;
- `OrderNumber` continues to use `csSettings.LastOrderNumber` when available.

Before merging v4 into production, test add-to-cart, quantity updates, checkout, organization/private-person orders, both delivery modes, extra products, order totals and `/admin-modern/order-view.php` against a copy of the live database.

## Deployment approach

Recommended transition path:

1. Deploy to a separate test directory or subdomain.
2. Point the test installation at the existing database with a limited test window.
3. Verify products, downloads, prices, screenshots, news and admin operations.
4. Keep the legacy `/admin/` available until all remaining business logic is migrated.
5. Switch the public site only after verification.

Do not import the bundled historical database dump over the live database. The live schema may contain columns/tables added after that dump.

## PHP requirements

Use a modern supported PHP release with PDO MySQL enabled. The code is written for modern PHP while preserving compatibility with the existing CIRITAS data model.
