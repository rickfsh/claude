/**
 * MH Spielturm Vergleich — Admin JS (v5.6.0)
 *
 * Handles dynamic add/remove of series and level rows.
 * Reindexes form field names so WordPress Settings API
 * processes them correctly as sequential arrays.
 */
(function() {
	'use strict';

	document.addEventListener('DOMContentLoaded', function() {

		// ── Reindex rows: ensures [series][0], [series][1] etc. are sequential ──
		function reindexTable(tbody, type, groupIdx) {
			var rows = tbody.querySelectorAll('tr.mh-stv-row');
			for (var i = 0; i < rows.length; i++) {
				var inputs = rows[i].querySelectorAll('input');
				for (var j = 0; j < inputs.length; j++) {
					var name = inputs[j].getAttribute('name');
					if (!name) continue;
					// Replace the index: mh_stv_groups[G][type][OLD_INDEX][field] → [G][type][NEW_INDEX][field]
					var regex = new RegExp('(mh_stv_groups\\[' + groupIdx + '\\]\\[' + type + '\\])\\[\\d+\\]');
					inputs[j].name = name.replace(regex, '$1[' + i + ']');
				}
				// Update row number for levels
				var numCell = rows[i].querySelector('.mh-stv-row-num');
				if (numCell) numCell.textContent = String(i + 1);
			}
		}

		// ── Remove row ──
		document.addEventListener('click', function(e) {
			var removeBtn = e.target.closest('.mh-stv-remove-row');
			if (!removeBtn) return;

			var row = removeBtn.closest('tr.mh-stv-row');
			if (!row) return;

			var tbody = row.parentNode;
			var table = tbody.closest('.mh-stv-editable-table');
			if (!table) return;

			// Minimum 1 row
			var remainingRows = tbody.querySelectorAll('tr.mh-stv-row');
			if (remainingRows.length <= 1) {
				alert('Mindestens eine Zeile muss erhalten bleiben.');
				return;
			}

			if (!confirm('Diese Zeile wirklich entfernen?')) return;

			row.parentNode.removeChild(row);

			// Determine type and group index, then reindex
			var groupIdx = table.getAttribute('data-group');
			var type = table.classList.contains('mh-stv-series-table') ? 'series' : 'levels';
			reindexTable(tbody, type, groupIdx);
		});

		// ── Add row ──
		document.addEventListener('click', function(e) {
			var addBtn = e.target.closest('.mh-stv-add-row');
			if (!addBtn) return;

			var type = addBtn.getAttribute('data-type'); // 'series' or 'levels'
			var groupIdx = addBtn.getAttribute('data-group');
			var table = addBtn.closest('.mh-stv-editable-table');
			if (!table) return;

			var tbody = table.querySelector('tbody');
			var existingRows = tbody.querySelectorAll('tr.mh-stv-row');
			var newIdx = existingRows.length;
			var prefix = 'mh_stv_groups[' + groupIdx + '][' + type + '][' + newIdx + ']';

			var tr = document.createElement('tr');
			tr.className = 'mh-stv-row mh-stv-row-new';

			if (type === 'series') {
				tr.innerHTML =
					'<td><input type="text" name="' + prefix + '[key]" value="" class="mh-stv-input-key" placeholder="z.B. neu" pattern="[a-z0-9_-]+" title="Nur Kleinbuchstaben, Zahlen, Bindestrich, Unterstrich" /></td>' +
					'<td><input type="text" name="' + prefix + '[label]" value="" class="regular-text" placeholder="z.B. Neues Design" /></td>' +
					'<td><input type="text" name="' + prefix + '[icon]" value="" class="mh-stv-input-icon" placeholder="\uD83C\uDFE0" /></td>' +
					'<td><input type="text" name="' + prefix + '[features]" value="" class="regular-text" placeholder="z.B. Feature1, Feature2" /></td>' +
					'<td><button type="button" class="button mh-stv-remove-row" title="Serie entfernen">&times;</button></td>';
			} else {
				tr.innerHTML =
					'<td class="mh-stv-row-num">' + (newIdx + 1) + '</td>' +
					'<td><input type="text" name="' + prefix + '[key]" value="" class="mh-stv-input-key" placeholder="z.B. upgrade" pattern="[a-z0-9_-]+" title="Nur Kleinbuchstaben, Zahlen, Bindestrich, Unterstrich" /></td>' +
					'<td><input type="text" name="' + prefix + '[label]" value="" class="regular-text" placeholder="z.B. Mit Upgrade" /></td>' +
					'<td><input type="text" name="' + prefix + '[toggle_desc]" value="" class="regular-text" placeholder="z.B. Schaukelanbau mit Picknicktisch" /></td>' +
					'<td><button type="button" class="button mh-stv-remove-row" title="Stufe entfernen">&times;</button></td>';
			}

			tbody.appendChild(tr);

			// Focus the key input of the new row
			var firstInput = tr.querySelector('input');
			if (firstInput) firstInput.focus();

			// Brief highlight animation
			setTimeout(function() { tr.classList.remove('mh-stv-row-new'); }, 600);
		});

		// ── Auto-generate key from label (if key is empty) ──
		document.addEventListener('blur', function(e) {
			if (!e.target.matches || !e.target.matches('.mh-stv-editable-table .regular-text')) return;

			// Check if this is a label field (second input in row)
			var row = e.target.closest('tr.mh-stv-row');
			if (!row) return;

			var keyInput = row.querySelector('.mh-stv-input-key');
			if (!keyInput || keyInput.value.trim() !== '') return;

			// Auto-generate key from label
			var label = e.target.value.trim();
			if (!label) return;

			var key = label
				.toLowerCase()
				.replace(/[äÄ]/g, 'ae')
				.replace(/[öÖ]/g, 'oe')
				.replace(/[üÜ]/g, 'ue')
				.replace(/[ß]/g, 'ss')
				.replace(/[^a-z0-9]+/g, '-')
				.replace(/^-|-$/g, '')
				.substring(0, 30);

			keyInput.value = key;
			keyInput.style.borderColor = '#2271b1';
			setTimeout(function() { keyInput.style.borderColor = ''; }, 1500);
		}, true);
	});
})();
