(function () {
	'use strict';

	if (!window.kpkFilter) return;

	var cfg = window.kpkFilter;
	var form = document.querySelector('[data-kpk-filters]');
	if (!form) return;

	var resetBtn = form.querySelector('[data-kpk-reset]');
	var searchInput = form.querySelector('.kpk-search__input');
	var requestId = 0;
	var searchTimer = null;

	function getFilters() {
		var filters = {};
		form.querySelectorAll('input[type="checkbox"][name^="kpk_filters"]:checked').forEach(function (input) {
			var match = input.name.match(/^kpk_filters\[([^\]]+)\]/);
			if (!match) return;
			var tax = match[1];
			if (!filters[tax]) {
				filters[tax] = [];
			}
			if (filters[tax].indexOf(input.value) === -1) {
				filters[tax].push(input.value);
			}
		});
		return filters;
	}

	function syncNested(changed) {
		if (!changed) return;

		if (changed.classList.contains('kpk-filter__parent') && changed.getAttribute('data-kpk-has-children')) {
			var item = changed.closest('.kpk-filter__item');
			if (!item) return;
			var children = item.querySelector('.kpk-filter__children');
			if (!children) return;

			if (!changed.checked) {
				children.querySelectorAll('input[type="checkbox"]').forEach(function (input) {
					input.checked = false;
				});
				children.hidden = true;
			} else {
				children.hidden = false;
			}
			return;
		}

		if (changed.classList.contains('kpk-filter__child')) {
			var parentId = changed.getAttribute('data-kpk-parent-input');
			var parent = parentId ? document.getElementById(parentId) : null;
			var childWrap = changed.closest('.kpk-filter__children');
			if (parent && changed.checked) {
				parent.checked = true;
			}
			if (childWrap) {
				childWrap.hidden = false;
			}
			if (parent && childWrap && !changed.checked) {
				var anyChild = childWrap.querySelector('input[type="checkbox"]:checked');
				if (!anyChild && !parent.checked) {
					childWrap.hidden = true;
				}
			}
		}
	}

	function getSearch() {
		return searchInput ? searchInput.value.trim() : '';
	}

	function hasActiveState(filters, search) {
		filters = filters || getFilters();
		search = typeof search === 'string' ? search : getSearch();
		return Object.keys(filters).length > 0 || search !== '';
	}

	function updateReset() {
		if (!resetBtn) return;
		resetBtn.hidden = !hasActiveState();
	}

	function buildUrl(filters, search) {
		var base = cfg.catalogUrl || cfg.shopUrl || (window.location.origin + window.location.pathname);
		var url = new URL(base, window.location.origin);
		var keys = [];
		url.searchParams.forEach(function (_value, key) {
			if (
				key.indexOf('kpk_filters') === 0 ||
				key === 'kpk_search' ||
				key === 'paged' ||
				key === 'product-page'
			) {
				keys.push(key);
			}
		});
		keys.forEach(function (key) {
			url.searchParams.delete(key);
		});

		Object.keys(filters).forEach(function (tax) {
			filters[tax].forEach(function (slug) {
				url.searchParams.append('kpk_filters[' + tax + '][]', slug);
			});
		});

		if (search) {
			url.searchParams.set('kpk_search', search);
		}

		return url;
	}

	function findProductsContainer() {
		return (
			document.querySelector('.shop-container .products') ||
			document.querySelector('div.products') ||
			document.querySelector('ul.products') ||
			document.querySelector('.products')
		);
	}

	function findPagination() {
		return document.querySelector('.woocommerce-pagination');
	}

	function findResultCount() {
		return document.querySelector('.woocommerce-result-count');
	}

	function setLoading(on) {
		document.body.classList.toggle('kpk-is-filtering', on);
	}

	function replaceProducts(html) {
		var container = findProductsContainer();
		if (!container) return;

		var wrap = document.createElement('div');
		wrap.innerHTML = String(html || '').trim();

		var nextProducts =
			wrap.querySelector('.shop-container .products') ||
			wrap.querySelector('div.products') ||
			wrap.querySelector('ul.products') ||
			wrap.querySelector('.products');
		var notice = wrap.querySelector('.kpk-no-results');
		var existingNotice = document.querySelector('.kpk-no-results');

		if (existingNotice) {
			existingNotice.remove();
		}

		if (nextProducts) {
			container.replaceWith(nextProducts);
			if (notice && nextProducts.parentNode) {
				nextProducts.parentNode.insertBefore(notice, nextProducts.nextSibling);
			}
			return;
		}

		container.innerHTML = html;
	}

	function replacePagination(html) {
		var current = findPagination();
		var wrap = document.createElement('div');
		wrap.innerHTML = String(html || '').trim();
		var next = wrap.firstElementChild;

		if (current && next) {
			current.replaceWith(next);
		} else if (current && !next) {
			current.remove();
		} else if (!current && next) {
			var products = findProductsContainer();
			if (products && products.parentNode) {
				products.parentNode.insertBefore(next, products.nextSibling);
			}
		}
	}

	function replaceCount(text) {
		var el = findResultCount();
		if (!el) return;
		if (text) {
			el.textContent = text;
			el.hidden = false;
		} else {
			el.textContent = '';
		}
	}

	function fetchFiltered(filters, search) {
		var id = ++requestId;
		setLoading(true);

		var body = new FormData();
		body.append('action', 'kpk_filter_shop');
		body.append('nonce', cfg.nonce);
		body.append('page', '1');
		body.append('kpk_search', search || '');
		if (cfg.contextTax && cfg.contextTerm) {
			body.append('kpk_context_tax', cfg.contextTax);
			body.append('kpk_context_term', String(cfg.contextTerm));
		}

		Object.keys(filters).forEach(function (tax) {
			filters[tax].forEach(function (slug) {
				body.append('kpk_filters[' + tax + '][]', slug);
			});
		});

		fetch(cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: body,
		})
			.then(function (res) {
				return res.json();
			})
			.then(function (json) {
				if (id !== requestId) return;
				if (!json || !json.success) throw new Error('bad');

				var data = json.data || {};
				replaceProducts(data.products || '');
				replacePagination(data.pagination || '');
				replaceCount(data.count || '');

				var url = buildUrl(filters, search);
				window.history.replaceState({}, '', url.toString());
			})
			.catch(function () {
				if (id !== requestId) return;
				window.location.href = buildUrl(filters, search).toString();
			})
			.finally(function () {
				if (id !== requestId) return;
				setLoading(false);
			});
	}

	function applyFilters() {
		var filters = getFilters();
		var search = getSearch();
		updateReset();

		if (cfg.ajax) {
			fetchFiltered(filters, search);
			return;
		}

		window.location.href = buildUrl(filters, search).toString();
	}

	form.addEventListener('change', function (event) {
		if (event.target && event.target.matches('input[type="checkbox"]')) {
			syncNested(event.target);
			applyFilters();
		}
	});

	form.addEventListener('submit', function (event) {
		event.preventDefault();
		applyFilters();
	});

	if (searchInput) {
		searchInput.addEventListener('input', function () {
			clearTimeout(searchTimer);
			searchTimer = setTimeout(function () {
				// Only fetch when the user actually typed something or cleared an existing search.
				applyFilters();
			}, 300);
		});
	}

	if (resetBtn) {
		resetBtn.addEventListener('click', function () {
			form.querySelectorAll('input[type="checkbox"]').forEach(function (input) {
				input.checked = false;
			});
			if (searchInput) {
				searchInput.value = '';
			}
			applyFilters();
		});
	}

	updateReset();
})();
