/* v3.1: Bundle cart display is handled entirely by inline footer script.
   This file is kept for backward compat — fee row styling only. */
(function(){
    function styleFeeRows() {
        document.querySelectorAll('tr.fee th, tr.fee td, .fee-label').forEach(function(el) {
            if (el.dataset.mhBtStyled) return;
            var text = el.textContent || '';
            if (text.indexOf('Bundle-Rabatt') === -1) return;
            el.dataset.mhBtStyled = '1';
            el.style.color = '#1a7a42';
            el.style.fontWeight = '600';
        });
    }
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', styleFeeRows); }
    else { styleFeeRows(); }
    if (typeof jQuery !== 'undefined') { jQuery(document.body).on('updated_cart_totals updated_checkout', styleFeeRows); }
})();
