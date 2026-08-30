<?php
/**
 * Plugin Name:       Gravity Forms Aggregator
 * Plugin URI:        https://github.com/gravity-forms-aggregator/gravity-forms-aggregator
 * Description:       Aggregate and export Gravity Forms entries from multiple forms into CSV or Excel.
 * Version:           0.9.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            محمد کریم قصبه
 * Author URI:        https://m-karim.ir
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       gravity-forms-aggregator
 *
 * @package GravityFormsAggregator
 */

defined( 'ABSPATH' ) || exit;

define( 'GFA_VERSION', '0.9.0' );
define( 'GFA_PLUGIN_FILE', __FILE__ );
define( 'GFA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GFA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once GFA_PLUGIN_DIR . 'includes/class-export-config.php';
require_once GFA_PLUGIN_DIR . 'includes/class-date-range.php';
require_once GFA_PLUGIN_DIR . 'includes/class-gf-schema.php';
require_once GFA_PLUGIN_DIR . 'includes/class-field-mapper.php';
require_once GFA_PLUGIN_DIR . 'includes/class-export-row.php';
require_once GFA_PLUGIN_DIR . 'includes/class-form-insights.php';
require_once GFA_PLUGIN_DIR . 'includes/class-data-extractor.php';
require_once GFA_PLUGIN_DIR . 'includes/class-export-engine.php';
require_once GFA_PLUGIN_DIR . 'includes/class-export-preview.php';
require_once GFA_PLUGIN_DIR . 'includes/class-export-runtime.php';
require_once GFA_PLUGIN_DIR . 'includes/class-export-history.php';
require_once GFA_PLUGIN_DIR . 'includes/class-entry-field-resolver.php';
require_once GFA_PLUGIN_DIR . 'includes/class-entry-summary-row.php';
require_once GFA_PLUGIN_DIR . 'includes/class-entries-list.php';
require_once GFA_PLUGIN_DIR . 'includes/class-capabilities.php';
require_once GFA_PLUGIN_DIR . 'includes/export/class-csv-exporter.php';
require_once GFA_PLUGIN_DIR . 'includes/export/class-xlsx-writer.php';
require_once GFA_PLUGIN_DIR . 'includes/export/class-xlsx-exporter.php';
require_once GFA_PLUGIN_DIR . 'includes/class-plugin.php';

GFA_Capabilities::register();

GFA_Plugin::instance();
