<?php

if (!defined('ABSPATH')) exit;

class KPK_Settings {

	public static function init() {
		add_action('admin_menu', array(__CLASS__, 'register_menu'));
		add_action('admin_init', array(__CLASS__, 'handle_save'));
		add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_assets'));
	}

	public static function register_menu() {
		add_submenu_page(
			'woocommerce',
			'Kafra Produktfilter',
			'Produktfilter',
			'manage_woocommerce',
			'kafra-produktkatalog',
			array(__CLASS__, 'render_page')
		);
	}

	public static function enqueue_assets($hook) {
		if ($hook !== 'woocommerce_page_kafra-produktkatalog') {
			return;
		}

		wp_enqueue_style(
			'kpk-admin',
			KPK_PLUGIN_URL . 'assets/admin.css',
			array(),
			KPK_VERSION
		);

		wp_enqueue_script(
			'kpk-admin',
			KPK_PLUGIN_URL . 'assets/admin.js',
			array(),
			KPK_VERSION,
			true
		);
	}

	public static function handle_save() {
		if (empty($_POST['kpk_save_settings'])) {
			return;
		}

		if (!current_user_can('manage_woocommerce')) {
			return;
		}

		check_admin_referer('kpk_save_settings');

		$taxonomies = array_keys(KPK_Plugin::get_filterable_taxonomies());

		$filters = array();
		$card_attrs = array();
		foreach ($taxonomies as $tax) {
			$filters[$tax] = !empty($_POST['filters'][$tax]) ? 1 : 0;
			$card_attrs[$tax] = !empty($_POST['card_attrs'][$tax]) ? 1 : 0;
		}

		$attr_rules = array();
		if (!empty($_POST['attr_rules']) && is_array($_POST['attr_rules'])) {
			$attr_rules = wp_unslash($_POST['attr_rules']);
		}

		KPK_Plugin::save_settings(array(
			'filters'             => $filters,
			'card_attrs'          => $card_attrs,
			'attr_rules'          => $attr_rules,
			'ajax'                => !empty($_POST['ajax']) ? 1 : 0,
			'show_search'         => !empty($_POST['show_search']) ? 1 : 0,
			'hide_empty_filters'  => !empty($_POST['hide_empty_filters']) ? 1 : 0,
			'search_placeholder'  => isset($_POST['search_placeholder']) ? wp_unslash($_POST['search_placeholder']) : '',
		));

		wp_safe_redirect(add_query_arg(
			array(
				'page'      => 'kafra-produktkatalog',
				'kpk_saved' => 1,
			),
			admin_url('admin.php')
		));
		exit;
	}

	public static function render_page() {
		if (!current_user_can('manage_woocommerce')) return;

		$settings   = KPK_Plugin::get_settings();
		$filterable = KPK_Plugin::get_filterable_taxonomies();
		$rules      = !empty($settings['attr_rules']) ? $settings['attr_rules'] : array();
		if (empty($rules)) {
			$rules = array(
				array(
					'parent_tax'    => '',
					'parent_value'  => '',
					'child_tax'     => '',
					'apply_cards'   => 1,
					'apply_filters' => 1,
				),
			);
		}
		?>
		<div class="wrap kpk-wrap">
			<h1 class="wp-heading-inline">Kafra Produktfilter</h1>
			<hr class="wp-header-end">

			<?php if (!empty($_GET['kpk_saved'])) : ?>
				<div class="notice notice-success is-dismissible"><p>Innstillinger lagret.</p></div>
			<?php endif; ?>

			<p class="kpk-intro">
				Legg til widgeten <strong>Kafra produktfilter</strong> i <strong>Shop Sidebar</strong>.
				Den filtrerer WooCommerce/Flatsome sin vanlige produktkatalog.
			</p>

			<form method="post" action="">
				<?php wp_nonce_field('kpk_save_settings'); ?>

				<div class="kpk-step">
					<h2>Filtre i widgeten</h2>
					<p class="description">Velg hvilke kategorier/attributter som skal vises i filtermenyen. Underordnede attributter fra regler under skjules automatisk som egne grupper.</p>

					<div class="kpk-toggle-grid">
						<?php foreach ($filterable as $tax => $label) : ?>
							<label class="kpk-toggle">
								<input type="checkbox" name="filters[<?php echo esc_attr($tax); ?>]" value="1" <?php checked(!empty($settings['filters'][$tax])); ?>>
								<span><?php echo esc_html($label); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
				</div>

				<div class="kpk-step">
					<h2>Visning på produktkort</h2>
					<p class="description">Velg hvilke attributter som skal vises på produktkortene. Regler under styrer når underordnede attributter vises.</p>

					<div class="kpk-toggle-grid">
						<?php foreach ($filterable as $tax => $label) : ?>
							<label class="kpk-toggle">
								<input type="checkbox" name="card_attrs[<?php echo esc_attr($tax); ?>]" value="1" <?php checked(!empty($settings['card_attrs'][$tax])); ?>>
								<span><?php echo esc_html($label); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
				</div>

				<div class="kpk-step">
					<h2>Attributtregler</h2>
					<p class="description">
						Eksempel: Hvis <strong>Mål</strong> = <strong>Spesialmål</strong>, nest/vis attributtet <strong>Spesialmål</strong>.
						På kort erstattes «Spesialmål» med den faktiske verdien. I filteret legges Spesialmål-valgene under Mål → Spesialmål.
					</p>

					<div class="kpk-rules" data-kpk-rules>
						<?php foreach ($rules as $index => $rule) :
							self::render_rule_row($index, $rule, $filterable);
						endforeach; ?>
					</div>

					<p>
						<button type="button" class="button" data-kpk-add-rule>Legg til regel</button>
					</p>

					<template id="kpk-rule-template">
						<?php self::render_rule_row('__INDEX__', array(
							'parent_tax'    => '',
							'parent_value'  => '',
							'child_tax'     => '',
							'apply_cards'   => 1,
							'apply_filters' => 1,
						), $filterable); ?>
					</template>
				</div>

				<div class="kpk-step">
					<h2>Oppførsel</h2>
					<label class="kpk-toggle">
						<input type="checkbox" name="show_search" value="1" <?php checked(!empty($settings['show_search'])); ?>>
						<span>Vis søkefelt i widgeten</span>
					</label>
					<label class="kpk-toggle">
						<input type="checkbox" name="ajax" value="1" <?php checked(!empty($settings['ajax'])); ?>>
						<span>AJAX-filtrering (uten full side-reload)</span>
					</label>
					<label class="kpk-toggle">
						<input type="checkbox" name="hide_empty_filters" value="1" <?php checked(!empty($settings['hide_empty_filters'])); ?>>
						<span>Skjul filtre/valg uten produkter i gjeldende kategori/kontekst</span>
					</label>
					<p class="kpk-field">
						<label for="kpk-search-placeholder"><strong>Placeholder for søk</strong></label><br>
						<input type="text" class="regular-text" id="kpk-search-placeholder" name="search_placeholder" value="<?php echo esc_attr($settings['search_placeholder']); ?>">
					</p>
				</div>

				<p class="submit">
					<button type="submit" name="kpk_save_settings" value="1" class="button button-primary">Lagre innstillinger</button>
				</p>
			</form>
		</div>
		<?php
	}

	private static function render_rule_row($index, $rule, $filterable) {
		$parent_tax = isset($rule['parent_tax']) ? $rule['parent_tax'] : '';
		$child_tax  = isset($rule['child_tax']) ? $rule['child_tax'] : '';
		$parent_val = isset($rule['parent_value']) ? $rule['parent_value'] : '';
		?>
		<div class="kpk-rule" data-kpk-rule>
			<div class="kpk-rule__grid">
				<label>
					<span>Hvis attributt</span>
					<select name="attr_rules[<?php echo esc_attr($index); ?>][parent_tax]">
						<option value="">— velg —</option>
						<?php foreach ($filterable as $tax => $label) : ?>
							<option value="<?php echo esc_attr($tax); ?>" <?php selected($parent_tax, $tax); ?>>
								<?php echo esc_html($label); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</label>

				<label>
					<span>er lik</span>
					<input type="text" name="attr_rules[<?php echo esc_attr($index); ?>][parent_value]" value="<?php echo esc_attr($parent_val); ?>" placeholder="f.eks. Spesialmål">
				</label>

				<label>
					<span>bruk underattributt</span>
					<select name="attr_rules[<?php echo esc_attr($index); ?>][child_tax]">
						<option value="">— velg —</option>
						<?php foreach ($filterable as $tax => $label) : ?>
							<option value="<?php echo esc_attr($tax); ?>" <?php selected($child_tax, $tax); ?>>
								<?php echo esc_html($label); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</label>
			</div>

			<div class="kpk-rule__meta">
				<label class="kpk-rule__check">
					<input type="checkbox" name="attr_rules[<?php echo esc_attr($index); ?>][apply_cards]" value="1" <?php checked(!empty($rule['apply_cards'])); ?>>
					<span>Produktkort</span>
				</label>
				<label class="kpk-rule__check">
					<input type="checkbox" name="attr_rules[<?php echo esc_attr($index); ?>][apply_filters]" value="1" <?php checked(!empty($rule['apply_filters'])); ?>>
					<span>Filtermeny</span>
				</label>
				<button type="button" class="button-link-delete kpk-rule__remove" data-kpk-remove-rule>Fjern</button>
			</div>
		</div>
		<?php
	}
}
