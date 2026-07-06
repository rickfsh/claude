/* MH Birthday Sale – Planer-Override v1.0.0 (ES5)
 *
 * Liest die Stückliste des Zaunplaners aus dem DOM, prüft die Set-Bedingung
 * (Taiga-Zaunfeld + Pfosten), rechnet die Geburtstags-Ersparnis live vor und
 * injiziert: Unlock-Tracker, −22%-Badge an Taiga-Zeilen, Ersparnis-Block und
 * (optional) den umgeschriebenen Gesamtpreis.
 *
 * Wichtig: Rein visuell. Der echte Rabatt wird serverseitig im Warenkorb
 * berechnet (negative Fee). Preis-Parsing nimmt immer den ERSTEN Preiswert
 * einer Zelle (= aktueller Preis; der Dauerstreichpreis steht dahinter).
 */
(function () {
	'use strict';

	var CFG = window.MHBS_CFG || {};
	var RATE = parseFloat(CFG.rate) || 0.22;
	var PCT = Math.round(RATE * 100);
	var MIN_POSTS = parseInt(CFG.minPosts, 10) || 2;

	var SEL = CFG.selectors || {};
	var ROOT_SEL = SEL.root || '#zaunplaner, .zaunplaner, .mh-planer';
	var ROW_SEL = SEL.row || 'tr';
	var TOTAL_SEL = SEL.total || '';

	var P = CFG.patterns || {};
	var RX_TAIGA = new RegExp(P.taiga || 'steckzaun\\s+taiga', 'i');
	var RX_PFOSTEN = new RegExp(P.pfosten || 'pfosten', 'i');
	var RX_PFOSTEN_EX = new RegExp(P.pfostenExclude || 'pfostentr|abdeckung', 'i');
	var RX_LED = new RegExp(P.led || 'led[\\s\\S]{0,30}leiste|leiste[\\s\\S]{0,30}led', 'i');
	var RX_PRICE = /-?\d{1,3}(?:\.\d{3})*,\d{2}/;

	var FLAGS = CFG.flags || {};
	var REWRITE_TOTAL = FLAGS.rewriteTotal !== false;
	var CONFETTI = FLAGS.confetti !== false;

	var L = CFG.labels || {};

	var root = null;
	var observer = null;
	var timer = null;
	var lastHash = '';
	var lastActive = null;

	/* ---------- Helpers ---------- */

	function parseFirstPrice(text) {
		var m = String(text || '').match(RX_PRICE);
		if (!m) { return null; }
		return parseFloat(m[0].replace(/\./g, '').replace(',', '.'));
	}

	function fmtEuro(v) {
		var s = Math.abs(v).toFixed(2);
		var parts = s.split('.');
		parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
		return (v < 0 ? '-' : '') + parts[0] + ',' + parts[1] + ' \u20ac';
	}

	function round2(v) {
		return Math.round(v * 100) / 100;
	}

	function svgLock(open) {
		var shackle = open
			? '<path d="M8 11V7a4 4 0 0 1 7.8-1.3"/>'
			: '<path d="M8 11V7a4 4 0 0 1 8 0v4"/>';
		return '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><rect x="4" y="11" width="16" height="9" rx="2"/>' + shackle + '</svg>';
	}

	/* ---------- Stückliste auslesen ---------- */

	function scanRows() {
		var rows = root.querySelectorAll(ROW_SEL);
		var data = {
			taigaQty: 0, taigaSum: 0,
			pfostenQty: 0,
			ledQty: 0, ledSum: 0,
			taigaNameCells: []
		};

		for (var i = 0; i < rows.length; i++) {
			var cells = rows[i].children;
			if (!cells || cells.length < 3) { continue; }

			/* Letzte Zelle = Zeilen-Gesamtpreis. Kein Preis (z.B. Kopfzeile) => skip. */
			var lineTotal = parseFirstPrice(cells[cells.length - 1].textContent);
			if (lineTotal === null) { continue; }

			var name = cells[0].textContent || '';

			/* Mengen-Zelle: Namenszelle (Index 0) überspringen und nur Zellen
			 * akzeptieren, die AUSSCHLIESSLICH eine Menge enthalten ("2 x", "2"),
			 * sonst greift z.B. "180x180 cm" im Produktnamen als Menge. */
			var qty = 1;
			for (var j = 1; j < cells.length; j++) {
				var ct = (cells[j].textContent || '').trim();
				var qm = ct.match(/^(\d+)\s*[x\u00d7]?$/i);
				if (qm) { qty = parseInt(qm[1], 10); break; }
			}

			if (RX_TAIGA.test(name)) {
				data.taigaQty += qty;
				data.taigaSum += lineTotal;
				data.taigaNameCells.push(cells[0]);
			} else if (RX_PFOSTEN.test(name) && !RX_PFOSTEN_EX.test(name)) {
				data.pfostenQty += qty;
			} else if (RX_LED.test(name)) {
				data.ledQty += qty;
				data.ledSum += lineTotal;
			}
		}

		return data;
	}

	function findTotalEl() {
		if (TOTAL_SEL) { return root.querySelector(TOTAL_SEL); }
		/* Heuristik: tiefstes Element, das "Gesamtpreis" UND einen Preis enthält
		 * (Spaltenkopf "Gesamtpreis" hat keinen Preis und fällt damit raus). */
		var nodes = root.querySelectorAll('*');
		var best = null;
		var i, el, t;
		for (i = 0; i < nodes.length; i++) {
			el = nodes[i];
			if (el.children.length > 4) { continue; }
			t = el.textContent || '';
			if (/gesamtpreis/i.test(t) && RX_PRICE.test(t)) { best = el; }
		}
		if (!best) { return null; }

		/* Innerhalb des Treffers das reine Preis-Element bevorzugen, damit das
		 * Label "Gesamtpreis" beim Rewrite erhalten bleibt. Der erste Preis des
		 * Kandidaten muss dem ersten Preis des Treffers entsprechen, sonst
		 * würde z.B. der Streichpreis-<del> erwischt. */
		var bestPrice = parseFirstPrice(best.textContent);
		var inner = best.querySelectorAll('*');
		var cand = null;
		for (i = 0; i < inner.length; i++) {
			el = inner[i];
			t = (el.textContent || '').trim();
			if (RX_PRICE.test(t) && !/gesamtpreis/i.test(t) && t.length <= 40 && parseFirstPrice(t) === bestPrice) {
				cand = el;
			}
		}
		return cand || best;
	}

	/* ---------- Rendering ---------- */

	function stepHtml(state, text) {
		var icon = state === 'ok' ? '&#10003;' : '&#8226;';
		return '<div class="mhbs-step mhbs-step--' + state + '">' +
			'<span class="mhbs-step-icon">' + icon + '</span>' +
			'<span class="mhbs-step-text">' + text + '</span></div>';
	}

	function renderTracker(d, active) {
		var el = document.getElementById('mhbs-tracker');
		if (!el) {
			el = document.createElement('div');
			el.id = 'mhbs-tracker';
			el.className = 'mhbs-tracker';
			var anchor = SEL.trackerAnchor ? root.querySelector(SEL.trackerAnchor) : null;
			if (anchor && anchor.parentNode) {
				anchor.parentNode.insertBefore(el, anchor);
			} else if (root.firstChild) {
				root.insertBefore(el, root.firstChild);
			} else {
				root.appendChild(el);
			}
		}

		var missing = Math.max(0, MIN_POSTS - d.pfostenQty);
		var html = '<div class="mhbs-tracker-title">' +
			(L.trackerTitle || '22 Jahre Mega-Holz \u2013 dein Set-Rabatt') + '</div>';
		html += '<div class="mhbs-steps">';
		html += stepHtml(d.taigaQty >= 1 ? 'ok' : 'todo',
			d.taigaQty >= 1 ? d.taigaQty + '\u00d7 Taiga-Zaunfeld' : 'Taiga-Zaunfeld w\u00e4hlen');
		html += stepHtml(d.pfostenQty >= MIN_POSTS ? 'ok' : 'todo',
			d.pfostenQty >= MIN_POSTS ? 'Pfosten dabei' : 'Noch ' + missing + ' Pfosten n\u00f6tig');
		html += '<div class="mhbs-chip ' + (active ? 'mhbs-chip--on' : 'mhbs-chip--off') + '">' +
			svgLock(active) + ' \u2212' + PCT + '% ' + (active ? 'aktiv' : 'gesperrt') + '</div>';
		html += '</div>';
		el.innerHTML = html;
	}

	function renderBadges(d) {
		for (var i = 0; i < d.taigaNameCells.length; i++) {
			var cell = d.taigaNameCells[i];
			if (!cell.querySelector('.mhbs-badge')) {
				var b = document.createElement('span');
				b.className = 'mhbs-badge';
				b.textContent = '\u2212' + PCT + '% im Set';
				cell.appendChild(b);
			}
		}
	}

	function getOriginalTotal(totalEl) {
		if (!totalEl) { return null; }
		var attr = totalEl.getAttribute('data-mhbs-orig');
		if (attr) { return parseFloat(attr); }
		return parseFirstPrice(totalEl.textContent);
	}

	function rewriteTotal(totalEl, grand, newTotal) {
		if (!totalEl) { return; }
		if (!totalEl.getAttribute('data-mhbs-orig')) {
			totalEl.setAttribute('data-mhbs-orig', String(grand));
			totalEl.setAttribute('data-mhbs-orig-html', encodeURIComponent(totalEl.innerHTML));
		}
		totalEl.innerHTML = '<span class="mhbs-total-new">' + fmtEuro(newTotal) + '</span> ' +
			'<del class="mhbs-total-old">' + fmtEuro(grand) + '</del>';
	}

	function restoreTotal(totalEl) {
		if (!totalEl) { return; }
		var orig = totalEl.getAttribute('data-mhbs-orig-html');
		if (orig) {
			totalEl.innerHTML = decodeURIComponent(orig);
			totalEl.removeAttribute('data-mhbs-orig');
			totalEl.removeAttribute('data-mhbs-orig-html');
		}
	}

	function renderSavings(active, taigaDisc, ledDisc, grand, totalEl) {
		var el = document.getElementById('mhbs-savings');
		if (!el) {
			el = document.createElement('div');
			el.id = 'mhbs-savings';
			el.className = 'mhbs-savings';
			var anchor = SEL.savingsAnchor ? root.querySelector(SEL.savingsAnchor) : null;
			if (anchor && anchor.parentNode) {
				anchor.parentNode.insertBefore(el, anchor.nextSibling);
			} else if (totalEl && totalEl.parentNode) {
				totalEl.parentNode.insertBefore(el, totalEl.nextSibling);
			} else {
				root.appendChild(el);
			}
		}

		if (!active || taigaDisc <= 0) {
			el.innerHTML = '<div class="mhbs-savings-teaser">Lege Taiga-Zaunfelder und Pfosten zusammen in den Warenkorb und sichere dir \u2212' + PCT + '% Geburtstagsrabatt auf die Zaunfelder.</div>';
			restoreTotal(totalEl);
			return;
		}

		var totalDisc = round2(taigaDisc + ledDisc);
		var html = '<div class="mhbs-savings-row"><span>Geburtstagsrabatt (\u2212' + PCT + '% auf Taiga)</span><span class="mhbs-neg">\u2212' + fmtEuro(taigaDisc) + '</span></div>';
		if (ledDisc > 0) {
			html += '<div class="mhbs-savings-row"><span>Geburtstagsbonus (\u2212' + PCT + '% LED-Leiste)</span><span class="mhbs-neg">\u2212' + fmtEuro(ledDisc) + '</span></div>';
		}
		if (grand !== null) {
			var newTotal = round2(grand - totalDisc);
			html += '<div class="mhbs-savings-total"><span>Dein Geburtstagspreis</span><span class="mhbs-new-price">' + fmtEuro(newTotal) + '</span></div>';
			html += '<div class="mhbs-savings-orig">statt <del>' + fmtEuro(grand) + '</del> \u2013 du sparst ' + fmtEuro(totalDisc) + '</div>';
			if (REWRITE_TOTAL) { rewriteTotal(totalEl, grand, newTotal); }
		}
		html += '<div class="mhbs-savings-note">' + (L.note || 'Der Rabatt wird im Warenkorb automatisch abgezogen.') + '</div>';
		el.innerHTML = html;
	}

	function fireConfetti() {
		if (!CONFETTI) { return; }
		var tracker = document.getElementById('mhbs-tracker');
		if (!tracker) { return; }
		var holder = document.createElement('div');
		holder.className = 'mhbs-confetti';
		var colors = ['#FAA41A', '#FF9000', '#33335C', '#060B23'];
		for (var i = 0; i < 16; i++) {
			var p = document.createElement('span');
			p.className = 'mhbs-confetti-piece';
			p.style.left = (Math.random() * 100) + '%';
			p.style.background = colors[i % colors.length];
			p.style.animationDelay = (Math.random() * 0.4) + 's';
			p.style.animationDuration = (0.9 + Math.random() * 0.6) + 's';
			holder.appendChild(p);
		}
		tracker.appendChild(holder);
		setTimeout(function () {
			if (holder.parentNode) { holder.parentNode.removeChild(holder); }
		}, 1800);
	}

	/* ---------- Apply-Zyklus ---------- */

	function apply() {
		var d = scanRows();
		var active = d.taigaQty >= 1 && d.pfostenQty >= MIN_POSTS;
		var taigaDisc = round2(d.taigaSum * RATE);
		var ledDisc = (active && d.ledQty > 0) ? round2(d.ledSum * RATE) : 0;

		var totalEl = findTotalEl();
		var grand = getOriginalTotal(totalEl);

		var hash = [d.taigaQty, d.taigaSum, d.pfostenQty, d.ledQty, d.ledSum, grand, active].join('|');
		if (hash === lastHash && document.getElementById('mhbs-tracker')) { return; }
		lastHash = hash;

		pause();
		renderTracker(d, active);
		renderBadges(d);
		renderSavings(active, taigaDisc, ledDisc, grand, totalEl);
		if (active && lastActive === false) { fireConfetti(); }
		lastActive = active;
		resume();
	}

	function schedule() {
		if (timer) { clearTimeout(timer); }
		timer = setTimeout(apply, 150);
	}

	function pause() {
		if (observer) { observer.disconnect(); }
	}

	function resume() {
		if (observer) {
			observer.observe(root, { childList: true, subtree: true, characterData: true });
		}
	}

	/* ---------- Boot (Planer lädt ggf. asynchron) ---------- */

	var tries = 0;

	function boot() {
		var el = document.querySelector(ROOT_SEL);
		if (!el) {
			tries++;
			if (tries < 60) { setTimeout(boot, 500); }
			return;
		}
		root = el;
		observer = new MutationObserver(schedule);
		resume();
		apply();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
