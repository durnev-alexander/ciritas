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

## Local configuration

Copy `config/local.example.php` to `config/local.php` on the server and fill in the real database/admin settings there. `config/local.php` is intentionally excluded from Git.

Do not commit passwords, database credentials, uploaded program files or screenshots.

## Uploads and photos

The repository intentionally does not include the large historical `Uploads` / `uploads` and `photos` directories. Keep the existing server copies and configure their paths in the local configuration.

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
