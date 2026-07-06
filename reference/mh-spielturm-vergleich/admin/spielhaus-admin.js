/**
 * MH Spielhaus Konfigurator — Admin JS (v5.37.0)
 *
 * Verschachteltes Add/Remove: Gruppe → Haus → Add-on → Rabattstufe.
 *
 * Reindexing ist NICHT nötig: neue Zeilen bekommen eindeutige (hohe) Indizes,
 * und der PHP-Sanitizer (MH_STV_Spielhaus_Settings::sanitize) baut beim Speichern
 * alle Arrays sequenziell neu auf. Das hält das JS robust trotz tiefer Schachtelung.
 *
 * v5.37.0: Add-on-Zeilen lassen sich per Drag-and-Drop (am Griff links) innerhalb
 * EINES Hauses umsortieren (native HTML5-DnD, keine Library). Da der Sanitizer die
 * Add-ons in Übermittlungs-(=DOM-)Reihenfolge neu aufbaut, wird die neue Reihenfolge
 * beim Speichern automatisch übernommen — kein Reindex der name-Attribute nötig.
 */
(function () {
	'use strict';

	// Eindeutige Indizes für neu hinzugefügte Zeilen (kollidiert nicht mit Server-Indizes).
	var uid = 100000;
	function nextUid() { return ++uid; }

	/** HTML mit Tokens ({{g}} …) in ein DOM-Element parsen (template-Kontext erlaubt <tr>). */
	function nodeFromTemplate(tplId, tokens) {
		var tpl = document.getElementById(tplId);
		if (!tpl) { return null; }
		var html = tpl.innerHTML;
		for (var key in tokens) {
			if (!tokens.hasOwnProperty(key)) { continue; }
			html = html.replace(new RegExp('\\{\\{' + key + '\\}\\}', 'g'), tokens[key]);
		}
		var parser = document.createElement('template');
		parser.innerHTML = html.trim();
		return parser.content.firstElementChild;
	}

	function highlight(node) {
		if (!node) { return; }
		node.classList.add('mh-stv-row-new');
		setTimeout(function () { node.classList.remove('mh-stv-row-new'); }, 600);
	}

	document.addEventListener('click', function (e) {
		var btn = e.target.closest ? e.target.closest('button') : null;
		if (!btn) { return; }

		/* ── Gruppe hinzufügen ── */
		if (btn.classList.contains('mh-sh-add-group')) {
			e.preventDefault();
			var groupsWrap = document.getElementById('mh-stv-sh-groups');
			var node = nodeFromTemplate('mh-sh-tpl-group', { g: nextUid() });
			if (node && groupsWrap) { groupsWrap.appendChild(node); highlight(node); }
			return;
		}

		/* ── Haus hinzufügen ── */
		if (btn.classList.contains('mh-sh-add-house')) {
			e.preventDefault();
			var g = btn.getAttribute('data-g');
			var group = btn.closest('.mh-sh-group');
			var housesWrap = group ? group.querySelector('.mh-sh-houses') : null;
			var houseNode = nodeFromTemplate('mh-sh-tpl-house', { g: g, h: nextUid() });
			if (houseNode && housesWrap) { housesWrap.appendChild(houseNode); highlight(houseNode); }
			return;
		}

		/* ── Add-on hinzufügen ── */
		if (btn.classList.contains('mh-sh-add-addon')) {
			e.preventDefault();
			var house = btn.closest('.mh-sh-house');
			var tbody = house ? house.querySelector('.mh-sh-addons-table tbody') : null;
			var addonNode = nodeFromTemplate('mh-sh-tpl-addon', {
				g: btn.getAttribute('data-g'),
				h: btn.getAttribute('data-h'),
				a: nextUid()
			});
			if (addonNode && tbody) {
				tbody.appendChild(addonNode);
				highlight(addonNode);
				var pid = addonNode.querySelector('.mh-sh-addon-pid');
				if (pid) { pid.focus(); }
			}
			return;
		}

		/* ── Rabattstufe hinzufügen ── */
		if (btn.classList.contains('mh-sh-add-tier')) {
			e.preventDefault();
			var addon = btn.closest('.mh-sh-addon');
			var tierList = addon ? addon.querySelector('.mh-sh-tier-list') : null;
			var tierNode = nodeFromTemplate('mh-sh-tpl-tier', {
				g: btn.getAttribute('data-g'),
				h: btn.getAttribute('data-h'),
				a: btn.getAttribute('data-a'),
				t: nextUid()
			});
			if (tierNode && tierList) {
				tierList.appendChild(tierNode);
				var from = tierNode.querySelector('.mh-sh-tier-from');
				if (from) { from.focus(); }
			}
			return;
		}

		/* ── Entfernen: Gruppe ── */
		if (btn.classList.contains('mh-sh-remove-group')) {
			e.preventDefault();
			var allGroups = document.querySelectorAll('#mh-stv-sh-groups .mh-sh-group');
			if (allGroups.length <= 1) { alert('Mindestens eine Gruppe muss erhalten bleiben.'); return; }
			if (!confirm('Diese Konfigurator-Gruppe mit allen Häusern entfernen?')) { return; }
			var gNode = btn.closest('.mh-sh-group');
			if (gNode) { gNode.parentNode.removeChild(gNode); }
			return;
		}

		/* ── Entfernen: Haus ── */
		if (btn.classList.contains('mh-sh-remove-house')) {
			e.preventDefault();
			var hParent = btn.closest('.mh-sh-houses');
			var siblings = hParent ? hParent.querySelectorAll('.mh-sh-house') : [];
			if (siblings.length <= 1) { alert('Eine Gruppe braucht mindestens ein Haus.'); return; }
			if (!confirm('Dieses Haus mit allen Add-ons entfernen?')) { return; }
			var hNode = btn.closest('.mh-sh-house');
			if (hNode) { hNode.parentNode.removeChild(hNode); }
			return;
		}

		/* ── Entfernen: Add-on (darf bis auf 0 leeren) ── */
		if (btn.classList.contains('mh-sh-remove-addon')) {
			e.preventDefault();
			var aNode = btn.closest('.mh-sh-addon');
			if (aNode) { aNode.parentNode.removeChild(aNode); }
			return;
		}

		/* ── Entfernen: Rabattstufe ── */
		if (btn.classList.contains('mh-sh-remove-tier')) {
			e.preventDefault();
			var tNode = btn.closest('.mh-sh-tier');
			if (tNode) { tNode.parentNode.removeChild(tNode); }
			return;
		}
	});

	/* ── „Inkludiert"-Toggle: Staffel optisch deaktivieren (Rabatt wirkt dort nie) ── */
	document.addEventListener('change', function (e) {
		if (!e.target.classList || !e.target.classList.contains('mh-sh-incl-toggle')) { return; }
		var row = e.target.closest('.mh-sh-addon');
		var tiers = row ? row.querySelector('.mh-sh-tiers') : null;
		if (tiers) { tiers.classList.toggle('mh-sh-tiers-disabled', e.target.checked); }
	});

	/* ──────────────────────────────────────────────────────────────────────────
	 * Drag-and-Drop: Add-on-Zeilen innerhalb EINES Hauses umsortieren.
	 *
	 * Native HTML5-DnD (keine Library), per Event-Delegation → greift auch für
	 * dynamisch via „+ Add-on hinzufügen" erzeugte Zeilen. Sortieren ist auf das
	 * jeweilige <tbody> beschränkt (= ein Haus), damit kein Add-on versehentlich in
	 * ein anderes Haus wandert (andere name-Präfixe). Die Zeile ist nur ziehbar,
	 * wenn der Drag am Griff (.mh-sh-drag-handle) startet — Klicks/Eingaben in den
	 * Feldern bleiben unberührt. Gespeichert wird die DOM-Reihenfolge automatisch
	 * (Sanitizer baut sequenziell auf), kein Reindex nötig.
	 * ────────────────────────────────────────────────────────────────────────── */
	var dragRow = null;   // aktuell gezogene <tr.mh-sh-addon>
	var dragBody = null;  // tbody, in dem gezogen wird (Sortier-Grenze = ein Haus)

	// Nur ziehbar machen, wenn der Druck auf dem Griff beginnt.
	document.addEventListener('mousedown', function (e) {
		var handle = e.target.closest ? e.target.closest('.mh-sh-drag-handle') : null;
		if (!handle) { return; }
		var row = handle.closest('.mh-sh-addon');
		if (row) { row.setAttribute('draggable', 'true'); }
	});

	// Griff gedrückt, aber NICHT gezogen → draggable wieder entfernen (sonst stört es Selektion/Klicks).
	document.addEventListener('mouseup', function () {
		if (dragRow) { return; } // echter Drag → dragend räumt auf
		var pending = document.querySelectorAll('tr.mh-sh-addon[draggable="true"]');
		for (var i = 0; i < pending.length; i++) { pending[i].removeAttribute('draggable'); }
	});

	document.addEventListener('dragstart', function (e) {
		var row = e.target.closest ? e.target.closest('tr.mh-sh-addon[draggable="true"]') : null;
		if (!row) { return; }
		dragRow = row;
		dragBody = row.parentNode;
		row.classList.add('mh-sh-row-dragging');
		if (e.dataTransfer) {
			e.dataTransfer.effectAllowed = 'move';
			try { e.dataTransfer.setData('text/plain', ''); } catch (err) {} // Firefox braucht gesetzte Daten
		}
	});

	document.addEventListener('dragover', function (e) {
		if (!dragRow) { return; }
		var over = e.target.closest ? e.target.closest('tr.mh-sh-addon') : null;
		// Nur innerhalb desselben tbody (= desselben Hauses) einsortieren.
		if (!over || over === dragRow || over.parentNode !== dragBody) { return; }
		e.preventDefault(); // erlaubt den Drop
		if (e.dataTransfer) { e.dataTransfer.dropEffect = 'move'; }
		var rect = over.getBoundingClientRect();
		var after = (e.clientY - rect.top) > rect.height / 2;
		if (after) {
			if (over.nextSibling !== dragRow) { dragBody.insertBefore(dragRow, over.nextSibling); }
		} else if (over !== dragRow) {
			dragBody.insertBefore(dragRow, over);
		}
	});

	document.addEventListener('dragend', function () {
		if (dragRow) {
			dragRow.classList.remove('mh-sh-row-dragging');
			dragRow.removeAttribute('draggable');
		}
		dragRow = null;
		dragBody = null;
	});
})();
