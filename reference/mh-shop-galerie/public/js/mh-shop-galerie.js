/* Mega-Holz Shop-Galerie v1.0.0
   1:1-Port des Spielhaus-Galerie-Verhaltens:
   - Hero: Klick = Lightbox, Ziehen = Bildwechsel (Peek-Bild folgt dem Finger),
     Pfeile immer sichtbar, Tastatur (Enter/Space/Pfeile)
   - Thumb-Streifen: Klick tauscht Hero, Drag-to-Scroll,
     eigene Laufleiste mit ziehbarem Balken + Pfeiltasten
   - Lightbox: Klick-Zoom (2.5x zum Klickpunkt), Mausrad-Zoom (1–5x),
     Pan (gezoomt), Pinch-Zoom, Peek-Swipe, Swipe-down schließt,
     ESC/Pfeile, Zähler, Thumb-Leiste */
(function () {
	'use strict';

	function el(tag, cls) {
		var e = document.createElement(tag);
		if (cls) { e.className = cls; }
		return e;
	}
	function ico(path, w) {
		return '<svg viewBox="0 0 24 24" width="' + (w || 24) + '" height="' + (w || 24) +
			'" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + path + '</svg>';
	}

	/* ════════════════ Lightbox (Singleton, von allen Galerien geteilt) ════════════════ */
	var LB = (function () {
		var box = null, stage = null, imgEl = null, peekEl = null;
		var thumbs = null, counter = null, prevBtn = null, nextBtn = null;
		var imgs = [], idx = 0;
		var lastFocus = null, touchX = 0, touchY = 0, touchActive = false;
		// Zoom/Pan-Zustand
		var scale = 1, tx = 0, ty = 0, panning = false, moved = false;
		var panStartX = 0, panStartY = 0, panOrigX = 0, panOrigY = 0;
		var pinchDist = 0, pinchScale = 1, pinchCX = 0, pinchCY = 0;
		// Peek-Swipe-Zustand (nicht gezoomt)
		var swActive = false, swStartX = 0, swStartY = 0, swDX = 0, swAxis = '';

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
			if (scale > 1.01 || imgs.length < 2) { return false; }
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
			box = el('div', 'mhsg-lb');
			box.setAttribute('role', 'dialog');
			box.setAttribute('aria-modal', 'true');

			var close = el('button', 'mhsg-lb-close');
			close.type = 'button';
			close.setAttribute('aria-label', 'Schlie\u00dfen');
			close.innerHTML = ico('<line x1="6" y1="6" x2="18" y2="18"/><line x1="18" y1="6" x2="6" y2="18"/>', 22);
			close.addEventListener('click', hide);

			counter = el('div', 'mhsg-lb-counter');

			prevBtn = el('button', 'mhsg-lb-nav mhsg-lb-prev');
			prevBtn.type = 'button';
			prevBtn.setAttribute('aria-label', 'Vorheriges Bild');
			prevBtn.innerHTML = ico('<polyline points="15 18 9 12 15 6"/>', 26);
			prevBtn.addEventListener('click', function (e) { e.stopPropagation(); go(-1); });

			nextBtn = el('button', 'mhsg-lb-nav mhsg-lb-next');
			nextBtn.type = 'button';
			nextBtn.setAttribute('aria-label', 'N\u00e4chstes Bild');
			nextBtn.innerHTML = ico('<polyline points="9 18 15 12 9 6"/>', 26);
			nextBtn.addEventListener('click', function (e) { e.stopPropagation(); go(1); });

			stage = el('div', 'mhsg-lb-stage');
			imgEl = el('img', 'mhsg-lb-img skip-lazy');
			imgEl.alt = '';
			peekEl = el('img', 'mhsg-lb-peek skip-lazy');
			peekEl.alt = ''; peekEl.draggable = false;
			stage.appendChild(imgEl);
			stage.appendChild(peekEl);

			thumbs = el('div', 'mhsg-lb-thumbs');

			box.appendChild(close);
			box.appendChild(counter);
			box.appendChild(prevBtn);
			box.appendChild(stage);
			box.appendChild(nextBtn);
			box.appendChild(thumbs);

			// Klick aufs Bild: Zoom umschalten (zum Klickpunkt)
			imgEl.addEventListener('click', function (e) {
				if (moved) { moved = false; return; }
				e.stopPropagation();
				var r = stage.getBoundingClientRect();
				if (scale > 1.01) { resetZoom(); }
				else { zoomTo(2.5, e.clientX - r.left, e.clientY - r.top, true); }
			});

			// Mausrad: stufenlos zoomen (Cursorpunkt bleibt fix)
			stage.addEventListener('wheel', function (e) {
				e.preventDefault();
				var r = stage.getBoundingClientRect();
				var factor = e.deltaY < 0 ? 1.2 : 1 / 1.2;
				zoomTo(scale * factor, e.clientX - r.left, e.clientY - r.top, false);
			}, { passive: false });

			// Maus: gezoomt = Pan, sonst = Peek-Swipe
			imgEl.addEventListener('mousedown', function (e) {
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

			box.addEventListener('click', function (e) {
				if (moved) { moved = false; return; }
				if (e.target === box || e.target === stage) { hide(); }
			});

			// Touch: 1 Finger = Peek-Swipe (unzoomed) bzw. Pan (gezoomt); 2 Finger = Pinch-Zoom
			stage.addEventListener('touchstart', function (e) {
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
				if (!touchActive || scale > 1.01) { touchActive = false; return; }
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
			for (var i = 0; i < imgs.length; i++) {
				(function (url, i2) {
					var tb = el('button', 'mhsg-lb-thumb');
					tb.type = 'button';
					tb.setAttribute('aria-label', 'Bild ' + (i2 + 1));
					tb.style.backgroundImage = "url('" + url + "')";
					tb.addEventListener('click', function (e) { e.stopPropagation(); showPhoto(i2); });
					thumbs.appendChild(tb);
				})(imgs[i], i);
			}
		}

		function syncThumbActive() {
			var all = thumbs.querySelectorAll('.mhsg-lb-thumb');
			for (var i = 0; i < all.length; i++) { all[i].classList.toggle('is-active', i === idx); }
		}

		var onChange = null; // informiert die Galerie über Bildwechsel in der Lightbox

		function showPhoto(i) {
			if (!imgs.length) { return; }
			resetZoom();
			idx = (i + imgs.length) % imgs.length;
			imgEl.src = imgs[idx] || '';
			counter.textContent = (idx + 1) + ' von ' + imgs.length;
			var multi = imgs.length > 1;
			prevBtn.style.display = multi ? '' : 'none';
			nextBtn.style.display = multi ? '' : 'none';
			if (multi) {
				new Image().src = imgs[(idx + 1) % imgs.length];
				new Image().src = imgs[(idx - 1 + imgs.length) % imgs.length];
			}
			syncThumbActive();
			if (onChange) { onChange(idx); }
		}

		function go(step) {
			if (imgs.length < 2) { return; }
			showPhoto(idx + step);
		}

		function show() {
			box.classList.add('is-open');
			lastFocus = document.activeElement;
			document.documentElement.classList.add('mhsg-lb-lock');
			document.addEventListener('keydown', onKey);
		}
		function hide() {
			if (!box) { return; }
			box.classList.remove('is-open');
			resetZoom();
			document.documentElement.classList.remove('mhsg-lb-lock');
			document.removeEventListener('keydown', onKey);
			if (lastFocus && lastFocus.focus) { try { lastFocus.focus(); } catch (e) {} }
		}

		function open(images, start, changeCb) {
			if (!box) { build(); }
			imgs = (images && images.length) ? images.slice() : [];
			if (!imgs.length) { return; }
			onChange = changeCb || null;
			renderThumbs();
			showPhoto(start || 0);
			show();
		}

		return { open: open };
	})();

	/* ════════════════ Galerie-Instanz ════════════════ */
	function initGallery(root) {
		if (root.dataset.mhsgInit) { return; }
		root.dataset.mhsgInit = '1';

		var dataEl = root.querySelector('.mhsg-data');
		var data;
		try { data = JSON.parse(dataEl.textContent); } catch (e) { return; }
		var images = data.images || [];
		var name = data.name || '';
		var n = images.length;
		if (!n) { return; }

		var hero = root.querySelector('.mhsg-hero');
		var hImg = root.querySelector('.mhsg-img');
		if (!hero || !hImg) { return; }
		var scroll = root.querySelector('.mhsg-thumbs-scroll');
		var thumbBtns = Array.prototype.slice.call(root.querySelectorAll('.mhsg-thumb'));
		var heroIdx = 0;
		var dragMoved = false;

		function singles() { return images.map(function (im) { return im.single; }); }
		function fulls() { return images.map(function (im) { return im.full || im.single; }); }

		/* v1.2.9: Nachbarbilder (single) vorwaermen, damit Pfeil-/Thumbnail-Wechsel
		   ohne frischen Netzwerk-Request und ohne Flackern erfolgt. Spiegelt das
		   Verhalten, das die Lightbox bereits nutzt. */
		function preloadNeighbors(i) {
			if (n < 2) { return; }
			var s = singles();
			new Image().src = s[(i + 1) % n];
			new Image().src = s[(i - 1 + n) % n];
		}

		function setHero(i) {
			heroIdx = ((i % n) + n) % n;
			var im = images[heroIdx];
			hImg.src = im.single;
			if (im.srcset) { hImg.srcset = im.srcset; hImg.sizes = '(max-width: 980px) 92vw, 720px'; }
			else { hImg.removeAttribute('srcset'); hImg.removeAttribute('sizes'); }
			hImg.alt = im.alt || name;
			for (var t = 0; t < thumbBtns.length; t++) {
				var on = (t === heroIdx);
				thumbBtns[t].classList.toggle('is-active', on);
				thumbBtns[t].setAttribute('aria-pressed', on ? 'true' : 'false');
			}
			scrollThumbIntoView(heroIdx);
			preloadNeighbors(heroIdx);
		}

		function openAt(i) {
			LB.open(fulls(), i, function (lbIdx) { setHero(lbIdx); });
		}

		/* ── Zoom-Affordance ── */
		var zoom = el('button', 'mhsg-zoom');
		zoom.type = 'button'; zoom.setAttribute('aria-label', 'Zoom'); zoom.tabIndex = -1;
		zoom.innerHTML = ico('<circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.6" y2="16.6"/><line x1="11" y1="8.5" x2="11" y2="13.5"/><line x1="8.5" y1="11" x2="13.5" y2="11"/>', 20);
		hero.appendChild(zoom);

		if (n > 1) {
			// Peek-Bild (Nachbar), das beim Ziehen real mit hereinkommt.
			var hPeek = el('img', 'mhsg-peek skip-lazy');
			hPeek.alt = ''; hPeek.draggable = false; hPeek.decoding = 'async';
			hero.appendChild(hPeek);

			// Pfeile (immer sichtbar)
			var gPrev = el('button', 'mhsg-gnav mhsg-gprev'); gPrev.type = 'button';
			gPrev.setAttribute('aria-label', 'Vorheriges Bild');
			gPrev.innerHTML = ico('<polyline points="15 18 9 12 15 6"/>', 22);
			gPrev.addEventListener('click', function (e) { e.stopPropagation(); setHero(heroIdx - 1); });
			var gNext = el('button', 'mhsg-gnav mhsg-gnext'); gNext.type = 'button';
			gNext.setAttribute('aria-label', 'N\u00e4chstes Bild');
			gNext.innerHTML = ico('<polyline points="9 18 15 12 9 6"/>', 22);
			gNext.addEventListener('click', function (e) { e.stopPropagation(); setHero(heroIdx + 1); });
			hero.appendChild(gPrev); hero.appendChild(gNext);

			// ── Peek-Drag (Maus + Touch): Nachbarbild folgt dem Finger, snappt beim Loslassen ──
			var dragging = false, dragStartX = 0, dragStartY = 0, dragDX = 0, dragAxis = '', peekIdx = -1;
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
					var pim = images[pIdx];
					hPeek.src = pim.single;
					if (pim.srcset) { hPeek.srcset = pim.srcset; hPeek.sizes = '(max-width: 980px) 92vw, 720px'; }
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
			var onMM = function (e) { dMove(e.clientX, e.clientY); };
			var onMU = function () { dEnd(); document.removeEventListener('mousemove', onMM); document.removeEventListener('mouseup', onMU); };
			hero.addEventListener('mousedown', function (e) {
				if (e.button !== 0) { return; }
				e.preventDefault(); dStart(e.clientX, e.clientY);
				document.addEventListener('mousemove', onMM); document.addEventListener('mouseup', onMU);
			});
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
		hero.addEventListener('click', function () { if (dragMoved) { return; } openAt(heroIdx); });
		hero.addEventListener('keydown', function (e) {
			if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openAt(heroIdx); }
			else if (n > 1 && e.key === 'ArrowLeft') { e.preventDefault(); setHero(heroIdx - 1); }
			else if (n > 1 && e.key === 'ArrowRight') { e.preventDefault(); setHero(heroIdx + 1); }
		});

		/* ── Thumb-Streifen ── */
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

		if (n > 1 && scroll) {
			var strip = scroll.parentNode;
			var dragScrolled = false; // unterscheidet Klick (Hero tauschen) von Ziehen (scrollen)

			thumbBtns.forEach(function (t, idx2) {
				t.addEventListener('click', function () { if (dragScrolled) { return; } setHero(idx2); });
			});

			// Drag-to-Scroll mit der Maus direkt auf den Thumbnails
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
			var barRow = el('div', 'mhsg-thumbbar-row');
			var tPrev = el('button', 'mhsg-tnav'); tPrev.type = 'button';
			tPrev.setAttribute('aria-label', 'Vorheriges Bild');
			tPrev.innerHTML = ico('<polyline points="15 18 9 12 15 6"/>', 16);
			var track = el('div', 'mhsg-thumbbar');
			var bar = el('div', 'mhsg-thumbbar-thumb');
			track.appendChild(bar);
			var tNext = el('button', 'mhsg-tnav'); tNext.type = 'button';
			tNext.setAttribute('aria-label', 'N\u00e4chstes Bild');
			tNext.innerHTML = ico('<polyline points="9 18 15 12 9 6"/>', 16);
			barRow.appendChild(tPrev); barRow.appendChild(track); barRow.appendChild(tNext);
			strip.appendChild(barRow);

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
			window.addEventListener('resize', function () { if (raf) { return; } raf = true; requestAnimationFrame(function () { raf = false; updateBar(); }); });

			// Balken ziehen → scrollt den Streifen (Maus + Touch)
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

			// Klick auf den Track springt dorthin
			track.addEventListener('click', function (e) {
				if (e.target === bar) { return; }
				var r = track.getBoundingClientRect();
				var frac = Math.max(0, Math.min(1, (e.clientX - r.left) / (r.width || 1)));
				scroll.scrollLeft = frac * maxScroll();
			});

			setTimeout(updateBar, 0);
			setTimeout(updateBar, 300); // nach Bildladung kann sich scrollWidth ändern
		}

		preloadNeighbors(0); // v1.2.9: Nachbarbilder direkt nach dem Laden vorwaermen
	}

	function initAll() {
		var roots = document.querySelectorAll('[data-mhsg]');
		for (var i = 0; i < roots.length; i++) { initGallery(roots[i]); }
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initAll);
	} else {
		initAll();
	}
})();
