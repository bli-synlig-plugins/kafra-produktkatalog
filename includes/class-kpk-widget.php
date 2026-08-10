<?php

if (!defined('ABSPATH')) exit;

class KPK_Widget extends WP_Widget {

	public function __construct() {
		parent::__construct(
			'kpk_product_filter',
			'Kafra produktfilter',
			array(
				'description' => 'AJAX-filtermeny til WooCommerce produktkatalog.',
				'classname'   => 'kpk-filter-widget',
			)
		);
	}

	public function widget($args, $instance) {
		if (!is_shop() && !is_product_taxonomy()) {
			return;
		}

		$settings    = KPK_Plugin::get_settings();
		$groups      = KPK_Filter::get_filter_groups();
		$show_search = !empty($settings['show_search']);

		if (!$show_search && empty($groups)) {
			return;
		}

		$title  = !empty($instance['title']) ? $instance['title'] : 'Filtrer';
		$active = KPK_Filter::get_active_filters_from_request();
		$search = KPK_Filter::get_active_search_from_request();

		echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		if ($title !== '') {
			echo $args['before_title'] . esc_html($title) . $args['after_title']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		?>
		<form class="kpk-filters" data-kpk-filters method="get" action="<?php echo esc_url(KPK_Filter::get_current_catalog_url()); ?>">
			<?php if ($show_search) :
				$search_id = $this->id . '-search';
				$placeholder = !empty($settings['search_placeholder']) ? $settings['search_placeholder'] : 'Søk produkter…';
				?>
				<div class="kpk-search">
					<label class="screen-reader-text" for="<?php echo esc_attr($search_id); ?>">Søk</label>
					<input
						type="search"
						id="<?php echo esc_attr($search_id); ?>"
						class="kpk-search__input"
						name="kpk_search"
						value="<?php echo esc_attr($search); ?>"
						placeholder="<?php echo esc_attr($placeholder); ?>"
						autocomplete="off"
					>
				</div>
			<?php endif; ?>

			<?php foreach ($groups as $group) :
				$selected = isset($active[$group['taxonomy']]) ? $active[$group['taxonomy']] : array();
				?>
				<fieldset class="kpk-filter" data-taxonomy="<?php echo esc_attr($group['taxonomy']); ?>">
					<legend class="kpk-filter__legend"><?php echo esc_html($group['label']); ?></legend>
					<div class="kpk-filter__options">
						<?php foreach ($group['terms'] as $term) :
							$input_id = $this->id . '-' . $group['taxonomy'] . '-' . $term['slug'];
							$checked  = in_array($term['slug'], $selected, true);
							$children = !empty($term['children']) ? $term['children'] : array();
							$has_active_child = false;
							foreach ($children as $child_group) {
								$child_selected = isset($active[$child_group['taxonomy']]) ? $active[$child_group['taxonomy']] : array();
								if (!empty($child_selected)) {
									$has_active_child = true;
									break;
								}
							}
							$open = $checked || $has_active_child;
							?>
							<div class="kpk-filter__item<?php echo $children ? ' kpk-filter__item--nested' : ''; ?>">
								<label class="kpk-filter__option" for="<?php echo esc_attr($input_id); ?>">
									<input
										type="checkbox"
										id="<?php echo esc_attr($input_id); ?>"
										class="kpk-filter__parent"
										name="kpk_filters[<?php echo esc_attr($group['taxonomy']); ?>][]"
										value="<?php echo esc_attr($term['slug']); ?>"
										<?php checked($checked || $has_active_child); ?>
										<?php echo $children ? ' data-kpk-has-children="1"' : ''; ?>
									>
									<span><?php echo esc_html($term['name']); ?></span>
								</label>

								<?php if ($children) : ?>
									<div class="kpk-filter__children" <?php echo $open ? '' : 'hidden'; ?>>
										<?php foreach ($children as $child_group) :
											$child_selected = isset($active[$child_group['taxonomy']]) ? $active[$child_group['taxonomy']] : array();
											?>
											<div class="kpk-filter__child-group" data-taxonomy="<?php echo esc_attr($child_group['taxonomy']); ?>">
												<?php foreach ($child_group['terms'] as $child_term) :
													$child_id = $this->id . '-' . $child_group['taxonomy'] . '-' . $child_term['slug'];
													$child_checked = in_array($child_term['slug'], $child_selected, true);
													?>
													<label class="kpk-filter__option kpk-filter__option--child" for="<?php echo esc_attr($child_id); ?>">
														<input
															type="checkbox"
															id="<?php echo esc_attr($child_id); ?>"
															class="kpk-filter__child"
															name="kpk_filters[<?php echo esc_attr($child_group['taxonomy']); ?>][]"
															value="<?php echo esc_attr($child_term['slug']); ?>"
															data-kpk-parent-input="<?php echo esc_attr($input_id); ?>"
															<?php checked($child_checked); ?>
														>
														<span><?php echo esc_html($child_term['name']); ?></span>
													</label>
												<?php endforeach; ?>
											</div>
										<?php endforeach; ?>
									</div>
								<?php endif; ?>
							</div>
						<?php endforeach; ?>
					</div>
				</fieldset>
			<?php endforeach; ?>

			<button type="button" class="kpk-filters__reset" data-kpk-reset <?php echo (empty($active) && $search === '') ? 'hidden' : ''; ?>>
				Nullstill
			</button>

			<noscript>
				<button type="submit" class="kpk-filters__submit">Filtrer</button>
			</noscript>
		</form>
		<?php

		echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	public function form($instance) {
		$title = isset($instance['title']) ? $instance['title'] : 'Filtrer';
		?>
		<p>
			<label for="<?php echo esc_attr($this->get_field_id('title')); ?>">Tittel</label>
			<input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>" name="<?php echo esc_attr($this->get_field_name('title')); ?>" type="text" value="<?php echo esc_attr($title); ?>">
		</p>
		<p class="description">Søk og filtre styres under WooCommerce → Produktfilter.</p>
		<?php
	}

	public function update($new_instance, $old_instance) {
		return array(
			'title' => sanitize_text_field(isset($new_instance['title']) ? $new_instance['title'] : ''),
		);
	}
}
