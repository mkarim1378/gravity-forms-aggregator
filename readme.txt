=== Gravity Forms Aggregator ===
Contributors: mkarim1378
Tags: gravity forms, export, csv, excel
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 0.9.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Aggregate and export Gravity Forms entries from multiple forms into a single CSV or Excel file.

== Description ==

Gravity Forms Aggregator lets site administrators:

* List all Gravity Forms
* Select multiple forms with checkboxes
* Filter entries by date range
* Export unified data as CSV or Excel

No additional database tables are created — data is read directly from Gravity Forms.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate the plugin through the Plugins screen
3. Ensure Gravity Forms is installed and active

== Changelog ==

= 0.9.0 =
* Add unified All Entries admin screen with form link, name, mobile, payment status, and date/time columns
* Paginated cross-form entry list sorted by newest first, with optional date filters

= 0.8.2 =
* Remove export presets (saved form selections)

= 0.8.1 =
* Fix Aggregate Export admin menu 404 by registering via Gravity Forms gform_addon_navigation

= 0.8.0 =
* Phase 8: export runtime optimization, custom capability, phone-only export mode, presets, recent export history, stale form warnings

= 0.7.0 =
* Phase 7: export preview with form/entry counts and empty-form warnings before download

= 0.6.0 =
* Phase 6: downloadable Excel (XLSX) export with the same unified data model as CSV

= 0.5.0 =
* Phase 5: downloadable CSV export with UTF-8 BOM and standard headers

= 0.4.0 =
* Phase 4: export engine, unified export rows, WP-CLI extract, unit tests

= 0.3.0 =
* Phase 3: admin UI for form selection, date range filter, export validation

= 0.2.0 =
* Phase 2: GFAPI data layer, field mapping, schema reference, wp gfa probe

= 0.1.0 =
* Phase 1: plugin scaffold, export scope configuration, date range rules
