(function () {
	'use strict';

	var list = document.querySelector('[data-kpk-rules]');
	var addBtn = document.querySelector('[data-kpk-add-rule]');
	var template = document.getElementById('kpk-rule-template');
	if (!list || !addBtn || !template) return;

	function nextIndex() {
		var max = -1;
		list.querySelectorAll('[data-kpk-rule]').forEach(function (row) {
			row.querySelectorAll('[name^="attr_rules["]').forEach(function (input) {
				var match = input.name.match(/^attr_rules\[(\d+)\]/);
				if (match) {
					max = Math.max(max, parseInt(match[1], 10));
				}
			});
		});
		return max + 1;
	}

	function reindexNames(row, index) {
		row.querySelectorAll('[name]').forEach(function (input) {
			input.name = input.name.replace(/attr_rules\[(?:__INDEX__|\d+)\]/, 'attr_rules[' + index + ']');
		});
	}

	addBtn.addEventListener('click', function () {
		var html = template.innerHTML.replace(/__INDEX__/g, String(nextIndex()));
		var wrap = document.createElement('div');
		wrap.innerHTML = html.trim();
		var row = wrap.firstElementChild;
		if (row) {
			list.appendChild(row);
		}
	});

	list.addEventListener('click', function (event) {
		var btn = event.target.closest('[data-kpk-remove-rule]');
		if (!btn) return;

		var row = btn.closest('[data-kpk-rule]');
		if (!row) return;

		if (list.querySelectorAll('[data-kpk-rule]').length <= 1) {
			row.querySelectorAll('select').forEach(function (el) {
				el.value = '';
			});
			row.querySelectorAll('input[type="text"]').forEach(function (el) {
				el.value = '';
			});
			row.querySelectorAll('input[type="checkbox"]').forEach(function (el) {
				el.checked = true;
			});
			return;
		}

		row.remove();
	});
})();
