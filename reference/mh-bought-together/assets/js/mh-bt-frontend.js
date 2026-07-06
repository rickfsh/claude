(function($){
        'use strict';
        var C = window.mhBtConfig || {};

        function mhBtInitWidget(w) {
            if (w.data('mh-bt-init')) return;
            w.data('mh-bt-init', true);

            var discount = parseInt(w.data('discount'))||0;
            var minItems = parseInt(w.data('min-items'))||2;
            var noMain   = !!w.data('no-main');

            // Find WooCommerce qty input.
            var qtyInput = $('form.cart input[name="quantity"]');
            if (!qtyInput.length) qtyInput = $('input[name="quantity"]').first();

            function mainQty() { return Math.max(1, parseInt(qtyInput.val())||1); }
            function calcQty(m,o,mq) { return Math.max(1, Math.ceil(mq*m+o)); }
            function fmt(p) { var parts=p.toFixed(2).split('.'); parts[0]=parts[0].replace(/\B(?=(\d{3})+(?!\d))/g,'.'); return parts.join(',') + ' ' + C.sym; }

            // Animated Price Counter (per widget instance)
            var currentPrice = 0;
            var priceRaf = null;
            function animatePrice(el, from, to, duration) {
                if (priceRaf) cancelAnimationFrame(priceRaf);
                var start = performance.now();
                duration = duration || 350;
                (function tick(now) {
                    var t = Math.min((now - start) / duration, 1);
                    t = t < 0.5 ? 4*t*t*t : 1 - Math.pow(-2*t + 2, 3) / 2;
                    el.text(fmt(from + (to - from) * t));
                    if (t < 1) { priceRaf = requestAnimationFrame(tick); }
                })(start);
            }

            // v2.7.1: Editable qty inputs — track state
            var prevQtyMap = {};
            var lastMainQty = 0;
            var inputsCreated = false;
            var prevDisc = false;

            function createQtyInputs() {
                if (inputsCreated) return;
                inputsCreated = true;
                var inpStyle = 'color:#000!important;-webkit-text-fill-color:#000!important;background:#fff!important;font-weight:600!important;font-size:13px!important;text-align:center!important;opacity:1!important;border:none!important;border-left:1px solid #e0e0e0!important;border-right:1px solid #e0e0e0!important;border-radius:0!important;padding:0!important;width:2.4em!important;height:100%!important;margin:0!important;box-shadow:none!important;line-height:2em!important;-webkit-appearance:none!important;-moz-appearance:textfield!important;';
                w.find('.mh-bt-product__qty-calc').each(function(){
                    var el = $(this);
                    var isMain = !!el.data('is-main');
                    var cls = isMain ? 'mh-bt-qty-input mh-bt-qty-input--main' : 'mh-bt-qty-input mh-bt-qty-input--linked';
                    el.html(
                        '<div class="mh-bt-qty-stepper">' +
                            '<button type="button" class="mh-bt-qty-btn mh-bt-qty-minus" aria-label="' + C.i18n.pcs + ' -">−</button>' +
                            '<input type="number" class="' + cls + '" value="1" min="1" step="1" style="' + inpStyle + '">' +
                            '<button type="button" class="mh-bt-qty-btn mh-bt-qty-plus" aria-label="' + C.i18n.pcs + ' +">+</button>' +
                        '</div>'
                    );
                });
            }

            function update() {
                var mq = mainQty(), total = 0, cnt = 0, sumRegular = 0, totalQty = 0;
                var prevPrice = currentPrice;
                var hasMultiplier = false;
                var mainChanged = (mq !== lastMainQty);
                lastMainQty = mq;

                // Ensure inputs exist.
                createQtyInputs();

                // Main product (only in primary box).
                if (!noMain) {
                    var mainCb = w.find('.mh-bt-product--main .mh-bt-checkbox');
                    var mp = parseFloat(mainCb.data('price'))||0;
                    var mrp = parseFloat(mainCb.data('regular-price'))||mp;
                    total += mp * mq;
                    sumRegular += mrp * mq;
                    cnt++;
                    totalQty += mq;
                    var mainQtyEl = w.find('.mh-bt-product--main .mh-bt-product__qty-calc');
                    var mainInput = mainQtyEl.find('.mh-bt-qty-input');
                    var prevMainQ = prevQtyMap['main']||0;
                    mainInput.val(mq);
                    // v2.7 #9: Qty-pop animation
                    if (prevMainQ && prevMainQ !== mq) {
                        mainQtyEl.removeClass('mh-bt-qty-pop');
                        void mainQtyEl[0].offsetWidth;
                        mainQtyEl.addClass('mh-bt-qty-pop');
                    }
                    prevQtyMap['main'] = mq;
                }

                // Linked products.
                w.find('.mh-bt-checkbox--linked').each(function(){
                    var cb = $(this);
                    var pid = cb.data('product-id');
                    var mRaw = cb.data('multiplier');
                    var m = (mRaw === '' || mRaw === undefined || mRaw === null) ? 1 : parseFloat(mRaw);
                    if (isNaN(m)) m = 1;
                    var o = parseInt(cb.data('offset'))||0;
                    var formulaQ = noMain ? 1 : calcQty(m, o, mq);
                    var p = parseFloat(cb.data('price'))||0;
                    var rp = parseFloat(cb.data('regular-price'))||p;
                    if (m !== 1 || o !== 0) hasMultiplier = true;

                    var qtyEl = cb.closest('.mh-bt-product').find('.mh-bt-product__qty-calc');
                    var input = qtyEl.find('.mh-bt-qty-input');
                    var prevQ = prevQtyMap[pid]||0;

                    // Set input value: recalculate from formula when main qty changes, otherwise keep user edit
                    if (mainChanged || prevQ === 0) {
                        input.val(formulaQ);
                    }

                    // Read actual value from input (user may have edited)
                    var actualQ = Math.max(1, parseInt(input.val())||1);
                    cb.data('calc-qty', actualQ);

                    // v2.7 #9: Qty-pop animation (only when formula changes)
                    if (prevQ && prevQ !== actualQ) {
                        qtyEl.removeClass('mh-bt-qty-pop');
                        void qtyEl[0].offsetWidth;
                        qtyEl.addClass('mh-bt-qty-pop');
                        // v2.9 A2: Highlight entire product row on qty-sync change
                        if (mainChanged) {
                            var hlProd = cb.closest('.mh-bt-product');
                            hlProd.removeClass('mh-bt-qty-highlight');
                            void hlProd[0].offsetWidth;
                            hlProd.addClass('mh-bt-qty-highlight');
                        }
                    }
                    prevQtyMap[pid] = actualQ;

                    // v3.1: Qty-Explain — only show custom label from admin, no auto text
                    var explainEl = cb.closest('.mh-bt-product').find('.mh-bt-product__qty-explain');
                    var customLabel = cb.data('qty-label') || '';
                    if (customLabel) {
                        var explainSvg = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 10 4 15 9 20"/><path d="M20 4v7a4 4 0 0 1-4 4H4"/></svg>';
                        explainEl.html(explainSvg + '<span>' + customLabel + '</span>').addClass('mh-bt-qty-explain--visible');
                    } else {
                        explainEl.removeClass('mh-bt-qty-explain--visible');
                    }

                    if (cb.is(':checked')) { total += p*actualQ; sumRegular += rp*actualQ; cnt++; totalQty += actualQ; }

                    // v2.6 #1+#6: Unchecked dimming on product + preceding separator
                    var prodEl = cb.closest('.mh-bt-product');
                    var prevSep = prodEl.prev('.mh-bt-plus-separator');
                    if (cb.is(':checked')) {
                        prodEl.removeClass('mh-bt-unchecked').addClass('mh-bt-checked');
                        prevSep.removeClass('mh-bt-dimmed');
                    } else {
                        prodEl.addClass('mh-bt-unchecked').removeClass('mh-bt-checked');
                        prevSep.addClass('mh-bt-dimmed');
                    }
                });

                // v2.7: Show/hide qty-sync hint (only relevant when multipliers exist)
                var hintEl = w.find('.mh-bt-qty-sync-hint');
                if (hasMultiplier && !noMain) { hintEl.show(); } else { hintEl.hide(); }

                var disc = discount>0 && cnt>=minItems;
                var fin = disc ? total*(1-discount/100) : total;

                // v3.0: Milestone progress bar
                var progressEl = w.find('.mh-bt-bundle-progress');
                if (discount > 0 && progressEl.length) {
                    progressEl.show();
                    var pctFill = Math.min(100, Math.round((cnt / minItems) * 100));
                    progressEl.find('.mh-bt-bundle-progress__fill').css('width', pctFill + '%');
                    var textEl = progressEl.find('.mh-bt-bundle-progress__text');
                    var fillEl = progressEl.find('.mh-bt-bundle-progress__fill');
                    var msEl = progressEl.find('.mh-bt-bundle-progress__milestone');
                    var badgeEl = progressEl.find('.mh-bt-bundle-progress__badge');
                    if (cnt >= minItems) {
                        textEl.text(C.i18n.bundleDone.replace('%d', discount)).addClass('mh-bt-bundle-progress--reached');
                        fillEl.addClass('mh-bt-bundle-progress--reached');
                        if (!msEl.hasClass('mh-bt-bundle-progress--reached')) {
                            msEl.addClass('mh-bt-bundle-progress--reached');
                        }
                        badgeEl.addClass('mh-bt-bundle-progress--reached').find('span').text(C.i18n.bundleDone.replace('%d', discount));
                    } else {
                        var need = minItems - cnt;
                        textEl.text(C.i18n.bundleNeed.replace('%d', need).replace('%d', discount)).removeClass('mh-bt-bundle-progress--reached');
                        fillEl.removeClass('mh-bt-bundle-progress--reached');
                        msEl.removeClass('mh-bt-bundle-progress--reached');
                        badgeEl.removeClass('mh-bt-bundle-progress--reached');
                    }
                }

                // Animated price counter — v2.9.1: green ONLY for bundle discount, not for deselecting
                var priceEl = w.find('.mh-bt-total__price');
                var priceChanged = Math.abs(currentPrice - fin) > 0.005;
                var discJustActivated = disc && !prevDisc;
                var discJustDeactivated = !disc && prevDisc;

                if (priceChanged) {
                    if (discJustActivated && total > fin) {
                        // Two-step: count up to full total, pause, then green count down to discounted price
                        animatePrice(priceEl, currentPrice, total, 250);
                        currentPrice = total;
                        setTimeout(function(){
                            priceEl.addClass('mh-bt-price--down');
                            animatePrice(priceEl, total, fin, 400);
                            currentPrice = fin;
                            setTimeout(function(){ priceEl.removeClass('mh-bt-price--down'); }, 600);
                        }, 350);
                    } else {
                        // Neutral animation — no green flash (deselecting, qty change, etc.)
                        animatePrice(priceEl, currentPrice, fin, 350);
                        currentPrice = fin;
                    }
                }
                prevDisc = disc;

                // Strikethrough original price: show when any savings exist (sale or bundle)
                var origEl = w.find('.mh-bt-total__original');
                var hasSavings = sumRegular - fin > 0.01;
                if (hasSavings) {
                    origEl.text(fmt(sumRegular)).show();
                } else {
                    origEl.hide();
                }

                // v2.9 E2: Bundle discount — glow only, badge removed (consolidated into savings pill)
                if (disc) {
                    w.find('.mh-bt-add-all').addClass('mh-bt-add-all--bundle-glow');
                } else {
                    w.find('.mh-bt-add-all').removeClass('mh-bt-add-all--bundle-glow');
                }

                // v2.9 B1+E2: Consolidated Savings Pill — one element with bundle % + euro savings
                var totalSavings = sumRegular - fin;
                var savingsPill = w.find('.mh-bt-savings-pill');
                if (totalSavings > 0.01) {
                    var savingsText;
                    if (disc) {
                        savingsText = C.i18n.savePct.replace('%d', discount).replace('%s', fmt(totalSavings));
                    } else {
                        savingsText = C.i18n.save.replace('%s', fmt(totalSavings));
                    }
                    w.find('.mh-bt-savings-pill__text').text(savingsText);
                    savingsPill.addClass('mh-bt-savings-pill--visible');
                } else {
                    savingsPill.removeClass('mh-bt-savings-pill--visible');
                }

                // Counter badge
                var countEl = w.find('.mh-bt-heading__count');
                countEl.text(cnt + ' ' + C.i18n.products);
                countEl.addClass('mh-bt-pop');
                setTimeout(function(){ countEl.removeClass('mh-bt-pop'); }, 200);

                // v2.7 #2: Dynamic CTA button text with total pieces + price
                var btnText = w.find('.mh-bt-add-all__text');
                if (!w.data('mh-bt-success')) {
                    btnText.text(C.i18n.btnDyn.replace('%d', totalQty).replace('%s', fmt(fin)));
                }
            }

            w.on('change', '.mh-bt-checkbox--linked', update);
            if (!noMain) {
                qtyInput.on('input change', update);
                $(document).on('click', '.quantity .plus, .quantity .minus', function(){ setTimeout(update,50); });
            }
            update();

            // v2.9.5: Tracking — impression (once per widget init)
            var mainId = parseInt(w.find('.mh-bt-add-all').data('main-product'))||0;
            if (mainId && C.ajaxUrl) {
                $.post(C.ajaxUrl, { action:'mh_bt_track', type:'impression', product_id:mainId, nonce:C.nonce });
            }
            // v2.9.5: Tracking — deselection events
            w.on('change', '.mh-bt-checkbox--linked', function(){
                if (!$(this).is(':checked') && mainId && C.ajaxUrl) {
                    $.post(C.ajaxUrl, { action:'mh_bt_track', type:'deselect', product_id:parseInt($(this).data('product-id'))||0, source_id:mainId, nonce:C.nonce });
                }
            });

            // v2.7.1: Editable qty inputs — event handlers
            // Main product input → sync to WooCommerce qty input
            w.on('change', '.mh-bt-qty-input--main', function(){
                var v = Math.max(1, parseInt($(this).val())||1);
                $(this).val(v);
                qtyInput.val(v).trigger('change');
            });
            // Linked product inputs → recalculate prices from current input values
            w.on('input change', '.mh-bt-qty-input--linked', function(){
                update();
            });
            // Plus/minus buttons
            w.on('click', '.mh-bt-qty-minus, .mh-bt-qty-plus', function(e){
                e.preventDefault();
                e.stopPropagation();
                var btn = $(this);
                var input = btn.closest('.mh-bt-qty-stepper').find('.mh-bt-qty-input');
                var val = Math.max(1, parseInt(input.val())||1);
                if (btn.hasClass('mh-bt-qty-minus')) { val = Math.max(1, val - 1); }
                else { val = val + 1; }
                input.val(val).trigger('change');
            });
            // Prevent checkbox toggle when clicking inside stepper
            w.on('click', '.mh-bt-qty-stepper, .mh-bt-qty-input, .mh-bt-qty-btn', function(e){ e.stopPropagation(); });

            // v2.9.2: Image preview tooltip — appended to body with fixed positioning
            w.find('.mh-bt-product__image[data-preview-img]').each(function(){
                var imgEl = $(this);
                var src = imgEl.data('preview-img');
                var name = imgEl.data('preview-name') || '';
                if (!src) return;
                var tip = $('<span class="mh-bt-preview-tooltip"><img src="'+src+'" alt="" /><span class="mh-bt-preview-tooltip__name">'+$('<span>').text(name).html()+'</span></span>');
                $('body').append(tip);
                var showTimer;
                function positionTip() {
                    var r = imgEl[0].getBoundingClientRect();
                    var tipW = 134, tipH = 150;
                    var left = r.right + 10;
                    if (left + tipW > window.innerWidth) { left = r.left - tipW - 10; }
                    var top = r.top + r.height / 2 - tipH / 2;
                    if (top < 8) top = 8;
                    if (top + tipH > window.innerHeight - 8) top = window.innerHeight - tipH - 8;
                    tip.css({ left: left + 'px', top: top + 'px' });
                }
                imgEl.on('mouseenter', function(){ positionTip(); showTimer = setTimeout(function(){ tip.addClass('mh-bt-preview--visible'); }, 350); });
                imgEl.on('mouseleave', function(){ clearTimeout(showTimer); tip.removeClass('mh-bt-preview--visible'); });
            });

            // v2.9.2: Show-more toggle for collapsible product lists
            w.find('.mh-bt-show-more').on('click', function(){
                var btn = $(this);
                var collapsible = btn.prev('.mh-bt-collapsible');
                if (collapsible.is(':visible')) {
                    collapsible.slideUp(250);
                    btn.removeClass('mh-bt-show-more--open');
                    var origCount = collapsible.find('.mh-bt-product').length;
                    btn.find('.mh-bt-show-more__text').text(origCount + ' weitere Produkte anzeigen');
                } else {
                    collapsible.slideDown(250);
                    btn.addClass('mh-bt-show-more--open');
                    btn.find('.mh-bt-show-more__text').text(C.i18n.showLess || 'Weniger anzeigen');
                }
            });

            // Add to cart with success animation.
            w.on('click', '.mh-bt-add-all', function(e){
                e.preventDefault();
                var btn=$(this), notice=w.find('.mh-bt-notice');
                var btnText = btn.find('.mh-bt-add-all__text');
                var iconCart = btn.find('.mh-bt-add-all__icon-cart');
                var iconCheck = btn.find('.mh-bt-add-all__icon-check');
                var mq = mainQty();
                var items = [];

                // Primary box: add main product first.
                var mainId = parseInt(btn.data('main-product'))||0;
                if (!noMain && mainId) {
                    items.push({id: mainId, qty: mq});
                }

                w.find('.mh-bt-checkbox--linked:checked').each(function(){
                    items.push({ id: parseInt($(this).val()), qty: parseInt($(this).data('calc-qty'))||1 });
                });
                if (items.length < 1) { notice.attr('class','mh-bt-notice mh-bt-notice--error').text(C.i18n.noItems).show(); return; }

                btn.prop('disabled',true);
                btnText.text(C.i18n.adding);
                iconCart.css('transform','rotate(15deg)');
                notice.hide();
                $.post(C.ajaxUrl, { action:'mh_bt_add_to_cart', nonce:C.nonce, items:items, source_product_id:mainId }, function(r){
                    if (r&&r.success) {
                        w.data('mh-bt-success', true);
                        btn.addClass('mh-bt-add-all--success').removeClass('mh-bt-add-all--bundle-glow');
                        iconCart.hide();
                        iconCheck.show();
                        btnText.text(C.i18n.btnOk);
                        notice.attr('class','mh-bt-notice mh-bt-notice--success')
                              .html(C.i18n.added+' <a href="'+C.cartUrl+'">'+C.i18n.cart+'</a>').show();
                        var fragments = (r.data && r.data.fragments) ? r.data.fragments : false;
                        var cartHash  = (r.data && r.data.cart_hash) ? r.data.cart_hash : '';
                        if (fragments) {
                            $.each(fragments, function(key, value) {
                                $(key).replaceWith(value);
                            });
                        }
                        $(document.body).trigger('added_to_cart', [fragments, cartHash, null]);
                        $(document.body).trigger('wc_fragment_refresh');
                        setTimeout(function(){
                            w.data('mh-bt-success', false);
                            btn.removeClass('mh-bt-add-all--success');
                            iconCheck.hide();
                            iconCart.show().css('transform','');
                            btn.prop('disabled',false);
                            update();
                        }, 2500);
                    } else {
                        notice.attr('class','mh-bt-notice mh-bt-notice--error').text((r&&r.data)||C.i18n.error).show();
                        btn.prop('disabled',false);
                        iconCart.css('transform','');
                        update();
                    }
                }).fail(function(){
                    notice.attr('class','mh-bt-notice mh-bt-notice--error').text(C.i18n.error).show();
                    btn.prop('disabled',false);
                    iconCart.css('transform','');
                    update();
                });
            });
        }

        function mhBtInit() {
            $('.mh-bt-widget').each(function(){ mhBtInitWidget($(this)); });
        }
        // Expose globally so external plugins (e.g. mh-spielturm-vergleich) can
        // re-initialize the widget after AJAX-refreshing the BT HTML.
        window.mhBtInit = mhBtInit;
        window.mhBtReinit = function() {
            // Clear dedup flags so widgets can be re-initialized.
            $('.mh-bt-widget').removeData('mh-bt-init');
            mhBtInit();
        };
        function mhBtLazyInit() {
            if ('IntersectionObserver' in window) {
                var observer = new IntersectionObserver(function(entries) {
                    entries.forEach(function(entry) {
                        if (entry.isIntersecting) {
                            mhBtInitWidget($(entry.target));
                            observer.unobserve(entry.target);
                        }
                    });
                }, { rootMargin: '200px 0px' });
                $('.mh-bt-widget').each(function(){ observer.observe(this); });
            } else {
                mhBtInit();
            }
        }
        $(document).ready(mhBtLazyInit);
        $(document).on('oxygen-ajax-loaded', mhBtInit);

    })(jQuery);

/* ==========================================================================
   v3.2: "Im Lieferumfang enthalten" — Collapsible Toggle
   ========================================================================== */
(function($){
    'use strict';
    var C = window.mhBtConfig || {};

    function mhIpInit() {
        $('.mh-ip-show-more').each(function(){
            var btn = $(this);
            if (btn.data('mh-ip-init')) return;
            btn.data('mh-ip-init', true);

            btn.on('click', function(e){
                e.preventDefault();
                var collapsible = btn.prev('.mh-ip-collapsible');
                if (!collapsible.hasClass('mh-ip-collapsible--hidden')) {
                    collapsible.addClass('mh-ip-collapsible--hidden');
                    btn.removeClass('mh-ip-show-more--open');
                    btn.attr('aria-expanded', 'false');
                    var origCount = collapsible.find('.mh-ip-product').length;
                    btn.find('.mh-ip-show-more__text').text(origCount + ' weitere Produkte anzeigen');
                } else {
                    collapsible.removeClass('mh-ip-collapsible--hidden');
                    btn.addClass('mh-ip-show-more--open');
                    btn.attr('aria-expanded', 'true');
                    btn.find('.mh-ip-show-more__text').text((C.i18n && C.i18n.showLess) || 'Weniger anzeigen');
                }
            });
        });
    }

    $(document).ready(mhIpInit);
    $(document).on('oxygen-ajax-loaded', mhIpInit);
})(jQuery);