=== Apogee Watcher: Automated PageSpeed Monitoring ===
Contributors: apogeeinformationsystems
Tags: pagespeed, core web vitals, performance, lighthouse, monitoring
Requires at least: 5.8
Tested up to: 7.1.4
Requires PHP: 7.4
Stable tag: 0.3.1
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect your WordPress site to Apogee Watcher. See Core Web Vitals and Lighthouse scores in the dashboard and get notified when performance drops.

== Description ==

**Apogee Watcher: Automated PageSpeed Monitoring** connects your site to [Apogee Watcher](https://apogeewatcher.com), a PageSpeed and Core Web Vitals monitoring service. Once connected, you can see your latest performance scores and key metrics directly in the WordPress admin—no need to leave your site to check how it's performing.

**What you get with the free plugin:**

* **Dashboard widget** — Latest Lighthouse performance score and Core Web Vitals (LCP, INP, CLS) at a glance, with a link to view full history in Watcher.
* **Test results in WordPress** — View recent page scores inside the WordPress admin, with page titles linking into Watcher and timestamps linking to the matching test result.
* **One-click connection** — Connect this site to Watcher with a secure device pairing flow (create a free Watcher account or sign in). No Google PageSpeed API key in WordPress.
* **Admin notifications** — When Watcher has open performance alerts, you see a notice in the admin with a link to investigate.

All testing runs on Watcher's infrastructure using Google PageSpeed Insights. The plugin does not store your Google API key or run tests on your server; it only displays results and lets you connect.

**Upgrade to Watcher** for multiple sites, performance budgets, scheduled alerts, client-ready reports, and team collaboration.

= External service =

This plugin connects to Apogee Watcher (`https://apogeewatcher.com`) operated by Apogee Information Systems. When you click **Connect to Watcher**, the plugin sends your site URL and (as a suggested signup address) your WordPress admin email to Watcher so you can approve pairing. OAuth access and refresh tokens are stored in WordPress options. The plugin also checks Apogee Watcher for newer plugin versions (self-hosted updates; no WordPress.org listing required). See [Apogee Watcher](https://apogeewatcher.com) for terms and privacy.

== Installation ==

1. Upload the `apogee-watcher` folder to `/wp-content/plugins/` or install via Plugins → Add New.
2. Activate the plugin through the **Plugins** menu.
3. Open **Watcher → Connection** and click **Connect to Watcher**.
4. Approve the site in your Apogee Watcher account (create a free account if needed).
5. Return to WordPress — the plugin finishes pairing automatically.

== Frequently Asked Questions ==

= Do I need a Google PageSpeed Insights API key? =

No. Watcher runs tests with its own integration. You only connect your WordPress site to your Watcher account.

= Is there a free plan? =

Yes. New users can create a Free Apogee Watcher account during Connect (subject to Free plan limits on Watcher).

= Does the plugin optimize my site? =

No. It is monitoring-only. Use your usual caching/optimization plugins alongside Watcher.

== Changelog ==

= 0.3.1 =
* Rename plugin to Apogee Watcher: Automated PageSpeed Monitoring.
* Plugin slug / folder / text domain: apogee-watcher (was apogee-wp-watcher); migrates connection options from the old keys.
* GDPR: privacy exporters/erasers for Tools → Export / Erase Personal Data.
* Clear alert-dismiss user meta on uninstall; accessibility labels on Tracked pages and pairing link.
* Sanitize Tracked pages bulk JSON input; load textdomain only for self-hosted (Update URI) builds.
* Distribution ZIP excludes repo files (.gitignore, CONTRIBUTING.md); optional --wporg build strips Update URI.
* Tested up to WordPress 7.1.4; License GPL-2.0-or-later in readme.

= 0.3.0 =
* Connection screen: Tracked pages table with bulk include / pause scheduled tests.

= 0.2.1 =
* Show Apogee Watcher icons on the Plugins / Updates screens (from Watcher latest API).

= 0.2.0 =
* Translations: Greek, French, and German (plus a full `.pot` catalogue).
* Wrap remaining updater strings for translation.

= 0.1.5 =
* Test results: two-column layout with pages on the left and the latest 20 alerts on the right.

= 0.1.4 =
* Self-hosted updates via Update URI (dashboard updates from apogeewatcher.com without WordPress.org).

= 0.1.3 =
* Test results table shows page titles (link to the page in Watcher) and last-tested timestamps (link to the result).

= 0.1.2 =
* Open Watcher links go to the connected site view (`/admin/watcher-sites/{id}`).

= 0.1.1 =
* Lock API base to production; allowlist OAuth verification URLs before opening them in the admin.

= 0.1.0 =
* Initial public release: device OAuth connect, dashboard widget, test results page, and alert notices.

== Upgrade Notice ==

= 0.3.1 =
Slug is now apogee-watcher. Deactivate the old plugin, install this ZIP, activate (connection migrates), then delete the old apogee-wp-watcher folder.

= 0.3.0 =
Add or pause Watcher scheduled pages from the Connection screen.

= 0.2.1 =
Plugin update notices use the Apogee Watcher logo.

= 0.2.0 =
Adds Greek, French, and German translations for the admin UI.

= 0.1.5 =
Test results page shows recent alerts beside page scores.

= 0.1.4 =
Adds self-hosted dashboard updates from Apogee Watcher.
