/**
 * MH Birthday Sale – Warenkorb-Styling.
 *
 * Markiert die Geburtstagsrabatt-/-bonus-Zeile in der Warenkorb-/Checkout-
 * Summe per Label-Text, damit CSS sie hervorheben kann. Funktioniert in
 * klassischem Warenkorb, Cart-/Checkout-Block und Builder-Templates (Oxygen),
 * weil rein DOM-basiert. Kein Shortcode noetig.
 */
(function () {
	'use strict';

	var CFG = window.MHBS_CART || {};
	var LABELS = ( CFG.labels && CFG.labels.length ) ? CFG.labels : [ 'Geburtstagsrabatt', 'Geburtstagsbonus' ];

	function startsWithLabel( text ) {
		text = ( text || '' ).replace( /\s+/g, ' ' ).trim();
		for ( var i = 0; i < LABELS.length; i++ ) {
			if ( text.indexOf( LABELS[ i ] ) === 0 ) {
				return true;
			}
		}
		return false;
	}

	function apply() {
		var nodes = document.querySelectorAll( 'th, td, span, div, p, li, dt, dd' );
		for ( var i = 0; i < nodes.length; i++ ) {
			var el = nodes[ i ];
			if ( ! startsWithLabel( el.textContent ) ) {
				continue;
			}
			// Nur das engste passende Element nehmen (kein Kind matcht denselben Anfang).
			var childMatch = false;
			for ( var c = 0; c < el.children.length; c++ ) {
				if ( startsWithLabel( el.children[ c ].textContent ) ) {
					childMatch = true;
					break;
				}
			}
			if ( childMatch ) {
				continue;
			}
			el.classList.add( 'mhbs-discount-label' );
			var row = el.closest ? el.closest( 'tr, .wc-block-components-totals-item, li, .cart-line, .order-total, .fee' ) : null;
			if ( row ) {
				row.classList.add( 'mhbs-discount-line' );
			}
		}
	}

	var scheduled = false;
	function schedule() {
		if ( scheduled ) {
			return;
		}
		scheduled = true;
		var run = function () {
			scheduled = false;
			apply();
		};
		if ( window.requestAnimationFrame ) {
			window.requestAnimationFrame( run );
		} else {
			window.setTimeout( run, 16 );
		}
	}

	function init() {
		apply();

		// Klassischer Warenkorb / Checkout (AJAX, jQuery-Events).
		if ( window.jQuery ) {
			window.jQuery( document.body ).on(
				'updated_cart_totals updated_checkout updated_wc_div wc_fragments_refreshed',
				schedule
			);
		}

		// Cart-/Checkout-Block & andere Re-Renders.
		if ( window.MutationObserver ) {
			var obs = new MutationObserver( schedule );
			obs.observe( document.body, { childList: true, subtree: true } );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}());
