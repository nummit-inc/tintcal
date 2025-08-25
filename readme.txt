=== TintCal ===
Contributors: nummit7310
Tags: calendar, events, schedule, holiday, business hours
Requires at least: 5.8
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 2.2.2
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A beautiful and simple event calendar plugin for Japanese users. Add a calendar to any site with a shortcode, block, or widget.

== Description ==

TintCal is a new kind of event calendar plugin designed for Japanese website creators and administrators.
It eliminates the complexity and confusion often found in plugins made overseas, responding to the simple need of "just wanting to place an event calendar" with the best usability and a beautiful design.

**= Key Features =**

*   **Easy Event Registration:** Anyone can intuitively register and edit events from the WordPress admin screen.
*   **Shortcode/Block Support:** Place a calendar anywhere just by adding the `[tintcal id="..."]` shortcode or a dedicated block in the editor.
*   **Responsive Design:** The calendar displays beautifully without breaking its layout on smartphones or tablets.
*   **Simple Settings:** Basic customizations, such as the calendar header and weekend colors, can be easily configured.
*   **Holiday Display:** Automatically displays Japanese public holidays on the calendar.
*   **Simple Category Management:** You can register one type of schedule as a category and color-code the calendar.

== Installation ==

1.  Upload the plugin files to the `/wp-content/plugins/` directory, or install the plugin through the WordPress plugins screen directly.
2.  Activate the plugin through the 'Plugins' screen in WordPress.
3.  Configure the settings by navigating to Settings > TintCal in your WordPress dashboard.

== Frequently Asked Questions ==

= How do I display the calendar? =

Add the shortcode `[tintcal id="..."]` to the post or page where you want the calendar to appear.

= Where can I get support? =

For basic questions, please use the official support forums on WordPress.org.

= Troubleshooting: the display or behavior looks wrong =
Temporarily disable cache/optimization plugins (JS/CSS minification, lazy loading) and any CDN. If the issue persists, switch to a default theme (e.g., Twenty Twenty-Five) to check for theme/plugin conflicts. Please include exact reproduction steps.

= The update installed but nothing changed =
Clear browser, server, and plugin caches, and purge your CDN if you use one. Confirm the Plugins screen shows “Version: 2.2.1”.

= What information should I include in a bug report? =
- WordPress and PHP versions (e.g., WP 6.6 / PHP 8.1)
- Theme and major active plugins
- Steps to reproduce (1 → 2 → 3)
- Expected result vs actual result
- Screenshots or error logs, if possible

== Screenshots ==

1.  The calendar display on the front-end.
2.  The event registration screen.
3.  The plugin settings screen.
4.  The base settings screen.

== Changelog ==

= 2.2.2 =
*   Fix: incorrect calendar layout in some themes.

= 2.2.1 =
*   Initial release on wordpress.org.

= 2.2.0 =
*   Major refactoring for the public release. Removed license verification and Pro features.
*   Simplified the settings page and admin menus.
*   Added a page to introduce the Pro version.
*   Fixed a bug where category visibility settings were not saved correctly in the block editor.
*   Fixed a bug where the export file name date was incorrect depending on the time zone.

= 2.1 =
*   Initial public release.