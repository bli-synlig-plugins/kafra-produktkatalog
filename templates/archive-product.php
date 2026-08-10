<?php
/**
 * Force normal Flatsome/WooCommerce catalog layout on shop.
 * Skips Flatsome "Shop Page Content" which hides products until search/filter.
 */

defined('ABSPATH') || exit;

get_header('shop');

$sidebar = 'left-sidebar';
if (function_exists('get_theme_mod')) {
	$sidebar = get_theme_mod('category_sidebar', 'left-sidebar');
}

wc_get_template_part('layouts/category', $sidebar);

get_footer('shop');
