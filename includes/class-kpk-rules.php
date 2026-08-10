<?php

if (!defined('ABSPATH')) exit;

class KPK_Rules {

	public static function get_rules() {
		$settings = KPK_Plugin::get_settings();
		return isset($settings['attr_rules']) && is_array($settings['attr_rules'])
			? $settings['attr_rules']
			: array();
	}

	public static function normalize_rules($rules) {
		$out = array();

		foreach ((array) $rules as $rule) {
			if (!is_array($rule)) {
				continue;
			}

			$parent_tax = isset($rule['parent_tax']) ? sanitize_key($rule['parent_tax']) : '';
			$child_tax  = isset($rule['child_tax']) ? sanitize_key($rule['child_tax']) : '';
			$parent_val = isset($rule['parent_value']) ? sanitize_text_field($rule['parent_value']) : '';

			if ($parent_tax === '' || $child_tax === '' || $parent_val === '') {
				continue;
			}

			if ($parent_tax === $child_tax) {
				continue;
			}

			$out[] = array(
				'parent_tax'    => $parent_tax,
				'parent_value'  => $parent_val,
				'child_tax'     => $child_tax,
				'apply_cards'   => empty($rule['apply_cards']) ? 0 : 1,
				'apply_filters' => empty($rule['apply_filters']) ? 0 : 1,
			);
		}

		return $out;
	}

	public static function get_filter_rules() {
		$rules = array();
		foreach (self::get_rules() as $rule) {
			if (empty($rule['apply_filters'])) {
				continue;
			}
			if (!taxonomy_exists($rule['parent_tax']) || !taxonomy_exists($rule['child_tax'])) {
				continue;
			}
			$rules[] = $rule;
		}
		return $rules;
	}

	public static function get_card_rules() {
		$rules = array();
		foreach (self::get_rules() as $rule) {
			if (empty($rule['apply_cards'])) {
				continue;
			}
			if (!taxonomy_exists($rule['parent_tax']) || !taxonomy_exists($rule['child_tax'])) {
				continue;
			}
			$rules[] = $rule;
		}
		return $rules;
	}

	public static function get_nested_filter_child_taxonomies() {
		$children = array();
		foreach (self::get_filter_rules() as $rule) {
			$children[$rule['child_tax']] = true;
		}
		return array_keys($children);
	}

	/**
	 * Map parent_tax => list of rules for nesting filter children under a parent term.
	 */
	public static function get_filter_nest_map() {
		$map = array();
		foreach (self::get_filter_rules() as $rule) {
			if (!isset($map[$rule['parent_tax']])) {
				$map[$rule['parent_tax']] = array();
			}
			$map[$rule['parent_tax']][] = $rule;
		}
		return $map;
	}

	public static function term_matches_value($term_name, $term_slug, $value) {
		$value = trim((string) $value);
		if ($value === '') {
			return false;
		}

		if (strcasecmp((string) $term_name, $value) === 0) {
			return true;
		}

		return sanitize_title($term_slug) === sanitize_title($value)
			|| sanitize_title($term_name) === sanitize_title($value);
	}

	public static function product_has_parent_value(WC_Product $product, $taxonomy, $value) {
		$terms = get_the_terms($product->get_id(), $taxonomy);
		if (is_wp_error($terms) || empty($terms)) {
			return false;
		}

		foreach ($terms as $term) {
			if (self::term_matches_value($term->name, $term->slug, $value)) {
				return true;
			}
		}

		return false;
	}

	public static function get_taxonomy_term_names(WC_Product $product, $taxonomy) {
		$taxonomy = sanitize_key($taxonomy);
		if ($taxonomy === '' || !taxonomy_exists($taxonomy)) {
			return array();
		}

		$terms = get_the_terms($product->get_id(), $taxonomy);
		if (is_wp_error($terms) || empty($terms)) {
			return array();
		}

		$names = array();
		foreach ($terms as $term) {
			$name = trim((string) $term->name);
			if ($name !== '') {
				$names[] = $name;
			}
		}

		return array_values(array_unique($names));
	}

	/**
	 * Resolve which attribute rows to show on a product card.
	 *
	 * @param array $enabled taxonomy => label
	 * @return array[] list of rows: taxonomy, label, values
	 */
	public static function resolve_card_rows(WC_Product $product, array $enabled) {
		$rows = array();
		foreach ($enabled as $taxonomy => $label) {
			$values = self::get_taxonomy_term_names($product, $taxonomy);
			if (empty($values)) {
				continue;
			}
			$rows[$taxonomy] = array(
				'taxonomy' => $taxonomy,
				'label'    => $label,
				'values'   => $values,
			);
		}

		$hidden_children = array();

		foreach (self::get_card_rules() as $rule) {
			$parent = $rule['parent_tax'];
			$child  = $rule['child_tax'];
			$match  = self::product_has_parent_value($product, $parent, $rule['parent_value']);

			if (!$match) {
				unset($rows[$child]);
				$hidden_children[$child] = true;
				continue;
			}

			$child_values = self::get_taxonomy_term_names($product, $child);
			if (empty($child_values)) {
				continue;
			}

			// Prefer showing real values under parent label when parent is enabled.
			if (isset($rows[$parent])) {
				$replaced = array();
				foreach ($rows[$parent]['values'] as $value) {
					if (self::term_matches_value($value, sanitize_title($value), $rule['parent_value'])) {
						foreach ($child_values as $cv) {
							$replaced[] = $cv;
						}
					} else {
						$replaced[] = $value;
					}
				}
				$rows[$parent]['values'] = array_values(array_unique($replaced));
				unset($rows[$child]);
				$hidden_children[$child] = true;
			} elseif (isset($enabled[$child]) && !isset($hidden_children[$child])) {
				$rows[$child] = array(
					'taxonomy' => $child,
					'label'    => $enabled[$child],
					'values'   => $child_values,
				);
			}
		}

		return array_values($rows);
	}

	public static function get_terms_for_taxonomy($taxonomy, $object_ids = null) {
		if (is_array($object_ids) && empty($object_ids)) {
			return array();
		}

		$args = array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => true,
		);

		if (is_array($object_ids)) {
			$args['object_ids'] = array_map('intval', $object_ids);
		}

		$terms = get_terms($args);

		if (is_wp_error($terms) || empty($terms)) {
			return array();
		}

		$items = array();
		foreach ($terms as $term) {
			$items[] = array(
				'slug' => $term->slug,
				'name' => $term->name,
			);
		}

		return $items;
	}
}
