/**
 * MH Shop the Look — Admin Pin Editor
 * Multi-image support: place pins on any gallery image
 * Click image to place pins, search products per pin, drag to reposition
 */
(function ($) {
    'use strict';

    var pins = [];
    var galleryImages = [];
    var currentImageIndex = 0;
    var $canvas, $image, $hidden, $listContainer;

    $(document).ready(function () {
        $canvas        = $('#mh-stl-canvas');
        $image         = $('#mh-stl-image');
        $hidden        = $('#mh-stl-pins-data');
        $listContainer = $('#mh-stl-pins-container');

        if (!$canvas.length || !$image.length) return;

        // Load existing pins
        try { pins = JSON.parse($hidden.val()) || []; } catch (e) { pins = []; }

        // Ensure all pins have image_index (backward compat)
        pins.forEach(function (p) {
            if (typeof p.image_index === 'undefined') p.image_index = 0;
        });

        // Load gallery images
        try {
            var $galleryData = $('#mh-stl-gallery-data');
            if ($galleryData.length) {
                galleryImages = JSON.parse($galleryData.val()) || [];
            }
        } catch (e) { galleryImages = []; }

        // Wait for image to load
        if ($image[0].complete) { init(); } else { $image.on('load', init); }
    });

    function init() {
        renderAll();
        bindGalleryStrip();

        // Click on canvas to add pin
        $canvas.on('click', function (e) {
            if ($(e.target).closest('.mh-stl-pin').length) return;

            var rect = $canvas[0].getBoundingClientRect();
            var x = ((e.clientX - rect.left) / rect.width * 100).toFixed(2);
            var y = ((e.clientY - rect.top) / rect.height * 100).toFixed(2);

            pins.push({
                x: parseFloat(x),
                y: parseFloat(y),
                product_id: 0,
                product_name: '',
                product_thumb: '',
                image_index: currentImageIndex
            });
            renderAll();
            save();
        });
    }

    /* ═══ GALLERY STRIP ═════════════════════════════════════════ */
    function bindGalleryStrip() {
        var $strip = $('#mh-stl-gallery-strip');
        if (!$strip.length) return;

        $strip.on('click', '.mh-stl-gallery-thumb-wrap', function () {
            var idx = parseInt($(this).attr('data-gindex'), 10);
            if (idx === currentImageIndex || !galleryImages[idx]) return;

            currentImageIndex = idx;

            // Update active state
            $strip.find('.mh-stl-gallery-thumb-wrap').removeClass('active');
            $(this).addClass('active');

            // Swap image
            $image.attr('src', galleryImages[idx].url);

            // Re-render pins for new image
            renderAll();
        });

        updatePinCounts();
    }

    function updatePinCounts() {
        if (!galleryImages.length || galleryImages.length < 2) return;

        var counts = {};
        pins.forEach(function (p) {
            var idx = p.image_index || 0;
            counts[idx] = (counts[idx] || 0) + 1;
        });

        $('.mh-stl-gallery-pin-count').each(function () {
            var idx = parseInt($(this).attr('data-gindex'), 10);
            var c = counts[idx] || 0;
            $(this).text(c > 0 ? c : '').toggle(c > 0);
        });
    }

    /* ═══ RENDER ═══════════════════════════════════════════════ */
    function renderAll() {
        renderCanvasPins();
        renderPinList();
        updatePinCounts();
    }

    function renderCanvasPins() {
        $canvas.find('.mh-stl-pin').remove();

        pins.forEach(function (pin, i) {
            // Only show pins for current image
            if ((pin.image_index || 0) !== currentImageIndex) return;

            var hasProduct = pin.product_id > 0;
            var $pin = $('<div class="mh-stl-pin' + (hasProduct ? '' : ' no-product') + '" data-index="' + i + '">')
                .css({ left: pin.x + '%', top: pin.y + '%' })
                .text(i + 1)
                .attr('title', hasProduct ? pin.product_name : 'Kein Produkt — bitte zuweisen');

            $canvas.append($pin);

            // Make draggable
            $pin.draggable({
                containment: $canvas,
                stop: function (e, ui) {
                    var rect = $canvas[0].getBoundingClientRect();
                    var cx = ui.position.left + 16;
                    var cy = ui.position.top + 16;
                    pins[i].x = parseFloat((cx / rect.width * 100).toFixed(2));
                    pins[i].y = parseFloat((cy / rect.height * 100).toFixed(2));
                    save();
                    renderPinList();
                    updatePinCounts();
                }
            });
        });
    }

    function renderPinList() {
        $listContainer.empty();

        // Separate pins: current image first, then others
        var currentPins = [];
        var otherPins = [];
        pins.forEach(function (pin, i) {
            var obj = { pin: pin, index: i };
            if ((pin.image_index || 0) === currentImageIndex) {
                currentPins.push(obj);
            } else {
                otherPins.push(obj);
            }
        });

        if (pins.length === 0) {
            $listContainer.html('<div class="mh-stl-empty">Noch keine Pins. Klicke auf das Bild um einen Pin zu platzieren.</div>');
            return;
        }

        // Current image pins
        if (currentPins.length > 0) {
            if (galleryImages.length > 1) {
                $listContainer.append('<div class="mh-stl-pin-group-label">📌 Bild ' + (currentImageIndex + 1) + ' <small>(' + currentPins.length + ' Pin' + (currentPins.length !== 1 ? 's' : '') + ')</small></div>');
            }
            currentPins.forEach(function (obj) {
                $listContainer.append(buildPinRow(obj.pin, obj.index, false));
            });
        } else if (galleryImages.length > 1) {
            $listContainer.append('<div class="mh-stl-empty">Keine Pins auf diesem Bild. Klicke auf das Bild um einen Pin zu platzieren.</div>');
        }

        // Other images pins
        if (otherPins.length > 0 && galleryImages.length > 1) {
            var grouped = {};
            otherPins.forEach(function (obj) {
                var idx = obj.pin.image_index || 0;
                if (!grouped[idx]) grouped[idx] = [];
                grouped[idx].push(obj);
            });

            Object.keys(grouped).sort(function (a, b) { return a - b; }).forEach(function (imgIdx) {
                var group = grouped[imgIdx];
                $listContainer.append('<div class="mh-stl-pin-group-label mh-stl-pin-group-other">📌 Bild ' + (parseInt(imgIdx) + 1) + ' <small>(' + group.length + ' Pin' + (group.length !== 1 ? 's' : '') + ')</small></div>');
                group.forEach(function (obj) {
                    $listContainer.append(buildPinRow(obj.pin, obj.index, true));
                });
            });
        }
    }

    function buildPinRow(pin, i, isOtherImage) {
        var hasProduct = pin.product_id > 0;
        var $row = $('<div class="mh-stl-pin-row' + (hasProduct ? '' : ' no-product') + (isOtherImage ? ' mh-stl-other-image' : '') + '" data-index="' + i + '">');

        // Number
        $row.append('<div class="mh-stl-pin-number">' + (i + 1) + '</div>');

        // Coords
        $row.append('<div class="mh-stl-pin-coords">' + pin.x + '%, ' + pin.y + '%</div>');

        if (hasProduct) {
            var $info = $('<div class="mh-stl-pin-product-info">');
            if (pin.product_thumb) {
                $info.append('<img src="' + pin.product_thumb + '" alt="">');
            }
            $info.append('<div><div class="mh-stl-pin-product-name">' + esc(pin.product_name) + '</div></div>');
            $row.append($info);

            var $changeBtn = $('<button type="button" class="button button-small">Ändern</button>');
            $changeBtn.on('click', function () {
                pins[i].product_id = 0;
                pins[i].product_name = '';
                pins[i].product_thumb = '';
                renderAll();
                save();
            });
            $row.append($changeBtn);
        } else {
            var $searchWrap = $('<div class="mh-stl-pin-search-wrap">');
            var $search = $('<input type="text" class="mh-stl-pin-search" placeholder="Produkt suchen (Name oder ID)…">');
            var $dropdown = $('<div class="mh-stl-pin-dropdown">');
            $searchWrap.append($search, $dropdown);
            $row.append($searchWrap);

            bindPinSearch($search, $dropdown, i);
        }

        // Remove button
        var $remove = $('<button type="button" class="mh-stl-pin-remove" title="Pin entfernen">&times;</button>');
        $remove.on('click', function () {
            pins.splice(i, 1);
            renderAll();
            save();
        });
        $row.append($remove);

        return $row;
    }

    /* ═══ PRODUCT SEARCH PER PIN ══════════════════════════════ */
    function bindPinSearch($input, $dropdown, pinIndex) {
        var timer;

        $input.on('input', function () {
            var q = $input.val().trim();
            clearTimeout(timer);
            if (q.length < 2) { $dropdown.removeClass('open').empty(); return; }

            timer = setTimeout(function () {
                $dropdown.html('<div style="padding:8px;color:#999;font-size:12px;">Suche läuft…</div>').addClass('open');

                $.post(mhSTL.ajax_url, {
                    action: 'mh_stl_search_products',
                    nonce: mhSTL.nonce,
                    term: q,
                }, function (resp) {
                    $dropdown.empty();
                    if (!resp.success || !resp.data.length) {
                        $dropdown.html('<div style="padding:8px;color:#999;font-size:12px;">Nichts gefunden</div>');
                        return;
                    }
                    resp.data.forEach(function (p) {
                        var img = p.thumb ? '<img src="' + p.thumb + '" alt="">' : '';
                        var $item = $('<div class="mh-stl-pin-dropdown-item">' + img + '<span>' + esc(p.name) + ' <small style="color:#888;">#' + p.id + '</small></span></div>');
                        $item.on('click', function () {
                            pins[pinIndex].product_id = p.id;
                            pins[pinIndex].product_name = p.name;
                            pins[pinIndex].product_thumb = p.thumb || '';
                            renderAll();
                            save();
                        });
                        $dropdown.append($item);
                    });
                    $dropdown.addClass('open');
                });
            }, 350);
        });

        $(document).on('click', function (e) {
            if (!$(e.target).closest($input.closest('.mh-stl-pin-search-wrap')).length) {
                $dropdown.removeClass('open');
            }
        });

        setTimeout(function () { $input.focus(); }, 100);
    }

    /* ═══ SAVE ════════════════════════════════════════════════ */
    function save() {
        var clean = pins.map(function (p) {
            return {
                x: p.x,
                y: p.y,
                product_id: p.product_id,
                product_name: p.product_name,
                product_thumb: p.product_thumb,
                image_index: p.image_index || 0
            };
        });
        $hidden.val(JSON.stringify(clean));
    }

    /* ═══ HELPERS ═════════════════════════════════════════════ */
    function esc(s) { return s ? $('<span>').text(s).html() : ''; }

})(jQuery);
