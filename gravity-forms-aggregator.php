<?php
/**
 * Plugin Name:       Gravity Forms Aggregator
 * Plugin URI:        https://github.com/gravity-forms-aggregator/gravity-forms-aggregator
 * Description:       Aggregate and export Gravity Forms entries from multiple forms into CSV or Excel.
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Mohamad Karim
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       gravity-forms-aggregator
 *
 * @package GravityFormsAggregator
 */

defined( 'ABSPATH' ) || exit;

define( 'GFA_VERSION', '0.1.0' );
define( 'GFA_PLUGIN_FILE', __FILE__ );
define( 'GFA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GFA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once GFA_PLUGIN_DIR . 'includes/class-export-config.php';
require_once GFA_PLUGIN_DIR . 'includes/class-date-range.php';
require_once GFA_PLUGIN_DIR . 'includes/class-plugin.php';

GFA_Plugin::instance();
