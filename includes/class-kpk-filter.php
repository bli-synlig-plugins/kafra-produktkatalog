<?php

if (!defined('ABSPATH')) exit;

class KPK_Filter {

	public static function init() {
		add_action('woocommerce_product_query', array(__CLASS__, 'apply_to_product_query'));
		add_action('wp_ajax_kpk_filter_shop', array(__CLASS__, 'ajax_filter_shop'));
		add_action('wp_ajax_nopriv_kpk_filter_shop', array(__CLASS__, 'ajax_filter_shop'));
		add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_assets'));
		add_filter('woocommerce_is_filtered', array(__CLASS__, 'maybe_mark_filtered'));
		add_filter('woocommerce_get_loop_display_mode', array(__CLASS__, 'force_products_display'), 99);
		add_filter('option_woocommerce_shop_page_display', array(__CLASS__, 'force_shop_option_products'));
		add_filter('option_woocommerce_category_archive_display', array(__CLASS__, 'force_shop_option_products'));
		add_filter('theme_mod_html_shop_page_content', array(__CLASS__, 'disable_flatsome_shop_override'));
		add_filter('template_include', array(__CLASS__, 'force_catalog_template'), 99);
	}

	public static function maybe_mark_filtered($is_filtered) {
		if ($is_filtered) {
			return $is_filtered;
		}

		if (self::get_active_search_from_request() !== '') {
			return true;
		}

		return !empty(self::get_active_filters_from_request());
	}

	public static function force_products_display($mode) {
		if (is_shop() || is_product_taxonomy()) {
			return 'products';
		}

		return $mode;
	}

	public static function force_shop_option_products($value) {
		if (!is_admin()) {
			return '';
		}

		return $value;
	}

	public static function disable_flatsome_shop_override($content) {
		if (is_admin()) {
			return $content;
		}

		return '';
	}

	public static function force_catalog_template($template) {
		if (is_admin()) {
			return $template;
		}

		if (!function_exists('is_shop') || (!is_shop() && !is_product_taxonomy())) {
			return $template;
		}

		$custom = KPK_PLUGIN_DIR . 'templates/archive-product.php';
		if (!file_exists($custom)) {
			return $template;
		}

		return $custom;
	}

	public static function enqueue_assets() {
		if (!self::should_load_assets()) {
			return;
		}

		$settings = KPK_Plugin::get_settings();

		wp_enqueue_style(
			'kpk-filter',
			KPK_PLUGIN_URL . 'assets/frontend.css',
			array(),
			KPK_VERSION
		);

		wp_enqueue_script(
			'kpk-filter',
			KPK_PLUGIN_URL . 'assets/frontend.js',
			array(),
			KPK_VERSION,
			true
		);

		$context = self::get_catalog_context();

		wp_localize_script('kpk-filter', 'kpkFilter', array(
			'ajaxUrl'     => admin_url('admin-ajax.php'),
			'nonce'       => wp_create_nonce('kpk_filter_shop'),
			'ajax'        => !empty($settings['ajax']),
			'catalogUrl'  => self::get_current_catalog_url(),
			'contextTax'  => $context ? $context['taxonomy'] : '',
			'contextTerm' => $context ? (int) $context['term_id'] : 0,
			'i18n'        => array(
				'loading' => 'Filtrerer…',
				'error'   => 'Kunne ikke filtrere. Prøv igjen.',
				'reset'   => 'Nullstill',
			),
		));
	}

	private static function should_load_assets() {
		if (is_admin()) {
			return false;
		}

		return is_shop() || is_product_taxonomy() || is_active_widget(false, false, 'kpk_product_filter', true);
	}

	public static function get_active_search_from_request() {
		$settings = KPK_Plugin::get_settings();
		if (empty($settings['show_search'])) {
			return '';
		}

		if (isset($_POST['kpk_search'])) {
			return sanitize_text_field(wp_unslash($_POST['kpk_search']));
		}

		if (isset($_GET['kpk_search'])) {
			return sanitize_text_field(wp_unslash($_GET['kpk_search']));
		}

		return '';
	}

	public static function get_active_filters_from_request() {
		$filters = array();
		$enabled = array_keys(KPK_Plugin::get_enabled_filters());

		// Nested child taxonomies from rules are allowed even if not top-level filters.
		foreach (KPK_Rules::get_nested_filter_child_taxonomies() as $child_tax) {
			$enabled[] = $child_tax;
		}
		$enabled = array_values(array_unique($enabled));

		$source = array();
		if (!empty($_POST['kpk_filters']) && is_array($_POST['kpk_filters'])) {
			$source = wp_unslash($_POST['kpk_filters']);
		} elseif (!empty($_GET['kpk_filters']) && is_array($_GET['kpk_filters'])) {
			$source = wp_unslash($_GET['kpk_filters']);
		}

		foreach ($source as $tax => $terms) {
			$tax = sanitize_key($tax);
			if (!in_array($tax, $enabled, true) || !taxonomy_exists($tax)) {
				continue;
			}

			if (!is_array($terms)) {
				$terms = array($terms);
			}

			$slugs = array();
			foreach ($terms as $term) {
				$slug = sanitize_title($term);
				if ($slug !== '') {
					$slugs[] = $slug;
				}
			}

			$slugs = array_values(array_unique($slugs));
			if ($slugs) {
				$filters[$tax] = $slugs;
			}
		}

		return $filters;
	}

	public static function apply_to_product_query($query) {
		$filters = self::get_active_filters_from_request();
		$search  = self::get_active_search_from_request();
		$context = self::get_catalog_context();

		if ($search !== '') {
			$query->set('s', $search);
		}

		if (empty($filters)) {
			return;
		}

		$tax_query = (array) $query->get('tax_query');
		if (empty($tax_query)) {
			$tax_query = array();
		}

		foreach ($filters as $taxonomy => $slugs) {
			if ($context && $taxonomy === $context['taxonomy']) {
				continue;
			}
			$tax_query[] = array(
				'taxonomy' => $taxonomy,
				'field'    => 'slug',
				'terms'    => $slugs,
				'operator' => 'IN',
			);
		}

		if (count($tax_query) > 1 && empty($tax_query['relation'])) {
			$tax_query['relation'] = 'AND';
		}

		$query->set('tax_query', $tax_query);
	}

	public static function ajax_filter_shop() {
		check_ajax_referer('kpk_filter_shop', 'nonce');

		$page = isset($_REQUEST['page']) ? max(1, (int) $_REQUEST['page']) : 1;

		// Seed $_GET so woocommerce_product_query / templates see the same filters.
		if (!empty($_REQUEST['kpk_filters']) && is_array($_REQUEST['kpk_filters'])) {
			$_GET['kpk_filters'] = wp_unslash($_REQUEST['kpk_filters']);
		} else {
			unset($_GET['kpk_filters']);
		}

		if (isset($_REQUEST['kpk_search'])) {
			$_GET['kpk_search'] = sanitize_text_field(wp_unslash($_REQUEST['kpk_search']));
		} else {
			unset($_GET['kpk_search']);
		}

		$ordering = WC()->query->get_catalog_ordering_args();
		$per_page = self::get_products_per_page();
		$search   = self::get_active_search_from_request();

		$args = array(
			'post_type'           => 'product',
			'post_status'         => 'publish',
			'ignore_sticky_posts' => 1,
			'posts_per_page'      => $per_page,
			'paged'               => $page,
			'orderby'             => $ordering['orderby'],
			'order'               => $ordering['order'],
			'meta_query'          => WC()->query->get_meta_query(),
			'tax_query'           => WC()->query->get_tax_query(),
		);

		if ($search !== '') {
			$args['s'] = $search;
		}

		if (!empty($ordering['meta_key'])) {
			$args['meta_key'] = $ordering['meta_key'];
		}

		if (!is_array($args['tax_query'])) {
			$args['tax_query'] = array();
		}
		if (empty($args['tax_query']['relation'])) {
			$args['tax_query']['relation'] = 'AND';
		}

		$context = self::get_catalog_context();
		if ($context) {
			$args['tax_query'][] = array(
				'taxonomy' => $context['taxonomy'],
				'field'    => 'term_id',
				'terms'    => array((int) $context['term_id']),
			);
		}

		$filters = self::get_active_filters_from_request();
		foreach ($filters as $taxonomy => $slugs) {
			// Keep archive context authoritative for that taxonomy.
			if ($context && $taxonomy === $context['taxonomy']) {
				continue;
			}
			$args['tax_query'][] = array(
				'taxonomy' => $taxonomy,
				'field'    => 'slug',
				'terms'    => $slugs,
				'operator' => 'IN',
			);
		}

		$query = new WP_Query($args);

		ob_start();

		woocommerce_product_loop_start();

		if ($query->have_posts()) {
			while ($query->have_posts()) {
				$query->the_post();
				wc_get_template_part('content', 'product');
			}
		}

		woocommerce_product_loop_end();

		if (!$query->have_posts()) {
			echo '<p class="woocommerce-info kpk-no-results">' . esc_html__('Ingen produkter ble funnet.', 'woocommerce') . '</p>';
		}

		$products_html = ob_get_clean();

		ob_start();
		self::render_pagination($query, $page);
		$pagination_html = ob_get_clean();

		$result_count = '';
		if ($query->found_posts > 0) {
			$first = (($page - 1) * $per_page) + 1;
			$last  = min($page * $per_page, (int) $query->found_posts);
			$total = (int) $query->found_posts;
			$result_count = sprintf(
				_nx(
					'Viser %1$d–%2$d av %3$d resultat',
					'Viser %1$d–%2$d av %3$d resultater',
					$total,
					'with first and last result',
					'woocommerce'
				),
				$first,
				$last,
				$total
			);
		}

		wp_reset_postdata();
		WC()->query->remove_ordering_args();

		wp_send_json_success(array(
			'products'   => $products_html,
			'pagination' => $pagination_html,
			'count'      => $result_count,
			'total'      => (int) $query->found_posts,
			'page'       => $page,
			'pages'      => (int) $query->max_num_pages,
		));
	}

	private static function get_products_per_page() {
		$columns = function_exists('wc_get_default_products_per_row') ? wc_get_default_products_per_row() : 4;
		$rows    = function_exists('wc_get_default_product_rows_per_page') ? wc_get_default_product_rows_per_page() : 4;
		$per     = (int) apply_filters('loop_shop_per_page', $columns * $rows);

		return max(1, $per);
	}

	private static function render_pagination($query, $current) {
		$total = (int) $query->max_num_pages;
		if ($total <= 1) {
			return;
		}

		echo '<nav class="woocommerce-pagination" aria-label="' . esc_attr__('Produktpaginering', 'woocommerce') . '">';
		echo paginate_links(array(
			'base'      => esc_url_raw(str_replace(999999999, '%#%', get_pagenum_link(999999999, false))),
			'format'    => '',
			'add_args'  => false,
			'current'   => max(1, $current),
			'total'     => $total,
			'prev_text' => '&larr;',
			'next_text' => '&rarr;',
			'type'      => 'list',
		));
		echo '</nav>';
	}

	/**
	 * Product IDs in current catalog context (archive + other active filters + search).
	 *
	 * @param string[] $exclude_taxonomies Active kpk filters to ignore (current facet).
	 * @param array    $force_filters      Extra taxonomy => slugs to require.
	 * @return int[]
	 */
	public static function get_facet_product_ids(array $exclude_taxonomies = array(), array $force_filters = array()) {
		static $cache = array();

		$cache_key = md5(wp_json_encode(array($exclude_taxonomies, $force_filters, self::get_catalog_context(), self::get_active_filters_from_request(), self::get_active_search_from_request())));
		if (isset($cache[$cache_key])) {
			return $cache[$cache_key];
		}

		$tax_query = array('relation' => 'AND');

		$context = self::get_catalog_context();
		if ($context) {
			$tax_query[] = array(
				'taxonomy' => $context['taxonomy'],
				'field'    => 'term_id',
				'terms'    => array((int) $context['term_id']),
			);
		}

		$filters = self::get_active_filters_from_request();
		foreach ($filters as $taxonomy => $slugs) {
			if (in_array($taxonomy, $exclude_taxonomies, true)) {
				continue;
			}
			$tax_query[] = array(
				'taxonomy' => $taxonomy,
				'field'    => 'slug',
				'terms'    => $slugs,
				'operator' => 'IN',
			);
		}

		foreach ($force_filters as $taxonomy => $slugs) {
			$taxonomy = sanitize_key($taxonomy);
			if ($taxonomy === '' || empty($slugs) || !taxonomy_exists($taxonomy)) {
				continue;
			}
			$tax_query[] = array(
				'taxonomy' => $taxonomy,
				'field'    => 'slug',
				'terms'    => array_values((array) $slugs),
				'operator' => 'IN',
			);
		}

		if (function_exists('WC') && WC()->query) {
			$visibility = WC()->query->get_tax_query();
			if (!empty($visibility)) {
				foreach ($visibility as $key => $clause) {
					if ($key === 'relation' || !is_array($clause)) {
						continue;
					}
					$tax_query[] = $clause;
				}
			}
		}

		$args = array(
			'post_type'              => 'product',
			'post_status'            => 'publish',
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'orderby'                => 'ID',
			'order'                  => 'ASC',
		);

		if (count($tax_query) > 1) {
			$args['tax_query'] = $tax_query;
		}

		$search = self::get_active_search_from_request();
		if ($search !== '') {
			$args['s'] = $search;
		}

		$ids = get_posts($args);
		$ids = is_array($ids) ? array_map('intval', $ids) : array();
		$cache[$cache_key] = $ids;

		return $ids;
	}

	/**
	 * Current catalog URL: category/attribute archive when applicable, otherwise shop.
	 */
	public static function get_current_catalog_url() {
		$context = self::get_catalog_context();
		if ($context) {
			$link = get_term_link((int) $context['term_id'], $context['taxonomy']);
			if (!is_wp_error($link) && $link) {
				return $link;
			}
		}

		return wc_get_page_permalink('shop');
	}

	/**
	 * Current product taxonomy archive, if any (front request or AJAX context params).
	 *
	 * @return array{taxonomy:string,term_id:int}|null
	 */
	public static function get_catalog_context() {
		if (!empty($_REQUEST['kpk_context_tax']) && !empty($_REQUEST['kpk_context_term'])) {
			$taxonomy = sanitize_key(wp_unslash($_REQUEST['kpk_context_tax']));
			$term_id  = (int) $_REQUEST['kpk_context_term'];
			if ($taxonomy && $term_id > 0 && taxonomy_exists($taxonomy) && term_exists($term_id, $taxonomy)) {
				return array(
					'taxonomy' => $taxonomy,
					'term_id'  => $term_id,
				);
			}
		}

		if (function_exists('is_product_taxonomy') && is_product_taxonomy()) {
			$term = get_queried_object();
			if ($term && !is_wp_error($term) && !empty($term->taxonomy) && !empty($term->term_id)) {
				return array(
					'taxonomy' => $term->taxonomy,
					'term_id'  => (int) $term->term_id,
				);
			}
		}

		return null;
	}

	public static function get_filter_groups() {
		$groups = array();
		$enabled = KPK_Plugin::get_enabled_filters();
		$nested_children = array_fill_keys(KPK_Rules::get_nested_filter_child_taxonomies(), true);
		$nest_map = KPK_Rules::get_filter_nest_map();
		$settings = KPK_Plugin::get_settings();
		$hide_empty = !empty($settings['hide_empty_filters']);

		foreach ($enabled as $taxonomy => $label) {
			if (isset($nested_children[$taxonomy])) {
				continue;
			}

			$object_ids = null;
			if ($hide_empty) {
				$object_ids = self::get_facet_product_ids(array($taxonomy));
			}

			$terms = KPK_Rules::get_terms_for_taxonomy($taxonomy, $object_ids);
			if (empty($terms)) {
				continue;
			}

			$items = array();
			$rules_for_parent = isset($nest_map[$taxonomy]) ? $nest_map[$taxonomy] : array();

			foreach ($terms as $term) {
				$children = array();
				foreach ($rules_for_parent as $rule) {
					if (!KPK_Rules::term_matches_value($term['name'], $term['slug'], $rule['parent_value'])) {
						continue;
					}

					$child_object_ids = $object_ids;
					if ($hide_empty) {
						$child_object_ids = self::get_facet_product_ids(
							array($rule['child_tax']),
							array($taxonomy => array($term['slug']))
						);
					}

					$child_terms = KPK_Rules::get_terms_for_taxonomy($rule['child_tax'], $child_object_ids);
					if (empty($child_terms)) {
						continue;
					}

					$child_label = $rule['child_tax'];
					$labels = KPK_Plugin::get_filterable_taxonomies();
					if (isset($labels[$rule['child_tax']])) {
						$child_label = $labels[$rule['child_tax']];
					}

					$children[] = array(
						'taxonomy' => $rule['child_tax'],
						'label'    => $child_label,
						'terms'    => $child_terms,
					);
				}

				$items[] = array(
					'slug'     => $term['slug'],
					'name'     => $term['name'],
					'children' => $children,
				);
			}

			$groups[] = array(
				'taxonomy' => $taxonomy,
				'label'    => $label,
				'terms'    => $items,
			);
		}

		return $groups;
	}
}
