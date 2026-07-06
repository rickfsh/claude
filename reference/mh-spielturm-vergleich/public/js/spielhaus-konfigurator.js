/**
 * MH Spielhaus Konfigurator — Frontend (v5.42.0)
 *
 * ES5-kompatibel. Rendert in #mh-sh-configurator. Liest mhShData (wp_localize_script).
 *
 * v5.42.0: Teilen messbar gemacht. (1) Analytics-Event stv_sh_share (Wert: desktop|
 * mobile) feuert beim Klick auf beide Teilen-Buttons → Dashboard. (2) Optionale
 * UTM-Parameter (Admin-Feld) werden NUR an den geteilten Link gehängt (buildShareLink),
 * inkl. auto utm_content=desktop|mobile → der Klick-Funnel ist mit dem Landing-Traffic
 * in Admetrics/GA verknüpfbar. Adresszeile des Teilenden bleibt UTM-frei.
 *
 * v5.41.0: „Konfiguration teilen"-Buttons. Desktop (Variante A): dezenter Outline-
 * Button unter dem Preis → kopiert den aktuellen Zustands-Link. Mobile (Variante E):
 * Icon im Sticky-CTA → navigator.share (System-Teilen-Menü), sonst Kopier-Fallback.
 * Beide rufen vor dem Teilen syncUrl() → der Link trägt garantiert Modell + Auswahl.
 *
 * v5.40.0: Komplette Konfiguration teilbar — neben dem Modell (?mh_haus) wird jetzt
 * auch die Zubehör-Auswahl als ?mh_cfg in die URL geschrieben (history.replaceState,
 * kein Reload) und beim Laden wieder angewandt (applyUrlConfig). Ein geteilter Link
 * reproduziert Modell + Add-ons (inkl. Mengen) exakt. mh_cfg entfällt bei Default-
 * Auswahl (schlanke URL). Parameternamen per Localize (mhShData.preselectParam/configParam).
 *
 * v5.39.0: Modellwechsel aktualisiert die URL (?mh_haus=<id>) per history.replaceState
 * ohne Reload (syncUrlToHouse) → geteilter Link öffnet dank Server-Vorauswahl (v5.25.0)
 * direkt das gewählte Modell. Parametername kommt per Localize (mhShData.preselectParam).
 *
 * v5.38.0: Bereits enthaltene Add-ons zeigen rechts ihren Wert (Menge × Einzelpreis)
 * als durchgestrichenen Preis + „inklusive" (renderIncludedCard) → macht die im
 * Hauspreis enthaltene Ersparnis sichtbar. Preisloses Produkt ⇒ kein Preis-Block.
 *
 * v5.36.0: Zubehör-Karten zeigen den Set-Rabatt jetzt auch bei ABGEWÄHLTEN Extras
 * als „Ghost"-Badge „−X% im Set" (potenzieller Satz = tierPercent bei distinct+1),
 * damit der Set-Vorteil sichtbar ist, bevor ausgewählt wird (updateExtraPrice).
 *
 * Schritt 3: Haus-Auswahl, Add-on-Karten (inkludiert vs. Extra), Live-Summe mit
 * Rabatt-Vorschau.
 * Schritt 4 (v5.11.0): CTA legt Haus + gewählte Extras als Bundle in den Warenkorb
 * (ein AJAX-Call, server-seitig validiert + getaggt). Mini-Cart-Fragmente werden
 * aktualisiert. Der Rabatt selbst kommt server-seitig als Positionspreis (Schritt 5).
 * Schritt 6 (v5.12.0): Analytics-Tracking (gebatcht, sendBeacon-Flush, Events im
 * stv_sh_-Namespace) + Galerie-Lightbox (Tastatur/Swipe) und geteilter 360°-Spinner
 * (MH360-Modul) für Häuser mit spin_frames.
 * Redesign (v5.13.0): Modell-Karten (Tag/Größe/Balken/Blurb + UVP-Streichpreis),
 * gestapelte Großbild-Galerie (jedes Bild öffnet Lightbox, kein 360° mehr im UI),
 * Zubehör-Karten mit Details-Akkordeon + UVP-/Set-Streichpreis, ein-/ausblendbarer
 * Lagerstatus (nicht lieferbare bleiben wählbar), Streich-Gesamtsumme + "Du sparst",
 * mobile Sticky-CTA-Leiste. Modell-Details sind im Admin pflegbar.
 *
 * v5.21.0: Mobile-Sticky-Bar zeigt jetzt Modellname + gewähltes Zubehör (mit
 * Menge) über Preis/CTA (fillMobileBar). CSS-seitig schwebt die Leiste höher
 * (Abstand + Safe-Area, kein Kollidieren mit dem Consent-Banner) und das
 * Wide-/Mobile-Layout wurde entzerrt/zentriert (siehe CSS-Changelog).
 *
 * v5.23.0: Layout an den Spielturm angeglichen. Die LINKE Galerie-Spalte ist
 * jetzt sticky (CSS) und bleibt beim Scrollen stehen, während die höhere rechte
 * Konfigurator-Spalte durchläuft. Das Produktinfo-Akkordeon (Beschreibung /
 * Montage & Lieferung / Hersteller) wandert aus der linken Spalte in einen
 * NEUEN vollbreiten Infobereich (.mh-sh-below) UNTER dem Grid; dort liegen auch
 * die Trust-USPs (renderTrust) und — wie beim Spielturm via geteiltem Setting
 * below_grid_selectors — eingebundene Seitenelemente wie der Klarna-Banner
 * (renderBelowEmbeds). Die Zahlungs-Icons bleiben in der rechten Spalte
 * (renderPayment). renderTrustPayment wurde in renderTrust/renderPayment
 * aufgeteilt. Reines Layout/CSS/JS + ein neues Localize-Feld; keine Provider-/
 * Cart-/Schema-Änderung, Spielturm unberührt.
 *
 * v5.33.0: Rechte-Spalte-Embeds wie beim Spielturm. renderBelowEmbeds wurde zu
 * einem generischen renderEmbeds(col, raw, prefix, below) verallgemeinert;
 * renderRightEmbeds(right) liest das GETEILTE Setting embed_selectors
 * (mhShData.embedSelectors) und verschiebt diese Seitenelemente (Verfügbarkeit/
 * Versand-Panel, Kundenprojekte/Social-Proof) in die RECHTE Spalte unter Payment
 * (Slots .mh-sh-embed-item[data-mh-sh-embed=rN]). below_grid_selectors bleibt
 * unverändert der vollbreite Bereich. Beide Selektor-Listen sind dieselben wie
 * beim Spielturm.
 *
 * v5.33.1: Fix — gepflückte Seiten-Embeds (Klarna, Lager-/Versand-Panel,
 * Kundenprojekte, Zahlungs-Icons) verschwanden beim Modellwechsel, weil
 * render() ROOT per innerHTML='' leert und die zuvor IN den Konfigurator
 * VERSCHOBENEN Elemente mitgelöscht wurden (anschließendes querySelector fand
 * sie nicht mehr). Jetzt werden sie einmal gepflückt und per JS-Referenz
 * (capturedEmbeds, Key=Selektor) behalten; bei jedem Render wird DASSELBE
 * Element in den neuen Slot zurückgehängt (überlebt das innerHTML-Leeren als
 * detachter Knoten). Zusätzlich Guard `document.body.contains(slot)`, damit ein
 * verspäteter Retry eines alten Renders das Element nicht in einen detachten
 * Slot zurückzieht. USPs (renderTrust) werden ohnehin pro Render neu erzeugt.
 *
 * v5.24.0: Produktinfo ist jetzt ein TAB-System statt Akkordeon — Stil aus dem
 * Spielturm (.mh-sh-tab-nav / .mh-sh-tab-btn / .mh-sh-tab-panel, aktive
 * Orange-Unterstreichung). renderInfoAccordion → renderInfoTabs: gleiche 3
 * Inhalte (Beschreibung / Montage & Lieferung / Hersteller) und derselbe
 * EINE Lazy-Load (loadDetail/accBody unverändert), nur die Hülle ist jetzt
 * eine horizontale Tab-Leiste. Standard-Tab „Beschreibung" lädt sofort
 * (Skeleton bis geladen); leere Panels inkl. Button werden ausgeblendet; ist
 * das Standard-Tab leer, wird das erste nicht-leere aktiviert. Pfeiltasten ←/→
 * + ARIA-Tablist. stv_sh_info_open feuert weiterhin nur bei echter
 * Nutzer-Interaktion (kein neues Event). Nur JS/CSS.
 *
 * v5.24.1 (Fix): Produktinfo lud beim 2./3. Haus nicht. Ursachen behoben:
 * (1) loadDetail rief das Callback INNERHALB des fetch-.then auf → ein Fehler
 * im Callback wurde vom .catch verschluckt und fälschlich als Ladefehler (null)
 * gemeldet; Callbacks werden jetzt asynchron (setTimeout 0) ausgeliefert.
 * (2) Galerie-Prewarm und Tabs feuerten je einen eigenen Detail-Request →
 * jetzt In-flight-Dedup (ein Request pro Haus). (3) res.text()+JSON.parse mit
 * Diagnose-Logs (HTTP-Status + Roh-Antwort) für Nonce-/PHP-Fehler; Fehler
 * werden NICHT gecacht (retry-bar). UI trennt jetzt „Ladefehler" (Hinweis +
 * „Erneut versuchen") von „geladen, aber leer"; behebt zudem den Skeleton-
 * Hänger, wenn alle Panels leer waren. Nur JS/CSS/Localize.
 *
 * v5.25.0: KEINE JS-Änderung. Modell-Vorauswahl per URL (?mh_haus=<id>) ist
 * rein serverseitig im Shortcode gelöst (setzt current_house_id → is_current →
 * hier nur via state.houseId = D.current_house_id übernommen). Versions-Notiz
 * zur Synchronität mit Plugin-Header/Konstante.
 *
 * v5.26.0: (1) Modell-Selector wird ausgeblendet, wenn die Gruppe nur EIN Haus
 * hat (renderHouseSelector gibt null zurück, render() hängt nichts an). (2)
 * Galerie-Override: liefert mhShData.galleryOverride[houseId] Markup (vom
 * Server via externem Shortcode, z. B. Toms [mh_product_gallery]), ersetzt
 * renderGallery dieses statt der eingebauten Galerie+Lightbox; activateScripts
 * führt Inline-Skripte des Markups aus, danach Event „mh-sh:gallery-injected"
 * (detail {host, productId}) fürs Re-Init beim Modellwechsel. Ohne Override
 * (Shortcode nicht verfügbar) bleibt es automatisch bei der eingebauten Galerie.
 *
 * v5.27.0: KEINE JS-Änderung. Der Galerie-Override-Shortcode wird jetzt im Admin
 * gepflegt (Feld „Bildgalerie – Shortcode") statt per Auto-Erkennung; die JS-
 * Seite (mhShData.galleryOverride[houseId] → renderGallery) ist unverändert.
 * Versions-Notiz zur Synchronität mit Plugin-Header/Konstante.
 *
 * v5.27.1: KEINE JS-Änderung. Fix nur PHP-seitig (build_gallery_overrides):
 * Query-Kontext (is_product()/queried_object) wird je Haus aufs Modell gesetzt,
 * roher/nicht-registrierter Shortcode wird abgefangen, WP_DEBUG-Diagnose im Log.
 * JS liest weiterhin mhShData.galleryOverride[houseId] → renderGallery.
 *
 * v5.27.2: KEINE JS-Änderung. Hotfix für v5.27.1 (kritischer Fehler): die
 * $wp_query-Manipulation (is_product()-Fake) ist RAUS — sie war fatal-anfällig.
 * do_shortcode() läuft nun in try/catch(Throwable) → ein fremder Shortcode kann
 * die Seite nicht mehr weißscreenen. Diagnose-Logs/Guards bleiben.
 *
 * v5.28.0: Eigene Galerie im Tom-Stil (statt externem Shortcode). renderGallery
 * baut Hero + Thumb-Streifen: Hero zeigt immer sichtbare Pfeile, einen Hover-
 * „Hand"-Hinweis und reagiert auf Swipe/Drag (Maus + Touch, horizontal) zum
 * Bildwechsel; Klick (ohne Wisch) öffnet die Lightbox am aktuellen Index. Die
 * Thumbnails TAUSCHEN jetzt das Hero-Bild (statt direkt die Lightbox zu öffnen),
 * aktiver Thumb hervorgehoben, scrollbarer Streifen mit ◄►-Pfeilen + schlankem
 * Scrollbalken (mobil nur Scroll). Lightbox (Zoom + Swipe) unverändert. Der
 * Galerie-Override-Zweig (mhShData.galleryOverride) bleibt als optionaler
 * Fallback erhalten, wird aber standardmäßig nicht mehr genutzt.
 *
 * v5.29.0: Galerie-„Feel" verfeinert. (1) Hero-Peek-Drag: das Nachbarbild
 * (.mh-sh-gpeek) schiebt sich beim Ziehen real herein und snappt beim Loslassen
 * sauber durch — kein ruckartiges Springen mehr (Maus + Touch). (2) Eigene
 * ziehbare Laufleiste unter den Thumbnails (.mh-sh-thumbbar + ziehbarer Balken)
 * plus Drag-to-Scroll direkt auf den Thumbnails (drücken/halten/ziehen; Klick vs.
 * Ziehen über dragScrolled-Flag getrennt). (3) Lightbox bekommt dieselbe Peek-
 * Zieh-Mechanik (.mh-sh-lb-peek) für Maus UND Touch, aber nur wenn NICHT gezoomt
 * — Zoom (Klick/Wheel/Pinch) + Pan + vertikales Wisch-zum-Schließen bleiben
 * vollständig erhalten; box-Klick ist gegen versehentliches Schließen nach einem
 * Wisch abgesichert (moved-Guard).
 *
 * v5.32.0: Galerie-Override KOMPLETT entfernt (seit der nativen Galerie v5.28.0
 * ungenutzt; der serverseitige Pfad war fatal-anfällig und verursachte beim
 * Live-Schalten einen kritischen Fehler). renderGallery rendert nur noch die
 * eingebaute Galerie; mhShData.galleryOverride, der Helfer activateScripts und das
 * Event „mh-sh:gallery-injected" sind raus — ebenso serverseitig
 * build_gallery_overrides() + das Admin-Feld „Bildgalerie – Shortcode".
 *
 * Rabatt-Regel (Vorschau hier = identisch zur Server-Engine):
 *   D = Anzahl VERSCHIEDENER gewählter Extras (Menge je Stück egal).
 *   Pro Extra: höchste Stufe mit from_distinct <= D → % auf (Preis × Menge).
 *   Inkludierte Add-ons: 0 €, nicht in der Summe. Das Haus: nie rabattiert.
 */
(function () {
	'use strict';

	if (typeof mhShData === 'undefined') { return; }

	var D    = mhShData.config;
	var I18N = mhShData.i18n || {};
	var PF   = mhShData.priceFormat || {};
	var SHOW_STOCK = !!mhShData.showStock;
	// Sichtbare Zubehör-Karten vor „Mehr anzeigen" (0 = nie einklappen). Default 3.
	var EXTRAS_VISIBLE = (typeof mhShData.extrasVisible === 'number') ? mhShData.extrasVisible : 3;
	var ROOT = document.getElementById('mh-sh-configurator');
	if (!ROOT || !D || !D.houses || !D.houses.length) { return; }

	/* ── State ── */
	var state = {
		houseId: D.current_house_id || D.houses[0].id,
		// selections[addonId] = { selected: bool, qty: int } — nur Extras des aktiven Hauses
		selections: {}
	};

	/* ── Helpers ── */
	function el(tag, cls) { var e = document.createElement(tag); if (cls) { e.className = cls; } return e; }

	/* ── Analytics: gebatchtes Event-Tracking (Muster aus dem Spielturm) ── */
	var TRACK_URL   = mhShData.ajaxUrl || '';
	var TRACK_NONCE = mhShData.trackNonce || '';
	var trackQueue  = [];
	var trackTimer  = null;
	function track(name, value) {
		if (!TRACK_URL || !TRACK_NONCE) { return; }
		trackQueue.push({ name: name, value: value == null ? '' : String(value) });
		if (!trackTimer) { trackTimer = setTimeout(flushTrack, 2000); }
	}
	function flushTrack() {
		trackTimer = null;
		if (trackQueue.length === 0) { return; }
		var batch = trackQueue.splice(0, 20);
		var body = new FormData();
		body.append('action', 'mh_stv_track');
		body.append('nonce', TRACK_NONCE);
		for (var i = 0; i < batch.length; i++) {
			body.append('events[' + i + '][name]', batch[i].name);
			body.append('events[' + i + '][value]', batch[i].value);
		}
		fetch(TRACK_URL, { method: 'POST', credentials: 'same-origin', body: body }).catch(function () {});
	}
	if (typeof navigator.sendBeacon === 'function') {
		window.addEventListener('pagehide', function () {
			if (trackQueue.length === 0) { return; }
			var body = new FormData();
			body.append('action', 'mh_stv_track');
			body.append('nonce', TRACK_NONCE);
			for (var i = 0; i < trackQueue.length; i++) {
				body.append('events[' + i + '][name]', trackQueue[i].name);
				body.append('events[' + i + '][value]', trackQueue[i].value);
			}
			navigator.sendBeacon(TRACK_URL, body);
			trackQueue = [];
		});
	}

	function currentHouse() {
		for (var i = 0; i < D.houses.length; i++) {
			if (D.houses[i].id === state.houseId) { return D.houses[i]; }
		}
		return D.houses[0];
	}

	/* ── URL-Sync (v5.39.0 Modell, v5.40.0 + Zubehör) ──
	 * Spiegelt den kompletten Konfigurator-Zustand (aktives Modell + gewähltes
	 * Zubehör) bei jeder Nutzer-Interaktion in die Adresszeile — OHNE Reload
	 * (history.replaceState). Round-trippt mit der server-seitigen Vorauswahl
	 * (v5.25.0, mh_haus) + applyUrlConfig (v5.40.0, mh_cfg): ein kopierter/geteilter
	 * Link öffnet den Konfigurator direkt mit demselben Modell UND derselben Auswahl.
	 * Parameternamen kommen per Localize (gleiche Filter wie PHP) → synchron.
	 *
	 * mh_cfg-Format: "-"-getrennte Add-on-Liste, je Eintrag "id" bzw. "id.menge"
	 * (Menge nur bei allow_qty und >1). Sentinel "0" = explizit leere Auswahl
	 * (überschreibt Default-Vorauswahlen). Alle Zeichen URL-sicher (Ziffern/-/.). */
	var URL_PARAM = (mhShData.preselectParam ? String(mhShData.preselectParam) : 'mh_haus');
	var CFG_PARAM = (mhShData.configParam ? String(mhShData.configParam) : 'mh_cfg');
	var SHARE_UTM = (mhShData.shareUtm ? String(mhShData.shareUtm) : '');

	/** Aktuelle Zubehör-Auswahl des aktiven Hauses → kompakter String ("0" = leer). */
	function encodeConfig() {
		var ex = extras(currentHouse());
		var out = [];
		for (var i = 0; i < ex.length; i++) {
			var a = ex[i], s = state.selections[a.id];
			if (!s || !s.selected) { continue; }
			var q = Math.max(1, s.qty || 1);
			out.push((a.allow_qty && q > 1) ? (a.id + '.' + q) : ('' + a.id));
		}
		return out.length ? out.join('-') : '0';   // "0" = explizit leer (kein gültiger Produkt-ID)
	}

	/** True, wenn die Auswahl exakt den Haus-Defaults entspricht → mh_cfg entfällt. */
	function isDefaultConfig() {
		var ex = extras(currentHouse());
		for (var i = 0; i < ex.length; i++) {
			var a = ex[i], s = state.selections[a.id] || {};
			var defSel = a.default_selected === true, curSel = !!s.selected;
			if (curSel !== defSel) { return false; }
			if (curSel && a.allow_qty && Math.max(1, s.qty || 1) !== (a.default_qty || 1)) { return false; }
		}
		return true;
	}

	/** mh_cfg-String → Map { "<id>": menge }. Robust gegen handgetippte URLs. */
	function parseConfig(raw) {
		var map = {};
		if (!raw) { return map; }
		var items = String(raw).split('-');
		for (var i = 0; i < items.length; i++) {
			if (!items[i]) { continue; }
			var dot = items[i].indexOf('.'), id, q;
			if (dot >= 0) { id = items[i].slice(0, dot); q = parseInt(items[i].slice(dot + 1), 10); }
			else { id = items[i]; q = 1; }
			if (/^\d+$/.test(id)) { map[id] = (q > 0 ? q : 1); }
		}
		return map;
	}

	/** Liest einen Query-Parameter aus der aktuellen URL (null = nicht vorhanden). */
	function getQueryParam(name) {
		var loc = window.location;
		var raw = loc.search && loc.search.charAt(0) === '?' ? loc.search.slice(1) : (loc.search || '');
		if (!raw) { return null; }
		var parts = raw.split('&'), enc = encodeURIComponent(name);
		for (var i = 0; i < parts.length; i++) {
			var eq = parts[i].indexOf('=');
			var k = eq >= 0 ? parts[i].slice(0, eq) : parts[i];
			if (k === enc || k === name) {
				var v = eq >= 0 ? parts[i].slice(eq + 1) : '';
				try { return decodeURIComponent(v); } catch (e) { return v; }
			}
		}
		return null;
	}

	/** Selektionen des Hauses anhand der URL-Config-Map setzen (autoritativ:
	 *  gelistet = an [+ Menge bei allow_qty], alles andere = aus). Nur einmal beim
	 *  Init fürs Anker-Haus — Modellwechsel danach nutzen wieder die Defaults. */
	function applyUrlConfig(house, map) {
		var ex = extras(house);
		for (var i = 0; i < ex.length; i++) {
			var a = ex[i], key = String(a.id);
			if (Object.prototype.hasOwnProperty.call(map, key)) {
				state.selections[a.id] = { selected: true, qty: a.allow_qty ? Math.max(1, map[key]) : (a.default_qty || 1) };
			} else {
				state.selections[a.id] = { selected: false, qty: a.default_qty || 1 };
			}
		}
	}

	/** Generischer Query-Editor: setzt/entfernt (Wert null/'') mehrere Parameter,
	 *  lässt alle übrigen + den Hash unangetastet, dedupliziert, kein Reload. */
	function writeUrl(updates) {
		if (!window.history || typeof window.history.replaceState !== 'function') { return; }
		var loc = window.location;
		var raw = loc.search && loc.search.charAt(0) === '?' ? loc.search.slice(1) : (loc.search || '');
		var parts = raw ? raw.split('&') : [];
		var out = [], done = {}, i, k;
		function matchKey(pk) {
			for (var kk in updates) {
				if (!updates.hasOwnProperty(kk)) { continue; }
				if (pk === kk || pk === encodeURIComponent(kk)) { return kk; }
			}
			return null;
		}
		function put(key, val) {
			if (val === null || val === undefined || val === '') { return; }   // null/'' ⇒ entfernen
			out.push(encodeURIComponent(key) + '=' + encodeURIComponent(val));
		}
		for (i = 0; i < parts.length; i++) {
			if (!parts[i]) { continue; }
			var pk = parts[i].split('=')[0];
			var hit = matchKey(pk);
			if (hit !== null) {
				if (!done[hit]) { put(hit, updates[hit]); done[hit] = true; }   // Duplikate weglassen
			} else {
				out.push(parts[i]);                                            // Fremd-Parameter unverändert
			}
		}
		for (k in updates) {                                                   // noch nicht vorhandene anhängen
			if (!updates.hasOwnProperty(k) || done[k]) { continue; }
			put(k, updates[k]);
		}
		var newUrl = loc.pathname + (out.length ? '?' + out.join('&') : '') + (loc.hash || '');
		try { window.history.replaceState(window.history.state, '', newUrl); } catch (e) { /* z. B. file:// */ }
	}

	/** Kompletten Zustand (Modell + Zubehör) in die URL schreiben. mh_cfg nur bei
	 *  Abweichung von den Defaults (sonst reproduziert mh_haus die Defaults selbst). */
	function syncUrl() {
		var updates = {};
		updates[URL_PARAM] = state.houseId;
		updates[CFG_PARAM] = isDefaultConfig() ? null : encodeConfig();
		writeUrl(updates);
	}

	/* ── Konfiguration teilen (v5.41.0) ──
	 * Desktop: dezenter Outline-Button unter dem Preis (Variante A) → kopiert den Link.
	 * Mobile: Icon im Sticky-CTA (Variante E) → natives Teilen-Menü (navigator.share),
	 * sonst Fallback aufs Kopieren. Beide nutzen denselben Link: syncUrl() bringt die
	 * Adresszeile zuerst auf den aktuellen Stand (Modell + Auswahl), dann wird
	 * location.href geteilt — der Link stimmt also auch ohne vorherige Interaktion. */
	var SHARE_ICON = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>';
	var CHECK_ICON = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>';

	/** Aktuellen Teilen-Link liefern (schreibt zuvor den Zustand in die Adresszeile). */
	function currentShareUrl() {
		syncUrl();
		return window.location.href;
	}

	/** Geteilten Link bauen: aktueller Zustandslink + optionale UTM-Parameter
	 *  (+ utm_content=<surface>, sofern nicht selbst gesetzt). Die UTM landen NUR im
	 *  zurückgegebenen String — nicht in der Adresszeile des Teilenden. surface =
	 *  'desktop' | 'mobile' (welcher Button). Leeres UTM-Feld ⇒ Link unverändert. */
	function buildShareLink(surface) {
		var url = currentShareUrl();
		var raw = SHARE_UTM ? SHARE_UTM.replace(/^[?&]+/, '') : '';
		if (!raw) { return url; }                       // Admin-Feld leer ⇒ keine UTM
		var extra = [raw];
		if (raw.indexOf('utm_content=') === -1) {       // Button-Herkunft automatisch ergänzen
			extra.push('utm_content=' + encodeURIComponent(surface));
		}
		var hash = '', hi = url.indexOf('#');
		if (hi >= 0) { hash = url.slice(hi); url = url.slice(0, hi); }   // Hash bewahren
		url += (url.indexOf('?') >= 0 ? '&' : '?') + extra.join('&');
		return url + hash;
	}

	/** In die Zwischenablage kopieren (Clipboard-API + Fallback). cb(true|false). */
	function copyToClipboard(text, cb) {
		try {
			if (window.navigator && navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(text).then(
					function () { if (cb) { cb(true); } },
					function () { copyFallback(text, cb); }
				);
				return;
			}
		} catch (e) {}
		copyFallback(text, cb);
	}
	function copyFallback(text, cb) {
		var ok = false;
		try {
			var ta = document.createElement('textarea');
			ta.value = text; ta.setAttribute('readonly', '');
			ta.style.position = 'absolute'; ta.style.left = '-9999px';
			document.body.appendChild(ta); ta.select();
			ok = document.execCommand('copy');
			document.body.removeChild(ta);
		} catch (e) {}
		if (cb) { cb(ok); }
	}

	/** Kurzes Feedback auf einem Button: innerHTML merken → ersetzen → zurücksetzen. */
	function flashBtn(btn, html) {
		if (btn.getAttribute('data-busy') === '1') { return; }
		var orig = btn.innerHTML;
		btn.setAttribute('data-busy', '1');
		btn.classList.add('is-copied');
		btn.innerHTML = html;
		setTimeout(function () {
			btn.classList.remove('is-copied');
			btn.innerHTML = orig;
			btn.removeAttribute('data-busy');
		}, 1800);
	}

	/** Desktop-Teilen-Button (Variante A): dezenter Outline-Button → Link kopieren. */
	function buildShareButton() {
		var btn = el('button', 'mh-sh-share');
		btn.type = 'button';
		btn.innerHTML = SHARE_ICON + '<span>' + (I18N.shareConfig || 'Konfiguration teilen') + '</span>';
		btn.addEventListener('click', function () {
			track('stv_sh_share', 'desktop');
			copyToClipboard(buildShareLink('desktop'), function () {
				flashBtn(btn, CHECK_ICON + '<span>' + (I18N.shareCopied || 'Link kopiert') + '</span>');
			});
		});
		return btn;
	}

	/** Mobile-Teilen-Aktion (Variante E): natives Share-Menü, sonst Kopier-Fallback. */
	function triggerNativeShare(btn) {
		track('stv_sh_share', 'mobile');
		var url = buildShareLink('mobile');
		if (window.navigator && typeof navigator.share === 'function') {
			try {
				navigator.share({ title: I18N.shareTitle || (I18N.shareConfig || 'Konfiguration teilen'), url: url }).catch(function () {});
				return;
			} catch (e) {}
		}
		copyToClipboard(url, function () { flashBtn(btn, CHECK_ICON); });
	}

	function fmtPrice(p) {
		var dec = (typeof PF.decimals === 'number') ? PF.decimals : 2;
		var n = (Math.round(p * 100) / 100).toFixed(dec);
		var parts = n.split('.');
		parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, PF.thousand || '.');
		var num = parts.join(PF.decimal || ',');
		var sym = PF.symbol || '€';
		return (PF.position === 'left' || PF.position === 'left_space') ? (sym + ' ' + num) : (num + ' ' + sym);
	}

	function extras(house) {
		var out = [];
		for (var i = 0; i < house.addons.length; i++) {
			if (!house.addons[i].included) { out.push(house.addons[i]); }
		}
		return out;
	}
	function included(house) {
		var out = [];
		for (var i = 0; i < house.addons.length; i++) {
			if (house.addons[i].included) { out.push(house.addons[i]); }
		}
		return out;
	}

	/** Selektionen für das aktive Haus initialisieren (Vorauswahl aus default_selected, Startmenge = default_qty). */
	function initSelections(house) {
		state.selections = {};
		var ex = extras(house);
		for (var i = 0; i < ex.length; i++) {
			state.selections[ex[i].id] = { selected: ex[i].default_selected === true, qty: ex[i].default_qty || 1 };
		}
	}

	/** Höchste passende Tier-% für ein Extra bei D verschiedenen Extras. */
	function tierPercent(addon, distinct) {
		var pct = 0;
		var tiers = addon.discount_tiers || [];
		for (var i = 0; i < tiers.length; i++) {           // aufsteigend sortiert → letzter passender gewinnt
			if (tiers[i].from_distinct <= distinct) { pct = tiers[i].percent; }
		}
		return pct;
	}

	/** Komplette Summenberechnung inkl. Rabatt-Vorschau. */
	function computeTotals() {
		var house = currentHouse();
		var ex = extras(house);

		// D = Anzahl verschiedener AUSGEWÄHLTER Extras.
		var distinct = 0;
		for (var i = 0; i < ex.length; i++) {
			var sel = state.selections[ex[i].id];
			if (sel && sel.selected) { distinct++; }
		}

		var lines = [];
		var extrasGross = 0, extrasNet = 0;
		for (var j = 0; j < ex.length; j++) {
			var a = ex[j];
			var s = state.selections[a.id];
			if (!s || !s.selected) { continue; }
			var qty = Math.max(1, s.qty || 1);
			var gross = a.price * qty;
			var pct = tierPercent(a, distinct);
			var net = gross * (1 - pct / 100);
			extrasGross += gross;
			extrasNet += net;
			lines.push({
				id: a.id, name: a.name, qty: qty, unit: a.price,
				gross: gross, net: net, pct: pct,
				regUnit: (a.regular_price || a.price), onSale: !!a.on_sale
			});
		}

		return {
			house: house,
			distinct: distinct,
			lines: lines,
			extrasGross: extrasGross,
			extrasNet: extrasNet,
			savings: extrasGross - extrasNet,
			total: house.price + extrasNet
		};
	}

	/* ── Warenkorb (Schritt 4) ── */
	var CART = {
		url:     mhShData.ajaxUrl || '',
		nonce:   mhShData.cartNonce || '',
		cartUrl: mhShData.cartUrl || '',
		group:   (typeof mhShData.groupIndex === 'number') ? mhShData.groupIndex : 0
	};

	/** Aktuell gewählte Extras als [{id, qty}] (für den Add-to-Cart-Call). */
	function selectedExtras() {
		var house = currentHouse();
		var ex = extras(house);
		var out = [];
		for (var i = 0; i < ex.length; i++) {
			var s = state.selections[ex[i].id];
			if (s && s.selected) { out.push({ id: ex[i].id, qty: Math.max(1, s.qty || 1) }); }
		}
		return out;
	}

	/** Bundle (Haus + gewählte Extras) in den Warenkorb legen. */
	function addToCart(btnEl, noticeEl) {
		if (!CART.url || !CART.nonce) { return; }
		var house = currentHouse();
		var items = selectedExtras();
		var orig  = btnEl.innerHTML;

		btnEl.disabled = true;
		btnEl.classList.remove('is-success', 'is-error');
		btnEl.classList.add('is-loading');
		btnEl.innerHTML = I18N.adding || 'Wird hinzugef&uuml;gt&hellip;';
		if (noticeEl) { noticeEl.style.display = 'none'; }

		var body = new FormData();
		body.append('action', 'mh_stv_sh_add_to_cart');
		body.append('nonce', CART.nonce);
		body.append('group', CART.group);
		body.append('house_id', house.id);
		for (var j = 0; j < items.length; j++) {
			body.append('extras[' + j + '][id]', items[j].id);
			body.append('extras[' + j + '][qty]', items[j].qty);
		}

		fetch(CART.url, { method: 'POST', body: body, credentials: 'same-origin' })
			.then(function (res) { return res.json(); })
			.then(function (json) {
				btnEl.classList.remove('is-loading');
				if (json && json.success) {
					var d = json.data || {};
					btnEl.classList.add('is-success');
					btnEl.innerHTML = '\u2713 ' + (I18N.added || 'Im Warenkorb');

					// Warenkorb-Zähler aktualisieren.
					if (d.cart_count != null) {
						var cc = document.querySelectorAll('.cart-count, .wc-cart-count, .cart_contents_count');
						for (var c = 0; c < cc.length; c++) { cc[c].textContent = d.cart_count; }
					}

					// WC-Fragmente + Events (Mini-Cart, Themes).
					if (typeof jQuery !== 'undefined') {
						if (d.fragments) {
							jQuery.each(d.fragments, function (k, v) { jQuery(k).replaceWith(v); });
						}
						jQuery(document.body).trigger('added_to_cart', [d.fragments || {}, d.cart_hash || '', null]);
						jQuery(document.body).trigger('wc_fragment_refresh');
					}

					if (noticeEl) {
						var cartUrl = d.cart_url || CART.cartUrl || '';
						var msg = (I18N.added || 'Im Warenkorb');
						if (cartUrl) {
							msg += ' \u2014 <a href="' + esc(cartUrl) + '">' + (I18N.toCart || 'Zum Warenkorb') + '</a>';
						}
						// Server-Hinweise (z. B. Lager-Anpassungen) ergänzen.
						if (d.notices && d.notices.length) {
							msg += '<span class="mh-sh-notice-sub">' + esc(d.notices.join(' ')) + '</span>';
						}
						noticeEl.className = 'mh-sh-notice is-success';
						noticeEl.innerHTML = msg;
						noticeEl.style.display = '';
					}

					track('stv_sh_add_to_cart', house.id + ':' + items.length);

					setTimeout(function () {
						btnEl.classList.remove('is-success');
						btnEl.disabled = false;
						btnEl.innerHTML = orig;
					}, 3500);
				} else {
					cartError(btnEl, noticeEl, orig, (json && json.data && json.data.message) || (I18N.error || 'Fehler'));
				}
			})
			.catch(function () {
				// Sanfter Fallback: zur Produktseite des Hauses.
				if (house && house.url) { window.location.href = house.url; return; }
				cartError(btnEl, noticeEl, orig, I18N.error || 'Fehler');
			});
	}

	function cartError(btnEl, noticeEl, orig, msg) {
		btnEl.classList.remove('is-loading', 'is-success');
		btnEl.classList.add('is-error');
		btnEl.disabled = false;
		btnEl.innerHTML = orig;
		if (noticeEl) {
			noticeEl.className = 'mh-sh-notice is-error';
			noticeEl.textContent = String(msg == null ? '' : msg);
			noticeEl.style.display = '';
		}
		setTimeout(function () { btnEl.classList.remove('is-error'); }, 3000);
	}

	/* ── Render: Haus-Auswahl ── */
	function renderHouseSelector() {
		// Nur ein Modell in der Gruppe → kein „Modell wählen"-Bereich nötig.
		if (!D.houses || D.houses.length <= 1) { return null; }

		var wrap = el('div', 'mh-sh-models-wrap');
		var head = el('div', 'mh-sh-sectionhead');
		head.innerHTML =
			'<span class="mh-sh-step-h">' + (I18N.chooseHouse || 'Modell w\u00e4hlen') + '</span>' +
			(I18N.chooseHint ? '<span class="mh-sh-step-hint">' + I18N.chooseHint + '</span>' : '');
		wrap.appendChild(head);

		var row = el('div', 'mh-sh-models');
		for (var i = 0; i < D.houses.length; i++) {
			(function (h) {
				var card = el('button', 'mh-sh-model' + (h.id === state.houseId ? ' is-active' : ''));
				card.type = 'button';
				card.setAttribute('data-id', h.id);

				var onSale = h.on_sale && h.regular_price > h.price + 0.001;
				var thumb = '<span class="mh-sh-m-thumb"' + (h.image ? ' style="background-image:url(\'' + h.image + '\')"' : '') + '></span>';
				var tag = h.tag ? '<span class="mh-sh-m-tag">' + esc(h.tag) + '</span>' : '';
				var size = h.size ? '<span class="mh-sh-m-size"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 7h18M3 7v10M21 7v10M3 17h18"/></svg>' + esc(h.size) + '</span>' : '';
				var bar = (h.size_pct > 0) ? '<span class="mh-sh-m-bar"><i style="width:' + h.size_pct + '%"></i></span>' : '';
				var blurb = h.blurb ? '<span class="mh-sh-m-blurb">' + esc(h.blurb) + '</span>' : '';
				var price = (onSale ? '<span class="mh-sh-m-uvp">' + fmtPrice(h.regular_price) + '</span>' : '') +
					'<span class="mh-sh-m-ab">' + (I18N.from || 'ab') + '</span>' +
					'<span class="mh-sh-m-now">' + fmtPrice(h.price) + '</span>';

				card.innerHTML =
					'<span class="mh-sh-m-check"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span>' +
					'<span class="mh-sh-m-head">' + thumb +
						'<span class="mh-sh-m-top">' + tag +
							'<span class="mh-sh-m-name">' + esc(h.label || h.name) + '</span>' + size +
						'</span>' +
					'</span>' +
					bar + blurb +
					'<span class="mh-sh-m-price">' + price + '</span>';

				card.addEventListener('click', function () {
					if (state.houseId === h.id) { return; }
					state.houseId = h.id;
					track('stv_sh_house_switch', h.id);
					initSelections(currentHouse());
					syncUrl();   // URL = neues Modell + dessen (Default-)Auswahl (v5.40.0, kein Reload)
					render();
				});
				row.appendChild(card);
			})(D.houses[i]);
		}
		wrap.appendChild(row);
		return wrap;
	}

	/* ── Lightbox (eigenständig; 360°-Modus über das geteilte MH360-Modul) ── */
	var LB = (function () {
		var box = null, stage = null, imgEl = null, spinWrap = null;
		var thumbs = null, counter = null, prevBtn = null, nextBtn = null;
		var imgs = [], frames = [], idx = 0, mode = 'photo', spinner = null;
		var lastFocus = null, touchX = 0, touchY = 0, touchActive = false;
		// Zoom/Pan-Zustand
		var scale = 1, tx = 0, ty = 0, panning = false, moved = false;
		var panStartX = 0, panStartY = 0, panOrigX = 0, panOrigY = 0;
		var pinchDist = 0, pinchScale = 1, pinchCX = 0, pinchCY = 0;
		// Peek-Swipe-Zustand (nur Foto, nicht gezoomt)
		var peekEl = null, swActive = false, swStartX = 0, swStartY = 0, swDX = 0, swAxis = '';

		function ico(path, w) { return '<svg viewBox="0 0 24 24" width="' + (w || 24) + '" height="' + (w || 24) + '" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' + path + '</svg>'; }

		/* ── Zoom/Pan ── */
		function applyTransform(animate) {
			imgEl.style.transition = animate ? 'transform .18s ease' : 'none';
			imgEl.style.transform = 'translate(' + tx + 'px,' + ty + 'px) scale(' + scale + ')';
			if (scale > 1.01) { stage.classList.add('is-zoomed'); } else { stage.classList.remove('is-zoomed'); }
		}
		function resetZoom() { scale = 1; tx = 0; ty = 0; if (imgEl) { applyTransform(false); } }
		function clampPan() {
			var iw = imgEl.clientWidth * scale, ih = imgEl.clientHeight * scale;
			var sw = stage.clientWidth, sh = stage.clientHeight;
			var maxX = Math.max(0, (iw - sw) / 2), maxY = Math.max(0, (ih - sh) / 2);
			if (tx > maxX) { tx = maxX; } if (tx < -maxX) { tx = -maxX; }
			if (ty > maxY) { ty = maxY; } if (ty < -maxY) { ty = -maxY; }
		}
		function zoomTo(s2, cx, cy, animate) {
			s2 = Math.max(1, Math.min(5, s2));
			var sw = stage.clientWidth, sh = stage.clientHeight;
			var ox = cx - sw / 2, oy = cy - sh / 2;
			var ix = (ox - tx) / scale, iy = (oy - ty) / scale;
			scale = s2;
			if (scale <= 1.01) { resetZoom(); return; }
			tx = ox - ix * scale;
			ty = oy - iy * scale;
			clampPan();
			applyTransform(animate !== false);
		}
		function tdist(t) { var dx = t[0].clientX - t[1].clientX, dy = t[0].clientY - t[1].clientY; return Math.sqrt(dx * dx + dy * dy); }

		/* ── Peek-Swipe (Bildwechsel durch Ziehen, Nachbarbild folgt) ── */
		function swStart(x, y) {
			if (mode !== 'photo' || scale > 1.01 || imgs.length < 2) { return false; }
			swActive = true; swDX = 0; swStartX = x; swStartY = y; swAxis = '';
			imgEl.style.transition = 'none'; if (peekEl) { peekEl.style.transition = 'none'; }
			return true;
		}
		function swMove(x, y) {
			if (!swActive) { return; }
			swDX = x - swStartX; var dy = y - swStartY;
			if (!swAxis && (Math.abs(swDX) > 6 || Math.abs(dy) > 6)) { swAxis = Math.abs(swDX) >= Math.abs(dy) ? 'x' : 'y'; }
			if (swAxis !== 'x') { return; }
			moved = true;
			var dir = swDX < 0 ? 1 : -1;
			var pIdx = (idx + dir + imgs.length) % imgs.length;
			if (peekEl) {
				peekEl.src = imgs[pIdx] || '';
				peekEl.style.display = 'block';
			}
			var w = stage.clientWidth || 1;
			imgEl.style.transform = 'translateX(' + swDX + 'px)';
			if (peekEl) { peekEl.style.transform = 'translateX(' + (dir > 0 ? (w + swDX) : (-w + swDX)) + 'px)'; }
		}
		function swReset() {
			if (peekEl) { peekEl.style.transition = 'none'; peekEl.style.transform = ''; peekEl.style.display = 'none'; }
			imgEl.style.transition = 'none'; imgEl.style.transform = '';
		}
		function swEnd() {
			if (!swActive) { return; }
			swActive = false;
			if (swAxis !== 'x') { swReset(); return; }
			var w = stage.clientWidth || 1;
			var dir = swDX < 0 ? 1 : -1;
			var pass = Math.abs(swDX) > Math.max(50, w * 0.15);
			imgEl.style.transition = 'transform .26s ease'; if (peekEl) { peekEl.style.transition = 'transform .26s ease'; }
			if (pass) {
				imgEl.style.transform = 'translateX(' + (dir > 0 ? -w : w) + 'px)';
				if (peekEl) { peekEl.style.transform = 'translateX(0px)'; }
				setTimeout(function () { swReset(); showPhoto(idx + dir); }, 270);
			} else {
				imgEl.style.transform = 'translateX(0px)';
				if (peekEl) { peekEl.style.transform = 'translateX(' + (dir > 0 ? w : -w) + 'px)'; }
				setTimeout(swReset, 270);
			}
			swDX = 0;
		}

		function build() {
			box = el('div', 'mh-sh-lb');
			box.setAttribute('role', 'dialog');
			box.setAttribute('aria-modal', 'true');

			var close = el('button', 'mh-sh-lb-close');
			close.type = 'button';
			close.setAttribute('aria-label', I18N.lbClose || 'Schlie\u00dfen');
			close.innerHTML = ico('<line x1="6" y1="6" x2="18" y2="18"/><line x1="18" y1="6" x2="6" y2="18"/>', 22);
			close.addEventListener('click', hide);

			counter = el('div', 'mh-sh-lb-counter');

			prevBtn = el('button', 'mh-sh-lb-nav mh-sh-lb-prev');
			prevBtn.type = 'button';
			prevBtn.setAttribute('aria-label', I18N.lbPrev || 'Vorheriges Bild');
			prevBtn.innerHTML = ico('<polyline points="15 18 9 12 15 6"/>', 26);
			prevBtn.addEventListener('click', function (e) { e.stopPropagation(); go(-1); });

			nextBtn = el('button', 'mh-sh-lb-nav mh-sh-lb-next');
			nextBtn.type = 'button';
			nextBtn.setAttribute('aria-label', I18N.lbNext || 'N\u00e4chstes Bild');
			nextBtn.innerHTML = ico('<polyline points="9 18 15 12 9 6"/>', 26);
			nextBtn.addEventListener('click', function (e) { e.stopPropagation(); go(1); });

			stage = el('div', 'mh-sh-lb-stage');
			imgEl = el('img', 'mh-sh-lb-img');
			imgEl.alt = '';
			peekEl = el('img', 'mh-sh-lb-peek');
			peekEl.alt = ''; peekEl.draggable = false;
			spinWrap = el('div', 'mh-sh-lb-360');
			stage.appendChild(imgEl);
			stage.appendChild(peekEl);
			stage.appendChild(spinWrap);

			thumbs = el('div', 'mh-sh-lb-thumbs');

			box.appendChild(close);
			box.appendChild(counter);
			box.appendChild(prevBtn);
			box.appendChild(stage);
			box.appendChild(nextBtn);
			box.appendChild(thumbs);

			// Klick aufs Bild: Zoom umschalten (zum Klickpunkt)
			imgEl.addEventListener('click', function (e) {
				if (mode !== 'photo') { return; }
				if (moved) { moved = false; return; }
				e.stopPropagation();
				var r = stage.getBoundingClientRect();
				if (scale > 1.01) { resetZoom(); }
				else { zoomTo(2.5, e.clientX - r.left, e.clientY - r.top, true); }
			});

			// Mausrad: stufenlos zoomen (Cursorpunkt bleibt fix)
			stage.addEventListener('wheel', function (e) {
				if (mode !== 'photo') { return; }
				e.preventDefault();
				var r = stage.getBoundingClientRect();
				var factor = e.deltaY < 0 ? 1.2 : 1 / 1.2;
				zoomTo(scale * factor, e.clientX - r.left, e.clientY - r.top, false);
			}, { passive: false });

			// Maus: gezoomt = Pan, sonst = Peek-Swipe (Bildwechsel durch Ziehen)
			imgEl.addEventListener('mousedown', function (e) {
				if (mode !== 'photo') { return; }
				if (scale > 1.01) {
					e.preventDefault();
					panning = true; moved = false;
					panStartX = e.clientX; panStartY = e.clientY; panOrigX = tx; panOrigY = ty;
					imgEl.classList.add('is-grabbing');
				} else if (imgs.length > 1) {
					e.preventDefault();
					swStart(e.clientX, e.clientY);
				}
			});
			document.addEventListener('mousemove', function (e) {
				if (panning) {
					var dx = e.clientX - panStartX, dy = e.clientY - panStartY;
					if (Math.abs(dx) > 3 || Math.abs(dy) > 3) { moved = true; }
					tx = panOrigX + dx; ty = panOrigY + dy; clampPan(); applyTransform(false);
				} else if (swActive) {
					swMove(e.clientX, e.clientY);
				}
			});
			document.addEventListener('mouseup', function () {
				if (panning) { panning = false; imgEl.classList.remove('is-grabbing'); }
				else if (swActive) { swEnd(); }
			});

			box.addEventListener('click', function (e) { if (moved) { moved = false; return; } if (e.target === box || e.target === stage) { hide(); } });

			// Touch: 1 Finger = Peek-Swipe (unzoomed) bzw. Pan (gezoomt); 2 Finger = Pinch-Zoom
			stage.addEventListener('touchstart', function (e) {
				if (mode === '360') { return; }
				if (e.touches.length === 2) {
					pinchDist = tdist(e.touches);
					pinchScale = scale;
					var r = stage.getBoundingClientRect();
					pinchCX = (e.touches[0].clientX + e.touches[1].clientX) / 2 - r.left;
					pinchCY = (e.touches[0].clientY + e.touches[1].clientY) / 2 - r.top;
					touchActive = false; panning = false; swActive = false;
				} else if (e.touches.length === 1) {
					if (scale > 1.01) {
						panning = true; moved = false;
						panStartX = e.touches[0].clientX; panStartY = e.touches[0].clientY; panOrigX = tx; panOrigY = ty;
					} else {
						touchX = e.touches[0].clientX; touchY = e.touches[0].clientY; touchActive = true;
						swStart(e.touches[0].clientX, e.touches[0].clientY);
					}
				}
			}, { passive: true });
			stage.addEventListener('touchmove', function (e) {
				if (mode === '360') { return; }
				if (e.touches.length === 2 && pinchDist > 0) {
					e.preventDefault();
					zoomTo(pinchScale * (tdist(e.touches) / pinchDist), pinchCX, pinchCY, false);
				} else if (panning && e.touches.length === 1) {
					e.preventDefault();
					var dx = e.touches[0].clientX - panStartX, dy = e.touches[0].clientY - panStartY;
					if (Math.abs(dx) > 3 || Math.abs(dy) > 3) { moved = true; }
					tx = panOrigX + dx; ty = panOrigY + dy; clampPan(); applyTransform(false);
				} else if (swActive && e.touches.length === 1) {
					swMove(e.touches[0].clientX, e.touches[0].clientY);
					if (swAxis === 'x') { e.preventDefault(); } // horizontales Wischen, kein Schließen/Scroll
				}
			}, { passive: false });
			stage.addEventListener('touchend', function (e) {
				if (pinchDist > 0 && e.touches.length < 2) {
					pinchDist = 0;
					if (scale <= 1.01) { resetZoom(); }
				}
				if (panning && e.touches.length === 0) { panning = false; return; }
				if (swActive) {
					if (swAxis === 'x') { touchActive = false; swEnd(); return; }
					// vertikal/Tap: Peek zurücksetzen; nach unten wischen schließt.
					var tv = (e.changedTouches && e.changedTouches[0]) || null;
					var dyy0 = tv ? (tv.clientY - touchY) : 0, dxx0 = tv ? (tv.clientX - touchX) : 0;
					swActive = false; touchActive = false; swReset();
					if (dyy0 > 80 && Math.abs(dyy0) > Math.abs(dxx0) && scale <= 1.01) { hide(); }
					return;
				}
				if (!touchActive || mode === '360' || scale > 1.01) { touchActive = false; return; }
				var t = (e.changedTouches && e.changedTouches[0]) || null;
				touchActive = false;
				if (!t) { return; }
				var dyy = t.clientY - touchY, dxx = t.clientX - touchX;
				if (dyy > 80 && Math.abs(dyy) > Math.abs(dxx)) { hide(); }
			}, { passive: true });

			document.body.appendChild(box);
		}

		function onKey(e) {
			if (!box || box.className.indexOf('is-open') === -1) { return; }
			if (e.key === 'Escape') { hide(); }
			else if (e.key === 'ArrowLeft') { go(-1); }
			else if (e.key === 'ArrowRight') { go(1); }
		}

		function renderThumbs() {
			thumbs.innerHTML = '';
			if (frames.length) {
				var t360 = el('button', 'mh-sh-lb-thumb mh-sh-lb-thumb-360');
				t360.type = 'button';
				t360.setAttribute('aria-label', I18N.view360 || '360\u00b0-Ansicht');
				if (frames[0]) { t360.style.backgroundImage = "url('" + frames[0] + "')"; }
				t360.innerHTML = '<span class="mh-sh-lb-360-badge">' + (I18N.spin360 || '360\u00b0') + '</span>';
				t360.addEventListener('click', function (e) { e.stopPropagation(); enter360(); });
				thumbs.appendChild(t360);
			}
			for (var i = 0; i < imgs.length; i++) {
				(function (url, i2) {
					var tb = el('button', 'mh-sh-lb-thumb');
					tb.type = 'button';
					tb.style.backgroundImage = "url('" + url + "')";
					tb.addEventListener('click', function (e) { e.stopPropagation(); showPhoto(i2); });
					thumbs.appendChild(tb);
				})(imgs[i], i);
			}
		}

		function syncThumbActive() {
			var all = thumbs.querySelectorAll('.mh-sh-lb-thumb');
			var off = frames.length ? 1 : 0;
			for (var i = 0; i < all.length; i++) { all[i].classList.remove('is-active'); }
			if (mode === '360' && all[0]) { all[0].classList.add('is-active'); }
			else if (mode === 'photo' && all[idx + off]) { all[idx + off].classList.add('is-active'); }
		}

		function destroySpinner() {
			if (spinner && spinner.destroy) { spinner.destroy(); }
			spinner = null;
			if (spinWrap) { spinWrap.innerHTML = ''; }
		}

		function showPhoto(i) {
			if (!imgs.length) { return; }
			mode = 'photo';
			destroySpinner();
			box.classList.remove('is-360');
			resetZoom();
			idx = (i + imgs.length) % imgs.length;
			imgEl.src = imgs[idx] || '';
			counter.textContent = (idx + 1) + ' ' + (I18N.lbOf || 'von') + ' ' + imgs.length;
			var multi = imgs.length > 1;
			prevBtn.style.display = multi ? '' : 'none';
			nextBtn.style.display = multi ? '' : 'none';
			if (multi) {
				new Image().src = imgs[(idx + 1) % imgs.length];
				new Image().src = imgs[(idx - 1 + imgs.length) % imgs.length];
			}
			syncThumbActive();
		}

		function go(step) {
			if (mode !== 'photo' || imgs.length < 2) { return; }
			showPhoto(idx + step);
		}

		function enter360() {
			if (!frames.length || typeof MH360 === 'undefined') { return; }
			mode = '360';
			box.classList.add('is-360');
			prevBtn.style.display = 'none';
			nextBtn.style.display = 'none';
			counter.textContent = (I18N.spin360 || '360\u00b0');
			destroySpinner();
			spinner = MH360.create(spinWrap, {
				frames: frames,
				accentColor: mhShData.accentColor || '#e8910c',
				autoplay: true,
				momentum: true
			});
			track('stv_sh_360_interact', 'lightbox');
			syncThumbActive();
		}

		function show() {
			box.classList.add('is-open');
			lastFocus = document.activeElement;
			document.documentElement.classList.add('mh-sh-lb-lock');
			document.addEventListener('keydown', onKey);
			track('stv_sh_lightbox_open', mode === '360' ? '360' : 'photo');
		}
		function hide() {
			if (!box) { return; }
			box.classList.remove('is-open', 'is-360');
			destroySpinner();
			resetZoom();
			document.documentElement.classList.remove('mh-sh-lb-lock');
			document.removeEventListener('keydown', onKey);
			if (lastFocus && lastFocus.focus) { try { lastFocus.focus(); } catch (e) {} }
		}

		function open(images, start, frameUrls) {
			if (!box) { build(); }
			imgs   = (images && images.length) ? images.slice() : [];
			frames = (frameUrls && frameUrls.length) ? frameUrls.slice() : [];
			if (!imgs.length && !frames.length) { return; }
			renderThumbs();
			if (imgs.length) { showPhoto(start || 0); } else { enter360(); }
			show();
		}
		function open360(frameUrls, images) {
			if (!box) { build(); }
			imgs   = (images && images.length) ? images.slice() : [];
			frames = (frameUrls && frameUrls.length) ? frameUrls.slice() : [];
			if (!frames.length) { return; }
			renderThumbs();
			enter360();
			show();
		}

		/* Tauscht bei offener Foto-Lightbox die Bildquellen (z. B. 600px → Vollauflösung),
		   ohne neu zu öffnen/zu tracken. Index bleibt erhalten; bei aktivem Zoom kein Eingriff. */
		function upgrade(images) {
			if (!box || !box.classList.contains('is-open') || mode !== 'photo') { return; }
			if (!images || !images.length || images.length !== imgs.length) { return; }
			if (scale > 1.01) { return; }
			imgs = images.slice();
			renderThumbs();
			showPhoto(idx);
		}

		return { open: open, open360: open360, upgrade: upgrade };
	})();

	/* ── Render: Galerie (Hero + Großbilder untereinander, jedes klickbar) ── */
	function renderGallery(house) {
		var wrap = el('div', 'mh-sh-gallery-col');

		var imgs = (house.gallery && house.gallery.length) ? house.gallery : (house.image ? [house.image] : []);
		if (!imgs.length) { return wrap; }

		var n = imgs.length;
		var heroIdx = 0;
		var thumbBtns = [];
		var scroll = null;
		var counterEl = null;

		// Lightbox in Vollauflösung: gallery_full liegt nur in den Detail-Daten (lazy).
		// Liefert die schärfsten verfügbaren Quellen, ohne auf den Detail-Load zu warten.
		function bestFull() {
			var d = detailCache[house.id];
			if (d && d.gallery_full && d.gallery_full.length) { return d.gallery_full; }
			if (house.gallery_full && house.gallery_full.length) { return house.gallery_full; }
			return imgs;
		}
		function openAt(i) {
			LB.open(bestFull(), i, []);
			// Falls noch nicht geladen: Vollauflösung nachholen und offene Lightbox aufrüsten.
			if (!(detailCache[house.id] && detailCache[house.id].gallery_full)) {
				loadDetail(house.id, function (d) {
					if (d && d.gallery_full && d.gallery_full.length) { LB.upgrade(d.gallery_full); }
				});
			}
		}
		// Vollauflösung vorwärmen, damit Lightbox & Info-Tabs ohne Wartezeit scharf sind
		// (loadDetail ist gecacht → nur ein Request pro Haus).
		loadDetail(house.id, function () {});

		function srcsetFor(i) {
			return (house.gallery_srcset && house.gallery_srcset[i]) ? house.gallery_srcset[i] : null;
		}

		// Hauptbild (Hero) — Klick öffnet Lightbox, Swipe/Pfeile wechseln das Bild.
		var hero = el('div', 'mh-sh-gshot mh-sh-ghero' + (n > 1 ? ' is-multi' : ''));
		hero.setAttribute('role', 'button');
		hero.setAttribute('tabindex', '0');
		hero.setAttribute('aria-label', I18N.lbImage || 'Bild');
		var hImg = el('img', 'mh-sh-gimg');
		hImg.src = imgs[0]; hImg.alt = house.name; hImg.loading = 'eager';
		hImg.decoding = 'async'; hImg.draggable = false;
		var heroSs0 = srcsetFor(0);
		if (heroSs0 && heroSs0.srcset) {
			hImg.srcset = heroSs0.srcset;
			hImg.sizes  = '(max-width: 980px) 92vw, 720px';
		}
		hero.appendChild(hImg);

		// Zoom-Affordance (Klick aufs Hero öffnet die Lightbox).
		var zoom = el('button', 'mh-sh-gzoom'); zoom.type = 'button'; zoom.setAttribute('aria-label', 'Zoom'); zoom.tabIndex = -1;
		zoom.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.6" y2="16.6"/><line x1="11" y1="8.5" x2="11" y2="13.5"/><line x1="8.5" y1="11" x2="13.5" y2="11"/></svg>';
		hero.appendChild(zoom);

		// Aktualisiert Hero-Bild + aktiven Thumb + (optional) Zähler.
		function setHero(i) {
			heroIdx = ((i % n) + n) % n;
			hImg.src = imgs[heroIdx];
			var ss = srcsetFor(heroIdx);
			if (ss && ss.srcset) { hImg.srcset = ss.srcset; hImg.sizes = '(max-width: 980px) 92vw, 720px'; }
			else { hImg.removeAttribute('srcset'); hImg.removeAttribute('sizes'); }
			for (var t = 0; t < thumbBtns.length; t++) {
				var on = (t === heroIdx);
				thumbBtns[t].classList.toggle('is-active', on);
				thumbBtns[t].setAttribute('aria-pressed', on ? 'true' : 'false');
			}
			scrollThumbIntoView(heroIdx);
			if (counterEl) { counterEl.textContent = (heroIdx + 1) + ' / ' + n; }
		}

		if (n > 1) {
			// Peek-Bild (Nachbar), das beim Ziehen real mit hereinkommt.
			var hPeek = el('img', 'mh-sh-gpeek'); hPeek.alt = ''; hPeek.draggable = false; hPeek.decoding = 'async';
			hero.appendChild(hPeek);

			// Pfeile (immer sichtbar, wie bei Tom).
			var gPrev = el('button', 'mh-sh-gnav mh-sh-gprev'); gPrev.type = 'button';
			gPrev.setAttribute('aria-label', I18N.lbPrev || 'Vorheriges Bild');
			gPrev.innerHTML = '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>';
			gPrev.addEventListener('click', function (e) { e.stopPropagation(); setHero(heroIdx - 1); });
			var gNext = el('button', 'mh-sh-gnav mh-sh-gnext'); gNext.type = 'button';
			gNext.setAttribute('aria-label', I18N.lbNext || 'N\u00e4chstes Bild');
			gNext.innerHTML = '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>';
			gNext.addEventListener('click', function (e) { e.stopPropagation(); setHero(heroIdx + 1); });
			hero.appendChild(gPrev); hero.appendChild(gNext);

			// ── Peek-Drag (Maus + Touch): Nachbarbild folgt dem Finger, snappt beim Loslassen ──
			var dragging = false, dragMoved = false, dragStartX = 0, dragStartY = 0, dragDX = 0, dragAxis = '', peekIdx = -1;
			var dStart = function (x, y) {
				dragging = true; dragMoved = false; dragAxis = ''; dragStartX = x; dragStartY = y; dragDX = 0; peekIdx = -1;
				hImg.style.transition = 'none'; hPeek.style.transition = 'none';
				hero.classList.add('is-dragging');
			};
			var dMove = function (x, y) {
				if (!dragging) { return; }
				dragDX = x - dragStartX;
				var dy = y - dragStartY;
				if (!dragAxis && (Math.abs(dragDX) > 6 || Math.abs(dy) > 6)) { dragAxis = Math.abs(dragDX) >= Math.abs(dy) ? 'x' : 'y'; }
				if (dragAxis !== 'x') { return; }
				dragMoved = true;
				var dir = dragDX < 0 ? 1 : -1; // links ziehen → nächstes Bild
				var pIdx = ((heroIdx + dir) % n + n) % n;
				if (pIdx !== peekIdx) {
					peekIdx = pIdx;
					hPeek.src = imgs[pIdx];
					var ps = srcsetFor(pIdx);
					if (ps && ps.srcset) { hPeek.srcset = ps.srcset; hPeek.sizes = '(max-width: 980px) 92vw, 720px'; }
					else { hPeek.removeAttribute('srcset'); hPeek.removeAttribute('sizes'); }
				}
				var w = hero.offsetWidth || 1;
				hPeek.style.display = 'block';
				hImg.style.transform = 'translateX(' + dragDX + 'px)';
				hPeek.style.transform = 'translateX(' + (dir > 0 ? (w + dragDX) : (-w + dragDX)) + 'px)';
			};
			var commitPeek = function () {
				var ni = peekIdx; peekIdx = -1;
				hPeek.style.transition = 'none'; hPeek.style.transform = ''; hPeek.style.display = 'none';
				hImg.style.transition = 'none'; hImg.style.transform = '';
				if (ni >= 0) { setHero(ni); }
			};
			var resetPeek = function () {
				peekIdx = -1;
				hPeek.style.transition = 'none'; hPeek.style.transform = ''; hPeek.style.display = 'none';
				hImg.style.transition = 'none'; hImg.style.transform = '';
			};
			var dEnd = function () {
				if (!dragging) { return; }
				dragging = false; hero.classList.remove('is-dragging');
				var w = hero.offsetWidth || 1;
				if (dragAxis === 'x' && peekIdx >= 0) {
					var dir = dragDX < 0 ? 1 : -1;
					var pass = Math.abs(dragDX) > Math.max(40, w * 0.12);
					hImg.style.transition = 'transform .26s ease'; hPeek.style.transition = 'transform .26s ease';
					if (pass) {
						hImg.style.transform = 'translateX(' + (dir > 0 ? -w : w) + 'px)';
						hPeek.style.transform = 'translateX(0px)';
						setTimeout(commitPeek, 270);
					} else {
						hImg.style.transform = 'translateX(0px)';
						hPeek.style.transform = 'translateX(' + (dir > 0 ? w : -w) + 'px)';
						setTimeout(resetPeek, 270);
					}
				} else {
					resetPeek();
				}
				dragDX = 0;
				setTimeout(function () { dragMoved = false; }, 30); // Klick nach Drag unterdrücken
			};
			// Maus: Move/Up nur während des Drags an document (kein Leak beim Re-Render).
			var onMM = function (e) { dMove(e.clientX, e.clientY); };
			var onMU = function () { dEnd(); document.removeEventListener('mousemove', onMM); document.removeEventListener('mouseup', onMU); };
			hero.addEventListener('mousedown', function (e) {
				if (e.button !== 0) { return; }
				e.preventDefault(); dStart(e.clientX, e.clientY);
				document.addEventListener('mousemove', onMM); document.addEventListener('mouseup', onMU);
			});
			// Touch: Handler am Hero selbst (werden beim DOM-Neuaufbau mit entsorgt).
			hero.addEventListener('touchstart', function (e) {
				if (e.touches.length !== 1) { return; }
				dStart(e.touches[0].clientX, e.touches[0].clientY);
			}, { passive: true });
			hero.addEventListener('touchmove', function (e) {
				if (!dragging || e.touches.length !== 1) { return; }
				dMove(e.touches[0].clientX, e.touches[0].clientY);
				if (dragAxis === 'x') { e.preventDefault(); } // horizontales Wischen, kein Seiten-Scroll
			}, { passive: false });
			hero.addEventListener('touchend', function () { dEnd(); }, { passive: true });
		}

		// Klick aufs Hero → Lightbox (außer es war ein Wisch).
		hero.addEventListener('click', function () { if (typeof dragMoved !== 'undefined' && dragMoved) { return; } openAt(heroIdx); });
		hero.addEventListener('keydown', function (e) {
			if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openAt(heroIdx); }
			else if (n > 1 && e.key === 'ArrowLeft')  { e.preventDefault(); setHero(heroIdx - 1); }
			else if (n > 1 && e.key === 'ArrowRight') { e.preventDefault(); setHero(heroIdx + 1); }
		});
		wrap.appendChild(hero);

		// ── Thumbnail-Streifen: Klick TAUSCHT das Hero-Bild (öffnet NICHT die Lightbox) ──
		function scrollThumbIntoView(i) {
			var t = thumbBtns[i]; if (!t || !scroll) { return; }
			var tl = t.offsetLeft, tr = tl + t.offsetWidth;
			var vl = scroll.scrollLeft, vr = vl + scroll.clientWidth, target = null;
			if (tl < vl) { target = tl - 8; }
			else if (tr > vr) { target = tr - scroll.clientWidth + 8; }
			if (target === null) { return; }
			if (scroll.scrollTo) { try { scroll.scrollTo({ left: target, behavior: 'smooth' }); return; } catch (e) {} }
			scroll.scrollLeft = target;
		}

		if (n > 1) {
			var strip = el('div', 'mh-sh-thumbstrip');

			scroll = el('div', 'mh-sh-thumbs-scroll');
			var dragScrolled = false; // unterscheidet Klick (Hero tauschen) von Ziehen (scrollen)
			for (var i = 0; i < n; i++) {
				(function (url, idx) {
					var t = el('button', 'mh-sh-thumb'); t.type = 'button';
					t.setAttribute('aria-label', (I18N.lbImage || 'Bild') + ' ' + (idx + 1));
					t.setAttribute('aria-pressed', idx === 0 ? 'true' : 'false');
					if (idx === 0) { t.classList.add('is-active'); }
					var im = el('img'); im.src = url; im.alt = house.name + ' ' + (idx + 1); im.loading = 'lazy'; im.draggable = false;
					t.appendChild(im);
					t.addEventListener('click', function () { if (dragScrolled) { return; } setHero(idx); });
					scroll.appendChild(t); thumbBtns.push(t);
				})(imgs[i], i);
			}

			// Drag-to-Scroll mit der Maus direkt auf den Thumbnails (drücken, halten, ziehen).
			var sDown = false, sStartX = 0, sStartLeft = 0;
			var sMM = function (e) {
				if (!sDown) { return; }
				var dx = e.clientX - sStartX;
				if (Math.abs(dx) > 4) { dragScrolled = true; }
				scroll.scrollLeft = sStartLeft - dx;
			};
			var sMU = function () {
				sDown = false; scroll.classList.remove('is-grabbing');
				document.removeEventListener('mousemove', sMM); document.removeEventListener('mouseup', sMU);
				setTimeout(function () { dragScrolled = false; }, 30);
			};
			scroll.addEventListener('mousedown', function (e) {
				if (e.button !== 0) { return; }
				sDown = true; dragScrolled = false; sStartX = e.clientX; sStartLeft = scroll.scrollLeft;
				scroll.classList.add('is-grabbing');
				document.addEventListener('mousemove', sMM); document.addEventListener('mouseup', sMU);
			});

			// ── Eigene Laufleiste: ◄ [Track + ziehbarer Balken] ► ──
			var barRow = el('div', 'mh-sh-thumbbar-row');
			var tPrev = el('button', 'mh-sh-tnav mh-sh-tprev'); tPrev.type = 'button';
			tPrev.setAttribute('aria-label', I18N.lbPrev || 'Vorheriges Bild');
			tPrev.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>';
			var track = el('div', 'mh-sh-thumbbar');
			var bar = el('div', 'mh-sh-thumbbar-thumb');
			track.appendChild(bar);
			var tNext = el('button', 'mh-sh-tnav mh-sh-tnext'); tNext.type = 'button';
			tNext.setAttribute('aria-label', I18N.lbNext || 'N\u00e4chstes Bild');
			tNext.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>';
			barRow.appendChild(tPrev); barRow.appendChild(track); barRow.appendChild(tNext);

			var raf = false;
			var maxScroll = function () { return Math.max(0, scroll.scrollWidth - scroll.clientWidth); };
			var updateBar = function () {
				var sw = scroll.scrollWidth, cw = scroll.clientWidth;
				if (sw <= cw + 1) { barRow.style.display = 'none'; return; }
				barRow.style.display = '';
				var tw = track.clientWidth || 1;
				var bw = Math.max(28, tw * (cw / sw));
				var ms = maxScroll();
				var bl = ms > 0 ? (scroll.scrollLeft / ms) * (tw - bw) : 0;
				bar.style.width = bw + 'px';
				bar.style.transform = 'translateX(' + bl + 'px)';
				tPrev.classList.toggle('is-disabled', scroll.scrollLeft <= 0);
				tNext.classList.toggle('is-disabled', scroll.scrollLeft >= ms - 1);
			};
			var pageScroll = function (dir) {
				var amt = Math.max(140, scroll.clientWidth * 0.8);
				var target = scroll.scrollLeft + dir * amt;
				if (scroll.scrollTo) { try { scroll.scrollTo({ left: target, behavior: 'smooth' }); return; } catch (e) {} }
				scroll.scrollLeft = target;
			};
			tPrev.addEventListener('click', function () { pageScroll(-1); });
			tNext.addEventListener('click', function () { pageScroll(1); });
			scroll.addEventListener('scroll', function () { if (raf) { return; } raf = true; requestAnimationFrame(function () { raf = false; updateBar(); }); });

			// Balken ziehen → scrollt den Streifen (Maus + Touch).
			var bDown = false, bStartX = 0, bStartLeft = 0;
			var barLeft = function () { var m = bar.style.transform.match(/-?\d+\.?\d*/); return m ? parseFloat(m[0]) : 0; };
			var bMM = function (e) {
				if (!bDown) { return; }
				var x = (e.touches && e.touches[0]) ? e.touches[0].clientX : e.clientX;
				var range = track.clientWidth - bar.offsetWidth;
				if (range <= 0) { return; }
				var frac = Math.max(0, Math.min(1, (bStartLeft + (x - bStartX)) / range));
				scroll.scrollLeft = frac * maxScroll();
			};
			var bMU = function () {
				bDown = false; bar.classList.remove('is-grabbing');
				document.removeEventListener('mousemove', bMM); document.removeEventListener('mouseup', bMU);
			};
			bar.addEventListener('mousedown', function (e) {
				if (e.button !== 0) { return; }
				e.preventDefault(); bDown = true; bStartX = e.clientX; bStartLeft = barLeft();
				bar.classList.add('is-grabbing');
				document.addEventListener('mousemove', bMM); document.addEventListener('mouseup', bMU);
			});
			bar.addEventListener('touchstart', function (e) {
				if (e.touches.length !== 1) { return; }
				bDown = true; bStartX = e.touches[0].clientX; bStartLeft = barLeft();
			}, { passive: true });
			bar.addEventListener('touchmove', function (e) { if (bDown) { bMM(e); e.preventDefault(); } }, { passive: false });
			bar.addEventListener('touchend', function () { bDown = false; }, { passive: true });

			strip.appendChild(scroll); strip.appendChild(barRow);
			wrap.appendChild(strip);
			setTimeout(updateBar, 0);
			setTimeout(updateBar, 300); // nach Bildladung kann sich scrollWidth ändern
		}
		return wrap;
	}

	/* ── Render: Add-on-Karten ── */
	function renderAddons(house) {
		var wrap = el('div', 'mh-sh-addons');
		var incl = included(house);
		var ex = extras(house);

		if (incl.length) {
			var ititle = el('div', 'mh-sh-addons-subtitle'); ititle.textContent = 'Bereits enthalten'; wrap.appendChild(ititle);
			var igrid = el('div', 'mh-sh-addon-grid');
			for (var i = 0; i < incl.length; i++) {
				igrid.appendChild(renderIncludedCard(incl[i]));
			}
			wrap.appendChild(igrid);
		}

		if (ex.length) {
			// "Zubehör hinzufügen"-Subtitle entfernt (v5.33.2): redundant, da die
			// Sektionsüberschrift "Zubehör konfigurieren" steht direkt darüber.
			var elist = el('div', 'mh-sh-extras-list');

			// Anzeige-Reihenfolge: vorausgewählte zuerst (stabil), damit angehakte Extras nie
			// hinter „Mehr anzeigen" verschwinden; der Rest behält die Admin-Reihenfolge.
			function isSel(a) { return !!(state.selections[a.id] && state.selections[a.id].selected); }
			var ordered = [], k;
			for (k = 0; k < ex.length; k++) { if (isSel(ex[k]))  { ordered.push(ex[k]); } }
			for (k = 0; k < ex.length; k++) { if (!isSel(ex[k])) { ordered.push(ex[k]); } }

			// Sichtfenster: mind. EXTRAS_VISIBLE, aber nie weniger als die Zahl der Vorausgewählten.
			// EXTRAS_VISIBLE = 0 → Funktion deaktiviert (alle zeigen).
			var preCount = 0;
			for (k = 0; k < ordered.length; k++) { if (isSel(ordered[k])) { preCount++; } }
			var visible  = EXTRAS_VISIBLE > 0 ? Math.max(EXTRAS_VISIBLE, preCount) : ordered.length;
			var collapse = ordered.length > visible;

			var collapsed = [];
			for (var j = 0; j < ordered.length; j++) {
				var ecard = renderExtraCard(ordered[j]);
				if (collapse && j >= visible) { ecard.classList.add('mh-sh-extra-collapsed'); collapsed.push(ecard); }
				elist.appendChild(ecard);
			}
			wrap.appendChild(elist);

			if (collapse) {
				var moreBtn = el('button', 'mh-sh-extras-more'); moreBtn.type = 'button';
				moreBtn.setAttribute('aria-expanded', 'false');
				var chev = ' <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>';
				var moreTxt = (I18N.showMore || 'Mehr anzeigen') + ' (' + collapsed.length + ')';
				var lessTxt = (I18N.showLess || 'Weniger anzeigen');
				moreBtn.innerHTML = moreTxt + chev;
				(function (btn, cards) {
					btn.addEventListener('click', function () {
						var expanded = btn.getAttribute('aria-expanded') === 'true';
						expanded = !expanded;
						for (var c = 0; c < cards.length; c++) { cards[c].classList.toggle('mh-sh-extra-collapsed', !expanded); }
						btn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
						btn.classList.toggle('is-open', expanded);
						btn.innerHTML = (expanded ? lessTxt : moreTxt) + chev;
						track('stv_sh_extras_more', expanded ? 'open' : 'close');
					});
				})(moreBtn, collapsed);
				wrap.appendChild(moreBtn);
			}
		}
		return wrap;
	}

	function renderIncludedCard(a) {
		var card = el('div', 'mh-sh-addon-card is-included');
		var qty = a.default_qty > 1 ? a.default_qty : 1;
		var qtyPrefix = (a.default_qty > 1 ? (a.default_qty + '\u00D7 ') : '');
		var nameHtml = a.url
			? '<a class="mh-sh-addon-name mh-sh-addon-link" href="' + esc(a.url) + '" target="_blank" rel="noopener">' + qtyPrefix + esc(a.name) + '</a>'
			: '<span class="mh-sh-addon-name">' + qtyPrefix + esc(a.name) + '</span>';

		// Wert des bereits enthaltenen Add-ons (Menge × Einzelpreis) rechts als
		// durchgestrichener Preis → zeigt, was im Hauspreis bereits „geschenkt" steckt.
		// Bei preislosen Produkten (value 0) bleibt die Karte sauber ohne Preis.
		var value = (a.price || 0) * qty;
		var priceHtml = value > 0
			? '<span class="mh-sh-incl-price"><s>' + fmtPrice(value) + '</s><span class="mh-sh-incl-free">' + (I18N.inclValue || 'inklusive') + '</span></span>'
			: '';

		card.innerHTML =
			'<span class="mh-sh-addon-thumb" style="background-image:url(\'' + (a.image || '') + '\')"></span>' +
			'<span class="mh-sh-addon-body">' +
				nameHtml +
				'<span class="mh-sh-incl-badge">' + (I18N.included || '✓ im Lieferumfang') + '</span>' +
			'</span>' +
			priceHtml;
		return card;
	}

	function renderExtraCard(a) {
		var sel = state.selections[a.id] || { selected: false, qty: a.default_qty || 1 };
		var card = el('div', 'mh-sh-extra' + (sel.selected ? ' is-selected' : ''));
		card.setAttribute('data-id', a.id);

		var arow = el('div', 'mh-sh-arow');

		var cbx = el('span', 'mh-sh-cbx');
		cbx.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>';

		var thumb = el('span', 'mh-sh-athumb');
		if (a.image) { thumb.style.backgroundImage = "url('" + a.image + "')"; }

		var info = el('span', 'mh-sh-ainfo');
		var name;
		if (a.url) {
			name = el('a', 'mh-sh-aname mh-sh-aname-link');
			name.href = a.url;
			name.target = '_blank';
			name.rel = 'noopener';
			name.textContent = a.name;
			// Klick auf den Namen öffnet das Produkt, statt die Auswahl zu togglen.
			name.addEventListener('click', function (e) { e.stopPropagation(); });
		} else {
			name = el('span', 'mh-sh-aname');
			name.textContent = a.name;
		}
		var price = el('span', 'mh-sh-aprice'); price.setAttribute('data-role', 'price');
		info.appendChild(name); info.appendChild(price);

		// Details-Akkordeon — nur wenn Beschreibung vorhanden.
		var hasDetails = !!(a.short_description && a.short_description.replace(/<[^>]*>/g, '').replace(/\s+/g, '').length);
		var detToggle = null;
		if (hasDetails) {
			detToggle = el('button', 'mh-sh-det-toggle'); detToggle.type = 'button';
			detToggle.innerHTML = (I18N.details || 'Details') + ' <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>';
			info.appendChild(detToggle);
		}

		var right = el('span', 'mh-sh-aright');
		if (a.allow_qty) {
			var stepper = el('span', 'mh-sh-stepper');
			var minus = el('button', 'mh-sh-step-btn'); minus.type = 'button'; minus.textContent = '\u2212';
			var qv = el('span', 'mh-sh-step-q'); qv.textContent = sel.qty;
			var plus = el('button', 'mh-sh-step-btn'); plus.type = 'button'; plus.textContent = '+';
			stepper.appendChild(minus); stepper.appendChild(qv); stepper.appendChild(plus);
			minus.addEventListener('click', function (e) {
				e.stopPropagation();
				var s = state.selections[a.id]; s.qty = Math.max(1, s.qty - 1); qv.textContent = s.qty; recompute(); syncUrl();
			});
			plus.addEventListener('click', function (e) {
				e.stopPropagation();
				var s = state.selections[a.id];
				var max = (SHOW_STOCK && a.stock_quantity > 0) ? a.stock_quantity : 99;
				s.qty = Math.min(max, s.qty + 1); qv.textContent = s.qty; recompute(); syncUrl();
			});
			right.appendChild(stepper);
		}

		arow.appendChild(cbx); arow.appendChild(thumb); arow.appendChild(info); arow.appendChild(right);
		// Ganze Zeile wählbar (auch nicht-lieferbare); Stepper/Details ausgenommen.
		arow.addEventListener('click', function (e) {
			if ((e.target.closest && (e.target.closest('.mh-sh-stepper') || e.target.closest('.mh-sh-det-toggle')))) { return; }
			var s = state.selections[a.id]; s.selected = !s.selected;
			card.classList.toggle('is-selected', s.selected);
			track('stv_sh_extra_toggle', a.id + ':' + (s.selected ? 'on' : 'off'));
			recompute(); syncUrl();
		});
		card.appendChild(arow);

		if (hasDetails) {
			var det = el('div', 'mh-sh-det');
			var din = el('div', 'mh-sh-det-in');
			var p = el('div', 'mh-sh-det-desc'); p.innerHTML = a.short_description;
			din.appendChild(p);
			det.appendChild(din); card.appendChild(det);
			detToggle.addEventListener('click', function (e) { e.stopPropagation(); card.classList.toggle('is-open'); });
		}

		setTimeout(function () { updateExtraPrice(a, card); }, 0);
		return card;
	}

	/* ── Live-Update der Add-on-Preise (UVP- + Set-Streichpreis, Badges) ── */
	function updateExtraPrice(a, card) {
		var totals = computeTotals();
		var sel = state.selections[a.id];
		var isSel = !!(sel && sel.selected);
		// Angewendeter Set-Rabatt nur bei ausgewähltem Add-on (echter Streich-/Bundle-Preis).
		var pct = isSel ? tierPercent(a, totals.distinct) : 0;
		// Potenzieller Set-Rabatt für ABGEWÄHLTE Add-ons (Variante A): der Satz, den genau
		// dieses Extra bekäme, wenn man es anhakt → ein weiteres verschiedenes Extra, also
		// tierPercent bei (distinct + 1). Macht den Set-Rabatt sichtbar, BEVOR ausgewählt
		// wird; passt sich bei gestaffelten Rabatten automatisch an (recompute rendert alle
		// Karten neu). Kein Streich-/Bundle-Preis hier — nur das „Ghost"-Badge als Hinweis.
		var potentialPct = isSel ? 0 : tierPercent(a, totals.distinct + 1);
		var priceEl = card.querySelector('[data-role="price"]');
		if (!priceEl) { return; }

		var onSale = a.on_sale && a.regular_price > a.price + 0.001;
		var html = '';
		if (onSale) { html += '<span class="mh-sh-a-uvp">' + fmtPrice(a.regular_price) + '</span>'; }
		if (pct > 0) {
			html += '<span class="mh-sh-a-sale is-struck">' + fmtPrice(a.price) + '</span>';
			html += '<span class="mh-sh-a-bundle">' + fmtPrice(a.price * (1 - pct / 100)) + '</span>';
		} else {
			html += '<span class="mh-sh-a-sale">' + fmtPrice(a.price) + '</span>';
		}
		if (onSale) { html += '<span class="mh-sh-badge mh-sh-badge-disc">\u2212' + pctOff(a.regular_price, a.price) + '%</span>'; }
		if (pct > 0) {
			html += '<span class="mh-sh-badge mh-sh-badge-set">\u2212' + fmtNum(pct) + '% ' + (I18N.inSet || 'im Set') + '</span>';
		} else if (potentialPct > 0) {
			html += '<span class="mh-sh-badge mh-sh-badge-set mh-sh-badge-set--potential">\u2212' + fmtNum(potentialPct) + '% ' + (I18N.inSet || 'im Set') + '</span>';
		}
		if (SHOW_STOCK && a.stock_status === 'outofstock') {
			html += '<span class="mh-sh-badge mh-sh-badge-oos">' + (I18N.oos || a.stock_text || 'Nicht auf Lager') + '</span>';
		}
		priceEl.innerHTML = html;
	}

	function pctOff(uvp, now) { return Math.round((1 - now / uvp) * 100); }
	function fmtNum(n) { return (Math.round(n * 100) / 100).toString().replace('.', PF.decimal || ','); }

	/* ── Render: Summe ── */
	function renderSummary() {
		var box = el('div', 'mh-sh-summary');
		box.setAttribute('data-role', 'summary');
		box.appendChild(buildSummaryInner(computeTotals()));
		return box;
	}

	function buildSummaryInner(t) {
		var frag = document.createDocumentFragment();
		var title = el('div', 'mh-sh-summary-title'); title.innerHTML = I18N.summaryTitle || 'Deine Konfiguration'; frag.appendChild(title);

		var list = el('div', 'mh-sh-summary-lines');

		// Haus (mit UVP-Streichpreis, falls im Angebot)
		var h = t.house;
		var hOnSale = h.on_sale && h.regular_price > h.price + 0.001;
		var hVal = (hOnSale ? '<span class="s">' + fmtPrice(h.regular_price) + '</span>' : '') + fmtPrice(h.price);
		list.appendChild(summaryLine((I18N.houseLabel || 'Spielhaus') + ': ' + esc(h.label || h.name), hVal, false));

		// UVP-Gesamt (für Streich-Summe) + Endsumme
		var uvpTotal = hOnSale ? h.regular_price : h.price;
		var finalTotal = h.price;

		for (var i = 0; i < t.lines.length; i++) {
			var ln = t.lines[i];
			var label = (ln.qty > 1 ? ln.qty + '\u00D7 ' : '') + esc(ln.name);
			var val;
			if (ln.pct > 0) {
				val = '<span class="s">' + fmtPrice(ln.gross) + '</span><span class="g">' + fmtPrice(ln.net) + '</span>';
			} else {
				val = fmtPrice(ln.gross);
			}
			list.appendChild(summaryLine(label, val, true));
			uvpTotal += (ln.onSale ? ln.regUnit * ln.qty : ln.gross);
			finalTotal += ln.net;
		}
		frag.appendChild(list);

		// Set-Vorteil (Bundle-Rabatt)
		if (t.savings > 0.005) {
			var sav = el('div', 'mh-sh-summary-savings');
			sav.innerHTML = '<span>' + (I18N.savings || 'Set-Vorteil') + '</span><span>&minus;' + fmtPrice(t.savings) + '</span>';
			frag.appendChild(sav);
		}

		var totalSavings = uvpTotal - finalTotal;

		var total = el('div', 'mh-sh-summary-total');
		total.innerHTML = '<span class="mh-sh-st-l">' + (I18N.total || 'Gesamt') + '</span>' +
			'<span class="mh-sh-st-v">' +
				(totalSavings > 0.005 ? '<span class="mh-sh-st-uvp">' + fmtPrice(uvpTotal) + '</span>' : '') +
				'<span class="mh-sh-st-now">' + fmtPrice(finalTotal) + '</span>' +
			'</span>';
		frag.appendChild(total);

		if (totalSavings > 0.005) {
			var save = el('div', 'mh-sh-savetotal');
			save.innerHTML = '\u2212' + fmtPrice(totalSavings) + ' ' + (I18N.saved || 'gespart');
			frag.appendChild(save);
		}

		var suffix = el('div', 'mh-sh-price-suffix'); suffix.innerHTML = I18N.priceSuffix || ''; frag.appendChild(suffix);

		// CTA — legt Haus + gewählte Extras als Bundle in den Warenkorb (Schritt 4).
		var cta = el('button', 'mh-sh-cta');
		cta.type = 'button';
		cta.innerHTML = (I18N.addToCart || 'In den Warenkorb');
		var notice = el('div', 'mh-sh-notice');
		notice.setAttribute('role', 'status');
		notice.setAttribute('aria-live', 'polite');
		notice.style.display = 'none';
		cta.addEventListener('click', function () { addToCart(cta, notice); });
		frag.appendChild(cta);
		frag.appendChild(notice);

		return frag;
	}

	function summaryLine(label, valHtml, isExtra) {
		var row = el('div', 'mh-sh-summary-line' + (isExtra ? ' is-extra' : ''));
		row.innerHTML = '<span class="mh-sh-sl-label">' + label + '</span><span class="mh-sh-sl-val">' + valHtml + '</span>';
		return row;
	}

	/* ── Recompute: Summe + alle Extra-Preise neu (D kann sich geändert haben) ── */
	function recompute() {
		var house = currentHouse();
		var ex = extras(house);
		for (var i = 0; i < ex.length; i++) {
			var card = ROOT.querySelector('.mh-sh-extra[data-id="' + ex[i].id + '"]');
			if (card) { updateExtraPrice(ex[i], card); }
		}
		var box = ROOT.querySelector('[data-role="summary"]');
		if (box) {
			box.innerHTML = '';
			box.appendChild(buildSummaryInner(computeTotals()));
		}
		var mbar = ROOT.querySelector('[data-role="mobilebar"]');
		if (mbar) { fillMobileBar(mbar, computeTotals()); }
	}

	/* ── Mobile Sticky-CTA-Leiste ── */
	function renderMobileBar() {
		var bar = el('div', 'mh-sh-mobilebar');
		bar.setAttribute('data-role', 'mobilebar');
		fillMobileBar(bar, computeTotals());
		return bar;
	}
	function fillMobileBar(bar, t) {
		var h = t.house;
		var hOnSale = h.on_sale && h.regular_price > h.price + 0.001;
		var uvpTotal = hOnSale ? h.regular_price : h.price;
		var finalTotal = h.price;
		var names = [];
		for (var i = 0; i < t.lines.length; i++) {
			var ln = t.lines[i];
			uvpTotal += (ln.onSale ? ln.regUnit * ln.qty : ln.gross);
			finalTotal += ln.net;
			names.push((ln.qty > 1 ? (ln.qty + '\u00D7 ') : '') + ln.name);
		}
		var save = uvpTotal - finalTotal;

		// Zubehör-Zeile: gewählte Extras (mit Menge) als kompakte, abgeschnittene
		// Liste. Ohne Auswahl ein dezenter Hinweis statt leerer Zeile.
		var extrasText = names.length ? ('+ ' + names.join(', ')) : (I18N.mbNoExtras || 'Ohne Zubeh\u00f6r');
		var extrasCls  = names.length ? 'mh-sh-mb-extras' : 'mh-sh-mb-extras is-empty';

		bar.innerHTML =
			'<div class="mh-sh-mb-top">' +
				'<span class="mh-sh-mb-house">' + esc(h.name) + '</span>' +
				'<span class="' + extrasCls + '">' + esc(extrasText) + '</span>' +
			'</div>' +
			'<div class="mh-sh-mb-row">' +
				'<div class="mh-sh-mb-price">' +
					(save > 0.005 ? '<span class="mh-sh-mb-uvp">' + fmtPrice(uvpTotal) + '</span>' : '') +
					'<span class="mh-sh-mb-now">' + fmtPrice(finalTotal) + '</span>' +
				'</div>' +
			'</div>';
		// Variante E (v5.41.0): natives Teilen im mobilen CTA — Icon links neben dem
		// Kauf-Button. navigator.share öffnet das System-Menü (WhatsApp/Mail/…), sonst
		// Fallback aufs Kopieren mit kurzem Häkchen-Feedback.
		var share = el('button', 'mh-sh-mb-share'); share.type = 'button';
		share.setAttribute('aria-label', I18N.shareConfig || 'Konfiguration teilen');
		share.innerHTML = SHARE_ICON;
		share.addEventListener('click', function () { triggerNativeShare(share); });
		bar.querySelector('.mh-sh-mb-row').appendChild(share);

		var btn = el('button', 'mh-sh-mb-btn'); btn.type = 'button'; btn.innerHTML = (I18N.addToCart || 'In den Warenkorb');
		btn.addEventListener('click', function () { addToCart(btn, null); });
		bar.querySelector('.mh-sh-mb-row').appendChild(btn);
	}

	/* ── Produktinfo-Tabs (vollbreit unter dem Grid, Stil aus dem Spielturm) ─
	   Lazy-Load der schweren Felder über den bestehenden mh_stv_sh_detail-
	   Endpoint. Ein Fetch je Haus (gecacht); beim Rendern der Tabs ausgelöst,
	   damit das Standard-Tab „Beschreibung" sofort Inhalt zeigt.
	   Panels ohne Inhalt werden inkl. Tab-Button ausgeblendet. */
	var detailCache = {};

	function accRowsTable(rows) {
		if (!rows.length) { return ''; }
		var html = '<table class="mh-sh-acc-table">';
		for (var i = 0; i < rows.length; i++) {
			// rows[i] = [key, value] (value wird escaped) ODER [key, value, true] (value = fertiges HTML).
			var val = (rows[i][2] === true) ? rows[i][1] : esc(rows[i][1]);
			html += '<tr><td class="mh-sh-acc-k">' + esc(rows[i][0]) + '</td><td class="mh-sh-acc-v">' + val + '</td></tr>';
		}
		return html + '</table>';
	}

	/** Body-HTML pro Panel-Key aus dem geladenen Detail. Leerer String → Panel raus. */
	function accBody(key, d) {
		if (key === 'description') {
			return (d.description && String(d.description).trim()) ? '<div class="mh-sh-acc-rich">' + d.description + '</div>' : '';
		}
		if (key === 'shipping') {
			var html = '';
			var srows = [];
			// d.shipping = { label, url } (v5.17.0). Älteres String-Format defensiv mitnehmen.
			var ship = d.shipping;
			var shipLabel = ship && typeof ship === 'object' ? ship.label : ship;
			var shipUrl   = ship && typeof ship === 'object' ? ship.url : '';
			if (shipLabel) {
				var shipVal = shipUrl
					? '<a class="mh-sh-acc-link" href="' + esc(shipUrl) + '">' + esc(shipLabel) + '</a>'
					: esc(shipLabel);
				srows.push([I18N.accShipMethod || 'Versandart', shipVal, true]);
			}
			html += accRowsTable(srows);
			var dls = d.downloads || [];
			for (var j = 0; j < dls.length; j++) {
				html += '<a class="mh-sh-acc-dl" href="' + dls[j].url + '" target="_blank" rel="noopener">' +
					'<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12"/><polyline points="7 11 12 16 17 11"/><path d="M5 20h14"/></svg>' +
					esc(dls[j].name) + '</a>';
			}
			return html;
		}
		if (key === 'manufacturer') {
			var mrows = [];
			var lines = String(d.manufacturer || '').split('\n');
			for (var k = 0; k < lines.length; k++) {
				var ln = lines[k].trim();
				if (!ln) { continue; }
				var idx = ln.indexOf(':');
				if (idx > 0) { mrows.push([ln.slice(0, idx).trim(), ln.slice(idx + 1).trim()]); }
				else { mrows.push(['', ln]); }
			}
			if (d.sku) { mrows.push([I18N.accSku || 'Artikelnummer', d.sku]); }
			if (d.categories && d.categories.length) {
				// d.categories = [{name, url}] (v5.17.0). Älteres String-Array defensiv mitnehmen.
				var parts = [];
				for (var c = 0; c < d.categories.length; c++) {
					var cat = d.categories[c];
					var cname = (cat && typeof cat === 'object') ? cat.name : cat;
					var curl  = (cat && typeof cat === 'object') ? cat.url : '';
					parts.push(curl
						? '<a class="mh-sh-acc-link" href="' + esc(curl) + '">' + esc(cname) + '</a>'
						: esc(cname));
				}
				mrows.push([I18N.accCategory || 'Kategorie', parts.join(', '), true]);
			}
			return accRowsTable(mrows);
		}
		return '';
	}

	// In-flight-Dedup: mehrere Aufrufer (Galerie-Prewarm + Info-Tabs) teilen sich
	// EINEN Request pro Haus statt zwei parallele abzufeuern.
	var detailPending = {};

	function loadDetail(houseId, cb) {
		cb = cb || function () {};
		if (detailCache[houseId]) { cb(detailCache[houseId]); return; }          // Erfolg ist gecacht → synchron
		if (detailPending[houseId]) { detailPending[houseId].push(cb); return; } // läuft schon → nur anhängen

		var url = mhShData.ajaxUrl || '';
		var nonce = mhShData.detailNonce || '';
		if (!url || !nonce) {
			if (window.console && console.warn) { console.warn('[mh-sh] Detail-Load: ajaxUrl/detailNonce fehlt im Localize.'); }
			cb(null); return;
		}

		detailPending[houseId] = [cb];

		function deliver(d) {
			var cbs = detailPending[houseId] || [cb];
			delete detailPending[houseId];
			// Callbacks ASYNCHRON ausliefern: so wird ein Fehler IM Callback nicht
			// vom fetch-.catch verschluckt und fälschlich als Ladefehler (null)
			// gemeldet — der frühere Hauptgrund für „lädt nicht" beim 2./3. Haus.
			for (var i = 0; i < cbs.length; i++) {
				(function (fn) { setTimeout(function () { fn(d); }, 0); })(cbs[i]);
			}
		}

		var body = new FormData();
		body.append('action', 'mh_stv_sh_detail');
		body.append('nonce', nonce);
		body.append('product_id', houseId);

		var httpStatus = 0;
		fetch(url, { method: 'POST', body: body, credentials: 'same-origin' })
			.then(function (res) { httpStatus = res.status; return res.text(); })
			.then(function (text) {
				var json = null;
				try {
					json = JSON.parse(text);
				} catch (e) {
					// Keine gültige JSON-Antwort → meist abgelaufener Nonce (Body „-1"/„0")
					// oder ein PHP-Fehler/Notice VOR der JSON-Ausgabe (z. B. the_content
					// auf Page-Builder-Inhalt). Roh-Antwort zur Diagnose loggen.
					if (window.console && console.warn) {
						console.warn('[mh-sh] Detail-Antwort ist kein JSON (HTTP ' + httpStatus + ') für Haus ' + houseId +
							' — Nonce abgelaufen oder PHP-Fehler? Antwort-Anfang: ' + String(text).slice(0, 300));
					}
					deliver(null); return;
				}
				var d = (json && json.success) ? (json.data || {}) : null;
				if (!d) {
					var m = (json && json.data && json.data.message) ? json.data.message : '';
					if (window.console && console.warn) {
						console.warn('[mh-sh] Detail-Load fehlgeschlagen (HTTP ' + httpStatus + ') für Haus ' + houseId +
							(m ? ' — „' + m + '"' : '') + '. Ist das Produkt veröffentlicht und auffindbar (wc_get_product)?');
					}
				} else {
					detailCache[houseId] = d;   // NUR Erfolg cachen → Fehler bleibt retry-bar
				}
				deliver(d);
			})
			.catch(function (err) {
				if (window.console && console.warn) { console.warn('[mh-sh] Detail-Netzwerkfehler für Haus ' + houseId + ':', err); }
				deliver(null);
			});
	}

	/**
	 * Produktinfo als TABS (v5.24.0) — Stil aus dem Spielturm (.mh-stv-tab-*),
	 * hier mit den 3 vorhandenen Spielhaus-Inhalten (Beschreibung / Montage &
	 * Lieferung / Herstellerinfos) und EINEM Lazy-Load (loadDetail liefert alle
	 * Felder auf einmal → alle Panels werden aus einem AJAX-Call befüllt). Leere
	 * Panels (kein Inhalt) werden inkl. Tab-Button ausgeblendet; ist das Standard-
	 * Tab leer, wird das erste nicht-leere aktiviert. Body-HTML = accBody() (gleiche
	 * .mh-sh-acc-* Inhaltsklassen wie zuvor, daher unverändert wiederverwendet).
	 */
	function renderInfoTabs(house) {
		var wrap = el('div', 'mh-sh-tabs');
		wrap.setAttribute('data-role', 'info-tabs');

		var order = ['description', 'shipping', 'manufacturer'];
		var labels = {
			description:  I18N.accDescription  || 'Produktbeschreibung',
			shipping:     I18N.accShipping     || 'Montage &amp; Lieferung',
			manufacturer: I18N.accManufacturer || 'Herstellerinformationen'
		};

		var nav = el('div', 'mh-sh-tab-nav');
		nav.setAttribute('role', 'tablist');
		nav.setAttribute('aria-label', I18N.infoTabsLabel || 'Produktinformationen');

		var btns = {};
		var panels = {};

		for (var i = 0; i < order.length; i++) {
			(function (key, idx) {
				var active = idx === 0;

				var btn = el('button', 'mh-sh-tab-btn' + (active ? ' is-active' : ''));
				btn.type = 'button';
				btn.setAttribute('role', 'tab');
				btn.setAttribute('data-tab', key);
				btn.id = 'mh-sh-tab-' + key;
				btn.setAttribute('aria-controls', 'mh-sh-panel-' + key);
				btn.setAttribute('aria-selected', active ? 'true' : 'false');
				btn.setAttribute('tabindex', active ? '0' : '-1');
				btn.innerHTML = labels[key];               // Labels sind vertrauenswürdige Localize-Strings (z. B. „&amp;")
				btn.addEventListener('click', function () { userSwitch(key); });
				btn.addEventListener('keydown', tabKeyHandler);
				nav.appendChild(btn);
				btns[key] = btn;

				var panel = el('div', 'mh-sh-tab-panel' + (active ? ' is-active' : ''));
				panel.setAttribute('role', 'tabpanel');
				panel.id = 'mh-sh-panel-' + key;
				panel.setAttribute('aria-labelledby', 'mh-sh-tab-' + key);
				panel.setAttribute('data-panel', key);
				panel.setAttribute('tabindex', '0');
				panels[key] = panel;
			})(order[i], i);
		}

		wrap.appendChild(nav);
		for (var p = 0; p < order.length; p++) { wrap.appendChild(panels[order[p]]); }

		function visibleKeys() {
			var vis = [];
			for (var k = 0; k < order.length; k++) {
				if (btns[order[k]].getAttribute('aria-hidden') !== 'true') { vis.push(order[k]); }
			}
			return vis;
		}
		function activeKey() {
			for (var k = 0; k < order.length; k++) {
				if (btns[order[k]].classList.contains('is-active')) { return order[k]; }
			}
			return null;
		}
		function switchTab(key) {
			if (!btns[key] || btns[key].getAttribute('aria-hidden') === 'true') { return; }
			for (var k = 0; k < order.length; k++) {
				var ok = order[k];
				var on = ok === key;
				btns[ok].classList.toggle('is-active', on);
				btns[ok].setAttribute('aria-selected', on ? 'true' : 'false');
				btns[ok].setAttribute('tabindex', on ? '0' : '-1');
				panels[ok].classList.toggle('is-active', on);
			}
		}

		// „open"-Event nur bei aktiver Nutzer-Interaktion feuern (gleiche Semantik
		// wie das frühere Akkordeon: ein Signal pro echtem Info-Öffnen).
		var userTracked = false;
		function userSwitch(key) {
			if (!userTracked) { userTracked = true; track('stv_sh_info_open', house.id); }
			switchTab(key);
		}

		function tabKeyHandler(e) {
			var code = e.keyCode || e.which;
			if (code !== 37 && code !== 39) { return; }   // ← / →
			e.preventDefault();
			var vis = visibleKeys();
			if (vis.length < 2) { return; }
			var cur = activeKey(), pos = -1;
			for (var v = 0; v < vis.length; v++) { if (vis[v] === cur) { pos = v; break; } }
			if (pos < 0) { return; }
			var next = code === 39 ? (pos + 1) % vis.length : (pos - 1 + vis.length) % vis.length;
			userSwitch(vis[next]);
			btns[vis[next]].focus();
		}

		function hideTab(key) {
			btns[key].style.display = 'none';
			btns[key].setAttribute('aria-hidden', 'true');
			btns[key].setAttribute('tabindex', '-1');
			panels[key].style.display = 'none';
			panels[key].classList.remove('is-active');
		}

		var SKELETON = '<div class="mh-sh-tab-skeleton"><span></span><span></span><span></span></div>';

		function showTab(key) {
			btns[key].style.display = '';
			btns[key].removeAttribute('aria-hidden');
			panels[key].style.display = '';
		}

		// Befüllt alle Panels aus EINEM Detail-Objekt. d === null ⇒ LADEFEHLER
		// (Netzwerk/Nonce/Server) — bewusst getrennt vom Fall „geladen, aber leer".
		function fillTabs(d) {
			if (!d) { showLoadError(); return; }

			var firstVisible = null, activeStillVisible = false, anyContent = false;
			for (var t = 0; t < order.length; t++) {
				var key = order[t];
				var html = accBody(key, d);
				if (!html) { hideTab(key); continue; }
				anyContent = true;
				showTab(key);
				panels[key].innerHTML = html;
				if (!firstVisible) { firstVisible = key; }
				if (btns[key].classList.contains('is-active')) { activeStillVisible = true; }
			}

			if (!anyContent) {
				// Detail gültig, aber keine Inhalte gepflegt → Hinweis im Beschreibung-
				// Panel (Tab sichtbar lassen, nicht alles ausblenden = kein Skeleton-Hänger).
				showTab('description');
				panels.description.innerHTML = '<p class="mh-sh-tab-empty">' + (I18N.infoNone || 'Keine Produktinformationen vorhanden.') + '</p>';
				switchTab('description');
				return;
			}
			// War das Standard-Tab leer → erstes nicht-leeres Tab aktivieren.
			if (!activeStillVisible && firstVisible) { switchTab(firstVisible); }
		}

		// Ladefehler: NICHT fälschlich „leer" behaupten, sondern Hinweis + Retry.
		function showLoadError() {
			showTab('description');
			switchTab('description');
			var p = panels.description;
			p.innerHTML = '';
			var msg = el('p', 'mh-sh-tab-empty');
			msg.textContent = I18N.infoError || 'Produktinformationen konnten nicht geladen werden.';
			var btn = el('button', 'mh-sh-tab-retry');
			btn.type = 'button';
			btn.textContent = I18N.retry || 'Erneut versuchen';
			btn.addEventListener('click', loadInfo);
			p.appendChild(msg);
			p.appendChild(btn);
		}

		function loadInfo() {
			panels.description.innerHTML = SKELETON;
			loadDetail(house.id, fillTabs);   // fehlgeschlagene Loads werden nicht gecacht → echter Retry
		}

		loadInfo();

		return wrap;
	}

	/* ── Trust-Signale (3 USPs aus den gemeinsamen Settings trust1/2/3) ──────
	   Seit v5.23.0 im vollbreiten Infobereich (.mh-sh-below) statt in der
	   rechten Spalte. Wird nur gerendert, wenn mindestens ein Text gepflegt
	   ist (sonst übernimmt die eingebundene Seiten-Trust-Leiste, s.
	   renderBelowEmbeds). */
	function renderTrust(col) {
		var trustSvgs = [
			'<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>',
			'<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>',
			'<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>'
		];
		var trustTexts = [I18N.trust1 || '', I18N.trust2 || '', I18N.trust3 || ''];
		if (!(trustTexts[0] || trustTexts[1] || trustTexts[2])) { return; }
		var trustRow = el('div', 'mh-sh-trust');
		for (var ti = 0; ti < 3; ti++) {
			if (!trustTexts[ti]) { continue; }
			var item = el('div', 'mh-sh-trust-item');
			var tpl = document.createElement('template');
			tpl.innerHTML = trustSvgs[ti];
			if (tpl.content.firstChild) { item.appendChild(tpl.content.firstChild); }
			var txt = el('span'); txt.textContent = trustTexts[ti];
			item.appendChild(txt);
			trustRow.appendChild(item);
		}
		col.appendChild(trustRow);
	}

	/* Einmal aus der Seite „gepflückte" Embeds (Klarna, Lager-/Versand-Panel,
	   Kundenprojekte, Zahlungs-Icons …) bleiben über Modellwechsel hinweg
	   erhalten: render() leert ROOT per innerHTML='' → das Element wird detached,
	   die JS-Referenz hier hält es aber am Leben. Beim nächsten Capture wird
	   DASSELBE Element in den neuen Slot zurückgehängt (kein erneutes
	   querySelector → robust auch bei kontextabhängigen Selektoren, und das
	   Element kann nach dem Verschieben in den Konfigurator gar nicht mehr an
	   seiner ursprünglichen Stelle gefunden werden). Key = Selektor-String. */
	var capturedEmbeds = {};

	/* ── Zahlungsarten-Icons (paymentSelector → in die rechte Spalte) ────────
	   Element wird per Selektor aus der Seite VERSCHOBEN; gleicher „deferred
	   capture" mit 5 Retries wie im Spielturm (Oxygen rendert teils verzögert).
	   Bleibt bewusst in der rechten Spalte, unter der Summe (wie Spielturm). */
	function renderPayment(col) {
		var PAY_SEL = (mhShData.paymentSelector || '').trim();
		if (!PAY_SEL) { return; }
		var slot = el('div', 'mh-sh-payment-embed');
		col.appendChild(slot);

		var capture = function (attempt) {
			// Slot eines alten Renders (ROOT inzwischen neu gebaut) → ignorieren.
			if (document.body && !document.body.contains(slot)) { return; }
			if (slot.children.length > 0) { return; }
			var keep = capturedEmbeds[PAY_SEL];
			if (keep) { slot.appendChild(keep); return; } // dasselbe Element zurückhängen
			var found = false;
			try {
				var payEl = document.querySelector(PAY_SEL);
				if (payEl) {
					payEl.parentNode.removeChild(payEl);
					slot.appendChild(payEl);
					capturedEmbeds[PAY_SEL] = payEl;
					found = true;
				}
			} catch (e) { found = true; }
			if (!found && attempt < 5) {
				var delays = [50, 200, 500, 1000, 2000];
				setTimeout(function () { capture(attempt + 1); }, delays[attempt]);
			}
		};
		setTimeout(function () { capture(0); }, 0);
	}

	/* ── Seiten-Embeds per Selektor in den Konfigurator holen. Spiegelt die
	   Spielturm-Logik: gewählte Seitenelemente werden in eigene Slots
	   VERSCHOBEN. Slots werden SYNCHRON in fester Reihenfolge erzeugt, der
	   Inhalt asynchron eingehängt (Oxygen rendert teils verzögert → bis zu 5
	   Retries). Leere Slots blendet das CSS aus.
	     below=true  → vollbreiter Infobereich unter dem Grid (belowSelectors /
	                   geteiltes Setting below_grid_selectors: Klarna, Trust …).
	     below=false → RECHTE Spalte (embedSelectors / geteiltes Setting
	                   embed_selectors: Verfügbarkeit/Versand, Kundenprojekte …).
	   Selektoren je Zeile oder Komma getrennt. ── */
	function renderEmbeds(col, raw, prefix, below) {
		raw = ('' + (raw || '')).replace(/^\s+|\s+$/g, '');
		if (!raw) { return; }
		var parts = raw.split(/[\n,]+/);
		var sels = [];
		for (var i = 0; i < parts.length; i++) {
			var s = parts[i].replace(/^\s+|\s+$/g, '');
			if (s) { sels.push(s); }
		}
		if (!sels.length) { return; }

		var slots = [];
		for (var k = 0; k < sels.length; k++) {
			var slot = el('div', 'mh-sh-embed-item' + (below ? ' mh-sh-embed-below' : ''));
			slot.setAttribute('data-mh-sh-embed', prefix + k);
			col.appendChild(slot);
			slots.push({ sel: sels[k], slot: slot });
		}

		var capture = function (attempt) {
			var pending = 0;
			for (var j = 0; j < slots.length; j++) {
				var entry = slots[j];
				// Slot eines alten Renders (ROOT inzwischen neu gebaut) → ignorieren,
				// damit ein verspäteter Retry das Element nicht in einen detachten
				// Slot zurückzieht (würde es wieder „verschwinden" lassen).
				if (document.body && !document.body.contains(entry.slot)) { continue; }
				if (entry.slot.children.length > 0) { continue; }
				var keep = capturedEmbeds[entry.sel];
				if (keep) {
					entry.slot.appendChild(keep); // dasselbe Element zurückhängen (überlebt Modellwechsel)
					continue;
				}
				try {
					var elFound = document.querySelector(entry.sel);
					if (elFound) {
						elFound.parentNode.removeChild(elFound);
						entry.slot.appendChild(elFound);
						capturedEmbeds[entry.sel] = elFound;
					} else {
						pending++;
					}
				} catch (e) { /* ungültiger Selektor → Slot bleibt leer/ausgeblendet */ }
			}
			if (pending > 0 && attempt < 5) {
				var delays = [50, 200, 500, 1000, 2000];
				setTimeout(function () { capture(attempt + 1); }, delays[attempt]);
			}
		};
		setTimeout(function () { capture(0); }, 0);
	}
	function renderBelowEmbeds(col) { renderEmbeds(col, mhShData.belowSelectors, 'b', true); }
	function renderRightEmbeds(col) { renderEmbeds(col, mhShData.embedSelectors, 'r', false); }

	/* ── Voll-Render ── */
	function render() {
		var house = currentHouse();
		ROOT.innerHTML = '';

		// Modell-Selector wird ab v5.33.2 NICHT mehr über dem Grid angehängt,
		// sondern weiter unten als erstes Element im vollbreiten „below"-Bereich
		// (direkt unter den beiden Spalten). Hier nur bauen, noch nicht einhängen.
		var selector = renderHouseSelector();

		var grid = el('div', 'mh-sh-grid mh-sh-layout-' + (house.layout || 'standard'));

		var left = el('div', 'mh-sh-left');
		left.appendChild(renderGallery(house));
		grid.appendChild(left);

		var right = el('div', 'mh-sh-right');

		var htitle = el('div', 'mh-sh-house-title');
		htitle.innerHTML = '<h2>' + esc(house.name) + '</h2>';
		right.appendChild(htitle);

		var onSale = house.on_sale && house.regular_price > house.price + 0.001;
		var pblock = el('div', 'mh-sh-priceblock');
		pblock.innerHTML = '<span class="mh-sh-p-now">' + fmtPrice(house.price) + '</span>' +
			(onSale
				? '<span class="mh-sh-p-uvp">' + fmtPrice(house.regular_price) + '</span>' +
				  '<span class="mh-sh-p-pct">\u2212' + pctOff(house.regular_price, house.price) + '%</span>'
				: '');
		right.appendChild(pblock);

		if (onSale) {
			var pSave = el('div', 'mh-sh-p-saveline');
			pSave.innerHTML = fmtPrice(house.regular_price - house.price) +
				' ' + (I18N.saved || 'gespart');
			right.appendChild(pSave);
		}

		// Variante A (v5.41.0): dezenter „Konfiguration teilen"-Button direkt unter dem Preis.
		right.appendChild(buildShareButton());

		if (house.short_description) {
			var desc = el('div', 'mh-sh-house-desc'); desc.innerHTML = house.short_description;
			normalizeChecklist(desc);
			right.appendChild(desc);
		}

		var step2 = el('div', 'mh-sh-sectionhead');
		step2.innerHTML = '<span class="mh-sh-step-h">' + (I18N.configure || 'Zubeh\u00f6r konfigurieren') + '</span>' +
			(I18N.configureHint ? '<span class="mh-sh-step-hint mh-sh-step-hint--block">' + I18N.configureHint + '</span>' : '');
		right.appendChild(step2);

		right.appendChild(renderAddons(house));
		right.appendChild(renderSummary());
		renderPayment(right);
		renderRightEmbeds(right);      // embed_selectors (Verfügbarkeit/Versand, Kundenprojekte) — geteilte Settings
		grid.appendChild(right);

		ROOT.appendChild(grid);

		// ── Vollbreiter Infobereich UNTER dem Grid (wie Spielturm): USPs →
		//    eingebundene Seitenelemente (Klarna etc.) → Produktinfo-Tabs.
		//    Liegt bewusst AUSSERHALB des Grids, damit sich die sticky linke
		//    Galerie genau hier sauber „loslöst" und der Infobereich volle Breite
		//    bekommt. ──
		var below = el('div', 'mh-sh-below');
		if (selector) { below.appendChild(selector); } // „Modell wählen" — direkt unter den beiden Spalten
		renderTrust(below);            // 3 USP-Signale (trust_1/2/3)
		renderBelowEmbeds(below);      // below_grid_selectors (Klarna …) — geteilte Settings
		below.appendChild(renderInfoTabs(house));
		ROOT.appendChild(below);

		ROOT.appendChild(renderMobileBar());
	}

	/**
	 * Vereinheitlicht die „Überblick"-Liste: entfernt ein evtl. im Inhalt
	 * eingebackenes führendes Häkchen (✓ ✔ ✅ ☑) je Listenpunkt — egal ob als
	 * Textzeichen oder als gekapseltes Icon-Element. Das eigentliche Häkchen
	 * setzt danach das CSS (.mh-sh-house-desc li::before), sodass ALLE Häuser
	 * identisch mit Häkchen rendern (statt mal Häkchen, mal Bulletpoints —
	 * abhängig davon, ob im Produkttext schon ein ✓ stand).
	 */
	function normalizeChecklist(root) {
		var rxLead = /^\s*[\u2713\u2714\u2705\u2611\uFE0F]+\s*/;
		var rxOnly = /^[\u2713\u2714\u2705\u2611\uFE0F\s]+$/;
		var lis = root.querySelectorAll('li');
		for (var i = 0; i < lis.length; i++) {
			var li = lis[i];
			var node = li.firstChild;
			while (node && node.nodeType === 3 && !node.nodeValue.replace(/\s/g, '').length) { node = node.nextSibling; }
			if (node && node.nodeType === 3) {
				node.nodeValue = node.nodeValue.replace(rxLead, '');
			} else if (node && node.nodeType === 1 && rxOnly.test(node.textContent || '')) {
				li.removeChild(node);
			}
		}
	}

	function esc(s) {
		return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
		});
	}

	/* ── Init ── */
	initSelections(currentHouse());
	// Geteilte Auswahl aus der URL (v5.40.0): nur beim Laden, nur fürs Anker-Haus.
	// state.houseId ist hier bereits server-seitig aus ?mh_haus aufgelöst (v5.25.0).
	var cfgRaw = getQueryParam(CFG_PARAM);
	if (cfgRaw !== null) { applyUrlConfig(currentHouse(), parseConfig(cfgRaw)); }
	render();
	track('stv_sh_view', state.houseId);
})();
