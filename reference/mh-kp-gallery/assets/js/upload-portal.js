/**
 * MH Upload Portal — Frontend Upload Interface (Performance-Optimized)
 */
(function ($) {
    'use strict';

    var S = { projects: [], categories: [], active: null, pins: [], dirty: false, saving: false, token: '' };
    var _productCache = {};
    var $app, $side, $main;

    $(function () {
        $app = $('#mh-upload-portal');
        if (!$app.length) return;
        if (mhPortal.authenticated === 'yes') {
            S.token = getCookie('mh_stl_team_token') || '';
            bootApp();
        } else {
            showLogin();
        }
    });

    /* ═══ API ═════════════════════════════════════════════════ */
    function api(ep, opts) {
        opts = opts || {};
        var cfg = {
            url: mhPortal.rest_url + ep,
            method: opts.method || 'GET',
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', mhPortal.nonce);
                if (S.token) xhr.setRequestHeader('X-MH-Team-Token', S.token);
            }
        };
        if (opts.data && !(opts.data instanceof FormData)) {
            cfg.contentType = 'application/json';
            cfg.data = JSON.stringify(opts.data);
        } else if (opts.data instanceof FormData) {
            cfg.data = opts.data;
            cfg.contentType = false;
            cfg.processData = false;
        }
        return $.ajax(cfg);
    }

    function fetchProjectList() {
        return api('projects?per_page=100&lite=1').then(function (r) { S.projects = r.items || []; });
    }

    /* ═══ LOGIN ══════════════════════════════════════════════ */
    function showLogin() {
        var title = $app.data('title') || 'Kundenprojekte hochladen';
        $app.html(
            '<div class="mh-portal-login"><div class="mh-portal-login-card">' +
            '<div class="mh-portal-login-icon">🔐</div>' +
            '<h2>' + esc(title) + '</h2>' +
            '<p>Bitte gib das Team-Passwort ein.</p>' +
            '<input type="password" class="mh-portal-login-input" id="mh-portal-pw" placeholder="Passwort…" autocomplete="off">' +
            '<button class="mh-portal-login-btn" id="mh-portal-login-btn">Anmelden</button>' +
            '<div class="mh-portal-login-error" id="mh-portal-login-err" style="display:none"></div>' +
            '</div></div>'
        );
        var $pw = $('#mh-portal-pw'), $btn = $('#mh-portal-login-btn'), $err = $('#mh-portal-login-err');
        function doLogin() {
            var pw = $pw.val().trim();
            if (!pw) { $pw.addClass('error').focus(); return; }
            $btn.prop('disabled', true).text('Prüfe…');
            $err.hide();
            $.ajax({
                url: mhPortal.rest_url + 'auth', method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ password: pw }),
                beforeSend: function (xhr) { xhr.setRequestHeader('X-WP-Nonce', mhPortal.nonce); }
            }).done(function (r) {
                S.token = r.token;
                setCookie('mh_stl_team_token', r.token, 1);
                bootApp();
            }).fail(function (xhr) {
                $err.text((xhr.responseJSON || {}).message || 'Fehler').show();
                $pw.addClass('error').val('').focus();
                $btn.prop('disabled', false).text('Anmelden');
            });
        }
        $btn.on('click', doLogin);
        $pw.on('keydown', function (e) { if (e.key === 'Enter') doLogin(); }).focus();
    }

    /* ═══ BOOT ═══════════════════════════════════════════════ */
    function bootApp() {
        $app.html('<div class="mh-portal-loading"><div class="mh-portal-spinner"></div><p>Projekte laden…</p></div>');
        Promise.all([
            fetchProjectList(),
            api('categories').then(function (r) { S.categories = r || []; })
        ]).then(buildShell).catch(function () {
            S.token = ''; deleteCookie('mh_stl_team_token'); showLogin();
        });
    }

    /* ═══ SHELL (built once) ═════════════════════════════════ */
    function buildShell() {
        $app.empty();
        var title = $app.data('title') || 'Kundenprojekte hochladen';
        $app.append(
            '<div class="mh-portal-header"><h1 class="mh-portal-title">📸 ' + esc(title) + '</h1>' +
            '<div class="mh-portal-header-actions">' +
            '<button class="mh-portal-btn mh-portal-btn-primary" id="mp-new">+ Neues Projekt</button>' +
            '<button class="mh-portal-btn mh-portal-btn-secondary" id="mp-logout">Abmelden</button></div></div>'
        );
        var $layout = $('<div class="mh-portal-layout">');
        $side = $('<div class="mh-portal-sidebar">');
        $main = $('<div class="mh-portal-main">');
        $layout.append($side, $main);
        $app.append($layout);
        renderSidebar();
        renderMain();
        $('#mp-new').on('click', createProject);
        $('#mp-logout').on('click', function () { S.token = ''; deleteCookie('mh_stl_team_token'); showLogin(); });
    }

    /* ═══ SIDEBAR ════════════════════════════════════════════ */
    function renderSidebar() {
        var h = '<div class="mh-portal-sidebar-head">Projekte (' + S.projects.length + ')</div>' +
                '<input type="text" class="mh-portal-sidebar-search" placeholder="Suchen…" id="mp-search">' +
                '<div class="mh-portal-list" id="mp-list">';
        for (var i = 0; i < S.projects.length; i++) {
            var p = S.projects[i];
            var thumb = p.thumb
                ? '<img class="mh-portal-item-thumb" src="' + esc(p.thumb) + '" loading="lazy">'
                : '<div class="mh-portal-item-placeholder">📷</div>';
            var st = p.status === 'publish' ? 'publish' : 'draft';
            var act = (S.active && S.active.id === p.id) ? ' active' : '';
            var pc = p.pin_count || 0;
            h += '<div class="mh-portal-item' + act + '" data-id="' + p.id + '">' + thumb +
                 '<div style="flex:1;min-width:0;"><div class="mh-portal-item-name">' + esc(p.title) + '</div>' +
                 '<div class="mh-portal-item-date">' + fmtDate(p.date) + (pc ? ' · ' + pc + ' Pin' + (pc > 1 ? 's' : '') : '') + '</div></div>' +
                 '<span class="mh-portal-item-status ' + st + '">' + (st === 'publish' ? 'Live' : 'Entwurf') + '</span></div>';
        }
        h += '</div>';
        $side.html(h);

        $('#mp-list').on('click', '.mh-portal-item', function () {
            if (S.dirty && !confirm('Ungespeicherte Änderungen verwerfen?')) return;
            selectProject(+$(this).data('id'));
        });
        $('#mp-search').on('input', function () {
            var q = this.value.toLowerCase();
            var items = document.querySelectorAll('#mp-list .mh-portal-item');
            for (var j = 0; j < items.length; j++) {
                items[j].style.display = items[j].querySelector('.mh-portal-item-name').textContent.toLowerCase().indexOf(q) > -1 ? '' : 'none';
            }
        });
    }

    function updateSidebarActive() {
        var items = document.querySelectorAll('#mp-list .mh-portal-item');
        for (var i = 0; i < items.length; i++) {
            items[i].classList.toggle('active', S.active && S.active.id === +items[i].dataset.id);
        }
    }

    function updateSidebarItem(proj) {
        var el = document.querySelector('#mp-list .mh-portal-item[data-id="' + proj.id + '"]');
        if (!el) return;
        el.querySelector('.mh-portal-item-name').textContent = proj.title;
        var badge = el.querySelector('.mh-portal-item-status');
        var pub = proj.status === 'publish';
        badge.className = 'mh-portal-item-status ' + (pub ? 'publish' : 'draft');
        badge.textContent = pub ? 'Live' : 'Entwurf';
    }

    /* ═══ MAIN ═══════════════════════════════════════════════ */
    function renderMain() {
        $main.empty();
        if (!S.active) {
            $main.html('<div class="mh-portal-card"><div class="mh-portal-empty">' +
                '<div class="mh-portal-empty-icon">📸</div><h3>Kein Projekt gewählt</h3>' +
                '<p>Wähle links ein Projekt oder erstelle ein neues.</p></div></div>');
            return;
        }
        var p = S.active;
        var cats = S.categories.map(function (c) {
            var on = (p.categories || []).some(function (pc) { return pc.id === c.id; });
            return '<span class="mh-portal-cat-tag' + (on ? ' on' : '') + '" data-cid="' + c.id + '">' + esc(c.name) + '</span>';
        }).join('');

        var gallery = p.gallery_images || [];
        if (!gallery.length && p.image_url) gallery = [{ id: p.image_id || 0, url: p.image_url }];
        var firstImg = gallery.length ? gallery[0].url : '';

        // Build all HTML as one string for single DOM insertion
        var html = '';

        // Details
        html += '<div class="mh-portal-card"><div class="mh-portal-card-head"><h3>✏️ Details</h3>' +
            '<button class="mh-portal-btn mh-portal-btn-danger mh-portal-btn-sm" id="mp-del">Löschen</button></div>' +
            '<div class="mh-portal-card-body"><div class="mh-portal-form-grid">' +
            '<div class="wide"><label class="mh-portal-label">Titel</label><input class="mh-portal-input" id="mp-title" value="' + esc(p.title) + '"></div>' +
            '<div class="wide"><label class="mh-portal-label">Anzeige-Titel</label><input class="mh-portal-input" id="mp-stl-title" value="' + esc(p.stl_title) + '" placeholder="Optional…"></div>' +
            '<div class="wide"><label class="mh-portal-label">Beschreibung</label><textarea class="mh-portal-textarea" id="mp-desc" rows="2">' + esc(p.stl_desc) + '</textarea></div>' +
            '<div><label class="mh-portal-label">Status</label><select class="mh-portal-select" id="mp-status">' +
            '<option value="draft"' + (p.status === 'draft' ? ' selected' : '') + '>Entwurf</option>' +
            '<option value="publish"' + (p.status === 'publish' ? ' selected' : '') + '>Veröffentlicht</option></select></div>' +
            '<div><label class="mh-portal-label">Optionen</label>' +
            '<label class="mh-portal-checkbox"><input type="checkbox" id="mp-slider" ' + (p.show_in_slider ? 'checked' : '') + '> Im Slider</label><br>' +
            '<label class="mh-portal-checkbox" style="margin-top:4px;"><input type="checkbox" id="mp-hero" ' + (p.hero_grid ? 'checked' : '') + '> Hero-Projekt</label></div>' +
            '<div class="wide"><label class="mh-portal-label">Kategorien</label><div class="mh-portal-cat-tags" id="mp-cats">' + cats + '</div></div>' +
            '</div></div></div>';

        // Gallery
        html += '<div class="mh-portal-card"><div class="mh-portal-card-head"><h3>📷 Bilder (' + gallery.length + ')</h3></div><div class="mh-portal-card-body">';
        html += '<div class="mh-portal-gallery" id="mp-gallery">';
        for (var gi = 0; gi < gallery.length; gi++) {
            html += '<div class="mh-portal-gallery-item" data-idx="' + gi + '"><img src="' + esc(gallery[gi].url) + '" loading="lazy">' +
                '<button class="mh-portal-gallery-remove" data-idx="' + gi + '">&times;</button>' +
                (gi === 0 ? '<span class="mh-portal-gallery-badge">Hauptbild</span>' : '') + '</div>';
        }
        html += '<div class="mh-portal-gallery-add" id="mp-add-img"><div class="mh-portal-gallery-add-icon">+</div><div class="mh-portal-gallery-add-text">Bild hinzufügen</div></div></div>';
        html += '<div class="mh-portal-progress" style="display:none" id="mp-progress"><div class="mh-portal-progress-bar"><div class="mh-portal-progress-fill" style="width:0%"></div></div></div>';
        html += '<p style="font-size:12px;color:#8c8f94;margin:8px 0 0;">Erstes Bild = Hauptbild. Automatische WebP-Konvertierung.</p></div></div>';

        // Pins
        html += '<div class="mh-portal-card"><div class="mh-portal-card-head"><h3>📌 Pins</h3><span style="font-size:12px;color:#8c8f94;">' + S.pins.length + ' Pin(s)</span></div><div class="mh-portal-card-body">';
        if (firstImg) {
            html += '<div class="mh-portal-pin-hint">💡 <strong>Klicke auf das Bild</strong> um Pins zu setzen.</div>' +
                '<div class="mh-portal-pin-canvas-wrap"><div class="mh-portal-pin-canvas" id="mp-pin-canvas"><img id="mp-pin-img" src="' + esc(firstImg) + '"></div></div>' +
                '<div class="mh-portal-pin-list" id="mp-pin-list"></div>';
        } else {
            html += '<p style="color:#8c8f94;font-size:14px;">Bitte zuerst ein Bild hochladen.</p>';
        }
        html += '</div></div>';

        // Save bar
        html += '<div class="mh-portal-save-bar"><span class="mh-portal-save-status" id="mp-save-status">Bereit</span>' +
            '<div style="display:flex;gap:8px;"><button class="mh-portal-btn mh-portal-btn-primary" id="mp-save">💾 Speichern</button></div></div>';

        // Single DOM insertion
        $main.html(html);

        // Bind events
        $main.find('input,textarea,select').on('change input', markDirty);
        $('#mp-cats').on('click', '.mh-portal-cat-tag', function () { $(this).toggleClass('on'); markDirty(); });
        $('#mp-del').on('click', deleteProject);
        $('#mp-save').on('click', saveProject);
        bindImageUpload();
        bindPinEditor();
    }

    /* ═══ IMAGE UPLOAD ═══════════════════════════════════════ */
    function bindImageUpload() {
        var $fi = $('<input type="file" accept="image/*" multiple style="display:none">');
        $main.append($fi);
        $('#mp-add-img').on('click', function () { $fi.trigger('click'); });
        var $gal = $('#mp-gallery');
        $gal.on('dragover dragenter', function (e) { e.preventDefault(); $('#mp-add-img').addClass('over'); });
        $gal.on('dragleave drop', function (e) { e.preventDefault(); $('#mp-add-img').removeClass('over'); });
        $gal.on('drop', function (e) { if (e.originalEvent.dataTransfer.files.length) uploadFiles(e.originalEvent.dataTransfer.files); });
        $fi.on('change', function () { if (this.files.length) uploadFiles(this.files); $(this).val(''); });
        $gal.on('click', '.mh-portal-gallery-remove', function (e) {
            e.stopPropagation();
            var g = S.active.gallery_images || [];
            g.splice(+$(this).data('idx'), 1);
            S.active.gallery_images = g;
            S.active.image_url = g.length ? g[0].url : '';
            S.active.image_id = g.length ? g[0].id : 0;
            markDirty(); renderMain();
        });
    }

    function uploadFiles(fileList) {
        var files = Array.from(fileList).filter(function (f) {
            return f.type.match(/^image\/(jpeg|png|webp|gif)$/) && f.size <= 15728640;
        });
        if (!files.length) { toast('Nur Bilder bis 15 MB', 'err'); return; }
        var $p = $('#mp-progress').show(), done = 0, total = files.length;
        $p.find('.mh-portal-progress-fill').css('width', '0%');

        // Process files sequentially to avoid memory spikes
        var queue = files.slice();
        function processNext() {
            if (!queue.length) return;
            var file = queue.shift();
            resizeAndUpload(file, function () {
                done++;
                $p.find('.mh-portal-progress-fill').css('width', Math.round(done / total * 100) + '%');
                if (done >= total) {
                    toast(done + ' Bild' + (done > 1 ? 'er' : '') + ' hochgeladen (WebP)', 'ok');
                    markDirty();
                    renderMain();
                } else {
                    processNext();
                }
            }, function () {
                done++;
                toast('Upload fehlgeschlagen', 'err');
                if (done >= total) $p.hide();
                else processNext();
            });
        }
        processNext();
    }

    /**
     * Resize image client-side to max 2000px, convert to WebP, then upload.
     * A 6MB phone photo becomes ~200-400KB — uploads in ~1 second.
     */
    function resizeAndUpload(file, onDone, onFail) {
        var MAX_DIM = 2000;
        var QUALITY = 0.82;

        var reader = new FileReader();
        reader.onload = function (e) {
            var img = new Image();
            img.onload = function () {
                var w = img.width, h = img.height;

                // Only resize if larger than MAX_DIM
                if (w > MAX_DIM || h > MAX_DIM) {
                    if (w > h) { h = Math.round(h * MAX_DIM / w); w = MAX_DIM; }
                    else { w = Math.round(w * MAX_DIM / h); h = MAX_DIM; }
                }

                var canvas = document.createElement('canvas');
                canvas.width = w;
                canvas.height = h;
                var ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, w, h);

                // Try WebP first, fallback to JPEG
                var mimeType = 'image/webp';
                var ext = '.webp';
                canvas.toBlob(function (blob) {
                    if (!blob) {
                        // WebP not supported — fallback to JPEG
                        mimeType = 'image/jpeg';
                        ext = '.jpg';
                        canvas.toBlob(function (blob2) {
                            if (!blob2) { onFail(); return; }
                            doUpload(blob2, file.name.replace(/\.[^.]+$/, ext), onDone, onFail);
                        }, 'image/jpeg', QUALITY);
                        return;
                    }
                    doUpload(blob, file.name.replace(/\.[^.]+$/, ext), onDone, onFail);
                }, mimeType, QUALITY);
            };
            img.onerror = function () {
                // Can't decode — upload original
                doUpload(file, file.name, onDone, onFail);
            };
            img.src = e.target.result;
        };
        reader.onerror = function () { doUpload(file, file.name, onDone, onFail); };
        reader.readAsDataURL(file);
    }

    function doUpload(blob, filename, onDone, onFail) {
        var fd = new FormData();
        fd.append('file', blob, filename);
        if (S.active) fd.append('project_id', S.active.id);

        $.ajax({
            url: mhPortal.rest_url + 'upload', method: 'POST', data: fd,
            contentType: false, processData: false,
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', mhPortal.nonce);
                if (S.token) xhr.setRequestHeader('X-MH-Team-Token', S.token);
            }
        }).done(function (r) {
            var g = S.active.gallery_images || [];
            g.push({ id: r.attachment_id, url: r.large || r.url });
            S.active.gallery_images = g;
            if (!S.active.image_url) { S.active.image_url = g[0].url; S.active.image_id = g[0].id; }
            onDone();
        }).fail(onFail);
    }

    /* ═══ PIN EDITOR ═════════════════════════════════════════ */
    function bindPinEditor() {
        var $c = $('#mp-pin-canvas'), $img = $('#mp-pin-img');
        if (!$c.length) return;
        var img = $img[0];
        if (img.complete) initPins($c); else $img.on('load', function () { initPins($c); });
    }

    function initPins($c) {
        drawPins($c);
        $c.off('click.pin').on('click.pin', function (e) {
            if ($(e.target).closest('.mh-portal-pin').length) return;
            var r = $c[0].getBoundingClientRect();
            S.pins.push({ x: +((e.clientX - r.left) / r.width * 100).toFixed(2), y: +((e.clientY - r.top) / r.height * 100).toFixed(2), product_id: 0, product_name: '', product_thumb: '' });
            drawPins($c); drawPinList(); markDirty();
        });
    }

    function drawPins($c) {
        $c.find('.mh-portal-pin').remove();
        S.pins.forEach(function (pin, i) {
            var $p = $('<div class="mh-portal-pin' + (pin.product_id > 0 ? '' : ' no-product') + '">').css({ left: pin.x + '%', top: pin.y + '%' }).text(i + 1);
            $c.append($p);
            $p.draggable({ containment: $c, stop: function (e, ui) {
                var r = $c[0].getBoundingClientRect();
                S.pins[i].x = +((ui.position.left + 15) / r.width * 100).toFixed(2);
                S.pins[i].y = +((ui.position.top + 15) / r.height * 100).toFixed(2);
                drawPinList(); markDirty();
            }});
        });
        drawPinList();
    }

    function drawPinList() {
        var $l = $('#mp-pin-list');
        if (!$l.length) return;
        if (!S.pins.length) { $l.html('<div style="color:#8c8f94;font-size:12px;">Keine Pins.</div>'); return; }
        var h = '';
        S.pins.forEach(function (pin, i) {
            var has = pin.product_id > 0;
            h += '<div class="mh-portal-pin-row' + (has ? '' : ' no-product') + '" data-i="' + i + '">' +
                 '<div class="mh-portal-pin-num">' + (i + 1) + '</div>' +
                 '<div class="mh-portal-pin-coords">' + pin.x + '%, ' + pin.y + '%</div>';
            if (has) {
                h += '<div class="mh-portal-pin-product">' + (pin.product_thumb ? '<img src="' + esc(pin.product_thumb) + '">' : '') +
                     '<span class="mh-portal-pin-product-name">' + esc(pin.product_name) + '</span></div>' +
                     '<button class="mh-portal-btn mh-portal-btn-secondary mh-portal-btn-sm mp-pin-change" data-i="' + i + '">Ändern</button>';
            } else {
                h += '<div class="mh-portal-pin-search-wrap"><input class="mh-portal-pin-search" data-i="' + i + '" placeholder="Produkt suchen…">' +
                     '<div class="mh-portal-pin-dd" data-i="' + i + '"></div></div>';
            }
            h += '<button class="mh-portal-pin-remove" data-i="' + i + '">&times;</button></div>';
        });
        $l.html(h);

        $l.off('.pins').on('click.pins', '.mp-pin-change', function () {
            var i = +$(this).data('i');
            S.pins[i] = { x: S.pins[i].x, y: S.pins[i].y, product_id: 0, product_name: '', product_thumb: '' };
            drawPins($('#mp-pin-canvas')); markDirty();
        }).on('click.pins', '.mh-portal-pin-remove', function () {
            S.pins.splice(+$(this).data('i'), 1);
            drawPins($('#mp-pin-canvas')); markDirty();
        });

        $l.find('.mh-portal-pin-search').each(function () {
            var $in = $(this), idx = +$in.data('i'), $dd = $l.find('.mh-portal-pin-dd[data-i="' + idx + '"]');
            var t;
            $in.on('input', function () {
                var q = $in.val().trim(); clearTimeout(t);
                if (q.length < 2) { $dd.removeClass('open').empty(); return; }
                if (_productCache[q]) { showResults($dd, _productCache[q], idx); return; }
                t = setTimeout(function () {
                    $dd.html('<div style="padding:8px;color:#999;font-size:12px;">Suche…</div>').addClass('open');
                    api('products?term=' + encodeURIComponent(q)).then(function (res) { _productCache[q] = res; showResults($dd, res, idx); });
                }, 300);
            });
            $(document).on('click', function (e) { if (!$(e.target).closest($in.closest('.mh-portal-pin-search-wrap')).length) $dd.removeClass('open'); });
            setTimeout(function () { $in.focus(); }, 50);
        });
    }

    function showResults($dd, results, idx) {
        $dd.empty();
        if (!results.length) { $dd.html('<div style="padding:8px;color:#999;font-size:12px;">Nichts gefunden</div>').addClass('open'); return; }
        results.forEach(function (p) {
            var $it = $('<div class="mh-portal-pin-dd-item">' + (p.thumb ? '<img src="' + esc(p.thumb) + '">' : '') + '<span>' + esc(p.name) + '</span></div>');
            $it.on('click', function () {
                S.pins[idx] = { x: S.pins[idx].x, y: S.pins[idx].y, product_id: p.id, product_name: p.name, product_thumb: p.thumb || '' };
                drawPins($('#mp-pin-canvas')); markDirty();
            });
            $dd.append($it);
        });
        $dd.addClass('open');
    }

    /* ═══ CRUD ════════════════════════════════════════════════ */
    function selectProject(id) {
        api('projects/' + id).then(function (r) {
            S.active = r; S.pins = r.pins || []; S.dirty = false;
            updateSidebarActive();
            renderMain();
        });
    }

    function createProject() {
        if (S.dirty && !confirm('Ungespeicherte Änderungen verwerfen?')) return;
        api('projects', { method: 'POST', data: { title: 'Neues Kundenprojekt', status: 'draft' } }).done(function (r) {
            toast('Erstellt', 'ok');
            S.projects.unshift({ id: r.id, title: r.title, status: r.status, date: r.date, thumb: '', pin_count: 0 });
            S.active = r; S.pins = []; S.dirty = false;
            renderSidebar(); renderMain();
        });
    }

    function deleteProject() {
        if (!S.active || !confirm('Projekt "' + S.active.title + '" löschen?')) return;
        var did = S.active.id;
        api('projects/' + did, { method: 'DELETE' }).then(function () {
            toast('Gelöscht', 'ok');
            S.projects = S.projects.filter(function (p) { return p.id !== did; });
            S.active = null; S.pins = []; S.dirty = false;
            renderSidebar(); renderMain();
        });
    }

    function saveProject() {
        if (S.saving || !S.active) return;
        S.saving = true;
        $('#mp-save-status').text('Speichern…');
        $('#mp-save').prop('disabled', true);
        var cats = [];
        $('#mp-cats .mh-portal-cat-tag.on').each(function () { cats.push(+$(this).data('cid')); });
        var data = {
            title: $('#mp-title').val(), stl_title: $('#mp-stl-title').val(), stl_desc: $('#mp-desc').val(),
            status: $('#mp-status').val(), show_in_slider: $('#mp-slider').is(':checked'), hero_grid: $('#mp-hero').is(':checked'),
            categories: cats, pins: S.pins.map(function (p) { return { x: p.x, y: p.y, product_id: p.product_id }; }),
        };
        if (S.active.image_id) data.image_id = S.active.image_id;
        var gallery = S.active.gallery_images || [];
        data.gallery_image_ids = gallery.map(function (img) { return img.id; }).filter(function (id) { return id > 0; });

        api('projects/' + S.active.id, { method: 'PUT', data: data }).done(function (r) {
            S.saving = false; S.dirty = false; S.active = r; S.pins = r.pins || [];
            $('#mp-save-status').text('✅ Gespeichert');
            $('#mp-save').prop('disabled', false);
            toast('Gespeichert!', 'ok');
            for (var i = 0; i < S.projects.length; i++) {
                if (S.projects[i].id === r.id) {
                    S.projects[i].title = r.title;
                    S.projects[i].status = r.status;
                    S.projects[i].pin_count = (r.pins || []).length;
                    break;
                }
            }
            updateSidebarItem(r);
        }).fail(function (xhr) {
            S.saving = false;
            $('#mp-save-status').text('❌ Fehler');
            $('#mp-save').prop('disabled', false);
            toast((xhr.responseJSON || {}).message || 'Fehler', 'err');
        });
    }

    /* ═══ HELPERS ═════════════════════════════════════════════ */
    function markDirty() { S.dirty = true; var $s = $('#mp-save-status'); if ($s.length) $s.text('● Ungespeichert'); }
    function toast(m, t) { var $t = $('<div class="mh-portal-toast ' + (t || 'ok') + '">' + esc(m) + '</div>'); $('body').append($t); setTimeout(function () { $t.fadeOut(200, function () { $t.remove(); }); }, 2200); }
    function esc(s) { return s ? $('<span>').text(s).html() : ''; }
    function fmtDate(d) { if (!d) return ''; return new Date(d).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' }); }
    function setCookie(n, v, d) { var e = new Date(); e.setTime(e.getTime() + d * 86400000); document.cookie = n + '=' + v + ';expires=' + e.toUTCString() + ';path=/;SameSite=Lax'; }
    function getCookie(n) { var m = document.cookie.match(new RegExp('(^| )' + n + '=([^;]+)')); return m ? m[2] : ''; }
    function deleteCookie(n) { document.cookie = n + '=;expires=Thu, 01 Jan 1970 00:00:00 UTC;path=/;'; }
})(jQuery);
