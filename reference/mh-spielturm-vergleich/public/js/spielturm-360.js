/**
 * MH 360° Spinner v1.2.0
 *
 * Interactive 360° product viewer using image sequences.
 *
 * v1.2.0 — Fixed flicker from v1.1.0 dual-image approach.
 * Single-image rendering (preloaded = instant swap, no ghost).
 * Keeps all smoothness improvements:
 * - rAF-throttled drag (frame changes at screen refresh rate only)
 * - Sub-pixel drag accumulator (fractional frame tracking)
 * - Velocity smoothing via exponential moving average
 * - Tuned sensitivity (8px/frame), friction (0.85), autoplay (5fps)
 * - Autoplay ease-in ramp (smoothstep over 1.5s)
 *
 * ES5 compatible. No dependencies.
 *
 * @package MH_Spielturm_Vergleich
 * @since   5.0.2
 */
var MH360 = (function() {
	'use strict';

	/* ── Tuning Constants ── */
	var SENSITIVITY    = 8;      // px of drag per frame step
	var FRICTION       = 0.85;   // momentum deceleration per tick
	var MIN_VELOCITY   = 0.08;   // stop momentum threshold
	var AUTOPLAY_FPS   = 5;      // slow elegant rotation
	var AUTOPLAY_DELAY = 3000;   // ms before autoplay resumes
	var AUTOPLAY_RAMP  = 1500;   // ms ease-in from standstill
	var PRELOAD_BATCH  = 8;      // concurrent image preloads
	var VELOCITY_SMOOTH = 0.3;   // EMA factor (lower = smoother)

	function el(tag, cls) {
		var e = document.createElement(tag);
		if (cls) e.className = cls;
		return e;
	}

	/* ══════════════════════════════════════
	 *  Spinner
	 * ══════════════════════════════════════ */
	function Spinner(container, opts) {
		opts = opts || {};

		this.container    = container;
		this.frames       = opts.frames || [];
		this.totalFrames  = this.frames.length;
		this.currentFrame = 0;
		this.accentColor  = opts.accentColor || '#e8910c';

		this.autoplayEnabled = opts.autoplay !== false;
		this.momentumEnabled = opts.momentum !== false;

		// Drag state.
		this.isDragging   = false;
		this.lastX        = 0;
		this.lastTime     = 0;
		this.dragAccum    = 0;
		this.velocity     = 0;
		this.rawVelocity  = 0;

		// Animation state.
		this.momentumRAF  = null;
		this.dragRAF      = null;
		this.pendingFrame = -1;
		this.autoplayTimer = null;
		this.resumeTimer  = null;
		this.isVisible    = false;
		this.isAutoPlaying = false;
		this.userInteracted = false;
		this.autoplayRampStart = 0;

		// Preloading.
		this.images      = new Array(this.totalFrames);
		this.loadedCount = 0;
		this.allLoaded   = false;

		// DOM.
		this.canvas     = null;
		this.imgEl      = null;
		this.loader     = null;
		this.hintEl     = null;

		this.onFrameChange = opts.onFrameChange || null;
		this.onExpand      = opts.onExpand || null;

		// Bound handlers.
		this._onMouseDown  = this._handleMouseDown.bind(this);
		this._onMouseMove  = this._handleMouseMove.bind(this);
		this._onMouseUp    = this._handleMouseUp.bind(this);
		this._onTouchStart = this._handleTouchStart.bind(this);
		this._onTouchMove  = this._handleTouchMove.bind(this);
		this._onTouchEnd   = this._handleTouchEnd.bind(this);

		if (this.totalFrames > 0) {
			this._build();
			this._preloadImages();
			this._setupObserver();
		}
	}

	/* ── Build DOM ── */
	Spinner.prototype._build = function() {
		this.container.classList.add('mh-360-container');

		this.canvas = el('div', 'mh-360-canvas');
		this.canvas.style.setProperty('--mh-360-accent', this.accentColor);

		// Single image — preloaded frames swap instantly via .src.
		this.imgEl = el('img', 'mh-360-img');
		this.imgEl.alt = '360\u00b0 Produktansicht';
		this.imgEl.draggable = false;
		this.canvas.appendChild(this.imgEl);

		// Loading overlay.
		this.loader = el('div', 'mh-360-loader');
		this.loader.innerHTML = '<div class="mh-360-loader-ring"></div><span class="mh-360-loader-text">0%</span>';
		this.canvas.appendChild(this.loader);

		// 360° badge.
		var badge = el('div', 'mh-360-badge');
		badge.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 11-3.5-7.1"/><polyline points="21 3 21 9 15 9"/></svg> 360\u00b0';
		this.canvas.appendChild(badge);

		// Drag hint.
		this.hintEl = el('div', 'mh-360-hint');
		this.hintEl.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M5 12h14M5 12l3-3m-3 3l3 3m8-6l3 3-3 3"/></svg> Ziehen zum Drehen';
		this.canvas.appendChild(this.hintEl);

		// Expand / fullscreen button.
		if (this.onExpand) {
			var self = this;
			var expandBtn = el('button', 'mh-360-expand');
			expandBtn.type = 'button';
			expandBtn.setAttribute('aria-label', 'Vollbild');
			expandBtn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/><line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/></svg>';
			expandBtn.addEventListener('click', function(e) {
				e.stopPropagation();
				e.preventDefault();
				self.onExpand();
			});
			this.canvas.appendChild(expandBtn);
		}

		this.container.appendChild(this.canvas);

		this.canvas.addEventListener('mousedown', this._onMouseDown);
		this.canvas.addEventListener('touchstart', this._onTouchStart, { passive: false });
		this.canvas.addEventListener('contextmenu', function(e) { e.preventDefault(); });

		// Double-tap to expand (mobile convenience — v5.2.0).
		if (this.onExpand) {
			var self = this;
			var lastTapTime = 0;
			this.canvas.addEventListener('touchend', function(e) {
				if (e.changedTouches.length !== 1) return;
				var now = Date.now();
				if (now - lastTapTime < 350) {
					e.preventDefault();
					self.onExpand();
					lastTapTime = 0;
				} else {
					lastTapTime = now;
				}
			});
		}
	};

	/* ── Show Frame (single image, instant swap) ── */
	Spinner.prototype._showFrame = function(idx) {
		idx = ((idx % this.totalFrames) + this.totalFrames) % this.totalFrames;
		if (idx === this.currentFrame) return;
		this.currentFrame = idx;

		// Preloaded Image object → browser serves from memory cache = instant.
		if (this.images[idx] && this.images[idx].src) {
			this.imgEl.src = this.images[idx].src;
		} else if (this.frames[idx]) {
			this.imgEl.src = this.frames[idx];
		}

		if (this.onFrameChange) this.onFrameChange(idx, this.totalFrames);
	};

	/* ── Preloading ── */
	Spinner.prototype._preloadImages = function() {
		var self = this;
		var total = this.totalFrames;

		// First frame immediately.
		var first = new Image();
		first.onload = function() {
			self.images[0] = first;
			self.imgEl.src = first.src;
			self.loadedCount++;
			self._updateLoadProgress();
		};
		first.src = this.frames[0];

		var queue = [];
		for (var i = 1; i < total; i++) queue.push(i);

		var active = 0;
		function next() {
			if (queue.length === 0) return;
			if (active >= PRELOAD_BATCH) return;
			active++;
			var idx = queue.shift();
			var img = new Image();
			img.onload = function() {
				self.images[idx] = img;
				self.loadedCount++;
				self._updateLoadProgress();
				active--;
				if (self.loadedCount >= total) {
					self.allLoaded = true;
					self._onAllLoaded();
				}
				next();
			};
			img.onerror = function() {
				self.loadedCount++;
				self._updateLoadProgress();
				active--;
				next();
			};
			img.src = self.frames[idx];
		}
		for (var b = 0; b < PRELOAD_BATCH; b++) next();
	};

	Spinner.prototype._updateLoadProgress = function() {
		var pct = Math.round((this.loadedCount / this.totalFrames) * 100);
		if (this.loader) {
			var t = this.loader.querySelector('.mh-360-loader-text');
			if (t) t.textContent = pct + '%';
		}
	};

	Spinner.prototype._onAllLoaded = function() {
		if (this.loader) this.loader.classList.add('is-hidden');
		if (this.isVisible && this.autoplayEnabled && !this.userInteracted) {
			this._startAutoplay();
		}
	};

	/* ── Visibility ── */
	Spinner.prototype._setupObserver = function() {
		var self = this;
		if (!('IntersectionObserver' in window)) { this.isVisible = true; return; }
		var obs = new IntersectionObserver(function(entries) {
			self.isVisible = entries[0].isIntersecting;
			if (self.isVisible && self.allLoaded && self.autoplayEnabled && !self.userInteracted) {
				self._startAutoplay();
			} else if (!self.isVisible) {
				self._stopAutoplay();
			}
		}, { threshold: 0.3 });
		obs.observe(this.container);
	};

	/* ── Autoplay (rAF loop + ease-in ramp) ── */
	Spinner.prototype._startAutoplay = function() {
		if (this.isAutoPlaying || !this.allLoaded) return;
		this.isAutoPlaying = true;
		this.autoplayRampStart = Date.now();
		this.canvas.classList.add('is-autoplaying');

		var self = this;
		var interval = Math.round(1000 / AUTOPLAY_FPS);
		var accum = 0;
		var last = Date.now();

		function tick() {
			if (!self.isAutoPlaying) return;
			var now = Date.now();
			var dt = now - last;
			last = now;

			// Smoothstep ease-in over AUTOPLAY_RAMP ms.
			var t = Math.min((now - self.autoplayRampStart) / AUTOPLAY_RAMP, 1);
			var ramp = t * t * (3 - 2 * t);

			accum += dt * ramp;
			if (accum >= interval) {
				self._showFrame(self.currentFrame + 1);
				accum -= interval;
			}
			self.autoplayTimer = requestAnimationFrame(tick);
		}
		self.autoplayTimer = requestAnimationFrame(tick);
	};

	Spinner.prototype._stopAutoplay = function() {
		if (this.autoplayTimer) {
			cancelAnimationFrame(this.autoplayTimer);
			this.autoplayTimer = null;
		}
		this.isAutoPlaying = false;
		this.canvas.classList.remove('is-autoplaying');
	};

	Spinner.prototype._scheduleResume = function() {
		var self = this;
		if (this.resumeTimer) clearTimeout(this.resumeTimer);
		this.resumeTimer = setTimeout(function() {
			if (self.isVisible && self.autoplayEnabled && self.allLoaded) {
				self._startAutoplay();
			}
		}, AUTOPLAY_DELAY);
	};

	/* ── Mouse ── */
	Spinner.prototype._handleMouseDown = function(e) {
		if (e.button !== 0) return;
		e.preventDefault();
		this._dragStart(e.clientX);
		document.addEventListener('mousemove', this._onMouseMove);
		document.addEventListener('mouseup', this._onMouseUp);
	};
	Spinner.prototype._handleMouseMove = function(e) { this._dragMove(e.clientX); };
	Spinner.prototype._handleMouseUp = function() {
		document.removeEventListener('mousemove', this._onMouseMove);
		document.removeEventListener('mouseup', this._onMouseUp);
		this._dragEnd();
	};

	/* ── Touch ── */
	Spinner.prototype._handleTouchStart = function(e) {
		if (e.touches.length !== 1) return;
		// v5.2.0: Don't intercept touches on the expand button — let click through.
		var t = e.target;
		if (t && (t.classList.contains('mh-360-expand') || t.closest('.mh-360-expand'))) return;
		e.preventDefault();
		this._dragStart(e.touches[0].clientX);
		this.canvas.addEventListener('touchmove', this._onTouchMove, { passive: false });
		this.canvas.addEventListener('touchend', this._onTouchEnd);
	};
	Spinner.prototype._handleTouchMove = function(e) {
		if (e.touches.length !== 1) return;
		e.preventDefault();
		this._dragMove(e.touches[0].clientX);
	};
	Spinner.prototype._handleTouchEnd = function() {
		this.canvas.removeEventListener('touchmove', this._onTouchMove);
		this.canvas.removeEventListener('touchend', this._onTouchEnd);
		this._dragEnd();
	};

	/* ── Drag (rAF-throttled + sub-pixel accumulator) ── */
	Spinner.prototype._dragStart = function(x) {
		if (this.momentumRAF) { cancelAnimationFrame(this.momentumRAF); this.momentumRAF = null; }
		if (this.dragRAF) { cancelAnimationFrame(this.dragRAF); this.dragRAF = null; }

		this._stopAutoplay();
		this.userInteracted = true;

		if (this.hintEl && !this.hintEl.classList.contains('is-hidden')) {
			this.hintEl.classList.add('is-hidden');
		}

		this.isDragging   = true;
		this.lastX        = x;
		this.lastTime     = Date.now();
		this.dragAccum    = 0;
		this.velocity     = 0;
		this.rawVelocity  = 0;
		this.pendingFrame = -1;
		this.canvas.classList.add('is-dragging');
	};

	Spinner.prototype._dragMove = function(x) {
		if (!this.isDragging) return;

		var now = Date.now();
		var dt  = now - this.lastTime;
		var dx  = x - this.lastX;

		// Smoothed velocity (EMA).
		if (dt > 0) {
			this.rawVelocity = dx / dt;
			this.velocity = this.velocity * (1 - VELOCITY_SMOOTH) + this.rawVelocity * VELOCITY_SMOOTH;
		}

		this.lastX    = x;
		this.lastTime = now;

		// Sub-pixel accumulator — only change frame on full-pixel boundaries.
		this.dragAccum += dx / SENSITIVITY;
		var delta = Math.trunc(this.dragAccum);
		if (delta !== 0) {
			this.dragAccum -= delta;
			var target = this.currentFrame - delta;
			target = ((target % this.totalFrames) + this.totalFrames) % this.totalFrames;
			this.pendingFrame = target;
		}

		// rAF throttle — render at screen refresh rate, not mousemove rate.
		if (this.pendingFrame >= 0 && !this.dragRAF) {
			var self = this;
			this.dragRAF = requestAnimationFrame(function() {
				self.dragRAF = null;
				if (self.pendingFrame >= 0) {
					self._showFrame(self.pendingFrame);
					self.pendingFrame = -1;
				}
			});
		}
	};

	Spinner.prototype._dragEnd = function() {
		if (!this.isDragging) return;
		this.isDragging = false;

		if (this.dragRAF) { cancelAnimationFrame(this.dragRAF); this.dragRAF = null; }
		if (this.pendingFrame >= 0) {
			this._showFrame(this.pendingFrame);
			this.pendingFrame = -1;
		}
		this.canvas.classList.remove('is-dragging');

		var velFrames = this.velocity / SENSITIVITY * 16;

		if (this.momentumEnabled && Math.abs(velFrames) > MIN_VELOCITY) {
			this._startMomentum(velFrames);
		} else {
			this._scheduleResume();
		}
	};

	/* ── Momentum ── */
	Spinner.prototype._startMomentum = function(v) {
		var self = this;
		var vel = -v;
		var accum = 0;

		function tick() {
			vel *= FRICTION;
			if (Math.abs(vel) < MIN_VELOCITY * 0.3) {
				self.momentumRAF = null;
				self._scheduleResume();
				return;
			}
			accum += vel;
			if (Math.abs(accum) >= 1) {
				var steps = Math.trunc(accum);
				self._showFrame(self.currentFrame + steps);
				accum -= steps;
			}
			self.momentumRAF = requestAnimationFrame(tick);
		}
		this.momentumRAF = requestAnimationFrame(tick);
	};

	/* ── Public API ── */
	Spinner.prototype.goTo = function(idx) { this._showFrame(idx); };
	Spinner.prototype.play = function() { this.userInteracted = false; this._startAutoplay(); };
	Spinner.prototype.stop = function() { this._stopAutoplay(); if (this.resumeTimer) clearTimeout(this.resumeTimer); };

	Spinner.prototype.destroy = function() {
		this._stopAutoplay();
		if (this.momentumRAF) cancelAnimationFrame(this.momentumRAF);
		if (this.dragRAF) cancelAnimationFrame(this.dragRAF);
		if (this.resumeTimer) clearTimeout(this.resumeTimer);
		this.canvas.removeEventListener('mousedown', this._onMouseDown);
		this.canvas.removeEventListener('touchstart', this._onTouchStart);
		document.removeEventListener('mousemove', this._onMouseMove);
		document.removeEventListener('mouseup', this._onMouseUp);
		this.container.classList.remove('mh-360-container');
		while (this.container.firstChild) this.container.removeChild(this.container.firstChild);
	};

	return {
		create: function(container, opts) { return new Spinner(container, opts); }
	};
})();
