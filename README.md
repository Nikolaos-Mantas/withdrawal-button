# Withdrawal Button

WordPress plugin for customer withdrawal requests: form, admin list, emails, optional WooCommerce and REST API.

**Author:** Nikolaos Mantas — [nmantas.eu](https://nmantas.eu) — info@nmantas.eu  
**Repository:** [github.com/Nikolaos-Mantas/withdrawal-button](https://github.com/Nikolaos-Mantas/withdrawal-button)  
**Version:** 3.0.0 · **Requires:** WordPress 5.8+, PHP 7.4+

## Install

1. Copy to `wp-content/plugins/withdrawal-button/`
2. Activate **Withdrawal Button**
3. Configure under **Withdrawals → Settings**

Compile Greek translations: `composer translations`

## Usage

Shortcode: `[withdrawal_form]`  
Block: `wb/withdrawal-form` · Elementor: **Withdrawal Form**

Admin: **Withdrawals** (requests) · **Withdrawals → Settings** (tabs)

Email placeholders: `{name}`, `{email}`, `{order_number}`, `{store}`, `{products}`, `{message}`, `{date}`, `{ip}`, `{site_name}`, `{admin_url}`, `{days}`, `{request_id}`, `{status}`

## REST API

Enable in Settings. Auth header: `X-WB-API-Key` (or query `api_key`).

| Method | Endpoint |
|--------|----------|
| GET | `/wp-json/wb/v1/health` |
| GET | `/wp-json/wb/v1/requests` |
| GET/PATCH/DELETE | `/wp-json/wb/v1/requests/{id}` |

## Updates

Updates are installed from GitHub Releases. Choose channel in **Settings → Updates**: stable, beta, or alpha. Pre-release channels show a warning before upgrade.

## WP-CLI

```bash
wp wb test all
wp wb export settings --file=settings.json
wp wb export requests --format=csv --file=requests.csv
wp wb import settings settings.json
wp wb logs --limit=50
```

## Development

```bash
composer install
composer test
composer translations
```

## License

Proprietary free-use license — see [LICENSE](LICENSE). Free to install and use on sites you operate; no resale or redistribution of the plugin package.
