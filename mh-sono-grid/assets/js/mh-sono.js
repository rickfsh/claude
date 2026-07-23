/* ============================================================
   MH SONO Grid v1.0.0 — Frontend (ES5, kein Framework)
   Client-seitige Filter (Typ/Höhe/Oberfläche), Oberflächen-
   Umschalter auf den Karten, Sticky-Filterleiste + Indicator,
   URL-Hash-Sync für Deep-Links (#sono-h180, #sono-typ-…).
   ============================================================ */
(function () {
  'use strict';

  function initRoot(root) {
    if (root.getAttribute('data-mhsono-init') === '1') { return; }
    root.setAttribute('data-mhsono-init', '1');

    var state = { type: '', height: '', finish: '' };

    /* ---------- Oberflächen-Umschalter (eine Karte) ---------- */

    function switchCardFinish(card, finish, href) {
      var els = card.querySelectorAll('[data-mhsono-var]');
      var k, el;
      for (k = 0; k < els.length; k++) {
        el = els[k];
        if (el.getAttribute('data-mhsono-var') === finish) {
          if (el.className.indexOf('is-active') === -1) { el.className += ' is-active'; }
        } else {
          el.className = el.className.replace(' is-active', '');
        }
      }
      var dots = card.getElementsByClassName('mhsono-dot');
      var activeDot = null;
      for (k = 0; k < dots.length; k++) {
        if (dots[k].getAttribute('data-mhsono-var') === finish) {
          if (dots[k].className.indexOf('is-active') === -1) { dots[k].className += ' is-active'; }
          dots[k].setAttribute('aria-pressed', 'true');
          activeDot = dots[k];
        } else {
          dots[k].className = dots[k].className.replace(' is-active', '');
          dots[k].setAttribute('aria-pressed', 'false');
        }
      }
      if (!href && activeDot) { href = activeDot.getAttribute('data-mhsono-href'); }
      if (href) {
        var links = card.getElementsByClassName('mhsono-jslink');
        for (k = 0; k < links.length; k++) { links[k].setAttribute('href', href); }
      }
      var name = card.querySelector('[data-mhsono-finishname]');
      if (name && activeDot) {
        var sr = activeDot.getElementsByClassName('mhsono-sr')[0];
        if (sr) { name.textContent = sr.textContent; }
      }
    }

    /* ---------- Filter anwenden ---------- */

    function cardMatches(card) {
      if (state.type && card.getAttribute('data-mhsono-type') !== state.type) { return false; }
      if (state.height && card.getAttribute('data-mhsono-height') !== state.height) { return false; }
      if (state.finish) {
        var finishes = ' ' + (card.getAttribute('data-mhsono-finishes') || '') + ' ';
        if (finishes.indexOf(' ' + state.finish + ' ') === -1) { return false; }
      }
      return true;
    }

    function applyFilters() {
      var cards = root.querySelectorAll('[data-mhsono-card]');
      var i, visibleTotal = 0;
      for (i = 0; i < cards.length; i++) {
        var show = cardMatches(cards[i]);
        toggleClass(cards[i], 'is-hidden', !show);
        if (show) {
          visibleTotal++;
          /* Oberflächen-Filter: Karte auf dieses Finish umschalten */
          if (state.finish) { switchCardFinish(cards[i], state.finish, null); }
        }
      }
      var secs = root.querySelectorAll('[data-mhsono-sec]');
      for (i = 0; i < secs.length; i++) {
        var visible = secs[i].querySelectorAll('[data-mhsono-card]:not(.is-hidden)').length;
        toggleClass(secs[i], 'is-hidden', visible === 0);
      }
      var empty = root.querySelector('[data-mhsono-empty]');
      if (empty) {
        if (visibleTotal === 0) { empty.removeAttribute('hidden'); }
        else { empty.setAttribute('hidden', 'hidden'); }
      }
      updatePills();
      moveIndicator(true);
      syncHash();
    }

    function updatePills() {
      var pills = root.querySelectorAll('[data-mhsono-filter]');
      var i, pill, dim, val, active;
      for (i = 0; i < pills.length; i++) {
        pill = pills[i];
        dim = pill.getAttribute('data-mhsono-filter');
        val = pill.getAttribute('data-mhsono-value');
        active = (state[dim] || '') === val;
        toggleClass(pill, 'is-active', active);
        if (pill.getAttribute('role') === 'tab') {
          pill.setAttribute('aria-selected', active ? 'true' : 'false');
        } else {
          pill.setAttribute('aria-pressed', active ? 'true' : 'false');
        }
      }
    }

    function resetFilters() {
      state.type = '';
      state.height = '';
      state.finish = '';
      applyFilters();
    }

    function toggleClass(el, cls, on) {
      var has = el.className.indexOf(cls) !== -1;
      if (on && !has) { el.className += ' ' + cls; }
      if (!on && has) {
        el.className = el.className.replace(new RegExp('(^|\\s)' + cls + '(\\s|$)', 'g'), ' ').replace(/\s+/g, ' ').replace(/^\s|\s$/g, '');
      }
    }

    /* ---------- URL-Hash (Deep-Links, kompatibel zu #sono-h180) ---------- */

    function readHash() {
      var h = window.location.hash || '';
      var m = h.match(/^#sono-h(\d{2,3})$/);
      if (m) { state.height = m[1]; return; }
      m = h.match(/^#sono-typ-([a-z0-9-]+)$/);
      if (m) { state.type = m[1]; }
    }

    function syncHash() {
      if (!window.history || !window.history.replaceState) { return; }
      var hash = '';
      if (state.height && !state.type && !state.finish) { hash = '#sono-h' + state.height; }
      else if (state.type && state.type !== '_default' && !state.height && !state.finish) { hash = '#sono-typ-' + state.type; }
      try {
        var base = window.location.pathname + window.location.search;
        window.history.replaceState(window.history.state, '', base + hash);
      } catch (e) { /* rein kosmetisch */ }
    }

    /* ---------- Sticky-Leiste + gleitender Indicator ---------- */

    var filters = root.querySelector('[data-mhsono-filters]');
    var indicator = null;

    function initSticky() {
      if (!filters || !('IntersectionObserver' in window)) { return; }
      var sentinel = document.createElement('div');
      sentinel.style.cssText = 'height:1px;margin-bottom:-1px;pointer-events:none;';
      filters.parentNode.insertBefore(sentinel, filters);
      var obs = new IntersectionObserver(function (entries) {
        var i;
        for (i = 0; i < entries.length; i++) {
          toggleClass(filters, 'is-stuck', !entries[i].isIntersecting);
        }
      }, { threshold: 0 });
      obs.observe(sentinel);
    }

    function initIndicator() {
      if (!filters) { return; }
      var typeGroup = filters.querySelector('.mhsono-fgroup--type');
      if (!typeGroup) { return; }
      indicator = document.createElement('div');
      indicator.className = 'mhsono-findicator';
      typeGroup.appendChild(indicator);
      typeGroup.className += ' has-indicator';
      moveIndicator(false);
      window.addEventListener('resize', function () { moveIndicator(false); });
    }

    function moveIndicator(animate) {
      if (!indicator) { return; }
      var typeGroup = indicator.parentNode;
      var active = typeGroup.querySelector('.mhsono-pill.is-active');
      if (!active) { indicator.style.opacity = '0'; return; }
      var gRect = typeGroup.getBoundingClientRect();
      var bRect = active.getBoundingClientRect();
      if (animate === false) { indicator.style.transition = 'none'; }
      indicator.style.left = (bRect.left - gRect.left + typeGroup.scrollLeft) + 'px';
      indicator.style.top = (bRect.top - gRect.top) + 'px';
      indicator.style.width = bRect.width + 'px';
      indicator.style.height = bRect.height + 'px';
      indicator.style.opacity = '1';
      if (animate === false) {
        void indicator.offsetHeight;
        indicator.style.transition = '';
      }
    }

    /* ---------- Event-Delegation ---------- */

    root.addEventListener('click', function (e) {
      var t = e.target;

      /* Oberflächen-Dot auf einer Karte */
      var dot = closest(t, '.mhsono-dot');
      if (dot && root.contains(dot)) {
        var card = closest(dot, '[data-mhsono-card]');
        if (card) {
          switchCardFinish(card, dot.getAttribute('data-mhsono-var'), dot.getAttribute('data-mhsono-href'));
        }
        return;
      }

      /* Filter-Pill */
      var pill = closest(t, '[data-mhsono-filter]');
      if (pill) {
        var dim = pill.getAttribute('data-mhsono-filter');
        var val = pill.getAttribute('data-mhsono-value');
        state[dim] = (state[dim] === val) ? '' : val;
        applyFilters();
        return;
      }

      /* Reset im Leerzustand */
      if (closest(t, '[data-mhsono-reset]')) {
        resetFilters();
      }
    });

    function closest(el, selector) {
      while (el && el !== document) {
        if (matches(el, selector)) { return el; }
        el = el.parentNode;
      }
      return null;
    }

    function matches(el, selector) {
      if (!el || el.nodeType !== 1) { return false; }
      var fn = el.matches || el.msMatchesSelector || el.webkitMatchesSelector;
      return fn ? fn.call(el, selector) : false;
    }

    /* ---------- Init ---------- */

    initSticky();
    initIndicator();
    readHash();
    if (state.type || state.height || state.finish) {
      applyFilters();
    } else {
      updatePills();
      moveIndicator(false);
    }
  }

  function initAll() {
    var roots = document.querySelectorAll('[data-mhsono]');
    var i;
    for (i = 0; i < roots.length; i++) { initRoot(roots[i]); }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAll);
  } else {
    initAll();
  }
})();
