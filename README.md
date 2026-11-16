# Apogee Watcher: Automated PageSpeed Monitoring

Official WordPress plugin for [Apogee Watcher](https://apogeewatcher.com): Core Web Vitals and Lighthouse monitoring in wp-admin, without a Google PageSpeed API key in WordPress.

**Display name:** Apogee Watcher: Automated PageSpeed Monitoring · **Slug:** `apogee-watcher` · **Version:** 0.3.2 · **Repository:** [Apogee-Information-Systems/apogee-wp-watcher](https://github.com/Apogee-Information-Systems/apogee-wp-watcher) 

## Features (v0.3)

- **Connect to Watcher** via OAuth 2.0 device authorisation (`apogee-watcher-wordpress` client)
- Free-account access to Watcher using the WordPress admin email as a suggested address
- Dashboard widget (latest score + LCP / INP / CLS)
- **Watcher → Test results** two-column view (pages + scores; latest 20 alerts)
- Admin notice when Watcher reports open alerts
- Self-hosted updates via `Update URI` (dashboard updates from apogeewatcher.com; no WordPress.org listing required)
- **Translations:** Greek (`el`), French (`fr_FR`), German (`de_DE`), plus a full `.pot` catalogue under `languages/`
- **Tracked pages** on Connection: bulk include / pause scheduled tests in Watcher
- Disconnect / revoke refresh token

## Requirements

- WordPress 5.8+ (tested up to 7.1)
- PHP 7.4+
- Outbound HTTPS to `https://apogeewatcher.com` only (API base is fixed in the plugin)

## Install

**Production ZIP:** download from [apogeewatcher.com/tools/wordpress](https://apogeewatcher.com/tools/wordpress), then Plugins → Add New → Upload Plugin.

**Development:**

1. Clone into `wp-content/plugins/apogee-watcher`
2. Activate in Plugins
3. **Watcher → Connection → Connect to Watcher**

After the first install, newer versions appear on the Plugins / Updates screens when Watcher publishes them (self-hosted update check).

## Release ZIP

Build a clean package (excludes `.git`, `.gitignore`, `CONTRIBUTING.md`, `bin/`, IDE junk):

```bash
./bin/build-release-zip.sh              # self-hosted (keeps Update URI)
./bin/build-release-zip.sh --wporg      # WordPress.org candidate (strips Update URI)
# → dist/apogee-watcher-0.3.2[.zip|-wporg.zip] + SHA256 on stdout
```

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
| `GET /api/v1/sites/{id}/alerts` | Admin notices + results alerts column |

Public (unauthenticated) update endpoints used by `Update URI`:

| Call | Purpose |
|------|---------|
| `GET /api/plugin/wordpress/latest` | Latest version metadata |
| `GET /api/plugin/wordpress/download` | Distribution ZIP |
| `GET /api/plugin/wordpress/changelog` | Changelog for “View details” |

## License

GPL-2.0-or-later (required for WordPress.org).

## Contributing

PRs welcome. Keep PHP 7.4-compatible, escape output, capability-check admin actions, and do not phone home before the admin clicks Connect. For string changes, regenerate `languages/apogee-watcher.pot` and update the locale files (see `CONTRIBUTING.md`).
