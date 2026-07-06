/**
 * MH Upload Manager — Complete Admin Interface
 * Project CRUD, Image Upload (FTP + WP), Pin Editor with Product Search
 */
(function ($) {
    'use strict';

    /* ═══ STATE ═══════════════════════════════════════════════ */
    var state = {
        projects: [],
        categories: [],
        activeProject: null,
        pins: [],
        dirty: false,
        uploading: false,
        saving: false,
    };

    var $app, $sidebar, $main;

    /* ═══ INIT ════════════════════════════════════════════════ */
    $(document).ready(function () {
        $app = $('#mh-upload-manager-app');
        if (!$app.length) return;

        // Load data, then render
        Promise.all([loadProjects(), loadCategories()]).then(function () {
            render();
        });
    });

    /* ═══ API HELPERS ═════════════════════════════════════════ */
    function api(endpoint, opts) {
        opts = opts || {};
        var config = {
            url: mhUpload.rest_url + endpoint,
            method: opts.method || 'GET',
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', mhUpload.nonce);
            },
        };

        if (opts.data && !(opts.data instanceof FormData)) {
            config.contentType = 'application/json';
            config.data = JSON.stringify(opts.data);
        } else if (opts.data instanceof FormData) {
            config.data = opts.data;
            config.contentType = false;
            config.processData = false;
        }

        return $.ajax(config);
    }

    function loadProjects() {
        return api('projects?per_page=100').then(function (resp) {
            state.projects = resp.items || [];
        });
    }

    function loadCategories() {
        return api('categories').then(function (resp) {
            state.categories = resp || [];
        });
    }

    /* ═══ RENDER ══════════════════════════════════════════════ */
    function render() {
        $app.empty();

        // Toolbar
        var ftpBadge = mhUpload.ftp_active
            ? '<span class="mh-um-ftp-badge active">● FTP aktiv</span>'
            : '<span class="mh-um-ftp-badge inactive">○ FTP nicht konfiguriert</span>';

        $app.append(
            '<div class="mh-um-toolbar">' +
                '<div class="mh-um-toolbar-left">' +
                    '<div class="mh-um-toolbar-title">📤 Upload Manager</div>' +
                    ftpBadge +
                '</div>' +
                '<div class="mh-um-toolbar-right">' +
                    '<button class="mh-um-btn mh-um-btn-primary" id="mh-um-new-project">+ Neues Projekt</button>' +
                '</div>' +
            '</div>'
        );

        // Layout
        var $layout = $('<div class="mh-um-layout">');
        $sidebar = $('<div class="mh-um-sidebar">');
        $main = $('<div class="mh-um-main">');
        $layout.append($sidebar, $main);
        $app.append($layout);

        renderSidebar();
        renderMain();

        // Events
        $('#mh-um-new-project').on('click', createNewProject);
    }

    /* ── Sidebar ──────────────────────────────────────────── */
    function renderSidebar() {
        $sidebar.empty();

        $sidebar.append(
            '<div class="mh-um-sidebar-header"><h3>Projekte (' + state.projects.length + ')</h3></div>' +
            '<input type="text" class="mh-um-sidebar-search" placeholder="Projekt suchen…" id="mh-um-search">'
        );

        var $list = $('<div class="mh-um-project-list" id="mh-um-project-list">');

        state.projects.forEach(function (p) {
            var thumb = p.image_url
                ? '<img class="mh-um-project-thumb" src="' + esc(p.image_url) + '" alt="">'
                : '<div class="mh-um-project-thumb-placeholder">📷</div>';

            var statusClass = p.status === 'publish' ? 'publish' : 'draft';
            var statusLabel = p.status === 'publish' ? 'Live' : 'Entwurf';
            var activeClass = (state.activeProject && state.activeProject.id === p.id) ? ' active' : '';
            var pinCount = (p.pins || []).length;
            var pinInfo = pinCount > 0 ? ' · ' + pinCount + ' Pin' + (pinCount > 1 ? 's' : '') : '';

            var $item = $(
                '<div class="mh-um-project-item' + activeClass + '" data-id="' + p.id + '">' +
                    thumb +
                    '<div class="mh-um-project-meta">' +
                        '<div class="mh-um-project-name">' + esc(p.title) + '</div>' +
                        '<div class="mh-um-project-date">' + formatDate(p.date) + pinInfo + '</div>' +
                    '</div>' +
                    '<span class="mh-um-project-status ' + statusClass + '">' + statusLabel + '</span>' +
                '</div>'
            );

            $item.on('click', function () {
                if (state.dirty && !confirm('Ungespeicherte Änderungen verwerfen?')) return;
                selectProject(p.id);
            });

            $list.append($item);
        });

        $sidebar.append($list);

        // Search filter
        $('#mh-um-search').off('input').on('input', function () {
            var q = $(this).val().toLowerCase();
            $list.find('.mh-um-project-item').each(function () {
                var name = $(this).find('.mh-um-project-name').text().toLowerCase();
                $(this).toggle(name.indexOf(q) !== -1);
            });
        });
    }

    /* ── Main Area ────────────────────────────────────────── */
    function renderMain() {
        $main.empty();

        if (!state.activeProject) {
            $main.html(
                '<div class="mh-um-card"><div class="mh-um-empty">' +
                    '<div class="mh-um-empty-icon">📸</div>' +
                    '<h3>Kein Projekt ausgewählt</h3>' +
                    '<p>Wähle ein Projekt aus der Liste oder erstelle ein neues.</p>' +
                '</div></div>'
            );
            return;
        }

        var p = state.activeProject;

        // ── Details Card ──
        var catsHtml = state.categories.map(function (c) {
            var sel = (p.categories || []).some(function (pc) { return pc.id === c.id; });
            return '<span class="mh-um-cat-tag' + (sel ? ' selected' : '') + '" data-cat-id="' + c.id + '">' + esc(c.name) + '</span>';
        }).join('');

        var detailsCard =
            '<div class="mh-um-card">' +
                '<div class="mh-um-card-header"><h3>✏️ Projekt-Details</h3>' +
                    '<div class="mh-um-btn-row">' +
                        '<button class="mh-um-btn mh-um-btn-danger mh-um-btn-sm" id="mh-um-delete">🗑️ Löschen</button>' +
                    '</div>' +
                '</div>' +
                '<div class="mh-um-card-body">' +
                    '<div class="mh-um-form-grid">' +
                        '<div class="mh-um-field mh-um-field-wide">' +
                            '<label>Titel</label>' +
                            '<input type="text" id="mh-um-title" value="' + esc(p.title) + '">' +
                        '</div>' +
                        '<div class="mh-um-field mh-um-field-wide">' +
                            '<label>Anzeige-Titel (optional)</label>' +
                            '<input type="text" id="mh-um-stl-title" value="' + esc(p.stl_title) + '" placeholder="z.B. Ein minimalistischer Zaun…">' +
                        '</div>' +
                        '<div class="mh-um-field mh-um-field-wide">' +
                            '<label>Beschreibung</label>' +
                            '<textarea id="mh-um-desc" rows="3" placeholder="Kurze Beschreibung…">' + esc(p.stl_desc) + '</textarea>' +
                        '</div>' +
                        '<div class="mh-um-field">' +
                            '<label>Status</label>' +
                            '<select id="mh-um-status">' +
                                '<option value="draft"' + (p.status === 'draft' ? ' selected' : '') + '>Entwurf</option>' +
                                '<option value="publish"' + (p.status === 'publish' ? ' selected' : '') + '>Veröffentlicht</option>' +
                            '</select>' +
                        '</div>' +
                        '<div class="mh-um-field">' +
                            '<label>Optionen</label>' +
                            '<label class="mh-um-checkbox"><input type="checkbox" id="mh-um-slider" ' + (p.show_in_slider ? 'checked' : '') + '> <span>Im Slider anzeigen</span></label><br>' +
                            '<label class="mh-um-checkbox" style="margin-top:6px;"><input type="checkbox" id="mh-um-hero" ' + (p.hero_grid ? 'checked' : '') + '> <span>Hero-Projekt im Grid</span></label>' +
                        '</div>' +
                        '<div class="mh-um-field mh-um-field-wide">' +
                            '<label>Kategorien</label>' +
                            '<div class="mh-um-cat-tags" id="mh-um-cats">' + catsHtml + '</div>' +
                        '</div>' +
                        '<div class="mh-um-field mh-um-field-wide">' +
                            '<label>Buttons</label>' +
                            '<div class="mh-um-button-pair">' +
                                '<div>' +
                                    '<input type="text" id="mh-um-btn1-text" value="' + esc((p.buttons[0] || {}).text || '') + '" placeholder="Button 1 Text">' +
                                    '<input type="url" id="mh-um-btn1-url" value="' + esc((p.buttons[0] || {}).url || '') + '" placeholder="https://…" style="margin-top:6px;">' +
                                '</div>' +
                                '<div>' +
                                    '<input type="text" id="mh-um-btn2-text" value="' + esc((p.buttons[1] || {}).text || '') + '" placeholder="Button 2 Text">' +
                                    '<input type="url" id="mh-um-btn2-url" value="' + esc((p.buttons[1] || {}).url || '') + '" placeholder="https://…" style="margin-top:6px;">' +
                                '</div>' +
                            '</div>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
            '</div>';

        // ── Image Upload Card ──
        var imageHtml;
        if (p.image_url) {
            imageHtml =
                '<div style="position:relative;display:inline-block;">' +
                    '<img src="' + esc(p.image_url) + '" style="max-width:100%;border-radius:8px;display:block;">' +
                    '<button class="mh-um-btn mh-um-btn-secondary mh-um-btn-sm" id="mh-um-change-img" style="position:absolute;top:8px;right:8px;">📷 Bild ändern</button>' +
                '</div>';
        } else {
            imageHtml =
                '<div class="mh-um-upload-zone" id="mh-um-dropzone">' +
                    '<div class="mh-um-upload-zone-icon">📸</div>' +
                    '<div class="mh-um-upload-zone-text">Bild hierher ziehen oder klicken</div>' +
                    '<div class="mh-um-upload-zone-hint">JPG, PNG, WebP · Max. 10 MB' + (mhUpload.ftp_active ? ' · wird auf FTP + WP hochgeladen' : '') + '</div>' +
                    '<div class="mh-um-upload-progress" style="display:none;" id="mh-um-upload-progress">' +
                        '<div class="mh-um-upload-bar"><div class="mh-um-upload-bar-fill" style="width:0%"></div></div>' +
                    '</div>' +
                '</div>';
        }

        var imageCard =
            '<div class="mh-um-card">' +
                '<div class="mh-um-card-header"><h3>📷 Projektbild</h3></div>' +
                '<div class="mh-um-card-body">' + imageHtml + '</div>' +
            '</div>';

        // ── Pin Editor Card ──
        var pinCard =
            '<div class="mh-um-card">' +
                '<div class="mh-um-card-header"><h3>📌 Pin-Editor</h3>' +
                    '<span style="font-size:12px;color:#8c8f94;">' + state.pins.length + ' Pin(s)</span>' +
                '</div>' +
                '<div class="mh-um-card-body">' +
                    '<div class="mh-um-pin-editor" id="mh-um-pin-editor">' +
                        (p.image_url
                            ? '<div class="mh-um-pin-hint">💡 <strong>Klicke auf das Bild</strong> um einen Pin zu platzieren. Pins können per Drag & Drop verschoben werden.</div>' +
                              '<div class="mh-um-pin-canvas-wrap"><div class="mh-um-pin-canvas" id="mh-um-pin-canvas">' +
                                  '<img id="mh-um-pin-image" src="' + esc(p.image_url) + '" alt="">' +
                              '</div></div>' +
                              '<div class="mh-um-pin-list" id="mh-um-pin-list"></div>'
                            : '<div class="mh-um-notice mh-um-notice-info">⬆️ Bitte zuerst ein Bild hochladen, dann können Pins gesetzt werden.</div>'
                        ) +
                    '</div>' +
                '</div>' +
            '</div>';

        // ── Save Bar ──
        var saveBar =
            '<div class="mh-um-save-bar" id="mh-um-save-bar">' +
                '<div class="mh-um-save-status" id="mh-um-save-status">Bereit</div>' +
                '<div class="mh-um-btn-row">' +
                    '<button class="mh-um-btn mh-um-btn-secondary" id="mh-um-reload">↻ Neu laden</button>' +
                    '<button class="mh-um-btn mh-um-btn-primary" id="mh-um-save">💾 Speichern</button>' +
                '</div>' +
            '</div>';

        $main.html(detailsCard + imageCard + pinCard + saveBar);

        // ── Bind Events ──
        bindFormEvents();
        bindImageEvents();
        bindPinEditor();
        bindSaveEvents();
    }

    /* ═══ EVENTS ══════════════════════════════════════════════ */

    function bindFormEvents() {
        // Category toggle
        $('#mh-um-cats').on('click', '.mh-um-cat-tag', function () {
            $(this).toggleClass('selected');
            markDirty();
        });

        // Mark dirty on changes
        $main.find('input, textarea, select').on('change input', function () {
            markDirty();
        });

        // Delete
        $('#mh-um-delete').on('click', function () {
            if (!confirm('Projekt "' + state.activeProject.title + '" wirklich löschen?')) return;
            api('projects/' + state.activeProject.id, { method: 'DELETE' }).then(function () {
                toast('Projekt gelöscht', 'success');
                state.activeProject = null;
                state.pins = [];
                state.dirty = false;
                loadProjects().then(function () { render(); });
            });
        });
    }

    function bindImageEvents() {
        var $dropzone = $('#mh-um-dropzone');
        var $fileInput = $('<input type="file" accept="image/*" style="display:none">');
        $app.append($fileInput);

        // Dropzone click
        $dropzone.on('click', function () { $fileInput.trigger('click'); });

        // Change image button
        $('#mh-um-change-img').on('click', function () { $fileInput.trigger('click'); });

        // Drag & drop
        $dropzone.on('dragover dragenter', function (e) {
            e.preventDefault(); e.stopPropagation();
            $dropzone.addClass('dragover');
        });
        $dropzone.on('dragleave drop', function (e) {
            e.preventDefault(); e.stopPropagation();
            $dropzone.removeClass('dragover');
        });
        $dropzone.on('drop', function (e) {
            var files = e.originalEvent.dataTransfer.files;
            if (files.length) uploadImage(files[0]);
        });

        $fileInput.on('change', function () {
            if (this.files.length) uploadImage(this.files[0]);
            $(this).val('');
        });
    }

    function uploadImage(file) {
        if (state.uploading) return;

        // Validate
        if (!file.type.match(/^image\/(jpeg|png|webp|gif)$/)) {
            toast('Nur Bilder erlaubt (JPG, PNG, WebP, GIF)', 'error');
            return;
        }
        if (file.size > 10 * 1024 * 1024) {
            toast('Datei zu groß (max. 10 MB)', 'error');
            return;
        }

        state.uploading = true;
        var $progress = $('#mh-um-upload-progress');
        $progress.show().find('.mh-um-upload-bar-fill').css('width', '10%');

        var fd = new FormData();
        fd.append('file', file);
        if (state.activeProject) {
            fd.append('project_id', state.activeProject.id);
        }

        $.ajax({
            url: mhUpload.rest_url + 'upload',
            method: 'POST',
            data: fd,
            contentType: false,
            processData: false,
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', mhUpload.nonce);
            },
            xhr: function () {
                var xhr = new XMLHttpRequest();
                xhr.upload.addEventListener('progress', function (e) {
                    if (e.lengthComputable) {
                        var pct = Math.round(e.loaded / e.total * 100);
                        $progress.find('.mh-um-upload-bar-fill').css('width', pct + '%');
                    }
                });
                return xhr;
            },
        })
        .done(function (resp) {
            state.uploading = false;
            state.activeProject.image_url = resp.large || resp.url;
            state.activeProject.image_id = resp.attachment_id;
            var msg = 'Bild hochgeladen';
            if (resp.ftp_uploaded) msg += ' (FTP + WordPress)';
            toast(msg, 'success');
            markDirty();
            renderMain();
        })
        .fail(function (xhr) {
            state.uploading = false;
            var msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Upload fehlgeschlagen';
            toast(msg, 'error');
            $progress.hide();
        });
    }

    /* ═══ PIN EDITOR ══════════════════════════════════════════ */

    function bindPinEditor() {
        var $canvas = $('#mh-um-pin-canvas');
        var $image = $('#mh-um-pin-image');
        if (!$canvas.length || !$image.length) return;

        // Wait for image
        var img = $image[0];
        if (img.complete) { initPinCanvas($canvas); }
        else { $image.on('load', function () { initPinCanvas($canvas); }); }
    }

    function initPinCanvas($canvas) {
        renderPins($canvas);

        // Click to add pin
        $canvas.off('click.pin').on('click.pin', function (e) {
            if ($(e.target).closest('.mh-um-pin').length) return;

            var rect = $canvas[0].getBoundingClientRect();
            var x = ((e.clientX - rect.left) / rect.width * 100).toFixed(2);
            var y = ((e.clientY - rect.top) / rect.height * 100).toFixed(2);

            state.pins.push({
                x: parseFloat(x), y: parseFloat(y),
                product_id: 0, product_name: '', product_thumb: '',
                image_index: 0
            });

            renderPins($canvas);
            renderPinList();
            markDirty();
        });
    }

    function renderPins($canvas) {
        $canvas.find('.mh-um-pin').remove();

        state.pins.forEach(function (pin, i) {
            var hasProduct = pin.product_id > 0;
            var $pin = $('<div class="mh-um-pin' + (hasProduct ? '' : ' no-product') + '" data-index="' + i + '">')
                .css({ left: pin.x + '%', top: pin.y + '%' })
                .text(i + 1)
                .attr('title', hasProduct ? pin.product_name : 'Kein Produkt zugewiesen');

            $canvas.append($pin);

            $pin.draggable({
                containment: $canvas,
                stop: function (e, ui) {
                    var rect = $canvas[0].getBoundingClientRect();
                    var cx = ui.position.left + 16;
                    var cy = ui.position.top + 16;
                    state.pins[i].x = parseFloat((cx / rect.width * 100).toFixed(2));
                    state.pins[i].y = parseFloat((cy / rect.height * 100).toFixed(2));
                    renderPinList();
                    markDirty();
                }
            });
        });

        renderPinList();
    }

    function renderPinList() {
        var $list = $('#mh-um-pin-list');
        if (!$list.length) return;
        $list.empty();

        if (state.pins.length === 0) {
            $list.html('<div style="color:#8c8f94;font-size:13px;font-style:italic;padding:4px 0;">Noch keine Pins. Klicke auf das Bild.</div>');
            return;
        }

        state.pins.forEach(function (pin, i) {
            var hasProduct = pin.product_id > 0;
            var $row = $('<div class="mh-um-pin-row' + (hasProduct ? '' : ' no-product') + '">');

            $row.append('<div class="mh-um-pin-num">' + (i + 1) + '</div>');
            $row.append('<div class="mh-um-pin-coords">' + pin.x + '%, ' + pin.y + '%</div>');

            if (hasProduct) {
                var thumbHtml = pin.product_thumb ? '<img src="' + esc(pin.product_thumb) + '" alt="">' : '';
                $row.append(
                    '<div class="mh-um-pin-product">' + thumbHtml +
                        '<span class="mh-um-pin-product-name">' + esc(pin.product_name) + '</span>' +
                    '</div>'
                );

                var $changeBtn = $('<button class="mh-um-btn mh-um-btn-secondary mh-um-btn-sm">Ändern</button>');
                $changeBtn.on('click', function () {
                    state.pins[i].product_id = 0;
                    state.pins[i].product_name = '';
                    state.pins[i].product_thumb = '';
                    var $canvas = $('#mh-um-pin-canvas');
                    renderPins($canvas);
                    markDirty();
                });
                $row.append($changeBtn);
            } else {
                var $searchWrap = $('<div class="mh-um-pin-search-wrap">');
                var $search = $('<input type="text" class="mh-um-pin-search" placeholder="Produkt suchen (Name oder ID)…">');
                var $dropdown = $('<div class="mh-um-pin-dropdown">');
                $searchWrap.append($search, $dropdown);
                $row.append($searchWrap);
                bindProductSearch($search, $dropdown, i);
            }

            var $remove = $('<button class="mh-um-pin-remove" title="Pin entfernen">&times;</button>');
            $remove.on('click', function () {
                state.pins.splice(i, 1);
                var $canvas = $('#mh-um-pin-canvas');
                renderPins($canvas);
                markDirty();
            });
            $row.append($remove);

            $list.append($row);
        });
    }

    /* ═══ PRODUCT SEARCH ══════════════════════════════════════ */

    function bindProductSearch($input, $dropdown, pinIndex) {
        var timer;

        $input.on('input', function () {
            var q = $input.val().trim();
            clearTimeout(timer);
            if (q.length < 2) { $dropdown.removeClass('open').empty(); return; }

            timer = setTimeout(function () {
                $dropdown.html('<div style="padding:8px;color:#999;font-size:12px;">Suche…</div>').addClass('open');

                api('products?term=' + encodeURIComponent(q)).then(function (results) {
                    $dropdown.empty();
                    if (!results.length) {
                        $dropdown.html('<div style="padding:8px;color:#999;font-size:12px;">Nichts gefunden</div>');
                        return;
                    }
                    results.forEach(function (p) {
                        var img = p.thumb ? '<img src="' + esc(p.thumb) + '" alt="">' : '';
                        var price = p.price ? '<span class="mh-um-product-price">' + esc(p.price) + '</span>' : '';
                        var $item = $('<div class="mh-um-pin-dropdown-item">' + img + '<span>' + esc(p.name) + ' <small style="color:#888;">#' + p.id + '</small></span>' + price + '</div>');

                        $item.on('click', function () {
                            state.pins[pinIndex].product_id = p.id;
                            state.pins[pinIndex].product_name = p.name;
                            state.pins[pinIndex].product_thumb = p.thumb || '';
                            var $canvas = $('#mh-um-pin-canvas');
                            renderPins($canvas);
                            markDirty();
                        });
                        $dropdown.append($item);
                    });
                    $dropdown.addClass('open');
                });
            }, 350);
        });

        $(document).on('click', function (e) {
            if (!$(e.target).closest($input.closest('.mh-um-pin-search-wrap')).length) {
                $dropdown.removeClass('open');
            }
        });

        setTimeout(function () { $input.focus(); }, 100);
    }

    /* ═══ SAVE / LOAD ═════════════════════════════════════════ */

    function bindSaveEvents() {
        $('#mh-um-save').on('click', saveProject);
        $('#mh-um-reload').on('click', function () {
            if (state.dirty && !confirm('Ungespeicherte Änderungen verwerfen?')) return;
            selectProject(state.activeProject.id);
        });
    }

    function saveProject() {
        if (state.saving || !state.activeProject) return;
        state.saving = true;
        $('#mh-um-save-status').text('Speichern…');
        $('#mh-um-save').prop('disabled', true);

        var selectedCats = [];
        $('#mh-um-cats .mh-um-cat-tag.selected').each(function () {
            selectedCats.push(parseInt($(this).data('cat-id'), 10));
        });

        var data = {
            title: $('#mh-um-title').val(),
            stl_title: $('#mh-um-stl-title').val(),
            stl_desc: $('#mh-um-desc').val(),
            status: $('#mh-um-status').val(),
            show_in_slider: $('#mh-um-slider').is(':checked'),
            hero_grid: $('#mh-um-hero').is(':checked'),
            categories: selectedCats,
            pins: state.pins.map(function (p) {
                return { x: p.x, y: p.y, product_id: p.product_id, image_index: p.image_index || 0 };
            }),
            buttons: [
                { text: $('#mh-um-btn1-text').val(), url: $('#mh-um-btn1-url').val() },
                { text: $('#mh-um-btn2-text').val(), url: $('#mh-um-btn2-url').val() },
            ],
        };

        if (state.activeProject.image_id) {
            data.image_id = state.activeProject.image_id;
        }

        api('projects/' + state.activeProject.id, { method: 'PUT', data: data })
            .done(function (resp) {
                state.saving = false;
                state.dirty = false;
                state.activeProject = resp;
                state.pins = resp.pins || [];
                $('#mh-um-save-status').text('✅ Gespeichert');
                $('#mh-um-save').prop('disabled', false);
                toast('Projekt gespeichert', 'success');

                // Refresh sidebar
                loadProjects().then(function () { renderSidebar(); });
            })
            .fail(function (xhr) {
                state.saving = false;
                var msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Fehler beim Speichern';
                $('#mh-um-save-status').text('❌ Fehler');
                $('#mh-um-save').prop('disabled', false);
                toast(msg, 'error');
            });
    }

    /* ═══ PROJECT ACTIONS ═════════════════════════════════════ */

    function selectProject(id) {
        var proj = state.projects.find(function (p) { return p.id === id; });
        if (!proj) return;

        // Load fresh from API
        api('projects/' + id).then(function (resp) {
            state.activeProject = resp;
            state.pins = resp.pins || [];
            state.dirty = false;
            renderSidebar();
            renderMain();
        });
    }

    function createNewProject() {
        if (state.dirty && !confirm('Ungespeicherte Änderungen verwerfen?')) return;

        api('projects', { method: 'POST', data: { title: 'Neues Kundenprojekt', status: 'draft' } })
            .done(function (resp) {
                toast('Neues Projekt erstellt', 'success');
                loadProjects().then(function () {
                    state.activeProject = resp;
                    state.pins = [];
                    state.dirty = false;
                    render();
                });
            })
            .fail(function () {
                toast('Fehler beim Erstellen', 'error');
            });
    }

    /* ═══ HELPERS ═════════════════════════════════════════════ */

    function markDirty() {
        state.dirty = true;
        var $status = $('#mh-um-save-status');
        if ($status.length) $status.text('● Ungespeicherte Änderungen');
    }

    function toast(msg, type) {
        var $t = $('<div class="mh-um-toast ' + (type || 'success') + '">' + esc(msg) + '</div>');
        $('body').append($t);
        setTimeout(function () { $t.fadeOut(300, function () { $t.remove(); }); }, 3000);
    }

    function esc(s) { return s ? $('<span>').text(s).html() : ''; }

    function formatDate(d) {
        if (!d) return '';
        var date = new Date(d);
        return date.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' });
    }

})(jQuery);
