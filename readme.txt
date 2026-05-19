=== WP Vigie ===
Contributors: Omar EL AMRANI EL IDRISSI - odacom
Tags: health check, security, maintenance, audit, monitoring
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 0.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

10-point health audit for WordPress. Open-source companion to WPSentinel Cloud.

== Description ==

WP Vigie runs a 10-point health check on your WordPress installation and outputs a scored report (0–100) directly in the admin panel.

Visit **Tools → WP Vigie** and click "Run Scan" to audit your site in under 2 seconds.

**What it checks:**

1. WordPress core version
2. PHP version
3. Database server version (MySQL / MariaDB)
4. Plugin updates available
5. Theme updates available
6. HTTPS enabled
7. WP_DEBUG status
8. Default "admin" username
9. Dashboard file editor
10. WP-Cron configuration

Each check returns **pass** (10 pts), **warn** (5 pts), or **fail** (0 pts) for a total score out of 100.

WP Vigie is the open-source companion to [WPSentinel Cloud](https://wpsentinel.net) — a paid SaaS for continuous, multi-site, scheduled monitoring with email alerts and branded reports.

== Installation ==

1. Upload the `wp-vigie` folder to `/wp-content/plugins/`.
2. Activate the plugin via **Plugins → Installed Plugins**.
3. Visit **Tools → WP Vigie** and click "Run Scan".

== Frequently Asked Questions ==

= Does this plugin make any external HTTP calls? =

No. All 10 checks run entirely on your server using local WordPress APIs and PHP built-ins. Nothing is sent or fetched remotely during a scan.

= Is there a settings page? =

No. WP Vigie is intentionally zero-configuration.

= Can I schedule automatic scans? =

Not in the free plugin. Scheduled scans, email alerts, and multi-site dashboards are features of [WPSentinel Cloud](https://wpsentinel.net).

= Does it work on WordPress Multisite? =

v0.1 targets single-site installs only.

== Screenshots ==

1. The WP Vigie admin page showing a scored report with 10 check cards.

== Changelog ==

= 0.1.0 =
* Initial release. 10-point health audit, single admin page, zero configuration.

== Upgrade Notice ==

= 0.1.0 =
Initial release.
