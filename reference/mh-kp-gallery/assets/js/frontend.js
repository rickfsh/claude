/**
 * MH Shop the Look — Slider + Lightbox Frontend
 */

/* ═══ SCROLL LOCK UTILITY ═════════════════════════════════════
   Prevents body scroll without the 17px scrollbar-jump.
   Measures scrollbar width, applies it as padding-right,
   then sets overflow:hidden.
   ═════════════════════════════════════════════════════════════ */
var _mhScrollLockCount = 0;
function mhScrollLock() {
    _mhScrollLockCount++;
    if (_mhScrollLockCount > 1) return;
    var scrollbarW = window.innerWidth - document.documentElement.clientWidth;
    document.documentElement.style.setProperty('--mh-scrollbar-w', scrollbarW + 'px');
    document.body.style.paddingRight = scrollbarW + 'px';
    document.body.classList.add('mh-stl-scroll-locked');
}
function mhScrollUnlock() {
    _mhScrollLockCount = Math.max(0, _mhScrollLockCount - 1);
    if (_mhScrollLockCount > 0) return;
    document.body.classList.remove('mh-stl-scroll-locked');
    document.body.style.paddingRight = '';
    document.documentElement.style.removeProperty('--mh-scrollbar-w');
}

/**
 * Build pin HTML for a specific gallery image index.
 * @param {Array}  allPins    - Full pins array (with image_index)
 * @param {number} imageIndex - Which gallery image (0-based)
 * @param {Function} escFn    - Escape function
 * @returns {string} HTML string of pin divs
 */
function mhBuildPinsHtml(allPins, imageIndex, escFn) {
    var h = '';
    allPins.forEach(function (pin, i) {
        if ((pin.image_index || 0) !== imageIndex) return;
        h += '<div class="mh-stl-pin" data-index="' + i + '" style="left:' + pin.x + '%;top:' + pin.y + '%">';
        h += '<span class="mh-stl-pin-num">' + (i + 1) + '</span>';
        h += '<span class="mh-stl-pin-tooltip">' + escFn(pin.name) + '</span>';
        h += '</div>';
    });
    return h;
}

/**
 * Check which image indices have pins.
 * @param {Array} allPins
 * @returns {Object} map of imageIndex -> pin count
 */
function mhPinImageMap(allPins) {
    var map = {};
    allPins.forEach(function (p) {
        var idx = p.image_index || 0;
        map[idx] = (map[idx] || 0) + 1;
    });
    return map;
}

/**
 * Build badge HTML for a product row linking to a gallery image.
 * Shows camera icon + "Bild X" label, transforms to "Anzeigen →" on hover.
 */
function mhBadgeHtml(imageIndex) {
    return '<span class="mh-stl-product-row-img-badge" data-gindex="' + imageIndex + '">' +
        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="6" width="20" height="14" rx="2"/><circle cx="12" cy="13" r="4"/><path d="M2 6l3-3h4l2 3"/></svg>' +
        '<span class="mh-badge-label">Bild ' + (imageIndex + 1) + '</span>' +
        '<span class="mh-badge-action">Anzeigen →</span>' +
        '</span>';
}

/**
 * Bind product-row hover → thumbnail highlight + click badge → navigate.
 * Hover: highlights the matching gallery thumbnail with a glow.
 * Click on "Bild X" badge: navigates gallery to that image.
 *
 * @param {HTMLElement} container - The info panel containing product rows
 * @param {Object}      gallery  - Gallery controller from mhInitGallery (with goTo)
 * @param {HTMLElement} thumbContainer - Element containing .mh-lb-gallery-thumbs
 */
function mhBindRowImageNav(container, gallery, thumbContainer) {
    if (!container || !gallery || !gallery.goTo) return;
    var thumbsRoot = thumbContainer || container;

    var rows = container.querySelectorAll('.mh-stl-product-row[data-image-index]');
    rows.forEach(function (row) {
        var targetImg = parseInt(row.getAttribute('data-image-index'));
        if (isNaN(targetImg)) return;

        // Hover → highlight thumbnail
        row.addEventListener('mouseenter', function () {
            var thumbs = thumbsRoot.querySelectorAll('.mh-lb-gallery-thumb');
            thumbs.forEach(function (t) {
                var gi = parseInt(t.getAttribute('data-gindex'));
                t.classList.toggle('is-hover-target', gi === targetImg);
            });
        });
        row.addEventListener('mouseleave', function () {
            var thumbs = thumbsRoot.querySelectorAll('.mh-lb-gallery-thumb');
            thumbs.forEach(function (t) {
                t.classList.remove('is-hover-target');
            });
        });

        // Click on "Bild X" badge → navigate gallery (prevent link)
        var badge = row.querySelector('.mh-stl-product-row-img-badge');
        if (badge) {
            badge.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                gallery.goTo(targetImg);
            });
        }
    });
}

(function () {
    'use strict';

    var slides = [];
    var currentIndex = 0;

    document.addEventListener('DOMContentLoaded', function () {
        var wrapper = document.querySelector('.mh-stl-wrapper');
        if (!wrapper) return;

        try { slides = JSON.parse(wrapper.getAttribute('data-slides')); } catch (e) { return; }
        if (!slides || !slides.length) return;

        showSlide(0, true);
        bindArrows();
        bindPinClicks();
        bindExpand();
        bindLightboxClose();

        // Keyboard
        document.addEventListener('keydown', function (e) {
            var lb = document.getElementById('mh-stl-lightbox');
            if (lb && lb.getAttribute('aria-hidden') === 'false') {
                if (e.key === 'Escape') closeLightbox();
                return;
            }
            if (e.key === 'ArrowLeft') navigate(-1);
            if (e.key === 'ArrowRight') navigate(1);
        });

        // Swipe
        var startX = 0;
        var imageSide = document.getElementById('mh-stl-image-side');
        if (imageSide) {
            imageSide.addEventListener('touchstart', function (e) { startX = e.touches[0].clientX; }, { passive: true });
            imageSide.addEventListener('touchend', function (e) {
                var diff = e.changedTouches[0].clientX - startX;
                if (Math.abs(diff) > 50) navigate(diff > 0 ? -1 : 1);
            }, { passive: true });
        }
    });

    /* ═══ SHOW SLIDE ═════════════════════════════════════════ */
    function showSlide(index, skipFade) {
        currentIndex = index;
        var s = slides[index];
        if (!s) return;

        var delay = skipFade ? 0 : 180;

        // Fade out image + info body (skip on first load)
        var imageSide = document.getElementById('mh-stl-image-side');
        var infoBody = document.querySelector('.mh-stl-info-body');
        if (!skipFade) {
            if (imageSide) imageSide.classList.add('mh-stl-fade-out');
            if (infoBody) infoBody.classList.add('mh-stl-fade-out');
        }

        // Short delay for fade-out, then swap content
        setTimeout(function () {
            // Image
            var img = document.getElementById('mh-stl-main-img');
            if (img) {
                imageSide.classList.add('loading');
                img.onload = function () { imageSide.classList.remove('loading'); };
                img.src = s.image;
                img.alt = s.title;
            }

            // Close any popup
            removeFixedPopup();

            // Pins (slider shows first image only, so filter to image_index=0)
            var pinsLayer = document.getElementById('mh-stl-pins-layer');
            if (pinsLayer) {
                pinsLayer.innerHTML = '';
                s.pins.forEach(function (pin, i) {
                    if ((pin.image_index || 0) !== 0) return;
                    var el = document.createElement('div');
                    el.className = 'mh-stl-pin';
                    el.setAttribute('data-index', i);
                    el.setAttribute('tabindex', '0');
                    el.setAttribute('role', 'button');
                    el.setAttribute('aria-label', pin.name);
                    el.style.left = pin.x + '%';
                    el.style.top = pin.y + '%';
                    el.innerHTML = '<span class="mh-stl-pin-num">' + (i + 1) + '</span>' +
                        '<span class="mh-stl-pin-tooltip">' + esc(pin.name) + '</span>';
                    pinsLayer.appendChild(el);
                });
            }

            // Title
            var titleEl = document.getElementById('mh-stl-title');
            if (titleEl) titleEl.textContent = s.title;

            // Description
            var descEl = document.getElementById('mh-stl-desc');
            if (descEl) descEl.textContent = s.desc || '';

            // Products list
            var listEl = document.getElementById('mh-stl-products-list');
            if (listEl) {
                var h = '';
                s.pins.forEach(function (pin, idx) {
                    h += '<a class="mh-stl-product-row" href="' + esc(pin.url) + '" target="_blank" rel="noopener">';
                    h += '<span class="mh-stl-product-row-num">' + (idx + 1) + '</span>';
                    if (pin.thumb) h += '<img src="' + esc(pin.thumb) + '" alt="' + esc(pin.name) + '">';
                    h += '<div class="mh-stl-product-row-info">';
                    h += '<div class="mh-stl-product-row-name">' + esc(pin.name) + '</div>';
                    h += renderPriceHtml(pin, 'mh-stl-product-row-price');
                    h += '</div>';
                    h += '<span class="mh-stl-product-row-arrow">→</span>';
                    h += '</a>';
                });
                // Add toggle button if more than 3 products
                if (s.pins.length > 3) {
                    listEl.classList.add('is-collapsed');
                    h += '<button class="mh-stl-products-toggle" id="mh-stl-products-toggle">';
                    h += '<span>Alle ' + s.pins.length + ' Produkte anzeigen</span>';
                    h += '<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>';
                    h += '</button>';
                } else {
                    listEl.classList.remove('is-collapsed');
                }
                listEl.innerHTML = h;

                // Bind toggle
                var toggleBtn = document.getElementById('mh-stl-products-toggle');
                if (toggleBtn) {
                    toggleBtn.addEventListener('click', function() {
                        var expanded = listEl.classList.toggle('is-collapsed');
                        toggleBtn.classList.toggle('is-expanded', !listEl.classList.contains('is-collapsed'));
                        toggleBtn.querySelector('span').textContent = listEl.classList.contains('is-collapsed')
                            ? 'Alle ' + s.pins.length + ' Produkte anzeigen'
                            : 'Weniger anzeigen';
                    });
                }
            }

            // Separator visibility (hide when no products)
            var sepEl = document.getElementById('mh-stl-separator');
            if (sepEl) sepEl.style.display = s.pins.length > 0 ? '' : 'none';

            // Buttons
            var btnsEl = document.getElementById('mh-stl-buttons');
            if (btnsEl) {
                var bh = '';
                if (s.buttons && s.buttons.length) {
                    s.buttons.forEach(function (btn) {
                        var cls = btn.style === 'primary' ? 'mh-stl-btn-primary' : 'mh-stl-btn-secondary';
                        bh += '<a class="' + cls + '" href="' + esc(btn.url) + '" target="_blank" rel="noopener">' + esc(btn.text) + '</a>';
                    });
                }
                btnsEl.innerHTML = bh;
            }

            // Progress bar
            var progressBar = document.getElementById('mh-stl-progress-bar');
            if (progressBar) {
                var pct = ((index + 1) / slides.length) * 100;
                progressBar.style.width = pct + '%';
            }

            // Counter text
            var counterText = document.getElementById('mh-stl-counter-text');
            if (counterText) {
                var cur = (index + 1 < 10 ? '0' : '') + (index + 1);
                var tot = (slides.length < 10 ? '0' : '') + slides.length;
                counterText.textContent = cur + ' — ' + tot;
            }

            // Fade back in
            if (imageSide) imageSide.classList.remove('mh-stl-fade-out');
            if (infoBody) infoBody.classList.remove('mh-stl-fade-out');

            // Bind pin ↔ product sync (slider: pins in image-side, rows in info-side)
            var infoSide = document.querySelector('.mh-stl-info-side');
            if (infoSide && imageSide) {
                mhBindPinProductSync(infoSide, '.mh-stl-pin', '.mh-stl-product-row', imageSide);
            }

            // Preload adjacent slides
            preloadAdjacent();
        }, delay);
    }

    /* ═══ NAVIGATION ═════════════════════════════════════════ */
    function navigate(dir) {
        var next = currentIndex + dir;
        if (next < 0) next = slides.length - 1;
        if (next >= slides.length) next = 0;
        showSlide(next);
    }

    /* ═══ IMAGE PRELOADING (Item 6) ═══════════════════════════ */
    function preloadAdjacent() {
        var indices = [currentIndex - 1, currentIndex + 1];
        indices.forEach(function (idx) {
            if (idx < 0) idx = slides.length - 1;
            if (idx >= slides.length) idx = 0;
            if (idx === currentIndex) return;
            var s = slides[idx];
            if (s && s.image) {
                var img = new Image();
                img.src = s.image;
            }
        });
    }

    function bindArrows() {
        var prev = document.getElementById('mh-stl-prev');
        var next = document.getElementById('mh-stl-next');
        if (prev) prev.addEventListener('click', function () { navigate(-1); });
        if (next) next.addEventListener('click', function () { navigate(1); });
    }

    /* ═══ PIN CLICKS → POPUP ═════════════════════════════════ */
    function bindPinClicks() {
        document.addEventListener('click', function (e) {
            var pinEl = e.target.closest('.mh-stl-pin');
            var container = e.target.closest('.mh-stl-image-side, .mh-stl-lb-image-wrap');
            if (!container) return;

            // Click on image (not pin/popup/button)
            if (!pinEl && !e.target.closest('.mh-stl-popup') && !e.target.closest('.mh-stl-expand-btn')) {
                // If a popup was open at the moment of click, just close it — do NOT open lightbox
                if (_mhPopupOpenAtClick) {
                    removeFixedPopup();
                    return;
                }

                // No popup was open → open lightbox (only from slider, not from inside lightbox)
                if (container.classList.contains('mh-stl-image-side')) {
                    openLightbox();
                }
                return;
            }

            if (!pinEl) return;
            e.stopPropagation();

            // Remove existing popup
            removeFixedPopup();

            var idx = parseInt(pinEl.getAttribute('data-index'));
            var slide = slides[currentIndex];
            if (!slide || !slide.pins[idx]) return;
            var pin = slide.pins[idx];

            showFixedPopup(pinEl, pin);
        });
    }

    /* ═══ LIGHTBOX ════════════════════════════════════════════ */
    function bindExpand() {
        var btn = document.getElementById('mh-stl-expand');
        if (btn) btn.addEventListener('click', function(e) { e.stopPropagation(); openLightbox(); });
    }

    var _sliderGallery = null;

    function openLightbox() {
        var lb = document.getElementById('mh-stl-lightbox');
        if (!lb) return;
        var s = slides[currentIndex];
        if (!s) return;

        var images = s.images || [];
        if (!images.length && s.image) {
            images = [{ url: s.image, thumb: s.image }];
        }

        var h = '<div class="mh-stl-lb-inner">';
        h += '<button class="mh-stl-lb-close" id="mh-stl-lb-close">&times;</button>';
        h += '<div class="mh-stl-lb-image-wrap" id="mh-stl-lb-wrap">';
        h += '<img src="' + esc(s.image) + '" alt="' + esc(s.title) + '">';
        h += '<div class="mh-grid-lb-zoom-hint"><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/><path d="M8 11h6M11 8v6"/></svg>Klicken zum Zoomen</div>';
        h += '<div class="mh-stl-lb-pins">';
        h += mhBuildPinsHtml(s.pins, 0, esc);
        h += '</div></div></div>';

        lb.innerHTML = h;
        lb.style.display = '';
        lb.setAttribute('aria-hidden', 'false');
        mhScrollLock();

        // ── Morph animation: origin from slider image ──
        var inner = lb.querySelector('.mh-stl-lb-inner');
        var imageSide = document.getElementById('mh-stl-image-side');
        if (inner && imageSide) {
            var rect = imageSide.getBoundingClientRect();
            var cx = rect.left + rect.width / 2;
            var cy = rect.top + rect.height / 2;
            inner.style.transformOrigin = cx + 'px ' + cy + 'px';
        }
        lb.offsetHeight;
        lb.classList.add('mh-lb-open');

        document.getElementById('mh-stl-lb-close').addEventListener('click', closeLightbox);

        // Zoom for slider lightbox
        var wrap = document.getElementById('mh-stl-lb-wrap');
        var img = wrap ? wrap.querySelector('img') : null;
        var zoomed = false;

        // Init gallery navigation
        if (wrap) {
            _sliderGallery = mhInitGallery(wrap, images, s.pins, {
                onImageChange: function () {
                    if (zoomed) {
                        zoomed = false;
                        wrap.classList.remove('zoomed');
                        if (img) img.style.transformOrigin = 'center center';
                    }
                    var hint = wrap.querySelector('.mh-grid-lb-zoom-hint');
                    if (hint) hint.style.display = '';
                }
            });
            if (_sliderGallery && _sliderGallery.keyHandler) {
                document.addEventListener('keydown', _sliderGallery.keyHandler);
            }
        }

        if (wrap && img) {
            wrap.addEventListener('click', function (e) {
                if (e.target.closest('.mh-stl-pin') || e.target.closest('.mh-stl-popup')) return;
                var isNav = e.target.closest('.mh-lb-gallery-nav') || e.target.closest('.mh-lb-gallery-thumb');
                if (isNav) return;

                if (_mhPopupOpenAtClick) {
                    removeFixedPopup();
                    return;
                }

                zoomed = !zoomed;
                wrap.classList.toggle('zoomed', zoomed);
                var pinsLayer = wrap.querySelector('.mh-stl-lb-pins');
                if (pinsLayer) pinsLayer.style.display = zoomed ? 'none' : '';
                if (!zoomed) img.style.transformOrigin = 'center center';
            });
            wrap.addEventListener('mousemove', function (e) {
                if (!zoomed) return;
                var rect = wrap.getBoundingClientRect();
                img.style.transformOrigin = ((e.clientX - rect.left) / rect.width * 100) + '% ' + ((e.clientY - rect.top) / rect.height * 100) + '%';
            });
        }
    }

    function closeLightbox() {
        if (_sliderGallery) {
            document.removeEventListener('keydown', _sliderGallery.keyHandler);
            _sliderGallery.destroy();
            _sliderGallery = null;
        }
        var lb = document.getElementById('mh-stl-lightbox');
        if (!lb) return;
        lb.classList.remove('mh-lb-open');
        lb.classList.add('mh-lb-closing');
        setTimeout(function() {
            lb.classList.remove('mh-lb-closing');
            lb.setAttribute('aria-hidden', 'true');
            lb.style.display = 'none';
            lb.innerHTML = '';
            mhScrollUnlock();
            removeFixedPopup();
        }, 300);
    }

    function bindLightboxClose() {
        document.addEventListener('click', function (e) {
            if (e.target && e.target.id === 'mh-stl-lightbox') closeLightbox();
        });
    }

    /* ═══ HELPERS ═════════════════════════════════════════════ */
    function renderPriceHtml(pin, className) {
        if (!pin.price) return '';
        if (pin.sale_price) {
            return '<div class="' + className + '">' +
                '<span class="mh-stl-price-old">' + esc(pin.price) + '</span> ' +
                '<span class="mh-stl-price-sale">' + esc(pin.sale_price) + '</span>' +
                '</div>';
        }
        return '<div class="' + className + '">' + esc(pin.price) + '</div>';
    }

    function esc(s) {
        if (!s) return '';
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(s));
        return d.innerHTML;
    }
})();

/* ═══ GLOBAL POPUP HELPERS (shared between slider + grid) ════ */

/* Capture-phase: record whether a popup was open BEFORE any handler removes it.
   This fires before all bubble-phase handlers (the global cleanup, bindPinClicks, etc.) */
var _mhPopupOpenAtClick = false;
document.addEventListener('click', function () {
    _mhPopupOpenAtClick = !!document.getElementById('mh-stl-fixed-popup');
}, true);

function removeFixedPopup() {
    var old = document.getElementById('mh-stl-fixed-popup');
    if (old) old.remove();
    var backdrop = document.getElementById('mh-stl-popup-backdrop');
    if (backdrop) backdrop.remove();
}

function renderPriceHtmlGlobal(pin, className) {
    var _e = function(s) {
        if (!s) return '';
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(s));
        return d.innerHTML;
    };
    if (!pin.price) return '';
    if (pin.sale_price) {
        return '<div class="' + className + '">' +
            '<span class="mh-stl-price-old">' + _e(pin.price) + '</span> ' +
            '<span class="mh-stl-price-sale">' + _e(pin.sale_price) + '</span>' +
            '</div>';
    }
    return '<div class="' + className + '">' + _e(pin.price) + '</div>';
}

function showFixedPopup(pinEl, pin) {
    removeFixedPopup();

    var vw = window.innerWidth;
    var vh = window.innerHeight;
    var isMobile = vw < 768;

    var popup = document.createElement('div');
    popup.className = 'mh-stl-popup' + (isMobile ? ' mh-stl-popup-mobile' : '');
    popup.id = 'mh-stl-fixed-popup';

    var _esc = function(s) {
        if (!s) return '';
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(s));
        return d.innerHTML;
    };

    var ph = '<button class="mh-stl-popup-close">&times;</button>';

    if (isMobile) {
        // Mobile: horizontal layout (thumb left, info right)
        ph += '<div class="mh-stl-popup-drag-indicator"></div>';
        ph += '<div class="mh-stl-popup-mobile-row">';
        if (pin.thumb) ph += '<img class="mh-stl-popup-thumb-sm" src="' + _esc(pin.thumb) + '" alt="">';
        ph += '<div class="mh-stl-popup-info">';
        ph += '<div class="mh-stl-popup-name">' + _esc(pin.name) + '</div>';
        if (pin.price) {
            if (pin.sale_price) {
                ph += '<div class="mh-stl-popup-price"><span class="mh-stl-price-old">' + _esc(pin.price) + '</span> <span class="mh-stl-price-sale">' + _esc(pin.sale_price) + '</span></div>';
            } else {
                ph += '<div class="mh-stl-popup-price">' + _esc(pin.price) + '</div>';
            }
        }
        ph += '</div>';
        ph += '<a class="mh-stl-popup-btn-sm" href="' + _esc(pin.url) + '" target="_blank" rel="noopener">Zum Produkt →</a>';
        ph += '</div>';
    } else {
        // Desktop: vertical card layout
        if (pin.thumb) ph += '<img class="mh-stl-popup-thumb" src="' + _esc(pin.thumb) + '" alt="">';
        ph += '<div class="mh-stl-popup-info">';
        ph += '<div class="mh-stl-popup-name">' + _esc(pin.name) + '</div>';
        if (pin.price) {
            if (pin.sale_price) {
                ph += '<div class="mh-stl-popup-price"><span class="mh-stl-price-old">' + _esc(pin.price) + '</span> <span class="mh-stl-price-sale">' + _esc(pin.sale_price) + '</span></div>';
            } else {
                ph += '<div class="mh-stl-popup-price">' + _esc(pin.price) + '</div>';
            }
        }
        ph += '<a class="mh-stl-popup-btn" href="' + _esc(pin.url) + '" target="_blank" rel="noopener">Zum Produkt →</a>';
        ph += '</div>';
    }

    popup.innerHTML = ph;

    if (isMobile) {
        // Mobile: bottom sheet
        popup.style.cssText = 'position:fixed;z-index:999999;left:0;right:0;bottom:0;pointer-events:auto;';

        // Add backdrop
        var backdrop = document.createElement('div');
        backdrop.id = 'mh-stl-popup-backdrop';
        backdrop.style.cssText = 'position:fixed;inset:0;z-index:999998;background:rgba(0,0,0,.3);';
        backdrop.addEventListener('click', function() { removeFixedPopup(); });
        document.body.appendChild(backdrop);
    } else {
        // Desktop: position next to pin
        var rect = pinEl.getBoundingClientRect();
        var left, top;
        if (rect.right + 260 < vw) {
            left = rect.right + 8;
        } else {
            left = rect.left - 260 - 8;
        }
        top = rect.top - 60;
        if (top + 320 > vh) top = vh - 330;
        if (top < 10) top = 10;

        popup.style.cssText = 'position:fixed;z-index:999999;left:' + left + 'px;top:' + top + 'px;width:250px;pointer-events:auto;';
    }

    document.body.appendChild(popup);

    // ── Mobile: swipe-to-dismiss ──
    if (isMobile) {
        var touchStartY = 0;
        var touchCurrentY = 0;
        var isDragging = false;

        popup.addEventListener('touchstart', function (e) {
            touchStartY = e.touches[0].clientY;
            touchCurrentY = touchStartY;
            isDragging = true;
            popup.style.transition = 'none';
        }, { passive: true });

        popup.addEventListener('touchmove', function (e) {
            if (!isDragging) return;
            touchCurrentY = e.touches[0].clientY;
            var deltaY = touchCurrentY - touchStartY;
            // Only allow dragging downward
            if (deltaY > 0) {
                popup.style.transform = 'translateY(' + deltaY + 'px)';
                // Fade backdrop proportionally
                var backdropEl = document.getElementById('mh-stl-popup-backdrop');
                if (backdropEl) {
                    var opacity = Math.max(0, 0.3 - (deltaY / 400));
                    backdropEl.style.background = 'rgba(0,0,0,' + opacity.toFixed(2) + ')';
                }
            }
        }, { passive: true });

        popup.addEventListener('touchend', function () {
            if (!isDragging) return;
            isDragging = false;
            var deltaY = touchCurrentY - touchStartY;
            if (deltaY > 80) {
                // Dismiss: slide out fully
                popup.style.transition = 'transform .2s ease, opacity .2s ease';
                popup.style.transform = 'translateY(100%)';
                popup.style.opacity = '0';
                setTimeout(function () { removeFixedPopup(); }, 200);
            } else {
                // Snap back
                popup.style.transition = 'transform .25s cubic-bezier(.22,1,.36,1)';
                popup.style.transform = 'translateY(0)';
                var backdropEl = document.getElementById('mh-stl-popup-backdrop');
                if (backdropEl) {
                    backdropEl.style.background = 'rgba(0,0,0,.3)';
                }
            }
        }, { passive: true });
    }

    popup.querySelector('.mh-stl-popup-close').addEventListener('click', function (ev) {
        ev.stopPropagation(); removeFixedPopup();
    });

    popup.addEventListener('click', function(e) { e.stopPropagation(); });
}

// Close fixed popup on any click outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('#mh-stl-fixed-popup') && !e.target.closest('.mh-stl-pin')) {
        removeFixedPopup();
    }
});

/* ═══ GLOBAL PIN ↔ PRODUCT SYNC ══════════════════════════════
   Bidirectional highlight: hover pin → highlight product row,
   hover product row → highlight pin. Works in slider, grid
   lightbox, and product lightbox.
   ═════════════════════════════════════════════════════════════ */

/**
 * Bind pin-product sync highlights inside a container.
 *
 * @param {HTMLElement} container  - Wrapper (slider info-side, lightbox inner, etc.)
 * @param {string}      pinSel    - CSS selector for pin elements (default: '.mh-stl-pin')
 * @param {string}      rowSel    - CSS selector for product rows (default: '.mh-stl-product-row')
 * @param {HTMLElement}  pinContainer - Optional separate container for pins (e.g. image-side vs info-side)
 */
function mhBindPinProductSync(container, pinSel, rowSel, pinContainer) {
    if (!container) return;
    pinSel = pinSel || '.mh-stl-pin';
    rowSel = rowSel || '.mh-stl-product-row';
    pinContainer = pinContainer || container;

    var pins = pinContainer.querySelectorAll(pinSel);
    var rows = container.querySelectorAll(rowSel);
    var productList = rows.length ? rows[0].parentElement : null;

    if (!pins.length || !rows.length) return;

    /**
     * Highlight by global pin array index (data-index on pin, sequential on rows).
     * Pins may be a subset of rows (multi-image: only current image's pins visible).
     */
    function highlightByDataIndex(dataIdx) {
        pins.forEach(function(p) {
            var pIdx = parseInt(p.getAttribute('data-index'));
            if (pIdx === dataIdx) {
                p.classList.add('is-synced');
                p.classList.remove('is-sync-dimmed');
            } else {
                p.classList.remove('is-synced');
                p.classList.add('is-sync-dimmed');
            }
        });
        if (productList) productList.classList.add('has-sync');
        rows.forEach(function(r, j) {
            r.classList.toggle('is-synced', j === dataIdx);
        });
        if (rows[dataIdx]) {
            rows[dataIdx].scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        }
    }

    function clearAll() {
        pins.forEach(function(p) {
            p.classList.remove('is-synced', 'is-sync-dimmed');
        });
        rows.forEach(function(r) {
            r.classList.remove('is-synced');
        });
        if (productList) productList.classList.remove('has-sync');
    }

    // Pin → Product row (use data-index attribute)
    pins.forEach(function(pin) {
        var dataIdx = parseInt(pin.getAttribute('data-index'));
        pin.addEventListener('mouseenter', function() { highlightByDataIndex(dataIdx); });
        pin.addEventListener('mouseleave', clearAll);
        pin.addEventListener('focus', function() { highlightByDataIndex(dataIdx); });
        pin.addEventListener('blur', clearAll);
    });

    // Product row → Pin (row position = global pin index)
    rows.forEach(function(row, i) {
        row.addEventListener('mouseenter', function() { highlightByDataIndex(i); });
        row.addEventListener('mouseleave', clearAll);
        row.addEventListener('focus', function() { highlightByDataIndex(i); });
        row.addEventListener('blur', clearAll);
    });
}

/* ═══ GLOBAL FLIP HELPER (smooth masonry load-more) ════════════ */

/**
 * Append HTML to a CSS-column container without visual jumping.
 * Uses FLIP (First-Last-Invert-Play) to animate existing cards
 * to their new positions after the column reflow.
 *
 * @param {HTMLElement} container - The column-count container
 * @param {string}      html     - HTML string of new cards to append
 * @param {string}      selector - CSS selector for existing cards
 */
function mhFlipAppend(container, html, selector) {
    var existingCards = container.querySelectorAll(selector);

    // ── F (First): snapshot current positions ──
    var firstRects = new Map();
    existingCards.forEach(function (card) {
        firstRects.set(card, card.getBoundingClientRect());
    });

    // Also snapshot scroll-relative position of an anchor element
    var anchorCard = null;
    var anchorViewportY = 0;
    // Pick the first card that's currently visible in viewport
    for (var i = 0; i < existingCards.length; i++) {
        var r = firstRects.get(existingCards[i]);
        if (r.top >= -50 && r.top < window.innerHeight) {
            anchorCard = existingCards[i];
            anchorViewportY = r.top;
            break;
        }
    }
    // Fallback to last card
    if (!anchorCard && existingCards.length) {
        anchorCard = existingCards[existingCards.length - 1];
        anchorViewportY = firstRects.get(anchorCard).top;
    }

    // ── Insert new cards (they start hidden via mh-grid-card-reveal) ──
    var tempDiv = document.createElement('div');
    tempDiv.innerHTML = html;
    while (tempDiv.firstChild) {
        var node = tempDiv.firstChild;
        if (node.nodeType === 1) {
            node.classList.add('mh-grid-card-reveal');
            node.classList.remove('is-visible');
        }
        container.appendChild(node);
    }

    // ── Scroll correction: keep anchor in same viewport position ──
    // Must be synchronous (before L/I reads getBoundingClientRect)
    if (anchorCard) {
        var newAnchorRect = anchorCard.getBoundingClientRect();
        var scrollDrift = newAnchorRect.top - anchorViewportY;
        if (Math.abs(scrollDrift) > 2) {
            // Instant correction — imperceptible, no smooth animation
            window.scrollBy({ top: scrollDrift, behavior: 'instant' });
        }
    }

    // ── L (Last): snapshot new positions ──
    // ── I (Invert): apply transforms to keep cards in old visual position ──
    existingCards.forEach(function (card) {
        var first = firstRects.get(card);
        var last = card.getBoundingClientRect();
        var dx = first.left - last.left;
        var dy = first.top - last.top;

        if (Math.abs(dx) < 1 && Math.abs(dy) < 1) return; // didn't move

        // Disable transition, apply inverted transform
        card.style.transition = 'none';
        card.style.transform = 'translate(' + dx + 'px, ' + dy + 'px)';
    });

    // ── P (Play): animate to final position ──
    requestAnimationFrame(function () {
        requestAnimationFrame(function () {
            existingCards.forEach(function (card) {
                if (!card.style.transform || card.style.transform === 'none') return;
                card.classList.add('mh-grid-flipping');
                card.style.transition = '';
                card.style.transform = '';
            });

            // Cleanup after animation
            setTimeout(function () {
                existingCards.forEach(function (card) {
                    card.classList.remove('mh-grid-flipping');
                    card.style.transform = '';
                    card.style.transition = '';
                });
            }, 400);
        });
    });
}

/* ═══ GLOBAL GALLERY HELPER (shared lightbox image gallery) ════ */

/**
 * Initialize gallery navigation on an image wrap element.
 *
 * @param {HTMLElement} imageWrap  - The container (.mh-grid-lb-image-wrap or .mh-stl-lb-image-wrap)
 * @param {Array}       images    - Array of { url, thumb } objects
 * @param {Array}       pins      - Pin data (shown only on first image)
 * @param {Object}      options   - { onImageChange: function(index), thumbContainer: HTMLElement }
 * @returns {Object}    controller with goTo(index), currentIndex, destroy()
 */
function mhInitGallery(imageWrap, images, pins, options) {
    options = options || {};
    if (!imageWrap || !images || images.length < 1) return null;

    var _esc = function(s) {
        if (!s) return '';
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(s));
        return d.innerHTML;
    };

    var currentGalleryIndex = 0;
    var img = imageWrap.querySelector('img');
    var pinsLayer = imageWrap.querySelector('.mh-stl-lb-pins');
    var isMulti = images.length > 1;
    var singleClass = isMulti ? '' : ' mh-lb-gallery-single';

    // Add wrapper class
    imageWrap.classList.toggle('mh-lb-gallery-single', !isMulti);

    if (!isMulti) {
        // No gallery needed, return noop controller
        return { goTo: function(){}, currentIndex: 0, destroy: function(){} };
    }

    // ── Build nav arrows ──
    var prevBtn = document.createElement('button');
    prevBtn.className = 'mh-lb-gallery-nav mh-lb-gallery-prev';
    prevBtn.innerHTML = '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg>';

    var nextBtn = document.createElement('button');
    nextBtn.className = 'mh-lb-gallery-nav mh-lb-gallery-next';
    nextBtn.innerHTML = '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"/></svg>';

    imageWrap.appendChild(prevBtn);
    imageWrap.appendChild(nextBtn);

    // ── Counter badge ──
    var counter = document.createElement('div');
    counter.className = 'mh-lb-gallery-counter';
    counter.textContent = '1 / ' + images.length;
    imageWrap.appendChild(counter);

    // ── Thumbnail strip ──
    var thumbStrip = document.createElement('div');
    thumbStrip.className = 'mh-lb-gallery-thumbs';

    images.forEach(function(imgData, i) {
        var wrap = document.createElement('div');
        wrap.className = 'mh-lb-gallery-thumb-wrap';

        var thumb = document.createElement('img');
        thumb.className = 'mh-lb-gallery-thumb' + (i === 0 ? ' active' : '');
        thumb.src = _esc(imgData.thumb || imgData.url);
        thumb.alt = '';
        thumb.setAttribute('data-gindex', i);

        wrap.appendChild(thumb);

        // Pin indicator dot on thumbnails that have pins
        var pinMap = mhPinImageMap(pins || []);
        if (pinMap[i]) {
            var dot = document.createElement('div');
            dot.className = 'mh-lb-gallery-pin-dot';
            dot.title = pinMap[i] + ' Produkt-Pin' + (pinMap[i] > 1 ? 's' : '');
            wrap.appendChild(dot);
        }

        thumbStrip.appendChild(wrap);

        thumb.addEventListener('click', function(e) {
            e.stopPropagation();
            goTo(i);
        });
    });

    // Append to custom container or imageWrap
    var thumbTarget = options.thumbContainer || imageWrap;
    thumbTarget.appendChild(thumbStrip);

    // ── Navigation logic (crossfade) ──
    function goTo(index) {
        if (index < 0) index = images.length - 1;
        if (index >= images.length) index = 0;
        if (index === currentGalleryIndex) return;

        currentGalleryIndex = index;

        // ── True crossfade: ghost old image on top, swap main, fade ghost out ──
        if (img) {
            // Mark container as crossfading
            imageWrap.classList.add('mh-lb-crossfading');

            // Create ghost from current image
            var ghost = document.createElement('img');
            ghost.className = 'mh-lb-crossfade-ghost';
            ghost.src = img.src;
            ghost.alt = '';
            imageWrap.insertBefore(ghost, img.nextSibling);

            // Hide main image, swap src
            img.style.opacity = '0';
            img.src = images[index].url;

            // When new image loads (or timeout), crossfade
            var revealed = false;
            var reveal = function() {
                if (revealed) return;
                revealed = true;
                // Fade ghost out, main in
                ghost.classList.add('is-fading');
                img.style.opacity = '';
                // Clean up ghost after transition
                setTimeout(function() {
                    if (ghost.parentNode) ghost.remove();
                    imageWrap.classList.remove('mh-lb-crossfading');
                }, 450);
            };

            img.addEventListener('load', reveal, { once: true });
            // Safety timeout if image is cached (load may not fire)
            setTimeout(reveal, 80);
        }

        // Rebuild pins for current image (multi-image pin support)
        if (pinsLayer) {
            pinsLayer.innerHTML = mhBuildPinsHtml(pins || [], index, _esc);
            pinsLayer.style.display = '';
        }

        // Update counter
        counter.textContent = (index + 1) + ' / ' + images.length;

        // Update thumbnails
        thumbStrip.querySelectorAll('.mh-lb-gallery-thumb').forEach(function(t) {
            t.classList.toggle('active', parseInt(t.getAttribute('data-gindex')) === index);
        });

        // Scroll active thumb into view (container-scoped, no page jump)
        var activeThumb = thumbStrip.querySelector('.mh-lb-gallery-thumb.active');
        if (activeThumb) {
            var stripRect = thumbStrip.getBoundingClientRect();
            var thumbRect = activeThumb.getBoundingClientRect();
            var targetScroll = thumbStrip.scrollLeft + (thumbRect.left - stripRect.left) - (stripRect.width / 2) + (thumbRect.width / 2);
            thumbStrip.scrollTo({ left: targetScroll, behavior: 'smooth' });
        }

        // Remove popup
        removeFixedPopup();

        // Callback
        if (options.onImageChange) options.onImageChange(index);
    }

    prevBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        goTo(currentGalleryIndex - 1);
    });

    nextBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        goTo(currentGalleryIndex + 1);
    });

    // Keyboard (only when this gallery's lightbox is active)
    var keyHandler = function(e) {
        if (e.key === 'ArrowLeft') { e.preventDefault(); goTo(currentGalleryIndex - 1); }
        if (e.key === 'ArrowRight') { e.preventDefault(); goTo(currentGalleryIndex + 1); }
    };

    return {
        goTo: goTo,
        get currentIndex() { return currentGalleryIndex; },
        keyHandler: keyHandler,
        destroy: function() {
            if (prevBtn.parentNode) prevBtn.remove();
            if (nextBtn.parentNode) nextBtn.remove();
            if (counter.parentNode) counter.remove();
            if (thumbStrip.parentNode) thumbStrip.remove();
        }
    };
}

/**
 * MH Shop the Look — Grid View
 * Filters, Load More, Card click → Lightbox
 */
(function () {
    'use strict';

    var gridCategory = '';
    var revealObserver = null;

    document.addEventListener('DOMContentLoaded', function () {
        if (!document.querySelector('.mh-stl-grid-wrapper')) return;
        bindGridFilters();
        bindGridLoadMore();
        bindGridCardClicks();
        initRevealObserver();
        observeRevealCards();
        initStickyFilters();
        initFilterIndicator();
    });

    /* ═══ STICKY FILTERS ══════════════════════════════════════ */
    function initStickyFilters() {
        var filters = document.getElementById('mh-grid-filters');
        if (!filters || !('IntersectionObserver' in window)) return;

        // Create sentinel element just above the filters
        var sentinel = document.createElement('div');
        sentinel.style.cssText = 'height:1px;margin-bottom:-1px;pointer-events:none;';
        filters.parentNode.insertBefore(sentinel, filters);

        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                filters.classList.toggle('is-stuck', !entry.isIntersecting);
            });
        }, { threshold: 0 });

        observer.observe(sentinel);
    }

    /* ═══ SLIDING FILTER INDICATOR ════════════════════════════ */
    function initFilterIndicator() {
        var filters = document.getElementById('mh-grid-filters');
        if (!filters) return;

        // Create indicator element
        var indicator = document.createElement('div');
        indicator.className = 'mh-grid-filter-indicator';
        filters.appendChild(indicator);

        // Position indicator on the active button
        moveFilterIndicator(false);

        // Re-position on window resize
        window.addEventListener('resize', function () { moveFilterIndicator(false); });

        // Mobile: scroll-end detection for fade indicator
        filters.addEventListener('scroll', function () {
            var atEnd = filters.scrollLeft + filters.clientWidth >= filters.scrollWidth - 8;
            filters.classList.toggle('scroll-end', atEnd);
            var atStart = filters.scrollLeft <= 8;
            filters.classList.toggle('scroll-start-off', !atStart);
        }, { passive: true });
        // Trigger initial check
        var atEnd = filters.scrollLeft + filters.clientWidth >= filters.scrollWidth - 8;
        filters.classList.toggle('scroll-end', atEnd);
        filters.classList.toggle('scroll-start-off', filters.scrollLeft > 8);
    }

    function moveFilterIndicator(animate) {
        var filters = document.getElementById('mh-grid-filters');
        if (!filters) return;
        var indicator = filters.querySelector('.mh-grid-filter-indicator');
        if (!indicator) return;

        var activeBtn = filters.querySelector('.mh-grid-filter.active');
        if (!activeBtn) { indicator.style.opacity = '0'; return; }

        var filtersRect = filters.getBoundingClientRect();
        var btnRect = activeBtn.getBoundingClientRect();

        if (animate === false) {
            indicator.style.transition = 'none';
        }

        indicator.style.left = (btnRect.left - filtersRect.left) + 'px';
        indicator.style.top = (btnRect.top - filtersRect.top) + 'px';
        indicator.style.width = btnRect.width + 'px';
        indicator.style.height = btnRect.height + 'px';
        indicator.style.opacity = '1';

        if (animate === false) {
            // Force reflow then re-enable transition
            indicator.offsetHeight;
            indicator.style.transition = '';
        }
    }

    /* ═══ SCROLL-IN ANIMATION ═════════════════════════════════ */
    function initRevealObserver() {
        if (!('IntersectionObserver' in window)) {
            // Fallback: show all immediately
            document.querySelectorAll('.mh-grid-card-reveal').forEach(function (el) { el.classList.add('is-visible'); });
            return;
        }
        var staggerQueue = [];
        var staggerTimer = null;

        revealObserver = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    staggerQueue.push(entry.target);
                    revealObserver.unobserve(entry.target);
                }
            });
            // Stagger: reveal cards with 60ms delay between each
            if (staggerQueue.length && !staggerTimer) {
                staggerTimer = setInterval(function () {
                    var el = staggerQueue.shift();
                    if (el) {
                        el.classList.add('is-visible');
                    }
                    if (!staggerQueue.length) {
                        clearInterval(staggerTimer);
                        staggerTimer = null;
                    }
                }, 60);
            }
        }, { threshold: 0.08, rootMargin: '0px 0px -40px 0px' });
    }

    function observeRevealCards() {
        if (!revealObserver) return;
        document.querySelectorAll('.mh-grid-card-reveal:not(.is-visible)').forEach(function (el) {
            revealObserver.observe(el);
        });
    }

    /* ═══ FILTERS ═════════════════════════════════════════════ */
    function bindGridFilters() {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.mh-grid-filter');
            if (!btn) return;

            document.querySelectorAll('.mh-grid-filter').forEach(function (f) {
                f.classList.remove('active');
                f.setAttribute('aria-selected', 'false');
            });
            btn.classList.add('active');
            btn.setAttribute('aria-selected', 'true');
            gridCategory = btn.getAttribute('data-category') || '';

            // Slide indicator to new active button
            moveFilterIndicator(true);

            // Mobile: scroll active button into view (container-scoped, no page jump)
            var filtersContainer = document.getElementById('mh-grid-filters');
            if (window.innerWidth <= 600 && filtersContainer) {
                var containerRect = filtersContainer.getBoundingClientRect();
                var btnRect = btn.getBoundingClientRect();
                var targetScroll = filtersContainer.scrollLeft + (btnRect.left - containerRect.left) - (containerRect.width / 2) + (btnRect.width / 2);
                filtersContainer.scrollTo({ left: targetScroll, behavior: 'smooth' });
            }

            // Scroll grid wrapper to top so filter change doesn't feel jumpy
            var gridWrapper = document.querySelector('.mh-stl-grid-wrapper');
            if (gridWrapper) {
                var wrapperTop = gridWrapper.getBoundingClientRect().top + window.pageYOffset - 80;
                if (window.pageYOffset > wrapperTop) {
                    window.scrollTo({ top: wrapperTop, behavior: 'smooth' });
                }
            }

            // Fade out current grid, then load new content with skeleton
            var grid = document.getElementById('mh-grid');
            if (grid) grid.classList.add('mh-grid-fading');
            loadGridProjects(1, true);
        });
    }

    function showGridSkeleton() {
        var grid = document.getElementById('mh-grid');
        if (!grid) return;
        var skeletonCount = 6;
        var heights = [220, 280, 200, 260, 240, 220];
        var h = '<div class="mh-grid-chunk">';
        for (var i = 0; i < skeletonCount; i++) {
            h += '<div class="mh-grid-skeleton" style="height:' + heights[i] + 'px"></div>';
        }
        h += '</div>';
        grid.innerHTML = h;
        var wrap = document.getElementById('mh-grid-loadmore-wrap');
        if (wrap) wrap.innerHTML = '';
    }

    /* ═══ LOAD MORE ═══════════════════════════════════════════ */
    function bindGridLoadMore() {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('#mh-grid-loadmore');
            if (!btn) return;
            var page = parseInt(btn.getAttribute('data-page')) || 2;
            btn.textContent = 'Lädt…';
            btn.style.opacity = '.5';
            btn.disabled = true;

            // Show skeleton chunk below existing chunks (inside grid, before loadmore)
            var grid = document.getElementById('mh-grid');
            if (grid) {
                var skChunk = document.createElement('div');
                skChunk.className = 'mh-grid-chunk mh-grid-skeleton-chunk';
                var skHeights = [220, 260, 200];
                for (var i = 0; i < 3; i++) {
                    var sk = document.createElement('div');
                    sk.className = 'mh-grid-skeleton';
                    sk.style.height = skHeights[i] + 'px';
                    skChunk.appendChild(sk);
                }
                grid.appendChild(skChunk);
            }

            loadGridProjects(page, false);
        });
    }

    function loadGridProjects(page, replace) {
        if (typeof mhSTLGrid === 'undefined') return;
        var fd = new FormData();
        fd.append('action', 'mh_stl_grid_load');
        fd.append('nonce', mhSTLGrid.nonce);
        fd.append('page', page);
        fd.append('category', gridCategory);
        fd.append('per_page', mhSTLGrid.per_page);

        var grid = document.getElementById('mh-grid');

        // Preserve min-height during replace to prevent vertical collapse/jump
        // Height captured BEFORE any DOM changes (showGridSkeleton no longer pre-nukes)
        var preservedHeight = 0;
        if (replace && grid) {
            preservedHeight = grid.offsetHeight;
            grid.style.minHeight = preservedHeight + 'px';

            // Wait for the CSS fade-out (.mh-grid-fading) to finish, then swap to skeleton
            var swapDelay = grid.classList.contains('mh-grid-fading') ? 180 : 0;
            setTimeout(function () {
                var skeletonCount = 6;
                var skeletonHtml = '<div class="mh-grid-chunk">';
                var skHeights = [220, 280, 200, 260, 240, 220];
                for (var i = 0; i < skeletonCount; i++) {
                    skeletonHtml += '<div class="mh-grid-skeleton" style="height:' + skHeights[i] + 'px"></div>';
                }
                skeletonHtml += '</div>';
                grid.innerHTML = skeletonHtml;
                grid.classList.remove('mh-grid-fading');
            }, swapDelay);
        }

        fetch(mhSTLGrid.ajax_url, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (resp) {
                if (!resp.success) return;
                if (!grid) return;

                if (replace) {
                    // ── Filter change: fresh single chunk ──
                    grid.classList.remove('mh-grid-fading');
                    grid.innerHTML = '<div class="mh-grid-chunk">' + resp.data.html + '</div>';

                    // Release minHeight after cards have had time to layout
                    setTimeout(function () {
                        grid.style.minHeight = '';
                    }, 300);
                } else {
                    // ── Load More: remove skeleton chunk, append new chunk ──
                    // Existing chunks stay pixel-perfect — no reflow!

                    var scrollY = window.pageYOffset;

                    // Lock grid min-height to prevent collapse while new images
                    // are still loading (without intrinsic dimensions, images
                    // start at 0px height and would let the page shrink).
                    var lockedHeight = grid.offsetHeight;
                    grid.style.minHeight = lockedHeight + 'px';

                    grid.querySelectorAll('.mh-grid-skeleton-chunk').forEach(function(sk) { sk.remove(); });

                    var newChunk = document.createElement('div');
                    newChunk.className = 'mh-grid-chunk';
                    newChunk.innerHTML = resp.data.html;
                    grid.appendChild(newChunk);

                    // Restore scroll position — page can no longer shrink past it
                    window.scrollTo(0, scrollY);

                    // Release the height lock once all new images have loaded.
                    var newImages = newChunk.querySelectorAll('img');
                    var pending = newImages.length;
                    var releaseLock = function () { grid.style.minHeight = ''; };

                    if (pending === 0) {
                        releaseLock();
                    } else {
                        var onDone = function () {
                            pending--;
                            if (pending <= 0) releaseLock();
                        };
                        newImages.forEach(function (img) {
                            if (img.complete && img.naturalHeight > 0) {
                                onDone();
                            } else {
                                img.addEventListener('load', onDone, { once: true });
                                img.addEventListener('error', onDone, { once: true });
                            }
                        });
                    }
                    // Safety: never leave the lock dangling
                    setTimeout(releaseLock, 5000);
                }

                var wrap = document.getElementById('mh-grid-loadmore-wrap');
                if (wrap) {
                    wrap.innerHTML = resp.data.has_more
                        ? '<button class="mh-grid-loadmore" id="mh-grid-loadmore" data-page="' + (page + 1) + '">Mehr Projekte laden</button>'
                        : '';
                }
                observeRevealCards();
            });
    }

    /* ═══ CARD CLICKS → LIGHTBOX WITH PINS ═══════════════════ */
    function bindGridCardClicks() {
        document.addEventListener('click', function (e) {
            var card = e.target.closest('.mh-grid-card');
            if (!card) return;
            e.preventDefault();
            openCardLightbox(card);
        });
        // Keyboard: Enter or Space on focused card
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter' && e.key !== ' ') return;
            var card = e.target.closest('.mh-grid-card');
            if (!card) return;
            e.preventDefault();
            openCardLightbox(card);
        });
    }

    function openCardLightbox(card) {
        var data;
        try { data = JSON.parse(card.getAttribute('data-project')); } catch (err) { return; }
        if (!data || !data.image) return;
        openGridLightbox(data, card);
    }

    function openGridLightbox(data, sourceCard) {
        var lb = document.getElementById('mh-stl-lightbox');
        if (!lb) return;

        var pins = data.pins || [];
        var images = data.images || [];
        var hasPins = pins.length > 0;

        // Fallback: if no images array, use single image
        if (!images.length && data.image) {
            images = [{ url: data.image, thumb: data.image }];
        }

        var h = '<div class="mh-stl-lb-inner mh-grid-lb-inner' + (hasPins ? '' : ' mh-grid-lb-simple') + '">';
        h += '<button class="mh-stl-lb-close" id="mh-grid-lb-close">&times;</button>';

        if (hasPins) {
            // Two-column layout: image + info
            h += '<div class="mh-grid-lb-layout">';

            h += '<div class="mh-stl-lb-image-wrap mh-grid-lb-image-wrap">';
            h += '<img src="' + esc(data.image) + '" alt="' + esc(data.title) + '">';
            h += '<div class="mh-grid-lb-zoom-hint"><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/><path d="M8 11h6M11 8v6"/></svg>Klicken zum Zoomen</div>';
            h += '<div class="mh-stl-lb-pins">';
            h += mhBuildPinsHtml(pins, 0, esc);
            h += '</div></div>';

            h += '<div class="mh-grid-lb-info">';
            h += '<div class="mh-grid-lb-accent-line"></div>';
            h += '<h3 class="mh-grid-lb-title">' + esc(data.title) + '</h3>';
            if (data.desc) h += '<p class="mh-grid-lb-desc">' + esc(data.desc) + '</p>';

            h += '<div class="mh-grid-lb-section-divider"></div>';
            h += '<div class="mh-grid-lb-section-label"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>Verwendete Produkte</div>';
            h += '<div class="mh-grid-lb-products">';
            pins.forEach(function (pin, i) {
                var imgIdx = pin.image_index || 0;
                h += '<a class="mh-stl-product-row" href="' + esc(pin.url) + '" target="_blank" rel="noopener" data-image-index="' + imgIdx + '" data-pin-index="' + i + '">';
                h += '<span class="mh-stl-product-row-num">' + (i + 1) + '</span>';
                if (pin.thumb) h += '<img src="' + esc(pin.thumb) + '" alt="">';
                h += '<div class="mh-stl-product-row-info">';
                h += '<div class="mh-stl-product-row-name">' + esc(pin.name) + '</div>';
                h += renderPriceHtmlGlobal(pin, 'mh-stl-product-row-price');
                h += '</div>';
                if (images.length > 1) h += mhBadgeHtml(imgIdx);
                h += '<span class="mh-stl-product-row-arrow">→</span>';
                h += '</a>';
            });
            h += '</div>';

            if (data.buttons && data.buttons.length) {
                h += '<div class="mh-stl-buttons">';
                data.buttons.forEach(function (btn) {
                    var cls = btn.style === 'primary' ? 'mh-stl-btn-primary' : 'mh-stl-btn-secondary';
                    h += '<a class="' + cls + '" href="' + esc(btn.url) + '" target="_blank" rel="noopener">' + esc(btn.text) + '</a>';
                });
                h += '</div>';
            }

            h += '</div>'; // info
            h += '</div>'; // layout

        } else {
            // Simple lightbox: just image with title
            h += '<div class="mh-grid-lb-simple-wrap mh-grid-lb-image-wrap">';
            h += '<img src="' + esc(data.image) + '" alt="' + esc(data.title) + '">';
            h += '</div>';
            if (data.title) {
                h += '<div class="mh-grid-lb-simple-bar">' + esc(data.title) + '</div>';
            }
        }

        h += '</div>'; // inner

        lb.innerHTML = h;
        lb.style.display = '';
        lb.setAttribute('aria-hidden', 'false');
        mhScrollLock();

        // ── Morph animation: set transform-origin from source card ──
        var inner = lb.querySelector('.mh-stl-lb-inner');
        if (inner && sourceCard) {
            var rect = sourceCard.getBoundingClientRect();
            var cx = rect.left + rect.width / 2;
            var cy = rect.top + rect.height / 2;
            inner.style.transformOrigin = cx + 'px ' + cy + 'px';
        }
        // Force reflow then trigger open transition
        lb.offsetHeight;
        lb.classList.add('mh-lb-open');

        // Store pins for popup handler
        lb._gridPins = pins;

        // Bind pin ↔ product sync (grid lightbox)
        if (hasPins) {
            var gridLbImageWrap = lb.querySelector('.mh-grid-lb-image-wrap');
            var gridLbInfo = lb.querySelector('.mh-grid-lb-info');
            if (gridLbInfo && gridLbImageWrap) {
                mhBindPinProductSync(gridLbInfo, '.mh-stl-pin', '.mh-stl-product-row', gridLbImageWrap);
            }
        }

        var gallery = null;

        var closeLb = function () {
            lb.classList.remove('mh-lb-open');
            lb.classList.add('mh-lb-closing');
            setTimeout(function() {
                if (gallery) { gallery.destroy(); document.removeEventListener('keydown', gallery.keyHandler); }
                lb.classList.remove('mh-lb-closing');
                lb.setAttribute('aria-hidden', 'true');
                lb.style.display = 'none';
                lb.innerHTML = '';
                mhScrollUnlock();
                removeFixedPopup();
                // Reset transform-origin
                var inner = lb.querySelector('.mh-stl-lb-inner');
                if (inner) inner.style.transformOrigin = '';
            }, 300);
        };

        document.getElementById('mh-grid-lb-close').addEventListener('click', closeLb);

        // Click on backdrop to close
        lb.addEventListener('click', function (e) {
            if (e.target === lb) closeLb();
        });

        // Pin clicks + Zoom in grid lightbox
        var imageWrap = lb.querySelector('.mh-grid-lb-image-wrap');
        if (imageWrap) {
            var img = imageWrap.querySelector('img');
            var isZoomed = false;

            // ── Init gallery navigation ──
            var layoutEl = lb.querySelector('.mh-grid-lb-layout');
            gallery = mhInitGallery(imageWrap, images, pins, {
                thumbContainer: layoutEl || imageWrap,
                onImageChange: function (idx) {
                    // Reset zoom when switching images
                    if (isZoomed) {
                        isZoomed = false;
                        imageWrap.classList.remove('zoomed');
                        if (layoutEl) layoutEl.classList.remove('is-zoomed');
                        if (img) img.style.transformOrigin = 'center center';
                    }
                    // Update zoom hint visibility
                    var hint = imageWrap.querySelector('.mh-grid-lb-zoom-hint');
                    if (hint) hint.style.display = '';
                    // Rebind pin ↔ product sync for new image's pins
                    var gridLbInfo2 = lb.querySelector('.mh-grid-lb-info');
                    if (gridLbInfo2 && imageWrap) {
                        mhBindPinProductSync(gridLbInfo2, '.mh-stl-pin', '.mh-stl-product-row', imageWrap);
                    }
                }
            });

            // Register gallery keyboard handler
            if (gallery && gallery.keyHandler) {
                document.addEventListener('keydown', gallery.keyHandler);
            }

            // Bind product row hover → highlight thumbnail, click badge → navigate
            if (gallery) {
                var infoPanel = lb.querySelector('.mh-grid-lb-info');
                if (infoPanel) mhBindRowImageNav(infoPanel, gallery, layoutEl || lb);
            }

            imageWrap.addEventListener('click', function (e) {
                var pinEl = e.target.closest('.mh-stl-pin');
                var isPopup = e.target.closest('.mh-stl-popup');
                var isNav = e.target.closest('.mh-lb-gallery-nav') || e.target.closest('.mh-lb-gallery-thumb');

                if (isPopup || isNav) return;

                if (pinEl && !isZoomed) {
                    e.stopPropagation();
                    var idx = parseInt(pinEl.getAttribute('data-index'));
                    var pin = pins[idx];
                    if (pin) showFixedPopup(pinEl, pin);
                    return;
                }

                if (_mhPopupOpenAtClick) { removeFixedPopup(); return; }

                isZoomed = !isZoomed;
                imageWrap.classList.toggle('zoomed', isZoomed);
                if (layoutEl) layoutEl.classList.toggle('is-zoomed', isZoomed);

                var pinsLayer = imageWrap.querySelector('.mh-stl-lb-pins');
                if (pinsLayer) pinsLayer.style.display = isZoomed ? 'none' : '';

                if (!isZoomed && img) img.style.transformOrigin = 'center center';
            });

            imageWrap.addEventListener('mousemove', function (e) {
                if (!isZoomed || !img) return;
                var rect = imageWrap.getBoundingClientRect();
                img.style.transformOrigin = ((e.clientX - rect.left) / rect.width * 100).toFixed(1) + '% ' + ((e.clientY - rect.top) / rect.height * 100).toFixed(1) + '%';
            });

            imageWrap.addEventListener('touchmove', function (e) {
                if (!isZoomed || !img) return;
                e.preventDefault();
                var touch = e.touches[0];
                var rect = imageWrap.getBoundingClientRect();
                img.style.transformOrigin = ((touch.clientX - rect.left) / rect.width * 100).toFixed(1) + '% ' + ((touch.clientY - rect.top) / rect.height * 100).toFixed(1) + '%';
            }, { passive: false });
        }

        // Escape key
        var onEscKey = function (e) {
            if (e.key === 'Escape') { closeLb(); document.removeEventListener('keydown', onEscKey); }
        };
        document.addEventListener('keydown', onEscKey);
    }

    function esc(s) {
        if (!s) return '';
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(s));
        return d.innerHTML;
    }
})();

/**
 * MH Shop the Look — Product Page Gallery
 * Card clicks → Lightbox, Reveal animations
 */
(function () {
    'use strict';

    var pgRevealObserver = null;

    document.addEventListener('DOMContentLoaded', function () {
        if (!document.querySelector('.mh-stl-product-wrapper')) return;
        bindProductCardClicks();
        bindProductLoadMore();
        initProductReveal();
        observeProductCards();
    });

    /* ═══ LOAD MORE ═══════════════════════════════════════════ */
    function bindProductLoadMore() {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('#mh-pg-loadmore');
            if (!btn) return;

            if (typeof mhSTLProduct === 'undefined') return;

            var page = parseInt(btn.getAttribute('data-page')) || 2;
            btn.textContent = 'Lädt…';
            btn.style.opacity = '.5';
            btn.style.pointerEvents = 'none';

            var fd = new FormData();
            fd.append('action', 'mh_stl_product_load');
            fd.append('nonce', mhSTLProduct.nonce);
            fd.append('product_id', mhSTLProduct.product_id);
            fd.append('page', page);
            fd.append('per_page', mhSTLProduct.per_page);

            var grid = document.getElementById('mh-pg-grid');

            fetch(mhSTLProduct.ajax_url, { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (resp) {
                    if (!resp.success || !grid) return;

                    // FLIP technique: smooth append
                    mhFlipAppend(grid, resp.data.html, '.mh-pg-card');

                    // Update load more button
                    var wrap = document.getElementById('mh-pg-loadmore-wrap');
                    if (wrap) {
                        if (resp.data.has_more) {
                            wrap.innerHTML = '<button class="mh-pg-loadmore" id="mh-pg-loadmore" data-page="' + (page + 1) + '">'
                                + 'Mehr Kundenprojekte anzeigen '
                                + '<span class="mh-pg-loadmore-count">(' + resp.data.remaining + ' weitere)</span>'
                                + '</button>';
                        } else {
                            wrap.innerHTML = '';
                        }
                    }

                    // Re-observe new cards for reveal animation
                    observeProductCards();
                })
                .catch(function () {
                    btn.textContent = 'Fehler — erneut versuchen';
                    btn.style.opacity = '1';
                    btn.style.pointerEvents = '';
                });
        });
    }

    /* ═══ REVEAL ANIMATION ════════════════════════════════════ */
    function initProductReveal() {
        if (!('IntersectionObserver' in window)) {
            document.querySelectorAll('.mh-pg-card.mh-grid-card-reveal').forEach(function (el) {
                el.classList.add('is-visible');
            });
            return;
        }
        var staggerQueue = [];
        var staggerTimer = null;

        pgRevealObserver = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    staggerQueue.push(entry.target);
                    pgRevealObserver.unobserve(entry.target);
                }
            });
            if (staggerQueue.length && !staggerTimer) {
                staggerTimer = setInterval(function () {
                    var el = staggerQueue.shift();
                    if (el) el.classList.add('is-visible');
                    if (!staggerQueue.length) {
                        clearInterval(staggerTimer);
                        staggerTimer = null;
                    }
                }, 80);
            }
        }, { threshold: 0.08, rootMargin: '0px 0px -30px 0px' });
    }

    function observeProductCards() {
        if (!pgRevealObserver) return;
        document.querySelectorAll('.mh-pg-card.mh-grid-card-reveal:not(.is-visible)').forEach(function (el) {
            pgRevealObserver.observe(el);
        });
    }

    /* ═══ CARD CLICKS → LIGHTBOX ══════════════════════════════ */
    function bindProductCardClicks() {
        document.addEventListener('click', function (e) {
            var card = e.target.closest('.mh-pg-card');
            if (!card) return;
            e.preventDefault();

            var data;
            try { data = JSON.parse(card.getAttribute('data-project')); } catch (err) { return; }
            if (!data || !data.image) return;

            openProductLightbox(data, card);
        });
    }

    function openProductLightbox(data, sourceEl) {
        var lb = document.getElementById('mh-stl-lightbox');
        if (!lb) {
            lb = document.createElement('div');
            lb.id = 'mh-stl-lightbox';
            lb.className = 'mh-stl-lightbox';
            lb.setAttribute('aria-hidden', 'true');
            document.body.appendChild(lb);
        }

        var pins = data.pins || [];
        var images = data.images || [];
        var hasPins = pins.length > 0;

        if (!images.length && data.image) {
            images = [{ url: data.image, thumb: data.image }];
        }

        var h = '<div class="mh-stl-lb-inner mh-grid-lb-inner' + (hasPins ? '' : ' mh-grid-lb-simple') + '">';
        h += '<button class="mh-stl-lb-close" id="mh-pg-lb-close">&times;</button>';

        if (hasPins) {
            h += '<div class="mh-grid-lb-layout">';
            h += '<div class="mh-stl-lb-image-wrap mh-grid-lb-image-wrap">';
            h += '<img src="' + _esc(data.image) + '" alt="' + _esc(data.title) + '">';
            h += '<div class="mh-grid-lb-zoom-hint"><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/><path d="M8 11h6M11 8v6"/></svg>Klicken zum Zoomen</div>';
            h += '<div class="mh-stl-lb-pins">';
            h += mhBuildPinsHtml(pins, 0, _esc);
            h += '</div></div>';

            h += '<div class="mh-grid-lb-info">';
            h += '<div class="mh-grid-lb-accent-line"></div>';
            h += '<h3 class="mh-grid-lb-title">' + _esc(data.title) + '</h3>';
            if (data.desc) h += '<p class="mh-grid-lb-desc">' + _esc(data.desc) + '</p>';

            h += '<div class="mh-grid-lb-section-divider"></div>';
            h += '<div class="mh-grid-lb-section-label"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>Verwendete Produkte</div>';
            h += '<div class="mh-grid-lb-products">';
            pins.forEach(function (pin, i) {
                var imgIdx = pin.image_index || 0;
                h += '<a class="mh-stl-product-row" href="' + _esc(pin.url) + '" target="_blank" rel="noopener" data-image-index="' + imgIdx + '" data-pin-index="' + i + '">';
                h += '<span class="mh-stl-product-row-num">' + (i + 1) + '</span>';
                if (pin.thumb) h += '<img src="' + _esc(pin.thumb) + '" alt="">';
                h += '<div class="mh-stl-product-row-info">';
                h += '<div class="mh-stl-product-row-name">' + _esc(pin.name) + '</div>';
                h += renderPriceHtmlGlobal(pin, 'mh-stl-product-row-price');
                h += '</div>';
                if (images.length > 1) h += mhBadgeHtml(imgIdx);
                h += '<span class="mh-stl-product-row-arrow">→</span>';
                h += '</a>';
            });
            h += '</div>';

            if (data.buttons && data.buttons.length) {
                h += '<div class="mh-stl-buttons">';
                data.buttons.forEach(function (btn) {
                    var cls = btn.style === 'primary' ? 'mh-stl-btn-primary' : 'mh-stl-btn-secondary';
                    h += '<a class="' + cls + '" href="' + _esc(btn.url) + '" target="_blank" rel="noopener">' + _esc(btn.text) + '</a>';
                });
                h += '</div>';
            }

            h += '</div></div>';
        } else {
            h += '<div class="mh-grid-lb-simple-wrap mh-grid-lb-image-wrap">';
            h += '<img src="' + _esc(data.image) + '" alt="' + _esc(data.title) + '">';
            h += '</div>';
            if (data.title) h += '<div class="mh-grid-lb-simple-bar">' + _esc(data.title) + '</div>';
        }

        h += '</div>';

        lb.innerHTML = h;
        lb.style.display = '';
        lb.setAttribute('aria-hidden', 'false');
        mhScrollLock();
        lb._gridPins = pins;

        // Bind pin ↔ product sync (product lightbox)
        if (hasPins) {
            var pgLbImageWrap = lb.querySelector('.mh-grid-lb-image-wrap');
            var pgLbInfo = lb.querySelector('.mh-grid-lb-info');
            if (pgLbInfo && pgLbImageWrap) {
                mhBindPinProductSync(pgLbInfo, '.mh-stl-pin', '.mh-stl-product-row', pgLbImageWrap);
            }
        }

        // ── Morph animation: origin from source card ──
        var inner = lb.querySelector('.mh-stl-lb-inner');
        if (inner && sourceEl) {
            var rect = sourceEl.getBoundingClientRect();
            var cx = rect.left + rect.width / 2;
            var cy = rect.top + rect.height / 2;
            inner.style.transformOrigin = cx + 'px ' + cy + 'px';
        }
        lb.offsetHeight;
        lb.classList.add('mh-lb-open');

        var gallery = null;

        var closeLb = function () {
            lb.classList.remove('mh-lb-open');
            lb.classList.add('mh-lb-closing');
            setTimeout(function() {
                if (gallery) { gallery.destroy(); document.removeEventListener('keydown', gallery.keyHandler); }
                lb.classList.remove('mh-lb-closing');
                lb.setAttribute('aria-hidden', 'true');
                lb.style.display = 'none';
                lb.innerHTML = '';
                mhScrollUnlock();
                removeFixedPopup();
            }, 300);
        };

        document.getElementById('mh-pg-lb-close').addEventListener('click', closeLb);
        lb.addEventListener('click', function (e) { if (e.target === lb) closeLb(); });

        var onKey = function (e) {
            if (e.key === 'Escape') { closeLb(); document.removeEventListener('keydown', onKey); }
        };
        document.addEventListener('keydown', onKey);

        // Pin clicks + Zoom + Gallery
        var imageWrap = lb.querySelector('.mh-grid-lb-image-wrap');
        if (imageWrap) {
            var img = imageWrap.querySelector('img');
            var isZoomed = false;

            // Init gallery
            var pgLayoutEl = lb.querySelector('.mh-grid-lb-layout');
            gallery = mhInitGallery(imageWrap, images, pins, {
                thumbContainer: pgLayoutEl || imageWrap,
                onImageChange: function () {
                    if (isZoomed) {
                        isZoomed = false;
                        imageWrap.classList.remove('zoomed');
                        if (pgLayoutEl) pgLayoutEl.classList.remove('is-zoomed');
                        if (img) img.style.transformOrigin = 'center center';
                    }
                    // Rebind pin ↔ product sync for new image's pins
                    var pgInfo2 = lb.querySelector('.mh-grid-lb-info');
                    if (pgInfo2 && imageWrap) {
                        mhBindPinProductSync(pgInfo2, '.mh-stl-pin', '.mh-stl-product-row', imageWrap);
                    }
                }
            });

            if (gallery && gallery.keyHandler) {
                document.addEventListener('keydown', gallery.keyHandler);
            }

            // Bind product row hover → highlight thumbnail, click badge → navigate
            if (gallery) {
                var pgInfoPanel = lb.querySelector('.mh-grid-lb-info');
                if (pgInfoPanel) mhBindRowImageNav(pgInfoPanel, gallery, pgLayoutEl || lb);
            }

            imageWrap.addEventListener('click', function (e) {
                var isNav = e.target.closest('.mh-lb-gallery-nav') || e.target.closest('.mh-lb-gallery-thumb');
                if (isNav) return;
                if (e.target.closest('.mh-stl-pin') && !isZoomed) {
                    e.stopPropagation();
                    var idx = parseInt(e.target.closest('.mh-stl-pin').getAttribute('data-index'));
                    var pin = pins[idx];
                    if (pin) showFixedPopup(e.target.closest('.mh-stl-pin'), pin);
                    return;
                }
                if (e.target.closest('.mh-stl-popup')) return;
                if (_mhPopupOpenAtClick) { removeFixedPopup(); return; }

                isZoomed = !isZoomed;
                imageWrap.classList.toggle('zoomed', isZoomed);
                if (pgLayoutEl) pgLayoutEl.classList.toggle('is-zoomed', isZoomed);
                var pinsLayer = imageWrap.querySelector('.mh-stl-lb-pins');
                if (pinsLayer) pinsLayer.style.display = isZoomed ? 'none' : '';
                if (!isZoomed && img) img.style.transformOrigin = 'center center';
            });

            imageWrap.addEventListener('mousemove', function (e) {
                if (!isZoomed || !img) return;
                var rect = imageWrap.getBoundingClientRect();
                img.style.transformOrigin = ((e.clientX - rect.left) / rect.width * 100).toFixed(1) + '% ' + ((e.clientY - rect.top) / rect.height * 100).toFixed(1) + '%';
            });

            imageWrap.addEventListener('touchmove', function (e) {
                if (!isZoomed || !img) return;
                e.preventDefault();
                var touch = e.touches[0];
                var rect = imageWrap.getBoundingClientRect();
                img.style.transformOrigin = ((touch.clientX - rect.left) / rect.width * 100).toFixed(1) + '% ' + ((touch.clientY - rect.top) / rect.height * 100).toFixed(1) + '%';
            }, { passive: false });
        }
    }

    function _esc(s) {
        if (!s) return '';
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(s));
        return d.innerHTML;
    }
})();
