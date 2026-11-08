# Apogee Watcher for WordPress

Official WordPress plugin for [Apogee Watcher](https://apogeewatcher.com) — Core Web Vitals and Lighthouse monitoring in wp-admin, without a Google PageSpeed API key in WordPress.

**Display name:** Apogee Watcher for WordPress · **Slug:** `apogee-wp-watcher` · **Repository:** [Apogee-Information-Systems/apogee-wp-watcher](https://github.com/Apogee-Information-Systems/apogee-wp-watcher)

## Features (v0.1)

- **Connect to Watcher** via OAuth 2.0 device authorisation (`apogee-watcher-wordpress` client)
- Free-account path on Watcher using the WordPress admin email as a suggested address
- Dashboard widget (latest score + LCP / INP / CLS)
- **Watcher → Test results** table (page titles → Watcher page, timestamps → result, mobile/desktop scores)
- Admin notice when Watcher reports open alerts
- Disconnect / revoke refresh token

## Requirements

- WordPress 5.8+
- PHP 7.4+
- Outbound HTTPS to `https://apogeewatcher.com` only (API base is fixed in the plugin)

## Install (development)

1. Clone into `wp-content/plugins/apogee-wp-watcher`
2. Activate in Plugins
3. **Watcher → Connection → Connect to Watcher**

## Watcher API

Uses the site-scoped REST API documented in the Watcher wiki plan `wordpress_integration_api_plan`:

| Call | Purpose |
|------|---------|
| `POST /oauth/device/code` | Start pairing |
| `POST /oauth/token` | Poll device grant / refresh |
| `POST /oauth/revoke` | Disconnect |
| `GET /api/v1/integration` | Connection identity |
| `GET /api/v1/sites/{id}/overview` | Widget |
| `GET /api/v1/sites/{id}/pages` | Results table |
| `GET /api/v1/sites/{id}/alerts` | Admin notices |

## License

GPL-2.0-or-later (required for WordPress.org).

## Contributing

PRs welcome. Keep PHP 7.4-compatible, escape output, capability-check admin actions, and do not phone home before the admin clicks Connect.
