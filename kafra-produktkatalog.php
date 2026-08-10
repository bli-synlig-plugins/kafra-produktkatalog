<?php
/**
 * Plugin Name: Kafra Produktkatalog
 * Description: Lett AJAX-filtermeny (widget) til WooCommerce sin vanlige produktkatalog.
 * Version: 1.4.1
 * Author: Benjamin
 * Text Domain: kafra-produktkatalog
 * Requires Plugins: woocommerce
 */

if (!defined('ABSPATH')) exit;

define('KPK_VERSION', '1.4.1');
define('KPK_PLUGIN_FILE', __FILE__);
define('KPK_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('KPK_PLUGIN_URL', plugin_dir_url(__FILE__));
define('KPK_OPTION_KEY', 'kpk_settings');

require_once KPK_PLUGIN_DIR . 'includes/class-kpk-plugin.php';
require_once KPK_PLUGIN_DIR . 'includes/class-kpk-rules.php';
require_once KPK_PLUGIN_DIR . 'includes/class-kpk-settings.php';
require_once KPK_PLUGIN_DIR . 'includes/class-kpk-filter.php';
require_once KPK_PLUGIN_DIR . 'includes/class-kpk-cards.php';
require_once KPK_PLUGIN_DIR . 'includes/class-kpk-widget.php';

add_action('plugins_loaded', array('KPK_Plugin', 'init'));
