/**
 * MH Spielturm Vergleich v5.7.0 — 2-Column Configurator
 *
 * ES5 compatible. Renders into #mh-stv-configurator.
 * Reads mhStvData from wp_localize_script.
 *
 * v5.7.0 changes:
 * - Features-Matrix: features_matrix[serie][level] replaces global features_map
 *   Each serie can now have different features per level (e.g. Klassisch always
 *   has Kletterseil, Pirato only at full level)
 * - Included features section reads from features_matrix[state.serie][levelKey()]
 * - Comparison table builds per-serie feature rows from features_matrix
 * - Comparison table now re-renders correctly when serie changes (was already the case)
 *
 * v5.6.0 changes:
 * - REMOVED: "Empfohlenes Zubehör ansehen" button from main CTA area
 *   Button was redundant with sticky bar BT pill — keeps only the sticky CTA version
 *   refs.accessoryLink removed, all show/hide logic cleaned up
 * - ADMIN: Series and Levels are now fully editable in admin settings
 *   Key, Label, Icon, Features/Extras all have visible input fields
 *   Add/remove rows via JavaScript (admin.js)
 *
 * v5.5.0 changes:
 * - REMOVED: "Ihre Konfiguration" summary bar — redundant with Inklusive section
 *   (DOM, update logic, CSS, mobile order, and i18n key all cleaned up)
 * - UX: 360° thumbnail now appears as second thumb (after hero) instead of last
 *   Active-class toggle uses regularIdx counter to skip 360° button in index matching
 * - UX: Hero gallery swipe is now instant (no .25s slide transition on commit/snap-back)
 * - UX: 360° slide added to lightbox gallery at index 1 (after hero image)
 *   Shows static preview frame + "360° Ansicht starten" overlay button
 *   Click activates MH360 spinner in-place (nav arrows + thumbs remain visible)
 *   Swipe disabled while spinner active, navigating away deactivates spinner
 *   Lightbox thumbstrip shows 360° badge on the corresponding thumbnail
 * - Lightbox gallery index mapping adjusted for injected 360° entry
 *
 * v5.4.1 changes:
 * - FIX: Sticky CTA qty +/- now respects stock-based max quantity (was unlimited)
 * - FIX: Tab cache premature return — attributes/productdata panels no longer
 *   block reviews panel from rendering on first load
 * - A11Y: aria-live region on price block for screen reader announcements
 * - A11Y: Visually-hidden sr-announce region announces product+price on config switch
 * - UX: Hero image object-fit changed to contain (no more cropping product photos)
 * - UX: BT widget shows loading skeleton during AJAX refresh (was empty gap)
 * - REFACTOR: levelKey()/applyLevel() now derive level from D.levels array order
 *   instead of hardcoded 'basic'/'swing'/'full' key checks → extensible for new levels
 * - REFACTOR: getQtyMax() shared helper replaces duplicated stock-qty logic
 * - CSS: Sticky CTA uses CSS custom properties (--_bg, --_border, --_text, --_muted)
 *   instead of 17 hardcoded hex values
 * - CSS: Added missing mhStvPulse keyframe (was referenced by PHP skeleton but never defined)
 * - PERF: will-change:transform only applied during active drag (.is-dragging),
 *   removed permanent compositor layer from hero+peek images
 * - PERF: Static in-memory cache for mh_stv_groups option in data provider
 *
 * v5.4.0 changes:
 * - SEC: sanitizeHTML() helper strips scripts/event-handlers before innerHTML
 *   → Applied to prod.description, prod.short_description, BT AJAX HTML, product meta HTML
 * - SEC: CSS-Injection whitelist regex on hide_selector (PHP)
 * - SEC: Rate-limiting on AJAX add-to-cart (12 req/min, PHP transient)
 * - A11Y: prefers-reduced-motion respected — REDUCED_MOTION flag skips animatePrice()
 *   → CSS @media rule disables all keyframe animations + transitions
 * - A11Y: Tab panels now have tabindex="0" for screen reader focus (ARIA tabs pattern)
 * - A11Y: Sticky CTA bar safe-area-inset-bottom for iPhone gesture bar
 * - UX: Undo after add-to-cart — "Rückgängig" link removes last-added item via AJAX
 * - UX: Quantity max enforced from stock_quantity (PHP + JS, fallback 99)
 * - UX: Lightbox swipe-down-to-close on mobile (vertical dismiss gesture)
 * - UX: Comparison table upgrade CTA — "Auswählen" button per level column
 *   → Mobile-responsive: buttons wrap + shrink at 1024px/480px breakpoints
 * - A11Y: Muted text contrast improved (#767676 → #636360, ~6:1 ratio)
 * - PERF: Unified image preload cache (removed duplicate lbPreloadCache)
 * - PERF: Reduced 15× setTimeout cascades for BT pill to single calls
 * - PERF: overflow:clip with overflow:hidden fallback for Safari <16
 * - PERF: Lightbox DOM prebuilt via requestIdleCallback (eliminates first-open jank)
 * - NOTE: ES5→ES6 migration deferred (3500 lines, tracked for v5.5.0)
 *
 * v5.2.0 changes:
 * - PERF: Complete gallery image architecture rewrite
 *   → Single hero <img> + peek <img> instead of N stacked images per product
 *   → In-memory Image cache (preloadImage/isImageCached) replaces DOM preloading
 *   → Eliminates 30-50 hidden full-size <img> elements from DOM
 *   → Thumbnail click is now instant when cached, shows loading pulse otherwise
 * - PERF: Smart progressive preloading
 *   → Current product gallery preloaded immediately
 *   → Adjacent images (prev/next) preloaded eagerly
 *   → Other products' galleries preloaded lazily on idle, current serie first
 * - FIX: 360° fullscreen now works on mobile
 *   → Expand button z-index + size increased for touch targets
 *   → Double-tap on 360° canvas opens fullscreen
 *   → Gallery overlay no longer clips expand button
 * - UX: Gallery loading indicator (pulse animation during image load)
 * - UX: Mobile thumbnails increased to 60px (was 52px) for better touch targets
 * - UX: Sticky CTA bar more compact on mobile (2 rows max)
 * - CSS: will-change removed from non-active gallery images (saves compositing layers)
 *
 * v5.1.0 changes:
 * - Sticky CTA bar now works on BOTH mobile and desktop
 *   → Desktop: top-fixed bar with slide-down animation (Amazon-style)
 *   → Mobile: bottom-fixed bar (unchanged position)
 * - Sticky bar now shows: product thumbnail (48px), product name, price,
 *   bought-together pill summary, qty selector, and add-to-cart button
 * - BT pill reads checked product names from .mh-bt-widget,
 *   click scrolls smoothly to bought-together section
 * - Desktop sticky bar max-width: 1200px, centered
 * - Responsive layout adapts via matchMedia listener
 *
 * v5.0.0 changes:
 * - 360° Image Sequence Spinner integration
 *   → 360° thumbnail appended to gallery thumbstrip when product has spin_frames
 *   → activate360() creates MH360 spinner overlay inside gallery hero
 *   → deactivate360() returns to normal gallery on regular thumb click
 *   → 360° lightbox mode (open360Lightbox + closeLightbox360Cleanup)
 *   → Gallery swipe + keyboard guards when spinner is active
 *   → Spinner auto-destroyed on product switch
 * - New state: state.is360 (boolean)
 * - Depends on spielturm-360.js (MH360 global)
 *
 * v4.9.0 changes:
 * - Lightbox: much bigger image (94vh vs 84vh), thumbnails as overlay at bottom
 * - Lightbox: drag-to-swipe with visual slide animation (finger-follow + peek image)
 * - Lightbox: slide transitions for prev/next navigation (replaces opacity fade)
 * - Lightbox: double-tap to zoom on mobile
 * - Lightbox: preload adjacent images for instant transitions
 * - Lightbox: counter repositioned as top-center overlay pill
 *
 * v4.8.0 changes:
 * - Fix: Bought-together quantity disappearing after variant switch
 *   <script> tags injected via innerHTML don't execute — now re-created as new
 *   DOM elements so mhBtInit() fires after every AJAX refresh
 * - Hidden form.cart quantity bridge: maintains a hidden input[name="quantity"]
 *   that the mh-bought-together plugin can find for qty sync
 * - syncBoughtTogetherQty() called on every qty change and after BT refresh
 *
 * v4.7.0 changes:
 * - Lightbox pinch-to-zoom fix: touch-action:none added to CSS (JS was already complete)
 * - Lightbox styles extracted to spielturm-lightbox.css for code organization
 * - Comparison table + design card + toggle prices use tabular-nums
 * - Comparison table active column gets stronger visual highlight
 *
 * v4.6.0 changes:
 * - AJAX bought-together refresh: widget updates when product config changes
 * - JS-based mobile DOM reorder replaces fragile display:contents approach
 *   Elements are physically moved in the DOM on breakpoint change (matchMedia)
 *   → robuster against Oxygen Builder wrappers and async embeds
 *
 * v4.5.3 changes:
 * - Stock badge conditionally rendered via SHOW_STOCK flag (admin toggle)
 * - CSS-only: Variant A "clean dividers" layout, left-aligned right column
 *
 * v4.5.0 changes:
 * - Embed mh-bought-together shortcode in right column (after summary bar)
 *   PHP renders [mh_bought_together] into hidden source div, JS moves it into layout
 *
 * v4.4.1 changes:
 * - Mobile reorder (CSS-only via display:contents, no JS changes)
 *
 * v4.4.0 changes:
 * - Mobile clipping fix (CSS-only, no JS changes needed)
 *
 * v4.3 changes:
 * - Mobile column reorder (right col first on mobile)
 * - Sticky CTA bar with IntersectionObserver
 * - Action block visual grouping (price→stock→included→CTA)
 * - Comparison table v2 with row headers
 * - Touch swipe on hero gallery
 * - innerHTML → DOM removal for perf/security
 * - Thumbnail DOM reuse (only rebuild on product change)
 * - Hover states wrapped in @media (hover: hover)
 */
(function() {
	'use strict';

	if (typeof mhStvData === 'undefined') return;
	if (!mhStvData.comparison) return;

	var D    = mhStvData.comparison;
	var I18N = mhStvData.i18n || {};
	var PF   = mhStvData.priceFormat || {};
	var ROOT = document.getElementById('mh-stv-configurator');
	if (!ROOT) return;

	var AJAX_URL   = mhStvData.ajaxUrl || '';
	var CART_NONCE = mhStvData.cartNonce || '';
	var UNDO_NONCE = mhStvData.undoNonce || '';
	var BT_NONCE   = mhStvData.btNonce || '';
	var DETAIL_NONCE = mhStvData.detailNonce || '';
	var TRACK_NONCE  = mhStvData.trackNonce || '';
	var SHOW_STOCK = !!mhStvData.showStock;

	/* ── Analytics: batched event tracking ── */
	var trackQueue = [];
	var trackTimer = null;
	function track(name, value) {
		if (!AJAX_URL || !TRACK_NONCE) return;
		trackQueue.push({ name: name, value: value || '' });
		if (!trackTimer) {
			trackTimer = setTimeout(flushTrack, 2000);
		}
	}
	function flushTrack() {
		trackTimer = null;
		if (trackQueue.length === 0) return;
		var batch = trackQueue.splice(0, 20);
		var body = new FormData();
		body.append('action', 'mh_stv_track');
		body.append('nonce', TRACK_NONCE);
		for (var i = 0; i < batch.length; i++) {
			body.append('events[' + i + '][name]', batch[i].name);
			body.append('events[' + i + '][value]', batch[i].value);
		}
		fetch(AJAX_URL, { method: 'POST', credentials: 'same-origin', body: body }).catch(function() {});
	}
	// Flush on page leave.
	if (typeof navigator.sendBeacon === 'function') {
		window.addEventListener('pagehide', function() {
			if (trackQueue.length === 0) return;
			var body = new FormData();
			body.append('action', 'mh_stv_track');
			body.append('nonce', TRACK_NONCE);
			for (var i = 0; i < trackQueue.length; i++) {
				body.append('events[' + i + '][name]', trackQueue[i].name);
				body.append('events[' + i + '][value]', trackQueue[i].value);
			}
			navigator.sendBeacon(AJAX_URL, body);
			trackQueue = [];
		});
	}
	var STICKY_BREAKPOINT = 1024;

	/* ── Fix #4 v5.4.0: Respect prefers-reduced-motion ── */
	var REDUCED_MOTION = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	/* ── Fix #1 v5.4.0: Sanitize HTML before innerHTML insertion ── */
	function sanitizeHTML(html) {
		if (!html) return '';
		var tpl = document.createElement('template');
		tpl.innerHTML = html;
		var frag = tpl.content;
		var scripts = frag.querySelectorAll('script,iframe,object,embed,link[rel="import"]');
		for (var i = scripts.length - 1; i >= 0; i--) {
			scripts[i].parentNode.removeChild(scripts[i]);
		}
		var allEls = frag.querySelectorAll('*');
		for (var j = 0; j < allEls.length; j++) {
			var el2 = allEls[j];
			var attrs = el2.attributes;
			for (var k = attrs.length - 1; k >= 0; k--) {
				var name = attrs[k].name.toLowerCase();
				if (name.indexOf('on') === 0 || (name === 'href' && attrs[k].value.trim().toLowerCase().indexOf('javascript:') === 0)) {
					el2.removeAttribute(attrs[k].name);
				}
			}
		}
		var tmp = document.createElement('div');
		tmp.appendChild(frag);
		return tmp.innerHTML;
	}

	/* ── State ── */
	var state = {
		serie: D.current_serie || '',
		swing: (D.current_level === 'swing' || D.current_level === 'full'),
		wall:  (D.current_level === 'full'),
		galleryIdx: 0,
		is360: false,
		activeTab: 'description',
		qty: 1
	};

	var prevPrice = 0;
	var priceEl   = null;
	var animTimer = null;

	/* ── Bought-Together refresh tracker ── */
	var btProductId    = null;
	var btRefreshTimer = null;

	/* ── Mobile layout tracker (false = render() builds desktop DOM by default) ── */
	var isMobileLayout = false;

	/* ── Refs (set during render) ── */
	var refs = {};

	/* ── Image preload cache (v5.2.0 — replaces DOM stacking) ── */
	var imageCache = {}; // url → { img: Image, loaded: boolean, callbacks: [] }

	function preloadImage(url, callback) {
		if (!url) return;
		if (imageCache[url]) {
			if (imageCache[url].loaded) {
				if (callback) callback(url);
			} else if (callback) {
				imageCache[url].callbacks.push(callback);
			}
			return;
		}
		var entry = { img: new Image(), loaded: false, callbacks: callback ? [callback] : [] };
		imageCache[url] = entry;
		entry.img.onload = function() {
			entry.loaded = true;
			for (var i = 0; i < entry.callbacks.length; i++) {
				try { entry.callbacks[i](url); } catch (e) {}
			}
			entry.callbacks = [];
		};
		entry.img.onerror = function() {
			entry.loaded = true; // mark done to avoid retries
			entry.callbacks = [];
		};
		entry.img.src = url;
	}

	function isImageCached(url) {
		return url && imageCache[url] && imageCache[url].loaded;
	}

	function preloadProductGallery(prod, priority) {
		if (!prod) return;
		var gallery = prod.gallery && prod.gallery.length ? prod.gallery : (prod.image ? [prod.image] : []);
		if (priority) {
			// Current product — preload all gallery + thumbs immediately.
			var thumbs = prod.thumbnails || [];
			for (var t = 0; t < thumbs.length; t++) preloadImage(thumbs[t]);
			for (var g = 0; g < gallery.length; g++) preloadImage(gallery[g]);
		} else {
			// Non-current product — only preload hero image (index 0).
			if (gallery.length > 0) preloadImage(gallery[0]);
		}
	}

	function preloadAdjacentImages(prod) {
		if (!prod) return;
		var gallery = prod.gallery && prod.gallery.length ? prod.gallery : (prod.image ? [prod.image] : []);
		if (gallery.length < 2) return;
		var prev = (state.galleryIdx - 1 + gallery.length) % gallery.length;
		var next = (state.galleryIdx + 1) % gallery.length;
		preloadImage(gallery[prev]);
		preloadImage(gallery[next]);
	}

	/* ── #2: Lazy product detail loading (description, attributes, meta, reviews, gallery_full) ── */
	var detailLoading = {}; // product_id → true while AJAX is in flight
	var detailCallbacks = {}; // product_id → [callback, ...]

	function loadProductDetail(productId, callback) {
		// Already loaded? (description !== null means detail was merged)
		var prod = findProductById(productId);
		if (prod && prod.description !== null) {
			if (callback) callback(prod);
			return;
		}
		// Queue callback.
		if (!detailCallbacks[productId]) detailCallbacks[productId] = [];
		if (callback) detailCallbacks[productId].push(callback);
		// Already loading?
		if (detailLoading[productId]) return;
		if (!AJAX_URL || !DETAIL_NONCE) return;

		detailLoading[productId] = true;
		var body = new FormData();
		body.append('action', 'mh_stv_product_detail');
		body.append('nonce', DETAIL_NONCE);
		body.append('product_id', productId);

		fetch(AJAX_URL, { method: 'POST', credentials: 'same-origin', body: body })
			.then(function(r) { return r.json(); })
			.then(function(json) {
				detailLoading[productId] = false;
				if (json.success && json.data) {
					// Merge detail into product in D.products.
					for (var i = 0; i < D.products.length; i++) {
						if (D.products[i].id === productId) {
							D.products[i].description  = json.data.description || '';
							D.products[i].attributes   = json.data.attributes || [];
							D.products[i].product_meta = json.data.product_meta || [];
							D.products[i].reviews      = json.data.reviews || [];
							D.products[i].gallery_full = json.data.gallery_full || [];
							break;
						}
					}
				}
				// Fire callbacks.
				var cbs = detailCallbacks[productId] || [];
				detailCallbacks[productId] = [];
				var updatedProd = findProductById(productId);
				for (var c = 0; c < cbs.length; c++) cbs[c](updatedProd);
			})
			.catch(function() {
				detailLoading[productId] = false;
				// On error: set empty defaults so we don't retry endlessly.
				for (var i = 0; i < D.products.length; i++) {
					if (D.products[i].id === productId) {
						D.products[i].description  = D.products[i].description || '';
						D.products[i].attributes   = D.products[i].attributes || [];
						D.products[i].product_meta = D.products[i].product_meta || [];
						D.products[i].reviews      = D.products[i].reviews || [];
						D.products[i].gallery_full = D.products[i].gallery_full || [];
						break;
					}
				}
				var cbs2 = detailCallbacks[productId] || [];
				detailCallbacks[productId] = [];
				var fallbackProd = findProductById(productId);
				for (var c2 = 0; c2 < cbs2.length; c2++) cbs2[c2](fallbackProd);
			});
	}

	function findProductById(id) {
		for (var i = 0; i < D.products.length; i++) {
			if (D.products[i].id === id) return D.products[i];
		}
		return null;
	}

	/* ── 360° Spinner integration ── */
	var spinner360 = null;        // Current MH360 Spinner instance
	var spinner360ProductId = 0;  // Which product the spinner belongs to

	/* ── Shared AJAX add-to-cart (Fix: dedup main CTA + sticky CTA) ── */
	function addToCartAjax(productId, qty, btnEl, originalText, onSuccess) {
		if (!AJAX_URL || !CART_NONCE) {
			var p = cur();
			if (p) window.location.href = p.url + '?add-to-cart=' + productId + '&quantity=' + qty;
			return;
		}

		btnEl.classList.add('is-loading');
		btnEl.textContent = decodeHTML(I18N.adding || 'Wird hinzugef\u00fcgt\u2026');

		var body = new FormData();
		body.append('action', 'mh_stv_add_to_cart');
		body.append('nonce', CART_NONCE);
		body.append('product_id', productId);
		body.append('quantity', qty);

		fetch(AJAX_URL, { method: 'POST', body: body, credentials: 'same-origin' })
			.then(function(res) { return res.json(); })
			.then(function(json) {
				btnEl.classList.remove('is-loading');
				if (json.success) {
					btnEl.classList.add('is-success');
					btnEl.textContent = '\u2713 ' + decodeHTML(I18N.added || 'Hinzugef\u00fcgt');
					track('stv_add_to_cart', productId.toString());

					// Update WC cart counts.
					if (json.data && json.data.cart_count) {
						var cartCountEls = document.querySelectorAll('.cart-count, .wc-cart-count, .cart_contents_count');
						for (var c = 0; c < cartCountEls.length; c++) {
							cartCountEls[c].textContent = json.data.cart_count;
						}
					}

					// Trigger WC event for themes that listen.
					if (typeof jQuery !== 'undefined') {
						jQuery(document.body).trigger('added_to_cart', [json.data.fragments, json.data.cart_count]);
					}

					if (onSuccess) onSuccess(json);

					// Reset button after 3s.
					setTimeout(function() {
						btnEl.classList.remove('is-success');
						btnEl.textContent = originalText;
					}, 3000);
				} else {
					btnEl.classList.add('is-error');
					btnEl.textContent = (json.data && json.data.message) || decodeHTML(I18N.error || 'Fehler');
					setTimeout(function() {
						btnEl.classList.remove('is-error');
						btnEl.textContent = originalText;
					}, 2500);
				}
			})
			.catch(function() {
				btnEl.classList.remove('is-loading');
				var p = cur();
				if (p) window.location.href = p.url + '?add-to-cart=' + productId + '&quantity=' + qty;
			});
	}

	/* ── Tab content cache (avoids innerHTML re-rendering) ── */
	var tabCache = {};

	/* ── Helpers ── */
	/* Fix v5.4.1: Dynamic level resolution from levels array (no hardcoded keys).
	   Levels are ordered least→most equipped. Toggle states map to level index:
	   index 0 = no toggles, index 1 = swing on, index 2+ = swing+wall on. */
	function levelKey() {
		var levels = D.levels || [];
		if (state.wall && state.swing && levels.length > 2) return levels[2].key;
		if (state.swing && levels.length > 1) return levels[1].key;
		return levels.length > 0 ? levels[0].key : 'basic';
	}

	/* Inverse of levelKey(): given a target level key, set toggle states. */
	function applyLevel(targetKey) {
		var levels = D.levels || [];
		var idx = 0;
		for (var i = 0; i < levels.length; i++) {
			if (levels[i].key === targetKey) { idx = i; break; }
		}
		state.swing = idx >= 1;
		state.wall  = idx >= 2;
	}

	function findProduct(serie, level) {
		for (var i = 0; i < D.products.length; i++) {
			if (D.products[i].serie === serie && D.products[i].level === level) return D.products[i];
		}
		return null;
	}

	function cur() { return findProduct(state.serie, levelKey()); }

	/* Fix v5.4.1: Shared qty max helper (used by main CTA + sticky CTA). */
	function getQtyMax() {
		var prod = cur();
		if (prod && prod.stock_quantity && prod.stock_quantity > 0) return prod.stock_quantity;
		return 99;
	}

	function fmtPrice(p) {
		var dec  = PF.decimals !== undefined ? PF.decimals : 2;
		var dSep = PF.decimal   || ',';
		var tSep = PF.thousand  || '.';
		var sym  = PF.symbol    || '\u20AC';
		var pos  = PF.position  || 'left';

		var fixed = p.toFixed(dec);
		var parts = fixed.split('.');
		var intPart = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, tSep);
		var formatted = dec > 0 ? intPart + dSep + parts[1] : intPart;

		switch (pos) {
			case 'left':       return sym + formatted;
			case 'left_space': return sym + '\u00A0' + formatted;
			case 'right_space':return formatted + '\u00A0' + sym;
			default:           return formatted + sym;
		}
	}

	function fmtDelta(d) { return '+' + fmtPrice(d); }

	function el(tag, cls) {
		var n = document.createElement(tag);
		if (cls) n.className = cls;
		return n;
	}

	function decodeHTML(str) {
		var tmp = document.createElement('span');
		tmp.innerHTML = str;
		return tmp.textContent || tmp.innerText || str;
	}

	/* ── Bought-Together Quantity Bridge ──
	   The mh-bought-together plugin looks for $('form.cart input[name="quantity"]')
	   or $('input[name="quantity"]').first() to sync main product qty.
	   Since our configurator replaces the WC product form, we maintain a hidden
	   bridge input that the BT plugin can find. */
	var btQtyBridge = null;

	function ensureBtQtyBridge() {
		if (btQtyBridge) return btQtyBridge;
		// Create a hidden form.cart with a quantity input the BT plugin can discover.
		var form = document.querySelector('form.cart');
		if (!form) {
			form = document.createElement('form');
			form.className = 'cart';
			form.style.cssText = 'position:absolute;width:0;height:0;overflow:hidden;pointer-events:none;opacity:0';
			ROOT.appendChild(form);
		}
		var inp = form.querySelector('input[name="quantity"]');
		if (!inp) {
			inp = document.createElement('input');
			inp.type = 'hidden';
			inp.name = 'quantity';
			inp.value = '1';
			form.appendChild(inp);
		}
		btQtyBridge = inp;
		return inp;
	}

	function syncBoughtTogetherQty() {
		var bridge = ensureBtQtyBridge();
		bridge.value = String(state.qty || 1);
		// Trigger jQuery change event so mhBtInit's updateAll() picks up the new qty.
		if (typeof jQuery !== 'undefined') {
			try { jQuery(bridge).trigger('change'); } catch (e) { /* noop */ }
		}

		// v5.2.0: Also sync the BT widget's own main product qty input.
		// The BT plugin recalculates its total only when its OWN inputs change.
		if (refs.btEmbed) {
			var mainRow = refs.btEmbed.querySelector('.mh-bt-product--main');
			if (mainRow) {
				var qtyInputs = mainRow.querySelectorAll('input[type="number"], input[type="text"][inputmode="numeric"], .mh-bt-qty__input');
				for (var qi = 0; qi < qtyInputs.length; qi++) {
					var inp = qtyInputs[qi];
					if (inp.value !== String(state.qty)) {
						inp.value = String(state.qty);
						// Fire native + jQuery events to trigger BT recalculation.
						inp.dispatchEvent(new Event('input', { bubbles: true }));
						inp.dispatchEvent(new Event('change', { bubbles: true }));
						if (typeof jQuery !== 'undefined') {
							try {
								jQuery(inp).trigger('input').trigger('change');
							} catch (e) { /* noop */ }
						}
					}
				}
			}
		}
	}

	function findSerieImage(serieKey) {
		var basicP = findProduct(serieKey, 'basic');
		if (basicP && basicP.image) return basicP.image;
		for (var i = 0; i < D.products.length; i++) {
			if (D.products[i].serie === serieKey && D.products[i].image) return D.products[i].image;
		}
		return '';
	}

	/* ── Animated Price ── */
	function animatePrice(target) {
		if (animTimer) clearInterval(animTimer);
		var start = prevPrice || target;
		var diff  = target - start;
		if (diff === 0 || REDUCED_MOTION) { setPriceText(target); prevPrice = target; return; }

		var dec  = PF.decimals !== undefined ? PF.decimals : 2;
		var mult = Math.pow(10, dec);
		var steps = 12, step = 0;
		if (priceEl) priceEl.classList.add('is-flash');

		animTimer = setInterval(function() {
			step++;
			var v = Math.round((start + diff * (step / steps)) * mult) / mult;
			setPriceText(v);
			if (step >= steps) {
				clearInterval(animTimer);
				animTimer = null;
				setPriceText(target);
				if (priceEl) priceEl.classList.remove('is-flash');
			}
		}, 25);
		prevPrice = target;
	}

	function setPriceText(val) {
		if (priceEl) priceEl.textContent = fmtPrice(val);
	}

	/* ── Gallery Hero Image (v5.2.0 — single img element, src swap) ── */
	function showHeroImage(url, srcsetData, prod, animate) {
		if (!refs.heroImg || !url) return;
		var wasSame = (refs.heroImg.src === url || refs.heroImg.getAttribute('data-src') === url);
		if (wasSame && refs.heroImg.src) return;

		if (isImageCached(url)) {
			// Instant swap — image already in browser memory cache.
			refs.heroImg.src = url;
			refs.heroImg.setAttribute('data-src', url);
			if (srcsetData && srcsetData.srcset) {
				refs.heroImg.setAttribute('srcset', srcsetData.srcset);
				refs.heroImg.setAttribute('sizes', srcsetData.sizes || '');
			} else {
				refs.heroImg.removeAttribute('srcset');
				refs.heroImg.removeAttribute('sizes');
			}
			refs.heroImg.classList.remove('is-loading');
			if (refs.galleryLoader) refs.galleryLoader.classList.remove('is-visible');
			// Preload adjacent images.
			preloadAdjacentImages(prod);
		} else {
			// Not cached yet — show loading state, then swap on load.
			refs.heroImg.classList.add('is-loading');
			if (refs.galleryLoader) refs.galleryLoader.classList.add('is-visible');
			preloadImage(url, function() {
				// Only apply if still the desired image (user may have clicked away).
				var currentProd = cur();
				var currentGallery = currentProd ? (currentProd.gallery && currentProd.gallery.length ? currentProd.gallery : (currentProd.image ? [currentProd.image] : [])) : [];
				var expectedUrl = currentGallery[state.galleryIdx] || currentGallery[0] || '';
				if (url === expectedUrl) {
					refs.heroImg.src = url;
					refs.heroImg.setAttribute('data-src', url);
					if (srcsetData && srcsetData.srcset) {
						refs.heroImg.setAttribute('srcset', srcsetData.srcset);
						refs.heroImg.setAttribute('sizes', srcsetData.sizes || '');
					} else {
						refs.heroImg.removeAttribute('srcset');
						refs.heroImg.removeAttribute('sizes');
					}
					refs.heroImg.classList.remove('is-loading');
					if (refs.galleryLoader) refs.galleryLoader.classList.remove('is-visible');
					preloadAdjacentImages(currentProd);
				}
			});
		}
	}

	/* ── Gallery Update ── */
	var galleryProductId = null; // Track which product's thumbs are rendered.

	function updateGallery(forceRebuildThumbs) {
		var prod = cur();
		if (!prod) return;

		// If product changed, deactivate any active 360° spinner.
		if (galleryProductId !== null && galleryProductId !== prod.id && state.is360) {
			deactivate360(true); // true = skip recursive updateGallery call
		}

		// Preload this product's gallery into memory cache.
		preloadProductGallery(prod, true);

		var gallery = prod.gallery && prod.gallery.length ? prod.gallery : (prod.image ? [prod.image] : []);
		var srcsetData = prod.gallery_srcset || [];

		// In normal mode (not 360°), show the active gallery image via hero <img>.
		if (!state.is360) {
			var targetSrc = gallery[state.galleryIdx] || gallery[0] || '';
			var targetSrcset = srcsetData[state.galleryIdx] || null;
			showHeroImage(targetSrc, targetSrcset, prod, true);
			if (refs.heroImg) refs.heroImg.style.display = '';
		}

		// Update gallery counter (hidden in 360° mode).
		if (refs.galleryCounter) {
			if (gallery.length > 1 && !state.is360) {
				refs.galleryCounter.textContent = (state.galleryIdx + 1) + ' / ' + gallery.length;
				refs.galleryCounter.style.display = '';
			} else {
				refs.galleryCounter.style.display = 'none';
			}
		}

		// Update thumbnails — only rebuild DOM when product changes.
		if (refs.thumbsWrap) {
			var needsRebuild = forceRebuildThumbs || (galleryProductId !== prod.id);
			var has360 = productHas360(prod);

			if (needsRebuild) {
				galleryProductId = prod.id;
				while (refs.thumbsWrap.firstChild) refs.thumbsWrap.removeChild(refs.thumbsWrap.firstChild);
				var thumbs = prod.thumbnails && prod.thumbnails.length ? prod.thumbnails : [];

				// Show thumbstrip if multiple images OR if product has 360°.
				if (thumbs.length <= 1 && !has360) {
					refs.thumbsWrap.style.display = 'none';
				} else {
					refs.thumbsWrap.style.display = '';

					// Regular image thumbnails + 360° thumb after first image.
					var btn360Node = null;
					if (has360) {
						btn360Node = el('button', 'mh-stv-thumb mh-stv-thumb-360');
						btn360Node.type = 'button';
						btn360Node.setAttribute('aria-label', prod.name + ' 360° Ansicht');
						if (state.is360) btn360Node.classList.add('is-active');

						var thumbSrc = prod.spin_thumb || (prod.spin_frames ? prod.spin_frames[0] : '');
						if (thumbSrc) {
							var im360 = el('img');
							im360.src = thumbSrc;
							im360.alt = '360°';
							im360.loading = 'lazy';
							btn360Node.appendChild(im360);
						}

						// 360° badge overlay on the thumbnail.
						var badge360 = el('span', 'mh-stv-thumb-360-badge');
						badge360.textContent = '360°';
						btn360Node.appendChild(badge360);

						btn360Node.addEventListener('click', function() {
							if (state.is360) {
								// Already in 360° → open fullscreen.
								open360Lightbox();
							} else {
								activate360();
							}
						});
					}

					for (var t = 0; t < thumbs.length; t++) {
						(function(idx) {
							var btn = el('button', 'mh-stv-thumb');
							btn.type = 'button';
							btn.setAttribute('aria-label', prod.name + ' Bild ' + (idx + 1));
							if (idx === state.galleryIdx && !state.is360) btn.classList.add('is-active');
							var im = el('img');
							im.src = thumbs[idx];
							im.alt = prod.name + ' ' + (idx + 1);
							im.loading = 'lazy';
							btn.appendChild(im);
							btn.addEventListener('click', function() {
								// Switch back to normal gallery if in 360° mode.
								if (state.is360) {
									deactivate360(true);
								}
								state.galleryIdx = idx;
								updateGallery();
							});
							refs.thumbsWrap.appendChild(btn);

							// Insert 360° thumb right after the first image (hero).
							if (idx === 0 && btn360Node) {
								refs.thumbsWrap.appendChild(btn360Node);
							}
						})(t);
					}

					// Fallback: if no regular thumbs but has 360°, append it anyway.
					if (thumbs.length === 0 && btn360Node) {
						refs.thumbsWrap.appendChild(btn360Node);
					}
				}
			} else {
				// Same product — just toggle active class on existing thumb buttons.
				var thumbBtns = refs.thumbsWrap.querySelectorAll('.mh-stv-thumb');
				var regularIdx = 0;
				for (var tb = 0; tb < thumbBtns.length; tb++) {
					var is360Btn = thumbBtns[tb].classList.contains('mh-stv-thumb-360');
					if (state.is360) {
						thumbBtns[tb].classList.toggle('is-active', is360Btn);
					} else {
						// Regular mode: match by gallery index, skipping the 360° button.
						thumbBtns[tb].classList.toggle('is-active', !is360Btn && regularIdx === state.galleryIdx);
					}
					if (!is360Btn) regularIdx++;
				}
			}
		}
	}

	/* ── Gallery Keyboard Navigation ── */
	function galleryKeyHandler(e) {
		var prod = cur();
		if (!prod) return;

		// In 360° mode, ignore gallery navigation keys (spinner handles its own input).
		if (state.is360) return;

		var gallery = prod.gallery && prod.gallery.length ? prod.gallery : (prod.image ? [prod.image] : []);

		if (e.key === 'Enter') {
			e.preventDefault();
			openLightbox(state.galleryIdx);
			return;
		}

		if (gallery.length < 2) return;

		if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
			e.preventDefault();
			state.galleryIdx = (state.galleryIdx + 1) % gallery.length;
			updateGallery();
		} else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
			e.preventDefault();
			state.galleryIdx = (state.galleryIdx - 1 + gallery.length) % gallery.length;
			updateGallery();
		}
	}

	/* ── 360° Gallery Integration ── */

	/**
	 * Activate 360° mode in the hero gallery.
	 * Creates a MH360 spinner overlay inside the gallery container.
	 */
	function activate360() {
		var prod = cur();
		if (!prod || !prod.spin_frames || !prod.spin_frames.length) return;
		if (typeof MH360 === 'undefined') return;
		if (!refs.galleryWrap) return;

		// Already active for this product.
		if (spinner360 && spinner360ProductId === prod.id && state.is360) return;

		// Destroy old spinner if switching products.
		deactivate360(true);

		state.is360 = true;

		// Create a container inside the gallery.
		var spinContainer = el('div', 'mh-stv-360-overlay');
		refs.galleryWrap.appendChild(spinContainer);
		refs.spinOverlay = spinContainer;

		// Hide hero image + zoom + counter.
		refs.galleryWrap.classList.add('is-360-active');
		if (refs.heroImg) refs.heroImg.style.display = 'none';

		var settings = mhStvData || {};
		var accent = settings.accentColor || '#e8910c';

		spinner360 = MH360.create(spinContainer, {
			frames: prod.spin_frames,
			accentColor: accent,
			autoplay: true,
			momentum: true,
			onExpand: function() { open360Lightbox(); }
		});
		spinner360ProductId = prod.id;

		// Update thumb highlights.
		updateGalleryThumbActive360();
	}

	/**
	 * Deactivate 360° mode — return to normal gallery.
	 */
	function deactivate360(skipGalleryUpdate) {
		if (spinner360) {
			spinner360.destroy();
			spinner360 = null;
			spinner360ProductId = 0;
		}

		state.is360 = false;

		// Remove the overlay container.
		if (refs.spinOverlay && refs.spinOverlay.parentNode) {
			refs.spinOverlay.parentNode.removeChild(refs.spinOverlay);
			refs.spinOverlay = null;
		}

		if (refs.galleryWrap) {
			refs.galleryWrap.classList.remove('is-360-active');
		}
		// Restore hero image visibility.
		if (refs.heroImg) refs.heroImg.style.display = '';

		if (!skipGalleryUpdate) {
			updateGallery();
		}
	}

	/**
	 * Update thumbnail active states when 360° is active.
	 */
	function updateGalleryThumbActive360() {
		if (!refs.thumbsWrap) return;
		var thumbBtns = refs.thumbsWrap.querySelectorAll('.mh-stv-thumb');
		for (var i = 0; i < thumbBtns.length; i++) {
			thumbBtns[i].classList.toggle('is-active', thumbBtns[i].classList.contains('mh-stv-thumb-360'));
		}
	}

	/**
	 * Check if current product has 360° frames.
	 */
	function productHas360(prod) {
		return prod && prod.spin_frames && prod.spin_frames.length > 0;
	}

	/**
	 * Open lightbox in 360° mode.
	 */
	function open360Lightbox() {
		var prod = cur();
		if (!prod || !productHas360(prod)) return;
		if (typeof MH360 === 'undefined') return;
		track('stv_360_interact');

		// Build lightbox if needed.
		if (!lightbox) buildLightbox();

		lightbox.classList.add('is-open', 'is-360-mode');
		document.body.style.overflow = 'hidden';

		// Hide normal lightbox image + nav, show 360° container.
		if (lbImgWrap) lbImgWrap.style.display = 'none';

		var existing360 = lightbox.querySelector('.mh-stv-lb-360-wrap');
		if (existing360) existing360.parentNode.removeChild(existing360);

		var lb360Wrap = el('div', 'mh-stv-lb-360-wrap');
		lb360Wrap.style.cssText = 'position:absolute;inset:0;z-index:2;display:flex;align-items:center;justify-content:center;';
		var content = lightbox.querySelector('.mh-stv-lightbox-content');
		if (content) content.appendChild(lb360Wrap);

		var settings = mhStvData || {};
		var accent = settings.accentColor || '#e8910c';

		// Store ref for cleanup.
		refs.lb360Spinner = MH360.create(lb360Wrap, {
			frames: prod.spin_frames,
			accentColor: accent,
			autoplay: false,
			momentum: true
		});
		refs.lb360Wrap = lb360Wrap;

		// Hide nav arrows + thumbs for 360° lightbox.
		var navBtns = lightbox.querySelectorAll('.mh-stv-lightbox-nav');
		for (var i = 0; i < navBtns.length; i++) navBtns[i].style.display = 'none';
		if (lightboxThumbs) lightboxThumbs.style.display = 'none';
		if (lightboxCounter) lightboxCounter.style.display = 'none';

		lightbox.focus();
	}

	/**
	 * Override closeLightbox to clean up 360° mode.
	 * We patch this after the original buildLightbox().
	 */
	var originalCloseLightbox = null; // set after buildLightbox

	function closeLightbox360Cleanup() {
		if (refs.lb360Spinner) {
			refs.lb360Spinner.destroy();
			refs.lb360Spinner = null;
		}
		if (refs.lb360Wrap && refs.lb360Wrap.parentNode) {
			refs.lb360Wrap.parentNode.removeChild(refs.lb360Wrap);
			refs.lb360Wrap = null;
		}
		if (lightbox) {
			lightbox.classList.remove('is-360-mode');
			// Restore normal lightbox elements.
			if (lbImgWrap) lbImgWrap.style.display = '';
			var navBtns = lightbox.querySelectorAll('.mh-stv-lightbox-nav');
			for (var i = 0; i < navBtns.length; i++) navBtns[i].style.display = '';
			if (lightboxThumbs) lightboxThumbs.style.display = '';
			if (lightboxCounter) lightboxCounter.style.display = '';
		}
	}

	/* ── Design Picker Update ── */
	function updateDesigns() {
		var btns = ROOT.querySelectorAll('.mh-stv-design-btn');
		for (var i = 0; i < btns.length; i++) {
			btns[i].classList.toggle('is-on', btns[i].getAttribute('data-serie') === state.serie);
		}

		// Update prices in design cards.
		var currentProd = cur();
		var currentPrice = currentProd ? currentProd.price : 0;
		var level = levelKey();

		var priceEls = ROOT.querySelectorAll('[data-serie-price]');
		for (var p = 0; p < priceEls.length; p++) {
			var serieKey = priceEls[p].getAttribute('data-serie-price');
			var prod = findProduct(serieKey, level);
			while (priceEls[p].firstChild) priceEls[p].removeChild(priceEls[p].firstChild);

			if (!prod) continue;

			var priceSpan = el('span', 'mh-stv-design-price-val');
			priceSpan.textContent = fmtPrice(prod.price);
			priceEls[p].appendChild(priceSpan);

			// Show delta if this is NOT the active serie.
			if (serieKey !== state.serie && currentPrice > 0) {
				var diff = prod.price - currentPrice;
				if (diff !== 0) {
					var deltaSpan = el('span', 'mh-stv-design-price-delta');
					if (diff > 0) {
						deltaSpan.textContent = '+' + fmtPrice(diff);
						deltaSpan.classList.add('is-more');
					} else {
						deltaSpan.textContent = '\u2212' + fmtPrice(Math.abs(diff));
						deltaSpan.classList.add('is-less');
					}
					priceEls[p].appendChild(deltaSpan);
				}
			}
		}
	}

	/* ── Toggle Update ── */
	function updateToggles() {
		var swingEl = ROOT.querySelector('[data-toggle="swing"]');
		var wallEl  = ROOT.querySelector('[data-toggle="wall"]');

		if (swingEl) {
			swingEl.classList.toggle('is-on', state.swing);
			swingEl.setAttribute('aria-checked', state.swing ? 'true' : 'false');
			var base  = findProduct(state.serie, 'basic');
			var swing = findProduct(state.serie, 'swing');
			var sDelta = (base && swing) ? (swing.price - base.price) : 0;
			var sDeltaEl = swingEl.querySelector('.mh-stv-toggle-delta');
			if (sDeltaEl) sDeltaEl.textContent = sDelta > 0 ? fmtDelta(sDelta) : '';
		}

		if (wallEl) {
			var isDisabled = !state.swing;
			wallEl.classList.toggle('is-on', state.wall);
			wallEl.classList.toggle('is-disabled', isDisabled);
			wallEl.setAttribute('aria-checked', state.wall ? 'true' : 'false');
			wallEl.setAttribute('aria-disabled', isDisabled ? 'true' : 'false');
			wallEl.setAttribute('tabindex', isDisabled ? '-1' : '0');

			var swingP = findProduct(state.serie, 'swing');
			var fullP  = findProduct(state.serie, 'full');
			var wDelta = (swingP && fullP) ? (fullP.price - swingP.price) : 0;
			var wDeltaEl = wallEl.querySelector('.mh-stv-toggle-delta');
			if (wDeltaEl) wDeltaEl.textContent = wDelta > 0 ? fmtDelta(wDelta) : '';
			var descEl = wallEl.querySelector('.mh-stv-toggle-desc');
			if (descEl) {
				var wLevel = D.levels.length > 2 ? D.levels[2] : {};
				descEl.textContent = state.swing
					? decodeHTML(wLevel.toggle_desc || I18N.wallDesc || '')
					: decodeHTML(I18N.wallDisabled || 'Schaukel wird ben\u00f6tigt');
			}
		}

		// Dependency hint.
		if (refs.depHint) {
			refs.depHint.classList.toggle('is-visible', !state.swing);
		}
	}

	/* ── Right Panel Update ── */
	function updateRight() {
		var prod = cur();
		if (!prod) return;

		// Name.
		if (refs.productName) refs.productName.textContent = prod.name;

		// Review stars inline (compact: ★ 4.8 (12)).
		if (refs.reviewInline) {
			refs.reviewInline.innerHTML = '';
			if (prod.average_rating > 0) {
				var starsWrap = el('div', 'mh-stv-stars');
				starsWrap.innerHTML = renderStars(prod.average_rating);
				refs.reviewInline.appendChild(starsWrap);
				var ratingText = el('span', 'mh-stv-review-inline-text');
				ratingText.textContent = prod.average_rating.toFixed(1) + ' (' + prod.review_count + ')';
				refs.reviewInline.appendChild(ratingText);
			}
		}

		// Stock.
		if (refs.stockBadge) {
			refs.stockBadge.className = 'mh-stv-stock';
			if (prod.stock_status === 'instock') {
				refs.stockBadge.classList.add('is-instock');
			} else if (prod.stock_status === 'onbackorder') {
				refs.stockBadge.classList.add('is-onbackorder');
			} else {
				refs.stockBadge.classList.add('is-outofstock');
			}
			var textEl = refs.stockBadge.querySelector('.mh-stv-stock-text');
			if (textEl) textEl.textContent = prod.stock_text || '';
		}

		// Included features — v5.7.0: reads from features_matrix[serie][level].
		if (refs.includedList) {
			while (refs.includedList.firstChild) refs.includedList.removeChild(refs.includedList.firstChild);
			var baseFeats = D.base_features || [];
			var serieFeats = (D.series_features && D.series_features[state.serie]) ? D.series_features[state.serie] : [];
			var currentLvl = levelKey();

			// Per-serie level addons from features_matrix.
			var levelAddons = [];
			var fmSerie = (D.features_matrix && D.features_matrix[state.serie]) ? D.features_matrix[state.serie] : {};
			var fmLevel = fmSerie[currentLvl] || [];
			// Filter out any that are already in base or serie features.
			var alreadyShown = baseFeats.concat(serieFeats);
			for (var fa = 0; fa < fmLevel.length; fa++) {
				if (alreadyShown.indexOf(fmLevel[fa]) < 0) {
					levelAddons.push(fmLevel[fa]);
				}
			}

			var frag = document.createDocumentFragment();
			var allBase = baseFeats.concat(serieFeats);
			for (var i = 0; i < allBase.length; i++) {
				var tag = el('span', 'mh-stv-included-tag');
				tag.textContent = allBase[i];
				frag.appendChild(tag);
			}
			if (levelAddons.length > 0) {
				frag.appendChild(el('div', 'mh-stv-included-sep'));
				for (var j = 0; j < levelAddons.length; j++) {
					var addon = el('span', 'mh-stv-included-tag is-addon');
					addon.textContent = levelAddons[j];
					frag.appendChild(addon);
				}
			}
			refs.includedList.appendChild(frag);
		}

		// CTA.
		buildCTA(prod);
	}

	/* ── CTA Builder (uses shared addToCartAjax helper) ── */
	function buildCTA(prod) {
		if (!refs.ctaArea) return;
		// Preserve qty across rebuilds.
		var preservedQty = state.qty || 1;
		while (refs.ctaArea.firstChild) refs.ctaArea.removeChild(refs.ctaArea.firstChild);

		// Quantity row — syncs with state.qty for sticky CTA.
		var qtyRow = el('div', 'mh-stv-qty-row');
		var qtyLabel = el('label', 'mh-stv-qty-label');
		qtyLabel.textContent = decodeHTML(I18N.qtyLabel || 'Anzahl');
		qtyRow.appendChild(qtyLabel);

		var qtyWrap = el('div', 'mh-stv-qty-wrap');
		var qtyMinus = el('button', 'mh-stv-qty-btn');
		qtyMinus.type = 'button';
		qtyMinus.textContent = '\u2212';
		qtyMinus.setAttribute('aria-label', 'Menge verringern');
		var qtyInput = el('input', 'mh-stv-qty-input');
		qtyInput.type = 'text';
		qtyInput.inputMode = 'numeric';
		qtyInput.pattern = '[0-9]*';
		qtyInput.min = '1';
		qtyInput.value = String(preservedQty);
		qtyInput.setAttribute('aria-label', 'Anzahl');
		refs.qtyInput = qtyInput;

		// Fix #6 v5.4.0: Compute max qty from stock or fallback 99.
		var qtyMax = getQtyMax();

		function syncQty() {
			var v = parseInt(qtyInput.value, 10);
			if (!v || v < 1) { v = 1; qtyInput.value = '1'; }
			if (v > qtyMax) { v = qtyMax; qtyInput.value = String(qtyMax); }
			state.qty = v;
			updateStickyCTA();
			syncBoughtTogetherQty();
		}
		qtyInput.addEventListener('input', function() {
			this.value = this.value.replace(/[^0-9]/g, '');
			if (this.value === '' || parseInt(this.value, 10) < 1) this.value = '1';
			syncQty();
		});

		var qtyPlus = el('button', 'mh-stv-qty-btn');
		qtyPlus.type = 'button';
		qtyPlus.textContent = '+';
		qtyPlus.setAttribute('aria-label', 'Menge erh\u00f6hen');

		qtyMinus.addEventListener('click', function() {
			var v = parseInt(qtyInput.value, 10) || 1;
			if (v > 1) { qtyInput.value = v - 1; syncQty(); }
		});
		qtyPlus.addEventListener('click', function() {
			var v = parseInt(qtyInput.value, 10) || 1;
			if (v < qtyMax) { qtyInput.value = v + 1; syncQty(); }
		});

		qtyWrap.appendChild(qtyMinus);
		qtyWrap.appendChild(qtyInput);
		qtyWrap.appendChild(qtyPlus);
		qtyRow.appendChild(qtyWrap);
		refs.ctaArea.appendChild(qtyRow);

		// AJAX Add-to-cart button — uses shared helper.
		var cartBtn = el('a', 'mh-stv-cta mh-stv-cta-primary');
		cartBtn.href = '#';
		cartBtn.setAttribute('role', 'button');
		var cartBtnText = decodeHTML(I18N.addToCart || 'In den Warenkorb');
		cartBtn.textContent = cartBtnText;

		cartBtn.addEventListener('click', function(e) {
			e.preventDefault();
			var p = cur();
			if (!p) return;
			addToCartAjax(p.id, state.qty, cartBtn, cartBtnText, function(json) {
				// Show cart notice with undo link (Fix #5 v5.4.0).
				if (refs.cartNotice) {
					refs.cartNotice.classList.add('is-visible');
					while (refs.cartNotice.firstChild) refs.cartNotice.removeChild(refs.cartNotice.firstChild);
					var checkSpan = document.createTextNode('\u2713 ' + decodeHTML(I18N.cartNotice || 'Produkt im Warenkorb.') + ' ');
					refs.cartNotice.appendChild(checkSpan);
					var cartLink = el('a');
					cartLink.href = (json.data && json.data.cart_url) ? json.data.cart_url : '/koszyk/';
					cartLink.textContent = decodeHTML(I18N.viewCart || 'Warenkorb ansehen');
					refs.cartNotice.appendChild(cartLink);

					// Undo link (Fix #5 v5.4.0).
					if (json.data && json.data.cart_item_key && UNDO_NONCE) {
						var sep = document.createTextNode(' \u00B7 ');
						refs.cartNotice.appendChild(sep);
						var undoLink = el('a', 'mh-stv-undo-link');
						undoLink.href = '#';
						undoLink.textContent = decodeHTML(I18N.undo || 'R\u00fcckg\u00e4ngig');
						undoLink.addEventListener('click', function(ev) {
							ev.preventDefault();
							var ub = new FormData();
							ub.append('action', 'mh_stv_undo_cart');
							ub.append('nonce', UNDO_NONCE);
							ub.append('cart_item_key', json.data.cart_item_key);
							undoLink.textContent = '\u2026';
							fetch(AJAX_URL, { method: 'POST', body: ub, credentials: 'same-origin' })
								.then(function(r) { return r.json(); })
								.then(function(uj) {
									if (uj.success) {
										refs.cartNotice.classList.remove('is-visible');
										cartBtn.classList.remove('is-success');
										cartBtn.textContent = cartBtnText;
										if (uj.data && uj.data.cart_count !== undefined) {
											var ccEls = document.querySelectorAll('.cart-count, .wc-cart-count, .cart_contents_count');
											for (var cc = 0; cc < ccEls.length; cc++) ccEls[cc].textContent = uj.data.cart_count;
										}
										if (typeof jQuery !== 'undefined') {
											jQuery(document.body).trigger('wc_fragment_refresh');
										}
									} else {
										undoLink.textContent = decodeHTML(I18N.undoFailed || 'Fehler');
									}
								})
								.catch(function() { undoLink.textContent = decodeHTML(I18N.undoFailed || 'Fehler'); });
						});
						refs.cartNotice.appendChild(undoLink);
					}
				}
			});
		});

		refs.ctaArea.appendChild(cartBtn);

		// Cart success notice container.
		var notice = el('div', 'mh-stv-cart-notice');
		refs.cartNotice = notice;
		refs.ctaArea.appendChild(notice);

		// v5.6.0: Accessory link removed from main CTA — BT pill in sticky bar is sufficient.
	}

	/* ── Tabs Update (with content caching) ── */
	function updateTabs() {
		var prod = cur();
		if (!prod) return;

		var cacheKey = prod.id + '_' + prod.serie + '_' + prod.level;

		// Update tab buttons (ARIA).
		var tabBtns = ROOT.querySelectorAll('.mh-stv-tab-btn');
		for (var i = 0; i < tabBtns.length; i++) {
			var isActive = tabBtns[i].getAttribute('data-tab') === state.activeTab;
			tabBtns[i].classList.toggle('is-active', isActive);
			tabBtns[i].setAttribute('aria-selected', isActive ? 'true' : 'false');
			tabBtns[i].setAttribute('tabindex', isActive ? '0' : '-1');
		}

		// Update review count in tab button.
		var reviewBtn = ROOT.querySelector('[data-tab="reviews"]');
		if (reviewBtn) {
			var countSpan = reviewBtn.querySelector('.mh-stv-tab-count');
			if (countSpan) countSpan.textContent = prod.review_count > 0 ? '(' + prod.review_count + ')' : '';
		}

		// Update tab panels.
		var panels = ROOT.querySelectorAll('.mh-stv-tab-panel');
		for (var p = 0; p < panels.length; p++) {
			var panelActive = panels[p].getAttribute('data-panel') === state.activeTab;
			panels[p].classList.toggle('is-active', panelActive);
		}

		// Description panel — short desc + full desc, cached.
		// #2: Lazy-load detail data if not yet available.
		if (refs.tabDescription) {
			if (prod.description === null) {
				refs.tabDescription.innerHTML = '<div class="mh-stv-tab-skeleton"><div style="height:16px;width:90%;background:#e5e3df;border-radius:4px;margin:0 0 10px;animation:mhStvPulse 1.5s ease-in-out infinite"></div><div style="height:16px;width:70%;background:#e5e3df;border-radius:4px;margin:0 0 10px;animation:mhStvPulse 1.5s ease-in-out infinite"></div><div style="height:16px;width:80%;background:#e5e3df;border-radius:4px;animation:mhStvPulse 1.5s ease-in-out infinite"></div></div>';
				loadProductDetail(prod.id, function() { tabCache = {}; updateTabs(); });
			} else {
				var descKey = cacheKey + '_desc';
				if (tabCache[descKey] === undefined) {
					var descParts = '';
					if (prod.short_description) {
						descParts += '<div class="mh-stv-short-desc">' + sanitizeHTML(prod.short_description) + '</div>';
					}
					if (prod.description) {
						descParts += sanitizeHTML(prod.description);
					}
					tabCache[descKey] = descParts || '';
				}
				refs.tabDescription.innerHTML = tabCache[descKey] || '<p style="color:var(--_muted)">Keine Beschreibung vorhanden.</p>';
			}
		}

		// Attributes panel — build with DocumentFragment.
		// #2: Lazy-load guard.
		if (refs.tabAttributes) {
			if (prod.attributes === null) {
				refs.tabAttributes.innerHTML = '<div class="mh-stv-tab-skeleton"><div style="height:14px;width:60%;background:#e5e3df;border-radius:4px;margin:0 0 8px;animation:mhStvPulse 1.5s ease-in-out infinite"></div><div style="height:14px;width:80%;background:#e5e3df;border-radius:4px;margin:0 0 8px;animation:mhStvPulse 1.5s ease-in-out infinite"></div></div>';
				loadProductDetail(prod.id, function() { tabCache = {}; updateTabs(); });
			} else {
				var attrKey = cacheKey + '_attr';
				if (!tabCache[attrKey]) {
					var attrs = prod.attributes || [];
					if (attrs.length === 0) {
						tabCache[attrKey] = '<p style="color:var(--_muted)">Keine zus\u00e4tzlichen Informationen.</p>';
					} else {
						var frag = document.createDocumentFragment();
						var table = el('table', 'mh-stv-attr-table');
						for (var a = 0; a < attrs.length; a++) {
							var tr = el('tr', a % 2 === 0 ? 'mh-stv-attr-even' : '');
							var th = el('th', 'mh-stv-attr-label');
							th.textContent = attrs[a].label;
							tr.appendChild(th);
							var td = el('td', 'mh-stv-attr-value');
							td.textContent = attrs[a].value;
							tr.appendChild(td);
							table.appendChild(tr);
						}
						frag.appendChild(table);
						refs.tabAttributes.innerHTML = '';
						refs.tabAttributes.appendChild(frag);
						tabCache[attrKey] = 'built';
					}
				}
				if (tabCache[attrKey] && tabCache[attrKey] !== 'built') {
					refs.tabAttributes.innerHTML = tabCache[attrKey];
				}
			}
		}

		// Produktdaten panel — SKU, shipping, categories, manufacturer.
		// #2: Lazy-load guard.
		if (refs.tabProductData) {
			if (prod.product_meta === null) {
				refs.tabProductData.innerHTML = '<div class="mh-stv-tab-skeleton"><div style="height:14px;width:50%;background:#e5e3df;border-radius:4px;margin:0 0 8px;animation:mhStvPulse 1.5s ease-in-out infinite"></div><div style="height:14px;width:70%;background:#e5e3df;border-radius:4px;animation:mhStvPulse 1.5s ease-in-out infinite"></div></div>';
				loadProductDetail(prod.id, function() { tabCache = {}; updateTabs(); });
			} else {
				var metaKey = cacheKey + '_meta';
				if (!tabCache[metaKey]) {
					var pmeta = prod.product_meta || [];
					if (pmeta.length === 0) {
						tabCache[metaKey] = '<p style="color:var(--_muted)">Keine Produktdaten vorhanden.</p>';
					} else {
						var fragMeta = document.createDocumentFragment();

						// Split into product data and manufacturer sections.
						var productRows = [];
						var mfgRows = [];
						for (var mi = 0; mi < pmeta.length; mi++) {
							if (pmeta[mi].section === 'manufacturer') {
								mfgRows.push(pmeta[mi]);
							} else {
								productRows.push(pmeta[mi]);
							}
						}

						// Product data table.
						if (productRows.length > 0) {
							var pdLabel = el('h3', 'mh-stv-meta-heading');
							pdLabel.textContent = 'Produktdaten';
							fragMeta.appendChild(pdLabel);
							var pdTable = el('table', 'mh-stv-attr-table');
							for (var pi = 0; pi < productRows.length; pi++) {
								var ptr = el('tr', pi % 2 === 0 ? 'mh-stv-attr-even' : '');
								var pth = el('th', 'mh-stv-attr-label');
								pth.textContent = productRows[pi].label;
								ptr.appendChild(pth);
								var ptd = el('td', 'mh-stv-attr-value');
								if (productRows[pi].html) {
									ptd.innerHTML = sanitizeHTML(productRows[pi].value);
								} else {
									ptd.textContent = productRows[pi].value;
								}
								ptr.appendChild(ptd);
								pdTable.appendChild(ptr);
							}
							fragMeta.appendChild(pdTable);
						}

						// Manufacturer table (from WC GPSR meta).
						if (mfgRows.length > 0) {
							var mfgLabel = el('h3', 'mh-stv-meta-heading');
							mfgLabel.textContent = 'Herstellerinformationen';
							mfgLabel.style.marginTop = '20px';
							fragMeta.appendChild(mfgLabel);
							var mfgTable = el('table', 'mh-stv-attr-table');
							for (var mfi = 0; mfi < mfgRows.length; mfi++) {
								var mtr = el('tr', mfi % 2 === 0 ? 'mh-stv-attr-even' : '');
								var mth = el('th', 'mh-stv-attr-label');
								mth.textContent = mfgRows[mfi].label;
								mtr.appendChild(mth);
								var mtd = el('td', 'mh-stv-attr-value');
								mtd.textContent = mfgRows[mfi].value;
								mtr.appendChild(mtd);
								mfgTable.appendChild(mtr);
							}
							fragMeta.appendChild(mfgTable);
						}

						// v5.7.2: Fallback — capture manufacturer block from Oxygen template DOM
						var MFG_SEL = (mhStvData.manufacturerSelector || '').trim();
						if (mfgRows.length === 0 && MFG_SEL) {
							try {
								var mfgDomEl = document.querySelector(MFG_SEL);
								if (mfgDomEl) {
									var mfgWrap = el('div', 'mh-stv-mfg-captured');
									mfgWrap.style.marginTop = '20px';
									mfgDomEl.parentNode.removeChild(mfgDomEl);
									mfgWrap.appendChild(mfgDomEl);
									fragMeta.appendChild(mfgWrap);
								}
							} catch (e) { /* invalid selector */ }
						}

						refs.tabProductData.innerHTML = '';
						refs.tabProductData.appendChild(fragMeta);
						tabCache[metaKey] = 'built';
					}
				}
				if (tabCache[metaKey] && tabCache[metaKey] !== 'built') {
					refs.tabProductData.innerHTML = tabCache[metaKey];
				}
			}
		}

		// Reviews panel — build with DocumentFragment.
		// #2: Lazy-load guard.
		if (refs.tabReviews) {
			if (prod.reviews === null) {
				refs.tabReviews.innerHTML = '<div class="mh-stv-tab-skeleton"><div style="height:14px;width:40%;background:#e5e3df;border-radius:4px;margin:0 0 8px;animation:mhStvPulse 1.5s ease-in-out infinite"></div></div>';
				loadProductDetail(prod.id, function() { tabCache = {}; updateTabs(); });
			} else {
				var revKey = cacheKey + '_rev';
				if (!tabCache[revKey]) {
					var reviews = prod.reviews || [];
					var frag2 = document.createDocumentFragment();

					if (reviews.length === 0) {
						var noRev = el('p');
						noRev.style.color = 'var(--_muted)';
						noRev.textContent = 'Noch keine Bewertungen.';
						frag2.appendChild(noRev);
					} else {
						if (prod.average_rating > 0) {
							var avgWrap = el('div', 'mh-stv-review-avg');
							var avgStars = el('div', 'mh-stv-stars');
							avgStars.innerHTML = renderStars(prod.average_rating);
							avgWrap.appendChild(avgStars);
							var avgText = el('span', 'mh-stv-review-avg-text');
							avgText.textContent = prod.average_rating.toFixed(1) + ' von 5 (' + prod.review_count + (prod.review_count === 1 ? ' Bewertung' : ' Bewertungen') + ')';
							avgWrap.appendChild(avgText);
							frag2.appendChild(avgWrap);
						}

						for (var r = 0; r < reviews.length; r++) {
							var rev = reviews[r];
							var card = el('div', 'mh-stv-review');
							var header = el('div', 'mh-stv-review-header');
							var stars = el('div', 'mh-stv-stars');
							stars.innerHTML = renderStars(rev.rating);
							header.appendChild(stars);
							var meta = el('div', 'mh-stv-review-meta');
							meta.textContent = rev.author + ' \u2014 ' + rev.date;
							header.appendChild(meta);
							card.appendChild(header);
							var body = el('div', 'mh-stv-review-body');
							body.textContent = rev.content;
							card.appendChild(body);
							frag2.appendChild(card);
						}
					}

					refs.tabReviews.innerHTML = '';
					refs.tabReviews.appendChild(frag2);
					tabCache[revKey] = true;
				}
			}
		}
	}

	function renderStars(rating) {
		var html = '';
		for (var i = 1; i <= 5; i++) {
			if (i <= Math.floor(rating)) {
				html += '<span class="mh-stv-star is-full">\u2605</span>';
			} else if (i - 0.5 <= rating) {
				html += '<span class="mh-stv-star is-half">\u2605</span>';
			} else {
				html += '<span class="mh-stv-star">\u2606</span>';
			}
		}
		return html;
	}

	function switchTab(tabKey) {
		state.activeTab = tabKey;
		track('stv_tab_open', tabKey);
		updateTabs();
	}

	/* ── Tab Keyboard Navigation (arrow keys between tabs) ── */
	function tabKeyHandler(e) {
		var tabBtns = ROOT.querySelectorAll('.mh-stv-tab-btn');
		var currentIdx = -1;
		for (var i = 0; i < tabBtns.length; i++) {
			if (tabBtns[i] === e.target) { currentIdx = i; break; }
		}
		if (currentIdx < 0) return;

		var newIdx = -1;
		if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
			e.preventDefault();
			newIdx = (currentIdx + 1) % tabBtns.length;
		} else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
			e.preventDefault();
			newIdx = (currentIdx - 1 + tabBtns.length) % tabBtns.length;
		} else if (e.key === 'Home') {
			e.preventDefault();
			newIdx = 0;
		} else if (e.key === 'End') {
			e.preventDefault();
			newIdx = tabBtns.length - 1;
		}

		if (newIdx >= 0) {
			tabBtns[newIdx].focus();
			switchTab(tabBtns[newIdx].getAttribute('data-tab'));
		}
	}

	/* ── Comparison View Builder v2 — with row headers ── */
	function buildComparisonView() {
		var compareWrap = el('div', 'mh-stv-compare');

		var levels = D.levels || [];
		if (levels.length < 2) return null;

		var currentLevel = levelKey();

		// Set CSS custom property for column count.
		compareWrap.style.setProperty('--_cols', levels.length);

		// Header row: empty corner + level labels.
		var header = el('div', 'mh-stv-compare-header');
		var cornerCell = el('div', 'mh-stv-compare-header-cell');
		cornerCell.textContent = decodeHTML(I18N.compareHeader || 'Ausstattung');
		header.appendChild(cornerCell);
		for (var h = 0; h < levels.length; h++) {
			var hCell = el('div', 'mh-stv-compare-header-cell');
			hCell.textContent = levels[h].label || levels[h].key;
			if (levels[h].key === currentLevel) hCell.classList.add('is-active');
			header.appendChild(hCell);
		}
		compareWrap.appendChild(header);

		// Feature rows — v5.7.0: per-serie from features_matrix.
		var allLevelKeys = [];
		for (var lk = 0; lk < levels.length; lk++) allLevelKeys.push(levels[lk].key);

		var featureMap = [];
		var seenFeats = {};

		// Base features → ✓ at all levels.
		var baseF = D.base_features || [];
		for (var bi = 0; bi < baseF.length; bi++) {
			featureMap.push({ name: baseF[bi], levels: allLevelKeys.slice() });
			seenFeats[baseF[bi]] = true;
		}

		// Series always-on features → ✓ at all levels for current serie.
		var serieF = (D.series_features && D.series_features[state.serie]) ? D.series_features[state.serie] : [];
		for (var sf = 0; sf < serieF.length; sf++) {
			if (!seenFeats[serieF[sf]]) {
				featureMap.push({ name: serieF[sf], levels: allLevelKeys.slice() });
				seenFeats[serieF[sf]] = true;
			}
		}

		// Per-serie level features from features_matrix.
		var fmSerie = (D.features_matrix && D.features_matrix[state.serie]) ? D.features_matrix[state.serie] : {};
		// Collect all unique feature names and which levels have them.
		var matrixFeats = {}; // name → [level_keys]
		for (var mlk = 0; mlk < allLevelKeys.length; mlk++) {
			var lvlFeats = fmSerie[allLevelKeys[mlk]] || [];
			for (var mfi = 0; mfi < lvlFeats.length; mfi++) {
				var fn = lvlFeats[mfi];
				if (seenFeats[fn]) continue; // Already in base/serie features.
				if (!matrixFeats[fn]) matrixFeats[fn] = [];
				matrixFeats[fn].push(allLevelKeys[mlk]);
			}
		}
		for (var mfName in matrixFeats) {
			if (matrixFeats.hasOwnProperty(mfName)) {
				featureMap.push({ name: mfName, levels: matrixFeats[mfName] });
			}
		}

		for (var f = 0; f < featureMap.length; f++) {
			var row = el('div', 'mh-stv-compare-row');
			// Row header.
			var rowHead = el('div', 'mh-stv-compare-rowhead');
			rowHead.textContent = featureMap[f].name;
			row.appendChild(rowHead);
			// Data cells — check or dash only.
			for (var l = 0; l < levels.length; l++) {
				var cell = el('div', 'mh-stv-compare-cell');
				if (levels[l].key === currentLevel) cell.classList.add('is-active');

				var hasFeature = featureMap[f].levels.indexOf(levels[l].key) >= 0;
				if (hasFeature) {
					var checkMark = el('span', 'mh-stv-compare-check');
					checkMark.textContent = '\u2713';
					cell.appendChild(checkMark);
				} else {
					var cross = el('span', 'mh-stv-compare-cross');
					cross.textContent = '\u2014';
					cell.appendChild(cross);
				}
				row.appendChild(cell);
			}
			compareWrap.appendChild(row);
		}

		// Price row.
		var priceRow = el('div', 'mh-stv-compare-row');
		var priceHead = el('div', 'mh-stv-compare-rowhead');
		priceHead.textContent = 'Preis';
		priceHead.style.fontWeight = '700';
		priceRow.appendChild(priceHead);
		for (var pl = 0; pl < levels.length; pl++) {
			var pCell = el('div', 'mh-stv-compare-cell');
			if (levels[pl].key === currentLevel) pCell.classList.add('is-active');
			var prod = findProduct(state.serie, levels[pl].key);
			if (prod) {
				var priceSpan = el('span', 'mh-stv-compare-price');
				priceSpan.textContent = fmtPrice(prod.price);
				if (levels[pl].key === currentLevel) priceSpan.classList.add('is-active');
				pCell.appendChild(priceSpan);
			} else {
				pCell.textContent = '\u2014';
			}
			priceRow.appendChild(pCell);
		}
		compareWrap.appendChild(priceRow);

		// Fix #8 v5.4.0: CTA row — "Auswählen" button per level column.
		var ctaRow = el('div', 'mh-stv-compare-row mh-stv-compare-cta-row');
		var ctaCorner = el('div', 'mh-stv-compare-rowhead');
		ctaRow.appendChild(ctaCorner);
		for (var cl = 0; cl < levels.length; cl++) {
			(function(levelDef) {
				var ctaCell = el('div', 'mh-stv-compare-cell');
				if (levelDef.key === currentLevel) {
					ctaCell.classList.add('is-active');
					var currentBadge = el('span', 'mh-stv-compare-current-badge');
					currentBadge.textContent = decodeHTML(I18N.currentConfig || 'Aktuelle Auswahl');
					ctaCell.appendChild(currentBadge);
				} else {
					var selectBtn = el('button', 'mh-stv-compare-select-btn');
					selectBtn.type = 'button';
					selectBtn.textContent = decodeHTML(I18N.selectVariant || 'Ausw\u00e4hlen');
					selectBtn.addEventListener('click', function() {
						// Fix v5.4.1: Derive toggle states dynamically from level position.
						applyLevel(levelDef.key);
						update(true);
						// Scroll to CTA area.
						if (refs.ctaArea) {
							refs.ctaArea.scrollIntoView({ behavior: REDUCED_MOTION ? 'auto' : 'smooth', block: 'center' });
						}
					});
					ctaCell.appendChild(selectBtn);
				}
				ctaRow.appendChild(ctaCell);
			})(levels[cl]);
		}
		compareWrap.appendChild(ctaRow);

		return compareWrap;
	}

	/* ── Update Comparison View ── */
	function updateComparisonView() {
		if (!refs.compareContainer) return;
		while (refs.compareContainer.firstChild) refs.compareContainer.removeChild(refs.compareContainer.firstChild);
		var view = buildComparisonView();
		if (view) refs.compareContainer.appendChild(view);
	}

	/* ── AJAX Bought-Together Refresh (debounced) ── */
	function refreshBoughtTogether(productId) {
		if (!AJAX_URL || !BT_NONCE) return;
		if (btProductId === productId) return;
		btProductId = productId;

		// v5.2.0: Immediately clear old BT content + reset sticky price.
		// Prevents stale BT total from previous product showing during AJAX load.
		// Fix v5.4.1: Show loading skeleton instead of hiding completely.
		if (refs.btEmbed) {
			while (refs.btEmbed.firstChild) refs.btEmbed.removeChild(refs.btEmbed.firstChild);
			var btSkeleton = el('div', 'mh-stv-bt-skeleton');
			var skPulse = 'background:#eeedea;border-radius:6px;animation:mhStvPulse 1.2s ease-in-out infinite;';
			btSkeleton.innerHTML = '<div style="display:flex;align-items:center;gap:12px;padding:14px 0">'
				+ '<div style="' + skPulse + 'width:48px;height:48px;flex-shrink:0;border-radius:8px"></div>'
				+ '<div style="flex:1;display:flex;flex-direction:column;gap:8px">'
				+ '<div style="' + skPulse + 'height:14px;width:70%"></div>'
				+ '<div style="' + skPulse + 'height:12px;width:45%"></div>'
				+ '</div></div>';
			refs.btEmbed.appendChild(btSkeleton);
			refs.btEmbed.style.display = '';
		}
		// Reset sticky price to single product price × qty.
		var prod = cur();
		if (prod && refs.stickyPrice) {
			refs.stickyPrice.textContent = fmtPrice(prod.price * (state.qty || 1));
		}
		if (refs.stickyPriceWrap) refs.stickyPriceWrap.classList.remove('has-bt-total');
		if (refs.stickyBtPill) refs.stickyBtPill.style.display = 'none';

		if (btRefreshTimer) clearTimeout(btRefreshTimer);
		btRefreshTimer = setTimeout(function() {
			if (!refs.btEmbed) return;

			var body = new FormData();
			body.append('action', 'mh_stv_refresh_bt');
			body.append('nonce', BT_NONCE);
			body.append('product_id', productId);

			fetch(AJAX_URL, { method: 'POST', body: body, credentials: 'same-origin' })
				.then(function(res) { return res.json(); })
				.then(function(json) {
					if (!json.success || !refs.btEmbed) return;
					// Clear existing content.
					while (refs.btEmbed.firstChild) refs.btEmbed.removeChild(refs.btEmbed.firstChild);
					if (json.data.html) {
						var temp = document.createElement('div');
						temp.innerHTML = sanitizeHTML(json.data.html);
						while (temp.firstChild) refs.btEmbed.appendChild(temp.firstChild);
						refs.btEmbed.style.display = '';

						// Re-initialize the BT widget via globally exposed mhBtReinit().
						// This clears the dedup flag and runs mhBtInitWidget on all .mh-bt-widget elements.
						// The BT plugin's JS is already loaded (enqueued on page load) — only the HTML is new.
						if (typeof window.mhBtReinit === 'function') {
							window.mhBtReinit();
						} else if (typeof window.mhBtInit === 'function') {
							window.mhBtInit();
						}

						// Sync qty bridge.
						syncBoughtTogetherQty();

						// Update sticky bar BT pill (Fix #14 v5.4.0: single delayed call, MutationObserver handles rest).
						setTimeout(updateStickyBtPill, 300);

						// Right column height may have changed — recalc sticky top.
						updateStickyTop();
					} else {
						refs.btEmbed.style.display = 'none';
						updateStickyBtPill();
					}
				})
				.catch(function() { /* silently fail */ });
		}, 300);
	}

	/* ── JS-based Mobile DOM Reorder ──
	   Works WITH display:contents (CSS dissolves left/right boxes).
	   JS physically moves elements into .mh-stv-grid for definitive ordering.
	   CSS order: rules serve as fallback until this runs. */
	function applyLayout() {
		var mobile = window.matchMedia('(max-width: ' + STICKY_BREAKPOINT + 'px)').matches;
		if (!refs.grid || !refs.left || !refs.right) return;
		if (mobile === isMobileLayout) return;
		isMobileLayout = mobile;

		if (mobile) {
			// Move children from left/right into grid in desired order.
			var mobileOrder = [
				refs.productName,
				refs.reviewInline,
				refs.galleryWrap,
				refs.thumbsWrap,
				refs.actionBlock,
				refs.controls,
				refs.btEmbed
			];
			for (var i = 0; i < mobileOrder.length; i++) {
				if (mobileOrder[i]) refs.grid.appendChild(mobileOrder[i]);
			}
			// Also move any embed items from right column into grid on mobile.
			var embedsInRight = refs.right.querySelectorAll('.mh-stv-embed-item');
			for (var j = 0; j < embedsInRight.length; j++) {
				refs.grid.appendChild(embedsInRight[j]);
			}
			// Move below-grid embeds into grid on mobile.
			var embedsInBelow = refs.belowGrid ? refs.belowGrid.querySelectorAll('.mh-stv-embed-item') : [];
			for (var jb = 0; jb < embedsInBelow.length; jb++) {
				refs.grid.appendChild(embedsInBelow[jb]);
			}
		} else {
			// Restore desktop: move children back to original parents.
			var leftChildren = [refs.galleryWrap, refs.thumbsWrap, refs.controls];
			for (var k = 0; k < leftChildren.length; k++) {
				if (leftChildren[k]) refs.left.appendChild(leftChildren[k]);
			}
			var rightChildren = [
				refs.productName, refs.reviewInline, refs.actionBlock,
				refs.btEmbed
			];
			for (var m = 0; m < rightChildren.length; m++) {
				if (rightChildren[m]) refs.right.appendChild(rightChildren[m]);
			}
			// Restore right-column embeds
			var embedsInGrid = refs.grid.querySelectorAll('.mh-stv-embed-item:not(.mh-stv-embed-below)');
			for (var n = 0; n < embedsInGrid.length; n++) {
				refs.right.appendChild(embedsInGrid[n]);
			}
			// Restore below-grid embeds
			var belowEmbeds = refs.grid.querySelectorAll('.mh-stv-embed-below');
			for (var nb = 0; nb < belowEmbeds.length; nb++) {
				if (refs.belowGrid) refs.belowGrid.appendChild(belowEmbeds[nb]);
			}
		}
	}

	/* ── Full State Update ── */
	function update(resetGallery) {
		if (state.wall && !state.swing) state.wall = false;
		var prod = cur();
		if (!prod) return;

		if (resetGallery) state.galleryIdx = 0;

		// Clear tab cache on product change.
		tabCache = {};

		updateGallery(resetGallery);
		animatePrice(prod.price);
		updateDesigns();
		updateToggles();
		updateRight();
		updateTabs();
		updateComparisonView();

		// Fix v5.4.1: Announce product change to screen readers.
		if (refs.srAnnounce) {
			refs.srAnnounce.textContent = prod.name + ', ' + fmtPrice(prod.price);
		}

		// v5.2.0: Refresh BT BEFORE sticky CTA — clears old BT DOM immediately
		// so updateStickyCTA doesn't read stale BT total from previous product.
		refreshBoughtTogether(prod.id);
		updateStickyCTA();
		updateStickyTop();
	}

	/* ── Build Toggle (with ARIA) ── */
	function buildToggle(cfg) {
		var row = el('div', 'mh-stv-toggle');
		row.setAttribute('data-toggle', cfg.id);
		row.setAttribute('role', 'switch');
		row.setAttribute('tabindex', '0');
		row.setAttribute('aria-label', cfg.label);

		if (cfg.id === 'swing' && state.swing) {
			row.classList.add('is-on');
			row.setAttribute('aria-checked', 'true');
		} else if (cfg.id === 'wall' && state.wall) {
			row.classList.add('is-on');
			row.setAttribute('aria-checked', 'true');
		} else {
			row.setAttribute('aria-checked', 'false');
		}

		if (cfg.id === 'wall' && !state.swing) {
			row.classList.add('is-disabled');
			row.setAttribute('aria-disabled', 'true');
			row.setAttribute('tabindex', '-1');
		}

		row.addEventListener('click', cfg.onClick);
		row.addEventListener('keydown', function(e) {
			if (e.key === 'Enter' || e.key === ' ') {
				e.preventDefault();
				cfg.onClick();
			}
		});

		var sw = el('div', 'mh-stv-switch');
		sw.appendChild(el('div', 'mh-stv-knob'));
		row.appendChild(sw);

		var info = el('div', 'mh-stv-toggle-info');
		var nm = el('div', 'mh-stv-toggle-name');
		nm.textContent = cfg.label;
		info.appendChild(nm);
		var desc = el('div', 'mh-stv-toggle-desc');
		desc.textContent = cfg.desc;
		info.appendChild(desc);
		row.appendChild(info);

		var delta = el('div', 'mh-stv-toggle-delta');
		row.appendChild(delta);

		return row;
	}

	/* ── Build DOM ── */
	function render() {
		// Remove skeleton loading state (if present from PHP).
		var skeleton = ROOT.querySelector('.mh-stv-skeleton');
		if (skeleton) skeleton.parentNode.removeChild(skeleton);
		// Clear any remaining content.
		while (ROOT.firstChild) ROOT.removeChild(ROOT.firstChild);

		var grid = el('div', 'mh-stv-grid');
		refs.grid = grid;

		// ═══ LEFT COLUMN ═══
		var left = el('div', 'mh-stv-left');
		refs.left = left;

		// Gallery (keyboard navigable + click to lightbox).
		var gallery = el('div', 'mh-stv-gallery');
		gallery.setAttribute('tabindex', '0');
		gallery.setAttribute('role', 'region');
		gallery.setAttribute('aria-label', 'Produktgalerie \u2014 Pfeiltasten zum Bl\u00e4ttern, Klick zum Vergr\u00f6\u00dfern');
		gallery.addEventListener('keydown', galleryKeyHandler);
		gallery.addEventListener('click', function(e) {
			// Don't trigger lightbox if clicking on counter or hint or after swipe.
			if (galSwiping) return;
			if (state.is360) return; // 360° spinner handles its own interactions
			if (e.target.classList.contains('mh-stv-gallery-counter') ||
				e.target.classList.contains('mh-stv-gallery-hint')) return;
			openLightbox(state.galleryIdx);
		});
		refs.galleryWrap = gallery;

		// ── Hero image (single element — v5.2.0 architecture) ──
		var heroImg = el('img', 'mh-stv-hero-img');
		heroImg.alt = 'Produktbild';
		heroImg.draggable = false;
		heroImg.setAttribute('fetchpriority', 'high');
		heroImg.width = 800;
		heroImg.height = 600;
		refs.heroImg = heroImg;
		gallery.appendChild(heroImg);

		// ── Peek image (for swipe slide-in animation) ──
		var peekImg = el('img', 'mh-stv-peek-img');
		peekImg.alt = '';
		peekImg.draggable = false;
		peekImg.setAttribute('decoding', 'async');
		refs.peekImg = peekImg;
		gallery.appendChild(peekImg);

		// ── Gallery loading indicator ──
		var galleryLoader = el('div', 'mh-stv-gallery-loader');
		var loaderRing = el('div', 'mh-stv-gallery-loader-ring');
		galleryLoader.appendChild(loaderRing);
		refs.galleryLoader = galleryLoader;
		gallery.appendChild(galleryLoader);

		// ── Swipe / Drag Gallery (touch + mouse, with visual slide feedback) ──
		var galDragging = false;
		var galSwiping = false;  // true once drag passes threshold (prevents lightbox)
		var galStartX = 0;
		var galDx = 0;
		var galPeekDir = 0;  // tracks which direction the peek is for
		var galWidth = 0;

		function galGetImages() {
			var prod = cur();
			if (!prod) return [];
			return prod.gallery && prod.gallery.length ? prod.gallery : (prod.image ? [prod.image] : []);
		}

		function galPeekIndex(direction) {
			var gal = galGetImages();
			if (gal.length < 2) return -1;
			if (direction < 0) return (state.galleryIdx + 1) % gal.length;       // swipe left → next
			return (state.galleryIdx - 1 + gal.length) % gal.length;             // swipe right → prev
		}

		function galShowPeek(direction) {
			var gal = galGetImages();
			var idx = galPeekIndex(direction);
			if (idx < 0 || !refs.peekImg) return;
			var targetSrc = gal[idx] || '';
			if (!targetSrc) return;
			refs.peekImg.src = targetSrc;
			refs.peekImg.classList.add('is-visible');
			// Position offscreen in the swipe-from direction.
			refs.peekImg.style.transform = 'translateX(' + (direction < 0 ? '100%' : '-100%') + ')';
			galPeekDir = direction;
		}

		function galHidePeek() {
			if (refs.peekImg) {
				refs.peekImg.classList.remove('is-visible');
				refs.peekImg.style.transform = '';
			}
			galPeekDir = 0;
		}

		function galOnDragStart(x) {
			if (state.is360) return; // 360° spinner handles its own drag
			var gal = galGetImages();
			if (gal.length < 2) return;
			galDragging = true;
			galSwiping = false;
			galStartX = x;
			galDx = 0;
			galWidth = gallery.offsetWidth || 400;
			gallery.classList.add('is-dragging');
		}

		function galOnDragMove(x) {
			if (!galDragging) return;
			galDx = x - galStartX;
			if (Math.abs(galDx) > 10) galSwiping = true;

			// Move current hero image.
			if (refs.heroImg) refs.heroImg.style.transform = 'translateX(' + galDx + 'px)';

			// Show/update peek image.
			var direction = galDx > 0 ? 1 : -1;
			// If direction changed mid-drag, reset peek.
			if (refs.peekImg && refs.peekImg.classList.contains('is-visible') && galPeekDir !== direction) {
				galHidePeek();
			}
			if (!refs.peekImg || !refs.peekImg.classList.contains('is-visible')) {
				galShowPeek(direction);
			}
			if (refs.peekImg && refs.peekImg.classList.contains('is-visible')) {
				var offset = (direction < 0)
					? galWidth + galDx   // from right
					: -galWidth + galDx; // from left
				refs.peekImg.style.transform = 'translateX(' + offset + 'px)';
			}
		}

		function galOnDragEnd() {
			if (!galDragging) return;
			galDragging = false;
			gallery.classList.remove('is-dragging');

			var threshold = galWidth * 0.2; // 20% of gallery width
			var gal = galGetImages();

			if (Math.abs(galDx) > threshold && gal.length > 1) {
				// Commit swipe — instant switch (no animation).
				var newIdx = galPeekIndex(galDx > 0 ? 1 : -1);
				// Reset transforms immediately.
				if (refs.heroImg) { refs.heroImg.style.transition = ''; refs.heroImg.style.transform = ''; }
				galHidePeek();
				// Update state and gallery.
				if (newIdx >= 0) state.galleryIdx = newIdx;
				updateGallery();
			} else {
				// Snap back — instant reset.
				if (refs.heroImg) { refs.heroImg.style.transition = ''; refs.heroImg.style.transform = ''; }
				galHidePeek();
			}

			galDx = 0;
			// Reset galSwiping after a tick so the click event (which fires after mouseup)
			// is still blocked, but subsequent clicks can open the lightbox.
			setTimeout(function() { galSwiping = false; }, 50);
		}

		// Touch events.
		gallery.addEventListener('touchstart', function(e) {
			if (e.touches.length === 1) galOnDragStart(e.touches[0].clientX);
		}, { passive: true });
		gallery.addEventListener('touchmove', function(e) {
			if (e.touches.length === 1) {
				galOnDragMove(e.touches[0].clientX);
				// Prevent vertical scroll while swiping horizontally.
				if (galSwiping) e.preventDefault();
			}
		}, { passive: false });
		gallery.addEventListener('touchend', function(e) {
			galOnDragEnd();
			if (galSwiping) e.preventDefault();
		}, { passive: false });

		// Mouse / Pointer events (desktop drag).
		gallery.addEventListener('mousedown', function(e) {
			if (e.button !== 0) return; // left click only
			e.preventDefault();
			galOnDragStart(e.clientX);

			function onMouseMove(ev) { galOnDragMove(ev.clientX); }
			function onMouseUp() {
				galOnDragEnd();
				document.removeEventListener('mousemove', onMouseMove);
				document.removeEventListener('mouseup', onMouseUp);
			}
			document.addEventListener('mousemove', onMouseMove);
			document.addEventListener('mouseup', onMouseUp);
		});

		// Zoom indicator.
		var zoomIcon = el('div', 'mh-stv-gallery-zoom');
		zoomIcon.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="11" y1="8" x2="11" y2="14"/><line x1="8" y1="11" x2="14" y2="11"/></svg>';
		gallery.appendChild(zoomIcon);

		// Gallery counter.
		var counter = el('div', 'mh-stv-gallery-counter');
		refs.galleryCounter = counter;
		gallery.appendChild(counter);

		// Keyboard hint.
		var hint = el('div', 'mh-stv-gallery-hint');
		hint.textContent = '\u2190 \u2192 Pfeiltasten zum Bl\u00e4ttern';
		gallery.appendChild(hint);

		left.appendChild(gallery);

		// Thumbnails.
		var thumbs = el('div', 'mh-stv-thumbs');
		refs.thumbsWrap = thumbs;
		left.appendChild(thumbs);

		// Controls.
		var controls = el('div', 'mh-stv-controls');
		refs.controls = controls;

		// Design picker.
		if (D.series.length > 1) {
			var sec1 = el('div', 'mh-stv-section');
			var lbl1 = el('div', 'mh-stv-label');
			lbl1.textContent = decodeHTML(I18N.step1 || 'Design w\u00e4hlen');
			sec1.appendChild(lbl1);

			var designGrid = el('div', 'mh-stv-designs');
			for (var s = 0; s < D.series.length; s++) {
				(function(serie) {
					var imgUrl = findSerieImage(serie.key);

					var btn = el('button', 'mh-stv-design-btn');
					btn.setAttribute('data-serie', serie.key);
					btn.type = 'button';
					if (serie.key === state.serie) btn.classList.add('is-on');

					btn.addEventListener('click', function() {
						state.serie = serie.key;
						track('stv_serie_switch', serie.label || serie.key);
						update(true);
					});

					var imgW = el('div', 'mh-stv-design-img');
					if (imgUrl) {
						var im = el('img');
						im.src = imgUrl;
						im.alt = serie.label || serie.key;
						im.loading = 'lazy';
						imgW.appendChild(im);
					}
					var chk = el('div', 'mh-stv-design-check');
					chk.textContent = '\u2713';
					imgW.appendChild(chk);
					btn.appendChild(imgW);

					var txt = el('div', 'mh-stv-design-text');
					var nm = el('div', 'mh-stv-design-name');
					nm.textContent = (serie.icon ? serie.icon + ' ' : '') + (serie.label || serie.key);
					txt.appendChild(nm);
					var priceRow = el('div', 'mh-stv-design-price');
					priceRow.setAttribute('data-serie-price', serie.key);
					txt.appendChild(priceRow);
					btn.appendChild(txt);

					designGrid.appendChild(btn);
				})(D.series[s]);
			}
			sec1.appendChild(designGrid);
			controls.appendChild(sec1);
		}

		// Toggles.
		var sec2 = el('div', 'mh-stv-section');
		var lbl2 = el('div', 'mh-stv-label');
		lbl2.textContent = I18N.step2 || 'Ausstattung konfigurieren';
		sec2.appendChild(lbl2);

		var togglesWrap = el('div', 'mh-stv-toggles');
		var swingLevel = D.levels.length > 1 ? D.levels[1] : {};
		var wallLevel = D.levels.length > 2 ? D.levels[2] : {};
		togglesWrap.appendChild(buildToggle({
			id: 'swing',
			label: swingLevel.label || I18N.swingLabel || 'Schaukel',
			desc: swingLevel.toggle_desc || I18N.swingDesc || '',
			onClick: function() {
				state.swing = !state.swing;
				if (!state.swing) state.wall = false;
				track('stv_level_switch', 'swing_' + (state.swing ? 'on' : 'off'));
				update(true);
			}
		}));
		togglesWrap.appendChild(buildToggle({
			id: 'wall',
			label: wallLevel.label || I18N.wallLabel || 'Kletterwand',
			desc: wallLevel.toggle_desc || I18N.wallDesc || '',
			onClick: function() {
				if (state.swing) {
					state.wall = !state.wall;
					track('stv_level_switch', 'wall_' + (state.wall ? 'on' : 'off'));
					update(true);
				}
			}
		}));
		sec2.appendChild(togglesWrap);

		// Dependency hint.
		var depHint = el('div', 'mh-stv-dep-hint');
		var depIcon = el('div', 'mh-stv-dep-hint-icon');
		depIcon.textContent = 'i';
		depHint.appendChild(depIcon);
		var depText = el('span');
		var wallName = wallLevel.label || 'Kletterwand';
		depText.textContent = wallName + ' kann nur zusammen mit dem Schaukelanbau gew\u00e4hlt werden, da die Erweiterung an der Schaukelkonstruktion befestigt wird.';
		depHint.appendChild(depText);
		refs.depHint = depHint;
		if (!state.swing) depHint.classList.add('is-visible');
		sec2.appendChild(depHint);

		controls.appendChild(sec2);

		// Comparison view container.
		var lbl3 = el('div', 'mh-stv-label');
		lbl3.textContent = 'Ausstattung vergleichen';
		lbl3.style.marginTop = '6px';
		controls.appendChild(lbl3);

		var compareContainer = el('div');
		refs.compareContainer = compareContainer;
		controls.appendChild(compareContainer);

		left.appendChild(controls);
		grid.appendChild(left);

		// ═══ RIGHT COLUMN ═══
		var right = el('div', 'mh-stv-right');
		refs.right = right;

		// Product name.
		var prodName = el('h1', 'mh-stv-product-name');
		refs.productName = prodName;
		right.appendChild(prodName);

		// Fix v5.4.1: Screen reader live region for product switch announcements.
		var srAnnounce = el('div', 'mh-stv-sr-announce');
		srAnnounce.setAttribute('aria-live', 'assertive');
		srAnnounce.setAttribute('aria-atomic', 'true');
		srAnnounce.setAttribute('role', 'status');
		srAnnounce.style.cssText = 'position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0';
		refs.srAnnounce = srAnnounce;
		right.appendChild(srAnnounce);

		// Review stars (compact, under product name).
		var reviewInline = el('div', 'mh-stv-review-inline');
		refs.reviewInline = reviewInline;
		right.appendChild(reviewInline);

		// ── Action Block (visual grouping) ──
		var actionBlock = el('div', 'mh-stv-action-block');
		refs.actionBlock = actionBlock;

		// Price.
		var priceBlock = el('div', 'mh-stv-price-block');
		priceBlock.setAttribute('aria-live', 'polite');
		priceBlock.setAttribute('aria-atomic', 'true');
		priceEl = el('div', 'mh-stv-price-val');
		priceEl.setAttribute('role', 'status');
		priceBlock.appendChild(priceEl);
		var priceSuffix = el('span', 'mh-stv-price-suffix');
		priceSuffix.textContent = I18N.priceSuffix || 'inkl. MwSt, zzgl. Versand';
		priceBlock.appendChild(priceSuffix);
		actionBlock.appendChild(priceBlock);

		// Stock badge (only if enabled in admin settings).
		if (SHOW_STOCK) {
			var stockBadge = el('div', 'mh-stv-stock');
			var stockDot = el('span', 'mh-stv-stock-dot');
			stockBadge.appendChild(stockDot);
			var stockText = el('span', 'mh-stv-stock-text');
			stockBadge.appendChild(stockText);
			refs.stockBadge = stockBadge;
			actionBlock.appendChild(stockBadge);
		}

		// Included.
		var inclSection = el('div', 'mh-stv-included');
		var inclLabel = el('div', 'mh-stv-included-label');
		inclLabel.textContent = I18N.included || 'Inklusive';
		inclSection.appendChild(inclLabel);
		var inclList = el('div', 'mh-stv-included-list');
		refs.includedList = inclList;
		inclSection.appendChild(inclList);
		actionBlock.appendChild(inclSection);

		// CTA area.
		var ctaArea = el('div', 'mh-stv-cta-area');
		refs.ctaArea = ctaArea;
		actionBlock.appendChild(ctaArea);

		// Trust signals (only if configured in admin). Fix: DOM manipulation instead of innerHTML.
		var trustSvgs = [
			'<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>',
			'<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>',
			'<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>'
		];
		var trustTexts = [I18N.trust1 || '', I18N.trust2 || '', I18N.trust3 || ''];
		var hasTrust = trustTexts[0] || trustTexts[1] || trustTexts[2];
		if (hasTrust) {
			var trustRow = el('div', 'mh-stv-trust');
			for (var ti = 0; ti < 3; ti++) {
				if (trustTexts[ti]) {
					var item = el('div', 'mh-stv-trust-item');
					// Parse SVG safely via template element.
					var tpl = document.createElement('template');
					tpl.innerHTML = trustSvgs[ti];
					if (tpl.content.firstChild) item.appendChild(tpl.content.firstChild);
					var txtSpan = el('span');
					txtSpan.textContent = decodeHTML(trustTexts[ti]);
					item.appendChild(txtSpan);
					trustRow.appendChild(item);
				}
			}
			actionBlock.appendChild(trustRow);
		}

		// Payment icons placeholder (filled by deferred capture).
		var PAYMENT_SEL = (mhStvData.paymentSelector || '').trim();
		var paymentSlot = null;
		if (PAYMENT_SEL) {
			paymentSlot = el('div', 'mh-stv-payment-embed');
			actionBlock.appendChild(paymentSlot);
		}

		right.appendChild(actionBlock);

		// ═══ BOUGHT TOGETHER (shortcode embed from mh-bought-together plugin) ═══
		// Always create wrapper. PHP may have rendered initial content into #mh-stv-bt-source.
		// JS refreshes content via AJAX when the configurator switches products.
		var btWrap = el('div', 'mh-stv-bt-embed');
		refs.btEmbed = btWrap;
		var btSource = document.getElementById('mh-stv-bt-source');
		if (btSource && btSource.innerHTML.trim()) {
			btSource.style.display = '';
			btSource.removeAttribute('id');
			btWrap.appendChild(btSource);
		} else {
			btWrap.style.display = 'none';
		}
		right.appendChild(btWrap);

		// ═══ EMBED EXTERNAL ELEMENTS (Oxygen reusable templates etc.) ═══
		// Deferred: Oxygen reusable templates may render after this script.
		// We retry a few times with increasing delay to catch them.
		var embedSels = mhStvData.embedSelectors || [];
		var belowSels = mhStvData.belowGridSelectors || [];
		var totalTargets = embedSels.length + belowSels.length + (PAYMENT_SEL ? 1 : 0);
		if (totalTargets > 0) {
			var captureAll = function(attempt) {
				var found = 0;

				// 1) Payment icons → into action block slot.
				if (PAYMENT_SEL && paymentSlot) {
					if (paymentSlot.children.length > 0) {
						found++; // already captured
					} else {
						try {
							var payEl = document.querySelector(PAYMENT_SEL);
							if (payEl) {
								payEl.parentNode.removeChild(payEl);
								paymentSlot.appendChild(payEl);
								found++;
							}
						} catch (e) { found++; }
					}
				}

				// 2) Right-column embed selectors (Verfügbarkeit, Kundenprojekte etc.)
				for (var ei = 0; ei < embedSels.length; ei++) {
					var sel = embedSels[ei];
					if (right.querySelector('[data-mh-stv-embed="r' + ei + '"]')) {
						found++;
						continue;
					}
					try {
						var embedEl = document.querySelector(sel);
						if (embedEl) {
							var embedWrap = el('div', 'mh-stv-embed-item');
							embedWrap.setAttribute('data-mh-stv-embed', 'r' + ei);
							embedEl.parentNode.removeChild(embedEl);
							embedWrap.appendChild(embedEl);
							right.appendChild(embedWrap);
							found++;
						}
					} catch (e) {
						found++;
					}
				}

				// 3) Below-grid embed selectors (Trust icons, Klarna etc.)
				if (refs.belowGrid) {
					for (var bi = 0; bi < belowSels.length; bi++) {
						var bsel = belowSels[bi];
						if (refs.belowGrid.querySelector('[data-mh-stv-embed="b' + bi + '"]')) {
							found++;
							continue;
						}
						try {
							var belowEl = document.querySelector(bsel);
							if (belowEl) {
								var belowWrap = el('div', 'mh-stv-embed-item mh-stv-embed-below');
								belowWrap.setAttribute('data-mh-stv-embed', 'b' + bi);
								belowEl.parentNode.removeChild(belowEl);
								belowWrap.appendChild(belowEl);
								refs.belowGrid.appendChild(belowWrap);
								found++;
							}
						} catch (e) {
							found++;
						}
					}
				}

				// Retry up to 5 times (50ms, 200ms, 500ms, 1000ms, 2000ms).
				if (found < totalTargets && attempt < 5) {
					var delays = [50, 200, 500, 1000, 2000];
					setTimeout(function() { captureAll(attempt + 1); }, delays[attempt]);
				}
			};
			setTimeout(function() { captureAll(0); }, 0);
		}

		grid.appendChild(right);
		ROOT.appendChild(grid);

		// ═══ BELOW-GRID: full-width area for embed items (trust icons, Klarna etc.) ═══
		var belowGrid = el('div', 'mh-stv-below-grid');
		refs.belowGrid = belowGrid;
		ROOT.appendChild(belowGrid);

		// ═══ TABS (full width below grid, ARIA pattern) ═══
		var tabsWrap = el('div', 'mh-stv-tabs');

		// Tab buttons with ARIA.
		var tabNav = el('div', 'mh-stv-tab-nav');
		tabNav.setAttribute('role', 'tablist');
		tabNav.setAttribute('aria-label', 'Produktinformationen');

		var tabDefs = [
			{ key: 'description', label: decodeHTML(I18N.tabDescription || 'Beschreibung') },
			{ key: 'attributes',  label: decodeHTML(I18N.tabAttributes || 'Zus\u00e4tzliche Informationen') },
			{ key: 'productdata', label: decodeHTML(I18N.tabProductData || 'Produktdaten') },
			{ key: 'reviews',     label: decodeHTML(I18N.tabReviews || 'Bewertungen') }
		];

		for (var ti = 0; ti < tabDefs.length; ti++) {
			(function(td) {
				var btn = el('button', 'mh-stv-tab-btn');
				btn.type = 'button';
				btn.setAttribute('role', 'tab');
				btn.setAttribute('data-tab', td.key);
				btn.setAttribute('id', 'mh-stv-tab-' + td.key);
				btn.setAttribute('aria-controls', 'mh-stv-panel-' + td.key);
				var isActive = td.key === state.activeTab;
				if (isActive) btn.classList.add('is-active');
				btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
				btn.setAttribute('tabindex', isActive ? '0' : '-1');

				var label = document.createTextNode(td.label + ' ');
				btn.appendChild(label);

				if (td.key === 'reviews') {
					var count = el('span', 'mh-stv-tab-count');
					btn.appendChild(count);
				}

				btn.addEventListener('click', function() { switchTab(td.key); });
				btn.addEventListener('keydown', tabKeyHandler);
				tabNav.appendChild(btn);
			})(tabDefs[ti]);
		}
		tabsWrap.appendChild(tabNav);

		// Tab panels with ARIA.
		var panelDesc = el('div', 'mh-stv-tab-panel is-active');
		panelDesc.setAttribute('role', 'tabpanel');
		panelDesc.setAttribute('id', 'mh-stv-panel-description');
		panelDesc.setAttribute('aria-labelledby', 'mh-stv-tab-description');
		panelDesc.setAttribute('data-panel', 'description');
		panelDesc.setAttribute('tabindex', '0');
		refs.tabDescription = panelDesc;
		tabsWrap.appendChild(panelDesc);

		var panelAttr = el('div', 'mh-stv-tab-panel');
		panelAttr.setAttribute('role', 'tabpanel');
		panelAttr.setAttribute('id', 'mh-stv-panel-attributes');
		panelAttr.setAttribute('aria-labelledby', 'mh-stv-tab-attributes');
		panelAttr.setAttribute('data-panel', 'attributes');
		panelAttr.setAttribute('tabindex', '0');
		refs.tabAttributes = panelAttr;
		tabsWrap.appendChild(panelAttr);

		var panelMeta = el('div', 'mh-stv-tab-panel');
		panelMeta.setAttribute('role', 'tabpanel');
		panelMeta.setAttribute('id', 'mh-stv-panel-productdata');
		panelMeta.setAttribute('aria-labelledby', 'mh-stv-tab-productdata');
		panelMeta.setAttribute('data-panel', 'productdata');
		panelMeta.setAttribute('tabindex', '0');
		refs.tabProductData = panelMeta;
		tabsWrap.appendChild(panelMeta);

		var panelRev = el('div', 'mh-stv-tab-panel');
		panelRev.setAttribute('role', 'tabpanel');
		panelRev.setAttribute('id', 'mh-stv-panel-reviews');
		panelRev.setAttribute('aria-labelledby', 'mh-stv-tab-reviews');
		panelRev.setAttribute('data-panel', 'reviews');
		panelRev.setAttribute('tabindex', '0');
		refs.tabReviews = panelRev;
		tabsWrap.appendChild(panelRev);

		ROOT.appendChild(tabsWrap);

		// ═══ STICKY CTA BAR v5.1.0 (desktop top + mobile bottom) ═══
		// Appended to document.body → outside #mh-stv-configurator.
		var accentColor = mhStvData.accentColor || '#e8910c';

		var stickyBar = el('div', 'mh-stv-sticky-cta');
		stickyBar.setAttribute('role', 'complementary');
		stickyBar.setAttribute('aria-label', 'Produkt-Schnellkauf');

		// ── Inner wrapper (max-width container for desktop centering) ──
		var stickyInner = el('div', 'mh-stv-sticky-inner');

		// ── Product info area (thumbnail + name + price) ──
		var stickyInfo = el('div', 'mh-stv-sticky-info');

		// Thumbnail.
		var stickyThumb = el('img', 'mh-stv-sticky-thumb');
		stickyThumb.alt = '';
		stickyThumb.loading = 'lazy';
		refs.stickyThumb = stickyThumb;
		stickyInfo.appendChild(stickyThumb);

		// Name + price (baseline-aligned on desktop, row on mobile).
		var stickyMeta = el('div', 'mh-stv-sticky-meta');
		var stickyName = el('div', 'mh-stv-sticky-name');
		refs.stickyName = stickyName;
		stickyMeta.appendChild(stickyName);

		// Price wrap: label ("Gesamt") + price value — label hidden until BT total active.
		var stickyPriceWrap = el('div', 'mh-stv-sticky-price-wrap');
		refs.stickyPriceWrap = stickyPriceWrap;
		var stickyPriceLabel = el('div', 'mh-stv-sticky-price-label');
		stickyPriceLabel.textContent = 'Gesamt';
		stickyPriceWrap.appendChild(stickyPriceLabel);
		var stickyPrice = el('div', 'mh-stv-sticky-price');
		stickyPrice.style.color = accentColor;
		refs.stickyPrice = stickyPrice;
		stickyPriceWrap.appendChild(stickyPrice);
		stickyMeta.appendChild(stickyPriceWrap);

		stickyInfo.appendChild(stickyMeta);
		stickyInner.appendChild(stickyInfo);

		// ── BT zone (wrapper for pill — hidden when no BT) ──
		var stickyBtZone = el('div', 'mh-stv-sticky-bt-zone');
		refs.stickyBtZone = stickyBtZone;

		var stickyBtPill = el('button', 'mh-stv-sticky-bt-pill');
		stickyBtPill.type = 'button';
		stickyBtPill.setAttribute('aria-label', 'Zubeh\u00f6rauswahl anzeigen');
		stickyBtPill.style.display = 'none';
		refs.stickyBtPill = stickyBtPill;
		stickyBtPill.addEventListener('click', function() {
			if (refs.btEmbed) {
				refs.btEmbed.scrollIntoView({ behavior: 'smooth', block: 'center' });
			}
		});
		stickyBtZone.appendChild(stickyBtPill);
		stickyInner.appendChild(stickyBtZone);

		// ── Actions area (qty + button) ──
		var stickyActions = el('div', 'mh-stv-sticky-actions');

		// Qty selector.
		var stickyQtyWrap = el('div', 'mh-stv-sticky-qty-wrap');
		var sqMinus = el('button', 'mh-stv-sticky-qty-btn');
		sqMinus.type = 'button';
		sqMinus.textContent = '\u2212';
		sqMinus.setAttribute('aria-label', 'Menge verringern');
		sqMinus.addEventListener('click', function() {
			if (state.qty > 1) {
				state.qty--;
				syncStickyQty();
			}
		});
		stickyQtyWrap.appendChild(sqMinus);

		var sqInput = el('input', 'mh-stv-sticky-qty-input');
		sqInput.type = 'text';
		sqInput.inputMode = 'numeric';
		sqInput.pattern = '[0-9]*';
		sqInput.value = String(state.qty);
		sqInput.setAttribute('aria-label', 'Anzahl');
		refs.stickyQtyInput = sqInput;
		sqInput.addEventListener('input', function() {
			this.value = this.value.replace(/[^0-9]/g, '');
			var v = parseInt(this.value, 10);
			var max = getQtyMax();
			if (!v || v < 1) { v = 1; this.value = '1'; }
			if (v > max) { v = max; this.value = String(max); }
			state.qty = v;
			syncMainQty();
		});
		stickyQtyWrap.appendChild(sqInput);

		var sqPlus = el('button', 'mh-stv-sticky-qty-btn');
		sqPlus.type = 'button';
		sqPlus.textContent = '+';
		sqPlus.setAttribute('aria-label', 'Menge erh\u00f6hen');
		sqPlus.addEventListener('click', function() {
			var max = getQtyMax();
			if (state.qty < max) {
				state.qty++;
				syncStickyQty();
			}
		});
		stickyQtyWrap.appendChild(sqPlus);
		stickyActions.appendChild(stickyQtyWrap);

		// Add-to-cart button.
		var stickyBtnText = decodeHTML(I18N.addToCart || 'In den Warenkorb');
		var stickyBtn = el('button', 'mh-stv-sticky-btn');
		stickyBtn.type = 'button';
		stickyBtn.textContent = stickyBtnText;
		stickyBtn.style.background = accentColor;
		refs.stickyBtn = stickyBtn;
		stickyBtn.addEventListener('click', function() {
			var p = cur();
			if (!p) return;
			addToCartAjax(p.id, state.qty, stickyBtn, stickyBtnText, null);
		});
		stickyActions.appendChild(stickyBtn);

		stickyInner.appendChild(stickyActions);
		stickyBar.appendChild(stickyInner);
		refs.stickyBar = stickyBar;
		document.body.appendChild(stickyBar);

		// ── Sync helpers: keep main qty ↔ sticky qty in sync ──
		function syncStickyQty() {
			if (refs.stickyQtyInput) refs.stickyQtyInput.value = String(state.qty);
			if (refs.qtyInput) refs.qtyInput.value = String(state.qty);
			// v5.3.1: Immediately update sticky price (single product × qty).
			// BT pill may override with bundle total via staggered retries below.
			var prod = cur();
			if (prod && refs.stickyPrice) {
				refs.stickyPrice.textContent = fmtPrice(prod.price * state.qty);
			}
			// v5.2.0: Also sync BT widget qty + refresh sticky price.
			syncBoughtTogetherQty();
			// Fix #14 v5.4.0: Single delayed call, MutationObserver handles rest.
			setTimeout(updateStickyBtPill, 200);
		}
		function syncMainQty() {
			if (refs.qtyInput) refs.qtyInput.value = String(state.qty);
			// v5.3.1: Immediately update sticky price (single product × qty).
			var prod = cur();
			if (prod && refs.stickyPrice) {
				refs.stickyPrice.textContent = fmtPrice(prod.price * state.qty);
			}
			syncBoughtTogetherQty();
			setTimeout(updateStickyBtPill, 200);
		}

		// Set initial state (lazy load only current product images).
		var prod = cur();
		if (prod) {
			prevPrice = prod.price;
			setPriceText(prod.price);
		}
		updateGallery(true);
		updateDesigns();
		updateToggles();
		updateRight();
		updateTabs();
		updateComparisonView();
		updateStickyCTA();

		// Set initial bought-together product ID (matches PHP-rendered shortcode).
		btProductId = prod ? prod.id : null;

		// Create the hidden quantity bridge so mh-bought-together can find it.
		ensureBtQtyBridge();

		// ── Mobile DOM reorder (replaces display:contents CSS approach) ──
		applyLayout();
		var mql = window.matchMedia('(max-width: ' + STICKY_BREAKPOINT + 'px)');
		if (mql.addEventListener) {
			mql.addEventListener('change', applyLayout);
		} else if (mql.addListener) {
			mql.addListener(applyLayout);
		}

		// ── Scroll observer for sticky CTA visibility ──
		initStickyObserver();

		// ── Delayed BT pill refresh (Fix #14 v5.4.0: single call, MutationObserver handles rest) ──
		setTimeout(updateStickyBtPill, 500);

		// ── Smart sticky right column ──
		updateStickyTop();
		window.addEventListener('resize', updateStickyTop);

		// ── Smart progressive preloading (v5.2.0) ──
		// 1. Current product gallery is already loaded by updateGallery()
		// 2. After idle: preload same-serie products, then other series
		var preheat = function() {
			var currentSerie = state.serie;
			// Same serie first (most likely to switch to).
			for (var pi = 0; pi < D.products.length; pi++) {
				if (D.products[pi].serie === currentSerie) {
					preloadProductGallery(D.products[pi], false);
				}
			}
			// Then other series (lower priority).
			setTimeout(function() {
				for (var pi2 = 0; pi2 < D.products.length; pi2++) {
					if (D.products[pi2].serie !== currentSerie) {
						preloadProductGallery(D.products[pi2], false);
					}
				}
			}, 2000);
		};
		if ('requestIdleCallback' in window) {
			requestIdleCallback(preheat);
		} else {
			setTimeout(preheat, 3000);
		}

		// Analytics: track configurator view.
		track('stv_view');
	}

	/* ── Smart Sticky Top (desktop only) ──
	   Dynamically sets top value on .mh-stv-right:
	   - If right column fits in viewport → top: 80px (sticks at top)
	   - If right column is taller → negative top so the bottom is visible when stuck
	   Called on init, resize, and after content changes (BT refresh, toggle). */
	var stickyTopRAF = null;
	function updateStickyTop() {
		if (stickyTopRAF) return; // debounce via rAF
		stickyTopRAF = requestAnimationFrame(function() {
			stickyTopRAF = null;
			if (!refs.right || isMobileLayout) return;
			var rightH = refs.right.offsetHeight;
			var viewH = window.innerHeight;
			var headerOffset = 80; // px below sticky navbar
			var bottomPad = 20;

			if (rightH <= viewH - headerOffset - bottomPad) {
				// Fits — stick at top
				refs.right.style.top = headerOffset + 'px';
			} else {
				// Taller than viewport — stick so bottom aligns with viewport bottom
				refs.right.style.top = -(rightH - viewH + bottomPad) + 'px';
			}
		});
	}

	/* ── Sticky CTA Update (v5.2.0: always set price directly) ── */
	function updateStickyCTA() {
		var prod = cur();
		if (!prod) return;

		// Product name.
		if (refs.stickyName) refs.stickyName.textContent = prod.name || '';

		// Thumbnail.
		if (refs.stickyThumb) {
			var thumbSrc = prod.image || '';
			if (thumbSrc && refs.stickyThumb.src !== thumbSrc) {
				refs.stickyThumb.src = thumbSrc;
				refs.stickyThumb.style.display = '';
			} else if (!thumbSrc) {
				refs.stickyThumb.style.display = 'none';
			}
		}

		// Qty sync.
		if (refs.stickyQtyInput) refs.stickyQtyInput.value = String(state.qty);

		// Always set default price first (single product price × qty).
		if (refs.stickyPrice) {
			refs.stickyPrice.textContent = fmtPrice(prod.price * state.qty);
		}
		if (refs.stickyPriceWrap) {
			refs.stickyPriceWrap.classList.remove('has-bt-total');
		}

		// BT pill may override price with bundle total.
		updateStickyBtPill();
	}

	/* ── BT Pill: read bought-together product names from DOM ──
	   mh-bought-together v3.x DOM structure:
	   - .mh-bt-widget                         (primary box)
	   - .mh-bt-widget.mh-bt-widget--optional   (optional box)
	   - Main product: .mh-bt-product--main → input[type=hidden].mh-bt-checkbox (always "on")
	   - Linked products: .mh-bt-product → input[type=checkbox].mh-bt-checkbox--linked
	   - Product name: .mh-bt-product__name (may contain <a>)
	   - Checked row: .mh-bt-checked  /  Unchecked row: .mh-bt-unchecked */
	function updateStickyBtPill() {
		if (!refs.stickyBtPill) return;
		if (!refs.btEmbed || refs.btEmbed.style.display === 'none') {
			refs.stickyBtPill.style.display = 'none';
			updateStickyPrice(false);
			return;
		}

		// Collect all BT widgets (primary + optional box).
		var btWidgets = refs.btEmbed.querySelectorAll('.mh-bt-widget');
		if (!btWidgets.length) {
			refs.stickyBtPill.style.display = 'none';
			updateStickyPrice(false);
			return;
		}

		var checkedNames = [];

		for (var w = 0; w < btWidgets.length; w++) {
			var widget = btWidgets[w];

			// Find all LINKED checkboxes that are checked (skip main product — it's a hidden input).
			var checkboxes = widget.querySelectorAll('.mh-bt-checkbox--linked:checked');
			for (var i = 0; i < checkboxes.length; i++) {
				var row = checkboxes[i].closest('.mh-bt-product');
				if (!row) continue;
				// Skip rows explicitly marked unchecked (defensive).
				if (row.classList.contains('mh-bt-unchecked')) continue;
				var nameEl = row.querySelector('.mh-bt-product__name');
				if (nameEl) {
					var name = nameEl.textContent.trim();
					if (name) checkedNames.push(name);
				}
			}

			// Fallback: if no --linked checkboxes found (older BT version), check for
			// .mh-bt-product rows with .mh-bt-checked class, excluding main.
			if (checkboxes.length === 0) {
				var checkedRows = widget.querySelectorAll('.mh-bt-product.mh-bt-checked:not(.mh-bt-product--main)');
				for (var j = 0; j < checkedRows.length; j++) {
					var nm = checkedRows[j].querySelector('.mh-bt-product__name');
					if (nm) {
						var n = nm.textContent.trim();
						if (n) checkedNames.push(n);
					}
				}
			}
		}

		if (checkedNames.length === 0) {
			refs.stickyBtPill.style.display = 'none';
			// Reset price to single product.
			updateStickyPrice(false);
			return;
		}

		// Build pill DOM: [package-icon] + "Rutsche Grün, Bodenanker" + [chevron]
		while (refs.stickyBtPill.firstChild) refs.stickyBtPill.removeChild(refs.stickyBtPill.firstChild);

		// Package SVG icon.
		var iconTpl = document.createElement('template');
		iconTpl.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#c07b2a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>';
		if (iconTpl.content.firstChild) refs.stickyBtPill.appendChild(iconTpl.content.firstChild);

		// Text span (truncatable).
		var pillTextSpan = el('span', 'mh-stv-sticky-bt-pill-text');
		var labelText = '+ ';
		if (checkedNames.length <= 2) {
			labelText += checkedNames.join(', ');
		} else {
			labelText += checkedNames.slice(0, 2).join(', ') + ' +' + (checkedNames.length - 2);
		}
		pillTextSpan.textContent = labelText;
		refs.stickyBtPill.appendChild(pillTextSpan);

		// Chevron SVG (scroll hint).
		var chevTpl = document.createElement('template');
		chevTpl.innerHTML = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#c07b2a" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;opacity:.5"><polyline points="6 9 12 15 18 9"/></svg>';
		if (chevTpl.content.firstChild) refs.stickyBtPill.appendChild(chevTpl.content.firstChild);

		refs.stickyBtPill.style.display = '';

		// Update price to BT total.
		updateStickyPrice(true);
	}

	/* ── Sticky Price: toggle between single product price and BT bundle total ── */
	function updateStickyPrice(showBtTotal) {
		var prod = cur();
		if (!prod || !refs.stickyPrice) return;

		if (showBtTotal && refs.btEmbed) {
			// Try reading the BT total from the widget DOM.
			var totalEl = refs.btEmbed.querySelector('.mh-bt-total__price');
			if (totalEl) {
				var totalText = totalEl.textContent.trim();
				if (totalText) {
					refs.stickyPrice.textContent = totalText;
					if (refs.stickyPriceWrap) refs.stickyPriceWrap.classList.add('has-bt-total');
					return;
				}
			}
		}

		// Default: show single product price × qty.
		refs.stickyPrice.textContent = fmtPrice(prod.price * (state.qty || 1));
		if (refs.stickyPriceWrap) refs.stickyPriceWrap.classList.remove('has-bt-total');
	}

	/* ── Sticky CTA scroll observer (v5.1.0: desktop top + mobile bottom) ── */
	function initStickyObserver() {
		if (!refs.stickyBar || !refs.ctaArea) return;
		if (!('IntersectionObserver' in window)) return;

		var stickyVisible = false;

		var observer = new IntersectionObserver(function(entries) {
			for (var i = 0; i < entries.length; i++) {
				stickyVisible = !entries[i].isIntersecting;
				applyStickyVisibility();
			}
		}, { threshold: 0 });

		observer.observe(refs.ctaArea);

		function applyStickyVisibility() {
			var isMobile = window.innerWidth <= STICKY_BREAKPOINT;
			var wasVisible = refs.stickyBar.classList.contains('is-visible');
			if (stickyVisible) {
				refs.stickyBar.classList.add('is-visible');
				// Toggle position mode.
				if (isMobile) {
					refs.stickyBar.classList.add('is-mobile');
					refs.stickyBar.classList.remove('is-desktop');
				} else {
					refs.stickyBar.classList.add('is-desktop');
					refs.stickyBar.classList.remove('is-mobile');
				}
				// Refresh BT pill every time bar becomes visible (catches lazy-init).
				if (!wasVisible) {
					setTimeout(updateStickyBtPill, 50);
				}
			} else {
				refs.stickyBar.classList.remove('is-visible');
			}
		}

		// Re-evaluate on resize.
		window.addEventListener('resize', applyStickyVisibility);

		// ── MutationObserver on BT widget: update pill when BT checkboxes change ──
		if (refs.btEmbed) {
			var btMutObs = new MutationObserver(function() {
				// Debounce: BT plugin may batch multiple class/attribute changes.
				setTimeout(updateStickyBtPill, 60);
			});
			btMutObs.observe(refs.btEmbed, { childList: true, subtree: true, attributes: true, attributeFilter: ['checked', 'class'] });

			// Listen for native change events on checkboxes.
			refs.btEmbed.addEventListener('change', function(e) {
				if (e.target && e.target.classList.contains('mh-bt-checkbox--linked')) {
					track('stv_bt_interact');
					setTimeout(updateStickyBtPill, 50);
				}
			});

			// Fallback: click on product label (jQuery may not fire native change).
			refs.btEmbed.addEventListener('click', function(e) {
				var label = e.target.closest('.mh-bt-product__label');
				if (label) {
					// Let the BT plugin process the click first, then read state.
					setTimeout(updateStickyBtPill, 100);
				}
			});
		}
	}

	/* ── Lightbox v4.9.0 (slide transitions, drag-to-swipe, double-tap zoom, preload) ── */
	var lightbox = null;
	var lightboxImg = null;
	var lightboxPeekImg = null;  // Second image for slide-in during swipe
	var lightboxCounter = null;
	var lightboxThumbs = null;
	var lightboxIdx = 0;
	var lightboxGallery = [];
	var lightboxThumbnails = [];

	// 360° in lightbox state.
	var LB_360_MARKER = '__360__';
	var lb360Active = false;       // true when 360° spinner is running inside lightbox
	var lb360Idx = -1;             // lightbox index of the 360° slide (-1 = none)
	var lb360SpinnerRef = null;    // MH360 instance in lightbox
	var lb360WrapRef = null;       // wrapper div for spinner

	// Zoom state.
	var lbZoomed = false;
	var lbScale = 1;
	var lbPanX = 0;
	var lbPanY = 0;
	var lbDragging = false;
	var lbDragStartX = 0;
	var lbDragStartY = 0;
	var lbPanStartX = 0;
	var lbPanStartY = 0;
	var lbImgWrap = null;

	// Swipe state.
	var lbSwipeDragging = false;
	var lbSwipeSwiping = false;  // true once threshold passed
	var lbSwipeStartX = 0;
	var lbSwipeStartY = 0;
	var lbSwipeStartTime = 0;
	var lbSwipeDx = 0;
	var lbSwipePeekDir = 0;
	var lbSwipeWidth = 0;
	var lbSwipeLocked = false;  // axis locked to horizontal
	var lbSwipeAnimating = false;

	// Fix #7 v5.4.0: Vertical swipe-down-to-close state.
	var lbSwipeVertical = false;   // true when axis-locked to vertical dismiss
	var lbSwipeDy = 0;

	// Double-tap state.
	var lbLastTapTime = 0;
	var lbLastTapX = 0;
	var lbLastTapY = 0;

	// Fix #13 v5.4.0: Removed duplicate lbPreloadCache — using main preloadImage() instead.

	function openLightbox(startIdx) {
		var prod = cur();
		if (!prod) return;
		lightboxGallery = prod.gallery_full && prod.gallery_full.length ? prod.gallery_full.slice() : (prod.gallery && prod.gallery.length ? prod.gallery.slice() : (prod.image ? [prod.image] : []));
		lightboxThumbnails = prod.thumbnails && prod.thumbnails.length ? prod.thumbnails.slice() : lightboxGallery.slice();
		if (lightboxGallery.length === 0) return;

		// #9: If gallery_full not yet loaded, trigger lazy-load for next open.
		if (!prod.gallery_full || prod.gallery_full.length === 0) {
			loadProductDetail(prod.id, function() {});
		}

		// Inject 360° slide at index 1 (after hero image).
		lb360Idx = -1;
		if (productHas360(prod) && typeof MH360 !== 'undefined') {
			var spinPreview = prod.spin_thumb || (prod.spin_frames ? prod.spin_frames[0] : '');
			if (spinPreview) {
				lightboxGallery.splice(1, 0, LB_360_MARKER);
				lightboxThumbnails.splice(1, 0, spinPreview);
				lb360Idx = 1;
			}
		}

		// Map hero gallery index → lightbox index (shift by 1 if 360° inserted before it).
		var mappedIdx = startIdx || 0;
		if (lb360Idx >= 0 && mappedIdx >= 1) mappedIdx += 1;
		lightboxIdx = mappedIdx;

		// Reset 360° in-lightbox state.
		lbDeactivate360InPlace();

		if (!lightbox) buildLightbox();
		lightbox.classList.add('is-open');
		document.body.style.overflow = 'hidden';
		resetZoom();
		lbSwipeAnimating = false;
		setLightboxImage(lightboxGallery[lightboxIdx]);
		updateLightboxCounter();
		buildLightboxThumbs();
		preloadAdjacent();
		lightbox.focus();
		track('stv_lightbox_open');
	}

	function closeLightbox() {
		if (!lightbox) return;
		lbDeactivate360InPlace(); // Clean up inline 360° spinner
		closeLightbox360Cleanup(); // Clean up any 360° lightbox state
		lightbox.classList.remove('is-open');
		document.body.style.overflow = '';
		resetZoom();
	}

	/* ── Image management ── */
	function setLightboxImage(src) {
		if (!lightboxImg) return;
		lightboxImg.classList.remove('is-sliding');
		if (lightboxPeekImg) {
			lightboxPeekImg.classList.remove('is-visible', 'is-sliding');
			lightboxPeekImg.style.transform = '';
		}

		// Remove any existing 360° overlay.
		var existingOverlay = lbImgWrap ? lbImgWrap.querySelector('.mh-stv-lb-360-overlay') : null;
		if (existingOverlay) existingOverlay.parentNode.removeChild(existingOverlay);

		if (src === LB_360_MARKER) {
			// 360° slide: show static preview + overlay button.
			var prod = cur();
			var previewSrc = (prod && prod.spin_thumb) || (prod && prod.spin_frames ? prod.spin_frames[0] : '');
			lightboxImg.src = previewSrc || '';
			lightboxImg.style.transform = '';
			lightboxImg.style.display = '';

			// Create "tap to rotate" overlay.
			if (lbImgWrap && !lb360Active) {
				var overlay = el('div', 'mh-stv-lb-360-overlay');
				overlay.addEventListener('click', function(e) { e.stopPropagation(); });
				var overlayBtn = el('button', 'mh-stv-lb-360-activate');
				overlayBtn.type = 'button';
				overlayBtn.innerHTML = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-9-9c2.52 0 4.93 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/></svg> 360\u00B0 Ansicht starten';
				overlayBtn.addEventListener('click', function(e) {
					e.stopPropagation();
					lbActivate360InPlace();
				});
				overlay.appendChild(overlayBtn);
				lbImgWrap.appendChild(overlay);
			}
		} else {
			lightboxImg.src = src || '';
			lightboxImg.style.transform = '';
		}
	}

	/* ── 360° inline lightbox spinner ── */
	function lbActivate360InPlace() {
		var prod = cur();
		if (!prod || !productHas360(prod) || typeof MH360 === 'undefined') return;
		if (lb360Active) return;

		// Remove overlay.
		var overlay = lbImgWrap ? lbImgWrap.querySelector('.mh-stv-lb-360-overlay') : null;
		if (overlay) overlay.parentNode.removeChild(overlay);

		// Hide static image.
		if (lightboxImg) lightboxImg.style.display = 'none';

		// Create spinner wrapper.
		lb360WrapRef = el('div', 'mh-stv-lb-360-inline');
		lb360WrapRef.style.cssText = 'position:absolute;inset:0;z-index:2;display:flex;align-items:center;justify-content:center;';
		if (lbImgWrap) lbImgWrap.appendChild(lb360WrapRef);

		var accent = (mhStvData && mhStvData.accentColor) || '#e8910c';
		lb360SpinnerRef = MH360.create(lb360WrapRef, {
			frames: prod.spin_frames,
			accentColor: accent,
			autoplay: false,
			momentum: true
		});
		lb360Active = true;
	}

	function lbDeactivate360InPlace() {
		if (!lb360Active && !lb360SpinnerRef) return;
		if (lb360SpinnerRef) {
			lb360SpinnerRef.destroy();
			lb360SpinnerRef = null;
		}
		if (lb360WrapRef && lb360WrapRef.parentNode) {
			lb360WrapRef.parentNode.removeChild(lb360WrapRef);
			lb360WrapRef = null;
		}
		if (lightboxImg) lightboxImg.style.display = '';
		lb360Active = false;
	}

	function updateLightboxCounter() {
		if (!lightboxCounter) return;
		lightboxCounter.textContent = (lightboxIdx + 1) + ' / ' + lightboxGallery.length;
		lightboxCounter.style.display = lightboxGallery.length > 1 ? '' : 'none';
	}

	/* ── Preload adjacent images ── */
	function preloadAdjacent() {
		if (lightboxGallery.length < 2) return;
		var prevIdx = (lightboxIdx - 1 + lightboxGallery.length) % lightboxGallery.length;
		var nextIdx = (lightboxIdx + 1) % lightboxGallery.length;
		if (lightboxGallery[prevIdx] !== LB_360_MARKER) preloadSrc(lightboxGallery[prevIdx]);
		if (lightboxGallery[nextIdx] !== LB_360_MARKER) preloadSrc(lightboxGallery[nextIdx]);
	}

	function preloadSrc(src) {
		preloadImage(src);
	}

	/* ── Navigate to index (instant swap, no transition) ── */
	function goToIndex(newIdx) {
		if (newIdx === lightboxIdx) return;
		// Deactivate inline 360° spinner when navigating away.
		if (lb360Active) lbDeactivate360InPlace();
		lightboxIdx = newIdx;
		setLightboxImage(lightboxGallery[lightboxIdx]);
		updateLightboxCounter();
		updateLightboxThumbActive();
		preloadAdjacent();
	}

	function lightboxPrev() {
		if (lightboxGallery.length < 2) return;
		lbZoomed = false; lbScale = 1; lbPanX = 0; lbPanY = 0;
		if (lbImgWrap) lbImgWrap.classList.remove('is-zoomed');
		var newIdx = (lightboxIdx - 1 + lightboxGallery.length) % lightboxGallery.length;
		goToIndex(newIdx);
	}

	function lightboxNext() {
		if (lightboxGallery.length < 2) return;
		lbZoomed = false; lbScale = 1; lbPanX = 0; lbPanY = 0;
		if (lbImgWrap) lbImgWrap.classList.remove('is-zoomed');
		var newIdx = (lightboxIdx + 1) % lightboxGallery.length;
		goToIndex(newIdx);
	}

	function lightboxGoTo(idx) {
		if (idx === lightboxIdx) return;
		lbZoomed = false; lbScale = 1; lbPanX = 0; lbPanY = 0;
		if (lbImgWrap) lbImgWrap.classList.remove('is-zoomed');
		goToIndex(idx);
	}

	/* ── Zoom ── */
	var rafPending = false;

	function resetZoom() {
		lbZoomed = false;
		lbScale = 1;
		lbPanX = 0;
		lbPanY = 0;
		if (lightboxImg) {
			lightboxImg.classList.add('is-zoom-animating');
			applyZoomTransform();
			setTimeout(function() {
				if (lightboxImg) lightboxImg.classList.remove('is-zoom-animating');
			}, 280);
		}
		if (lbImgWrap) lbImgWrap.classList.remove('is-zoomed');
	}

	function toggleZoom(e) {
		if (lbZoomed) {
			resetZoom();
		} else {
			lbZoomed = true;
			lbScale = 2.8;

			// Zoom toward click/tap point.
			if (e && lbImgWrap) {
				var rect = lbImgWrap.getBoundingClientRect();
				var cx = e.clientX - rect.left - rect.width / 2;
				var cy = e.clientY - rect.top - rect.height / 2;
				// Pan so the clicked point stays under the cursor.
				lbPanX = -cx * (lbScale - 1);
				lbPanY = -cy * (lbScale - 1);
				clampPan();
			}

			if (lightboxImg) {
				lightboxImg.classList.add('is-zoom-animating');
				applyZoomTransform();
				setTimeout(function() {
					if (lightboxImg) lightboxImg.classList.remove('is-zoom-animating');
				}, 280);
			}
			if (lbImgWrap) lbImgWrap.classList.add('is-zoomed');
		}
	}

	function applyZoomTransform() {
		if (!lightboxImg) return;
		// translate BEFORE scale = values are in screen pixels (no division needed).
		lightboxImg.style.transform = 'translate(' + lbPanX + 'px,' + lbPanY + 'px) scale(' + lbScale + ')';
	}

	function scheduleTransform() {
		if (rafPending) return;
		rafPending = true;
		requestAnimationFrame(function() {
			rafPending = false;
			applyZoomTransform();
		});
	}

	function clampPan() {
		if (!lbImgWrap) return;
		// Use the unscaled dimensions for clamping.
		var w = lbImgWrap.clientWidth;
		var h = lbImgWrap.clientHeight;
		var maxX = w * (lbScale - 1) / 2;
		var maxY = h * (lbScale - 1) / 2;
		lbPanX = Math.max(-maxX, Math.min(maxX, lbPanX));
		lbPanY = Math.max(-maxY, Math.min(maxY, lbPanY));
	}

	/* ── Drag-to-swipe (lightbox) ── */
	function lbSwipeStart(x, y) {
		if (lbZoomed || lbSwipeAnimating || lb360Active) return;
		// Fix #7 v5.4.0: Allow swipe-down-to-close even with single image.
		lbSwipeDragging = true;
		lbSwipeSwiping = false;
		lbSwipeLocked = false;
		lbSwipeVertical = false;
		lbSwipeStartX = x;
		lbSwipeStartY = y;
		lbSwipeStartTime = Date.now();
		lbSwipeDx = 0;
		lbSwipeDy = 0;
		lbSwipePeekDir = 0;
		lbSwipeWidth = (lbImgWrap ? lbImgWrap.offsetWidth : window.innerWidth) || window.innerWidth;
	}

	function lbSwipeMove(x, y) {
		if (!lbSwipeDragging || lbZoomed) return;
		var dx = x - lbSwipeStartX;
		var dy = y - lbSwipeStartY;

		// Axis lock: determine horizontal-swipe vs vertical-dismiss.
		if (!lbSwipeLocked && !lbSwipeVertical) {
			if (Math.abs(dx) > 8 || Math.abs(dy) > 8) {
				if (Math.abs(dy) > Math.abs(dx) * 1.2 && dy > 0) {
					// Fix #7 v5.4.0: Vertical downward → dismiss mode.
					lbSwipeVertical = true;
				} else if (Math.abs(dy) > Math.abs(dx) * 1.2) {
					// Vertical upward — cancel swipe entirely.
					lbSwipeDragging = false;
					return;
				} else {
					// Horizontal — lock to horizontal swipe (only if >1 image).
					if (lightboxGallery.length < 2) { lbSwipeDragging = false; return; }
					lbSwipeLocked = true;
				}
			} else {
				return; // Too small, wait.
			}
		}

		// Fix #7 v5.4.0: Vertical dismiss — translate content down + fade.
		if (lbSwipeVertical) {
			lbSwipeDy = Math.max(0, dy); // only allow downward
			lbSwipeSwiping = lbSwipeDy > 10;
			var content = lightbox ? lightbox.querySelector('.mh-stv-lightbox-content') : null;
			if (content) {
				var progress = Math.min(lbSwipeDy / 300, 1);
				content.style.transform = 'translateY(' + lbSwipeDy + 'px)';
				content.style.opacity = String(1 - progress * 0.5);
				content.style.transition = 'none';
			}
			return;
		}

		lbSwipeDx = dx;
		if (Math.abs(dx) > 10) lbSwipeSwiping = true;
		if (lbImgWrap) lbImgWrap.classList.add('is-swiping');

		// Move main image.
		if (lightboxImg) {
			lightboxImg.style.transform = 'translateX(' + lbSwipeDx + 'px)';
		}

		// Show/update peek image.
		var direction = lbSwipeDx > 0 ? 1 : -1; // +1 = swiping right (prev), -1 = swiping left (next)
		if (lbSwipePeekDir !== direction) {
			lbSwipePeekDir = direction;
			if (lightboxPeekImg) {
				var peekIdx = direction > 0
					? (lightboxIdx - 1 + lightboxGallery.length) % lightboxGallery.length  // prev
					: (lightboxIdx + 1) % lightboxGallery.length;                           // next
				lightboxPeekImg.src = lightboxGallery[peekIdx] || '';
				lightboxPeekImg.classList.add('is-visible');
			}
		}
		if (lightboxPeekImg) {
			var peekOffset = (direction < 0)
				? lbSwipeWidth + lbSwipeDx   // from right
				: -lbSwipeWidth + lbSwipeDx; // from left
			lightboxPeekImg.style.transform = 'translateX(' + peekOffset + 'px)';
		}
	}

	function lbSwipeEnd() {
		if (!lbSwipeDragging) return;
		lbSwipeDragging = false;
		if (lbImgWrap) lbImgWrap.classList.remove('is-swiping');

		// Fix #7 v5.4.0: Vertical dismiss — close if swiped down >120px or fast flick.
		if (lbSwipeVertical) {
			var content = lightbox ? lightbox.querySelector('.mh-stv-lightbox-content') : null;
			var elapsed = Date.now() - lbSwipeStartTime;
			var vVelocity = lbSwipeDy / Math.max(elapsed, 1);
			if (lbSwipeDy > 120 || (vVelocity > 0.4 && lbSwipeDy > 40)) {
				// Commit dismiss.
				if (content) {
					content.style.transition = 'transform .25s ease, opacity .25s ease';
					content.style.transform = 'translateY(' + (window.innerHeight) + 'px)';
					content.style.opacity = '0';
				}
				setTimeout(function() {
					closeLightbox();
					if (content) {
						content.style.transform = '';
						content.style.opacity = '';
						content.style.transition = '';
					}
				}, 260);
			} else {
				// Snap back.
				if (content) {
					content.style.transition = 'transform .2s ease, opacity .2s ease';
					content.style.transform = '';
					content.style.opacity = '';
					setTimeout(function() { if (content) content.style.transition = ''; }, 220);
				}
			}
			lbSwipeVertical = false;
			lbSwipeDy = 0;
			setTimeout(function() { lbSwipeSwiping = false; }, 50);
			return;
		}

		var threshold = lbSwipeWidth * 0.18; // 18% of width
		var elapsed = Date.now() - lbSwipeStartTime;
		var velocity = Math.abs(lbSwipeDx) / Math.max(elapsed, 1);
		var fastSwipe = velocity > 0.4 && Math.abs(lbSwipeDx) > 30; // fast flick

		if ((Math.abs(lbSwipeDx) > threshold || fastSwipe) && lightboxGallery.length > 1) {
			// Commit swipe — instant swap.
			var direction = lbSwipeDx > 0 ? 1 : -1;
			var newIdx = direction > 0
				? (lightboxIdx - 1 + lightboxGallery.length) % lightboxGallery.length
				: (lightboxIdx + 1) % lightboxGallery.length;

			// Clean up peek.
			if (lightboxPeekImg) {
				lightboxPeekImg.classList.remove('is-visible');
				lightboxPeekImg.style.transform = '';
			}

			lightboxIdx = newIdx;
			setLightboxImage(lightboxGallery[lightboxIdx]);
			updateLightboxCounter();
			updateLightboxThumbActive();
			preloadAdjacent();
		} else {
			// Snap back — instant reset.
			if (lightboxImg) {
				lightboxImg.style.transform = '';
			}
			if (lightboxPeekImg) {
				lightboxPeekImg.classList.remove('is-visible');
				lightboxPeekImg.style.transform = '';
			}
		}

		lbSwipeDx = 0;
		setTimeout(function() { lbSwipeSwiping = false; }, 50);
	}

	/* ── Double-tap to zoom ── */
	function handleDoubleTap(e) {
		var now = Date.now();
		var x = e.changedTouches ? e.changedTouches[0].clientX : e.clientX;
		var y = e.changedTouches ? e.changedTouches[0].clientY : e.clientY;

		if (now - lbLastTapTime < 320 && Math.abs(x - lbLastTapX) < 30 && Math.abs(y - lbLastTapY) < 30) {
			// Double-tap detected.
			e.preventDefault();
			var fakeEvent = { clientX: x, clientY: y };
			toggleZoom(fakeEvent);
			lbLastTapTime = 0; // Reset to avoid triple-tap.
			return true;
		}
		lbLastTapTime = now;
		lbLastTapX = x;
		lbLastTapY = y;
		return false;
	}

	/* ── Thumbnail strip ── */
	function buildLightboxThumbs() {
		if (!lightboxThumbs) return;
		while (lightboxThumbs.firstChild) lightboxThumbs.removeChild(lightboxThumbs.firstChild);
		if (lightboxGallery.length < 2) {
			lightboxThumbs.style.display = 'none';
			return;
		}
		lightboxThumbs.style.display = '';

		for (var i = 0; i < lightboxGallery.length; i++) {
			(function(idx) {
				var is360Slide = (lightboxGallery[idx] === LB_360_MARKER);
				var btn = el('button', 'mh-stv-lb-thumb' + (is360Slide ? ' mh-stv-lb-thumb-360' : ''));
				btn.type = 'button';
				btn.setAttribute('aria-label', is360Slide ? '360° Ansicht' : ('Bild ' + (idx + 1)));
				if (idx === lightboxIdx) btn.classList.add('is-active');
				var img = el('img');
				img.src = lightboxThumbnails[idx] || lightboxGallery[idx];
				img.alt = '';
				img.loading = 'lazy';
				btn.appendChild(img);
				// Add 360° badge to thumb.
				if (is360Slide) {
					var badge = el('span', 'mh-stv-lb-thumb-360-badge');
					badge.textContent = '360\u00B0';
					btn.appendChild(badge);
				}
				btn.addEventListener('click', function(e) {
					e.stopPropagation();
					lightboxGoTo(idx);
				});
				lightboxThumbs.appendChild(btn);
			})(i);
		}
		scrollThumbIntoView();
	}

	function updateLightboxThumbActive() {
		if (!lightboxThumbs) return;
		var btns = lightboxThumbs.querySelectorAll('.mh-stv-lb-thumb');
		for (var i = 0; i < btns.length; i++) {
			btns[i].classList.toggle('is-active', i === lightboxIdx);
		}
		scrollThumbIntoView();
	}

	function scrollThumbIntoView() {
		if (!lightboxThumbs) return;
		var active = lightboxThumbs.querySelector('.mh-stv-lb-thumb.is-active');
		if (active) {
			active.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
		}
	}

	/* ── Build Lightbox DOM ── */
	function buildLightbox() {
		lightbox = el('div', 'mh-stv-lightbox');
		lightbox.setAttribute('role', 'dialog');
		lightbox.setAttribute('aria-label', 'Bildansicht');
		lightbox.setAttribute('tabindex', '-1');

		// Backdrop.
		var backdrop = el('div', 'mh-stv-lightbox-backdrop');
		backdrop.addEventListener('click', closeLightbox);
		lightbox.appendChild(backdrop);

		// Content wrapper (centered, full viewport).
		var content = el('div', 'mh-stv-lightbox-content');

		// Image wrapper (handles zoom + pan + swipe).
		lbImgWrap = el('div', 'mh-stv-lightbox-imgwrap');

		// Main image.
		lightboxImg = el('img', 'mh-stv-lightbox-img');
		lightboxImg.alt = 'Produktbild';
		lbImgWrap.appendChild(lightboxImg);

		// Peek image (for slide-in during swipe).
		lightboxPeekImg = el('img', 'mh-stv-lightbox-peek');
		lightboxPeekImg.alt = '';
		lbImgWrap.appendChild(lightboxPeekImg);

		// ── Click on image → toggle zoom. Click on black area → close. ──
		lightboxImg.addEventListener('click', function(e) {
			if (lbDragging || lbSwipeSwiping || lb360Active) return;
			e.stopPropagation();
			toggleZoom(e);
		});
		lbImgWrap.addEventListener('click', function(e) {
			// Click hit the wrapper (black area), not the image → close.
			if (lbDragging || lbSwipeSwiping || lbSwipeAnimating || lb360Active) return;
			closeLightbox();
		});

		// ── Mouse drag to pan when zoomed ──
		lbImgWrap.addEventListener('mousedown', function(e) {
			if (!lbZoomed) return;
			e.preventDefault();
			lbDragging = false;
			lbDragStartX = e.clientX;
			lbDragStartY = e.clientY;
			lbPanStartX = lbPanX;
			lbPanStartY = lbPanY;

			function onMove(ev) {
				var dx = ev.clientX - lbDragStartX;
				var dy = ev.clientY - lbDragStartY;
				if (Math.abs(dx) > 3 || Math.abs(dy) > 3) lbDragging = true;
				lbPanX = lbPanStartX + dx;
				lbPanY = lbPanStartY + dy;
				clampPan();
				scheduleTransform();
			}
			function onUp() {
				document.removeEventListener('mousemove', onMove);
				document.removeEventListener('mouseup', onUp);
				setTimeout(function() { lbDragging = false; }, 10);
			}
			document.addEventListener('mousemove', onMove);
			document.addEventListener('mouseup', onUp);
		});

		// ── Touch: pinch-to-zoom, pan (when zoomed), swipe (when not zoomed) ──
		var pinchStartDist = 0;
		var pinchStartScale = 1;
		var touchPanStartX = 0;
		var touchPanStartY = 0;
		var touchPanPrevX = 0;
		var touchPanPrevY = 0;

		lbImgWrap.addEventListener('touchstart', function(e) {
			if (e.touches.length === 2) {
				// Pinch start.
				pinchStartDist = Math.hypot(
					e.touches[0].clientX - e.touches[1].clientX,
					e.touches[0].clientY - e.touches[1].clientY
				);
				pinchStartScale = lbScale;
				e.preventDefault();
			} else if (e.touches.length === 1) {
				if (lbZoomed) {
					// Pan start.
					touchPanStartX = e.touches[0].clientX;
					touchPanStartY = e.touches[0].clientY;
					touchPanPrevX = lbPanX;
					touchPanPrevY = lbPanY;
				} else {
					// Swipe start.
					lbSwipeStart(e.touches[0].clientX, e.touches[0].clientY);
				}
			}
		}, { passive: false });

		lbImgWrap.addEventListener('touchmove', function(e) {
			if (e.touches.length === 2) {
				// Pinch zoom.
				var dist = Math.hypot(
					e.touches[0].clientX - e.touches[1].clientX,
					e.touches[0].clientY - e.touches[1].clientY
				);
				lbScale = Math.max(1, Math.min(5, pinchStartScale * (dist / pinchStartDist)));
				lbZoomed = lbScale > 1.05;
				if (lbZoomed) {
					lbImgWrap.classList.add('is-zoomed');
				} else {
					lbImgWrap.classList.remove('is-zoomed');
				}
				clampPan();
				scheduleTransform();
				e.preventDefault();
			} else if (e.touches.length === 1 && lbZoomed) {
				// Pan.
				lbPanX = touchPanPrevX + (e.touches[0].clientX - touchPanStartX);
				lbPanY = touchPanPrevY + (e.touches[0].clientY - touchPanStartY);
				clampPan();
				scheduleTransform();
				e.preventDefault();
			} else if (e.touches.length === 1 && !lbZoomed) {
				// Swipe.
				lbSwipeMove(e.touches[0].clientX, e.touches[0].clientY);
				if (lbSwipeSwiping) e.preventDefault();
			}
		}, { passive: false });

		lbImgWrap.addEventListener('touchend', function(e) {
			if (lbScale <= 1.05 && lbZoomed) resetZoom();

			if (!lbZoomed && lbSwipeDragging) {
				lbSwipeEnd();
			}

			// Double-tap to zoom (only when not mid-swipe).
			if (e.changedTouches.length === 1 && !lbSwipeSwiping && !lbSwipeAnimating) {
				handleDoubleTap(e);
			}
		}, { passive: false });

		content.appendChild(lbImgWrap);

		// Counter (overlay, top-center).
		lightboxCounter = el('div', 'mh-stv-lightbox-counter');
		content.appendChild(lightboxCounter);

		// Thumbnail strip (overlay, bottom-center).
		lightboxThumbs = el('div', 'mh-stv-lightbox-thumbs');
		content.appendChild(lightboxThumbs);

		lightbox.appendChild(content);

		// Close button.
		var closeBtn = el('button', 'mh-stv-lightbox-close');
		closeBtn.type = 'button';
		closeBtn.setAttribute('aria-label', 'Schlie\u00dfen');
		closeBtn.innerHTML = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
		closeBtn.addEventListener('click', closeLightbox);
		lightbox.appendChild(closeBtn);

		// Prev button.
		var prevBtn = el('button', 'mh-stv-lightbox-nav mh-stv-lightbox-prev');
		prevBtn.type = 'button';
		prevBtn.setAttribute('aria-label', 'Vorheriges Bild');
		prevBtn.innerHTML = '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>';
		prevBtn.addEventListener('click', function(e) { e.stopPropagation(); lightboxPrev(); });
		lightbox.appendChild(prevBtn);

		// Next button.
		var nextBtn = el('button', 'mh-stv-lightbox-nav mh-stv-lightbox-next');
		nextBtn.type = 'button';
		nextBtn.setAttribute('aria-label', 'N\u00e4chstes Bild');
		nextBtn.innerHTML = '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>';
		nextBtn.addEventListener('click', function(e) { e.stopPropagation(); lightboxNext(); });
		lightbox.appendChild(nextBtn);

		// Keyboard.
		lightbox.addEventListener('keydown', function(e) {
			if (e.key === 'Escape') { closeLightbox(); }
			else if (e.key === 'ArrowLeft') { lightboxPrev(); }
			else if (e.key === 'ArrowRight') { lightboxNext(); }
			else if (e.key === '+' || e.key === '=') { if (!lbZoomed) toggleZoom(null); }
			else if (e.key === '-') { if (lbZoomed) resetZoom(); }
		});

		document.body.appendChild(lightbox);
	}

	/* ── Init ── */
	function init() {
		try {
			render();
			// Fix #16 v5.4.0: Prebuild lightbox DOM on idle to avoid first-open jank.
			if (typeof requestIdleCallback === 'function') {
				requestIdleCallback(function() {
					if (!lightbox) buildLightbox();
				}, { timeout: 3000 });
			} else {
				setTimeout(function() {
					if (!lightbox) buildLightbox();
				}, 2000);
			}
		} catch (e) {
			ROOT.innerHTML = '';
			// Clean up sticky bar if it was appended to body.
			if (refs.stickyBar && refs.stickyBar.parentNode) {
				refs.stickyBar.parentNode.removeChild(refs.stickyBar);
			}
			if (window.console) console.error('MH STV Error:', e);
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
