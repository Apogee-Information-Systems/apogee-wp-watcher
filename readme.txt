=== Apogee Watcher for WordPress ===
Contributors: apogeeinformationsystems
Tags: pagespeed, core web vitals, performance, lighthouse, monitoring
Requires at least: 5.8
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect your WordPress site to Apogee Watcher. See Core Web Vitals and Lighthouse scores in the dashboard and get notified when performance drops.

== Description ==

**Apogee Watcher for WordPress** connects your site to [Apogee Watcher](https://apogeewatcher.com), a PageSpeed and Core Web Vitals monitoring service. Once connected, you can see your latest performance scores and key metrics directly in the WordPress admin—no need to leave your site to check how it's performing.

**What you get with the free plugin:**

* **Dashboard widget** — Latest Lighthouse performance score and Core Web Vitals (LCP, INP, CLS) at a glance, with a link to view full history in Watcher.
* **Test results in WordPress** — View recent page scores inside the WordPress admin.
* **One-click connection** — Connect this site to Watcher with a secure device pairing flow (create a free Watcher account or sign in). No Google PageSpeed API key in WordPress.
* **Admin notifications** — When Watcher has open performance alerts, you see a notice in the admin with a link to investigate.

All testing runs on Watcher's infrastructure using Google PageSpeed Insights. The plugin does not store your Google API key or run tests on your server; it only displays results and lets you connect.

**Upgrade to Watcher** for multiple sites, performance budgets, scheduled alerts, client-ready reports, and team collaboration.

= External service =

This plugin connects to Apogee Watcher (`https://apogeewatcher.com`) operated by Apogee Information Systems. When you click **Connect to Watcher**, the plugin sends your site URL and (as a suggested signup address) your WordPress admin email to Watcher so you can approve pairing. OAuth access and refresh tokens are stored in WordPress options. See [Apogee Watcher](https://apogeewatcher.com) for terms and privacy.

== Installation ==

1. Upload the `apogee-wp-watcher` folder to `/wp-content/plugins/` or install via Plugins → Add New.
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

= 0.1.0 =
* Initial public release: device OAuth connect, dashboard widget, test results page, and alert notices.

== Upgrade Notice ==

= 0.1.0 =
First release.
