<?php

if (!defined('ABSPATH')) exit;

class KPK_Cards {

	public static function init() {
		// Must not use `wp` — admin-ajax (filter) never fires it, so cards would lose attrs after AJAX.
		add_action('init', array(__CLASS__, 'register_hooks'), 30);
	}

	public static function register_hooks() {
		if (empty(KPK_Plugin::get_enabled_card_attrs())) {
			return;
		}

		// Flatsome wraps WC title hooks; place attrs after price box text.
		if (self::is_flatsome()) {
			add_action('flatsome_product_box_after', array(__CLASS__, 'render'));
			return;
		}

		add_action('woocommerce_after_shop_loop_item_title', array(__CLASS__, 'render'), 15);
	}

	private static function is_flatsome() {
		$template = function_exists('get_template') ? get_template() : '';
		return $template === 'flatsome' || function_exists('flatsome_setup');
	}

	public static function render() {
		global $product;

		if (!$product instanceof WC_Product) {
			return;
		}

		$attrs = KPK_Plugin::get_enabled_card_attrs();
		if (empty($attrs)) {
			return;
		}

		$rows = KPK_Rules::resolve_card_rows($product, $attrs);
		if (empty($rows)) {
			return;
		}

		echo '<div class="kpk-card-attrs">';
		foreach ($rows as $row) {
			echo '<div class="kpk-card-attr" data-taxonomy="' . esc_attr($row['taxonomy']) . '">';
			echo '<span class="kpk-card-attr__label">' . esc_html($row['label']) . '</span>';
			echo '<span class="kpk-card-attr__values">';
			foreach ($row['values'] as $value) {
				echo '<span class="kpk-card-attr__value">' . esc_html($value) . '</span>';
			}
			echo '</span>';
			echo '</div>';
		}
		echo '</div>';
	}
}
