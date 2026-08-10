<?php

if (!defined('ABSPATH')) exit;

class KPK_Plugin {

	public static function init() {
		if (!self::dependencies_met()) {
			add_action('admin_notices', array(__CLASS__, 'missing_dependencies_notice'));
			return;
		}

		KPK_Settings::init();
		KPK_Filter::init();
		KPK_Cards::init();

		add_action('widgets_init', array(__CLASS__, 'register_widgets'));
	}

	public static function register_widgets() {
		register_widget('KPK_Widget');
	}

	public static function dependencies_met() {
		return class_exists('WooCommerce');
	}

	public static function missing_dependencies_notice() {
		if (!current_user_can('activate_plugins')) return;

		echo '<div class="notice notice-error"><p>';
		echo esc_html('Kafra Produktkatalog krever at WooCommerce er installert og aktivert.');
		echo '</p></div>';
	}

	public static function get_settings() {
		$raw = get_option(KPK_OPTION_KEY, array());
		if (!is_array($raw)) {
			$raw = array();
		}

		return self::normalize_settings($raw);
	}

	public static function normalize_settings($raw) {
		$defaults = self::default_settings();
		$settings = wp_parse_args(is_array($raw) ? $raw : array(), $defaults);

		$settings['filters'] = self::normalize_tax_map(
			isset($raw['filters']) && is_array($raw['filters']) ? $raw['filters'] : $defaults['filters']
		);
		if (empty($settings['filters'])) {
			$settings['filters'] = $defaults['filters'];
		}

		$settings['card_attrs'] = self::normalize_tax_map(
			isset($raw['card_attrs']) && is_array($raw['card_attrs']) ? $raw['card_attrs'] : $defaults['card_attrs']
		);

		$settings['attr_rules'] = KPK_Rules::normalize_rules(
			isset($raw['attr_rules']) && is_array($raw['attr_rules']) ? $raw['attr_rules'] : $defaults['attr_rules']
		);

		$settings['ajax'] = empty($settings['ajax']) ? 0 : 1;
		$settings['show_search'] = empty($settings['show_search']) ? 0 : 1;
		$settings['hide_empty_filters'] = empty($settings['hide_empty_filters']) ? 0 : 1;
		$settings['search_placeholder'] = sanitize_text_field($settings['search_placeholder']);
		if ($settings['search_placeholder'] === '') {
			$settings['search_placeholder'] = $defaults['search_placeholder'];
		}

		return $settings;
	}

	public static function default_settings() {
		return array(
			'filters' => array(
				'product_cat'    => 1,
				'pa_bruksomrade' => 1,
			),
			'card_attrs' => array(),
			'attr_rules' => array(),
			'ajax'                => 1,
			'show_search'         => 1,
			'hide_empty_filters'  => 1,
			'search_placeholder'  => 'Søk produkter…',
		);
	}

	public static function normalize_tax_map($filters) {
		$out = array();
		foreach ((array) $filters as $tax => $enabled) {
			$tax = sanitize_key($tax);
			if ($tax === '') {
				continue;
			}
			$out[$tax] = empty($enabled) ? 0 : 1;
		}

		return $out;
	}

	public static function save_settings($settings) {
		return update_option(KPK_OPTION_KEY, self::normalize_settings($settings), false);
	}

	public static function get_filterable_taxonomies() {
		$options = array(
			'product_cat' => 'Produktkategori',
		);

		if (!function_exists('wc_get_attribute_taxonomies')) {
			return $options;
		}

		foreach (wc_get_attribute_taxonomies() as $attribute) {
			$taxonomy = wc_attribute_taxonomy_name($attribute->attribute_name);
			$options[$taxonomy] = $attribute->attribute_label;
		}

		return $options;
	}

	public static function get_enabled_filters() {
		return self::get_enabled_taxonomies('filters');
	}

	public static function get_enabled_card_attrs() {
		return self::get_enabled_taxonomies('card_attrs');
	}

	private static function get_enabled_taxonomies($setting_key) {
		$settings = self::get_settings();
		$labels   = self::get_filterable_taxonomies();
		$enabled  = array();
		$map      = isset($settings[$setting_key]) && is_array($settings[$setting_key])
			? $settings[$setting_key]
			: array();

		foreach ($map as $tax => $on) {
			if (empty($on) || !taxonomy_exists($tax)) {
				continue;
			}
			$enabled[$tax] = isset($labels[$tax]) ? $labels[$tax] : $tax;
		}

		return $enabled;
	}
}
