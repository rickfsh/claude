<?php
/**
 * Plugin Name: Mega-Holz Konfigurator Lazy Loader
 * Description: Lazy-Loading für den 3D-Konfigurator – kompakter Canvas, WooCommerce-Preis als Platzhalter, optionaler Startscreen.
 * Version: 7.2.0
 * Author: Mega-Holz
 *
 * WAS ES MACHT:
 *   1. Entfernt BabylonJS Scripts aus dem initialen HTML
 *   2. Zeigt kompakten Placeholder statt dem 3D-Canvas (optional abschaltbar)
 *   3. Scripts laden bei Klick auf "Konfigurator starten" (oder sofort wenn Splash aus)
 *   4. Nach dem Laden: Canvas-Höhe per JS begrenzt (in beiden Modi)
 *   5. Zeigt WooCommerce-Produktpreis statt "0,00 €"
 *   6. "Konfigurator schließen" Button
 *   7. Einstellung unter Einstellungen → Konfigurator
 *
 * INSTALLATION:
 *   Dashboard → Plugins → Installieren → Plugin hochladen → ZIP → Aktivieren
 *   Danach: WP Rocket Cache leeren!
 *
 * DEBUG:
 *   ?mh_lazy_debug=1 an die URL anhängen
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ==========================================================================
   KONFIGURATION — hier anpassen falls nötig
   ========================================================================== */

define( 'MH_KONFIG_SCRIPT_BLOCK_ID', 'code_block-59-242070' );
define( 'MH_KONFIG_CANVAS_ID', 'planer' );
define( 'MH_KONFIG_PREVIEW_IMG', 'https://mega-holz.de/wp-content/uploads/2026/05/930149_Product.webp' );
define( 'MH_KONFIG_PRICE_DISPLAY_ID', 'shopping-basket-full-price-display' );
define( 'MH_KONFIG_CANVAS_MAX_HEIGHT', 1000 ); // px — Canvas-Höhe nach dem Laden

/* ==========================================================================
   ADMIN SETTINGS
   ========================================================================== */

final class MH_Lazy_Konfigurator_Settings {

    public static function register(): void {
        add_action( 'admin_menu', [ __CLASS__, 'add_menu' ] );
        add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
    }

    public static function add_menu(): void {
        add_options_page(
            'Konfigurator Lazy Loader',
            'Konfigurator',
            'manage_options',
            'mh-lazy-konfigurator',
            [ __CLASS__, 'render_page' ]
        );
    }

    public static function register_settings(): void {
        register_setting( 'mh_lazy_konfig_group', 'mh_konfig_show_splash', [
            'type'              => 'string',
            'default'           => '1',
            'sanitize_callback' => function ( $v ) { return $v === '0' ? '0' : '1'; },
        ] );
    }

    public static function render_page(): void {
        $show_splash = get_option( 'mh_konfig_show_splash', '1' );
        ?>
        <div class="wrap">
            <h1>Konfigurator Lazy Loader — Einstellungen</h1>
            <form method="post" action="options.php">
                <?php settings_fields( 'mh_lazy_konfig_group' ); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">Startscreen anzeigen</th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="radio" name="mh_konfig_show_splash" value="1" <?php checked( $show_splash, '1' ); ?>>
                                    <strong>Ja</strong> — „Konfigurator starten"-Button wird angezeigt (Lazy Loading)
                                </label><br>
                                <label>
                                    <input type="radio" name="mh_konfig_show_splash" value="0" <?php checked( $show_splash, '0' ); ?>>
                                    <strong>Nein</strong> — Konfigurator lädt sofort (Größenbegrenzung bleibt aktiv)
                                </label>
                            </fieldset>
                            <p class="description">
                                Bei „Nein" wird kein Platzhalter-Screen angezeigt. Die BabylonJS-Scripts laden automatisch beim Seitenaufruf.<br>
                                Die Größenbegrenzung (max. <?php echo MH_KONFIG_CANVAS_MAX_HEIGHT; ?>px) bleibt in beiden Modi aktiv.
                            </p>
                        </td>
                    </tr>
                </table>
                <?php submit_button( 'Einstellungen speichern' ); ?>
            </form>
        </div>
        <?php
    }

    public static function show_splash(): bool {
        return get_option( 'mh_konfig_show_splash', '1' ) === '1';
    }
}

MH_Lazy_Konfigurator_Settings::register();

/* ==========================================================================
   CORE
   ========================================================================== */

final class MH_Lazy_Konfigurator_V7 {

    private string $wc_price = '';
    private string $wc_regular_price = '';
    private bool   $show_splash = true;

    public function init(): void {
        if ( is_admin() || $this->is_oxygen_editor() ) {
            return;
        }

        $this->show_splash = MH_Lazy_Konfigurator_Settings::show_splash();

        // WooCommerce-Preis frühzeitig erfassen
        add_action( 'wp', [ $this, 'capture_wc_price' ] );

        if ( defined( 'WP_ROCKET_VERSION' ) ) {
            add_filter( 'rocket_buffer', [ $this, 'process_buffer' ], 1 );
        } else {
            add_action( 'template_redirect', function () {
                ob_start( [ $this, 'process_buffer' ] );
            }, 1 );
        }
    }

    /**
     * WooCommerce-Produktpreis holen bevor der Buffer verarbeitet wird.
     */
    public function capture_wc_price(): void {
        if ( ! function_exists( 'wc_get_product' ) ) {
            return;
        }

        $product_id = get_queried_object_id();
        if ( ! $product_id ) {
            return;
        }

        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            return;
        }

        // Preis INKL. MwSt (Brutto) holen
        $price_incl_tax = wc_get_price_including_tax( $product );
        if ( $price_incl_tax && floatval( $price_incl_tax ) > 0 ) {
            $this->wc_price = strip_tags( wc_price( $price_incl_tax ) );
        }

        // Originalpreis (UVP) wenn Angebot aktiv
        if ( $product->is_on_sale() ) {
            $regular = $product->get_regular_price();
            if ( $regular && floatval( $regular ) > 0 ) {
                $regular_incl_tax = wc_get_price_including_tax( $product, [ 'price' => $regular ] );
                $this->wc_regular_price = strip_tags( wc_price( $regular_incl_tax ) );
            }
        }
    }

    public function process_buffer( string $html ): string {
        if ( ! $this->page_has_configurator( $html ) ) {
            return $html;
        }

        $debug    = isset( $_GET['mh_lazy_debug'] ); // phpcs:ignore
        $block_id = MH_KONFIG_SCRIPT_BLOCK_ID;
        $block_re = '/<div\s+id="' . preg_quote( $block_id, '/' ) . '"[^>]*>(.*?)<\/div>/s';

        if ( ! preg_match( $block_re, $html, $m ) ) {
            if ( $debug ) {
                $html = str_replace( '</head>', '<script>console.warn("[MH Lazy] Code Block nicht gefunden");</script></head>', $html );
            }
            return $html;
        }

        // Script-URLs extrahieren
        $urls = [];
        preg_match_all( '/data-rocket-src=["\']([^"\']+)["\']/i', $m[1], $r );
        if ( ! empty( $r[1] ) ) {
            $urls = $r[1];
        }
        preg_match_all( '/<script[^>]+\bsrc=["\']([^"\']+)["\'][^>]*>/i', $m[1], $s );
        if ( ! empty( $s[1] ) ) {
            foreach ( $s[1] as $u ) {
                if ( ! in_array( $u, $urls, true ) ) {
                    $urls[] = $u;
                }
            }
        }

        if ( empty( $urls ) ) {
            return $html;
        }

        // Block ersetzen
        $json = wp_json_encode( $urls );
        $html = str_replace( $m[0],
            '<div id="' . esc_attr( $block_id ) . '" class="ct-code-block" style="display:none"><!-- MH Lazy v7.0: ' . count( $urls ) . ' scripts --></div>'
            . '<script>window.mhLazyScripts=' . $json . ';</script>',
            $html
        );

        $cid  = MH_KONFIG_CANVAS_ID;
        $pid  = MH_KONFIG_PRICE_DISPLAY_ID;
        $img  = MH_KONFIG_PREVIEW_IMG;
        $maxH = MH_KONFIG_CANVAS_MAX_HEIGHT;

        $splash = $this->show_splash;

        // CSS einfügen
        $html = str_replace( '</head>', $this->css( $cid, $maxH, $splash ) . '</head>', $html );

        // Placeholder VOR Canvas (nur wenn Splash aktiv)
        if ( $splash ) {
            $html = str_replace(
                '<div id="' . $cid . '"',
                $this->placeholder( $img ) . '<div id="' . $cid . '"',
                $html
            );
        }

        // JS vor </body> — WooCommerce-Preis mitgeben
        $html = str_replace( '</body>', $this->js( $cid, $pid, $maxH, $this->wc_price, $this->wc_regular_price, $debug, $splash ) . '</body>', $html );

        return $html;
    }

    /* ──────────────────────────────────────────
       CSS
       ────────────────────────────────────────── */
    private function css( string $cid, int $maxH, bool $splash ): string {
        // Wenn kein Splash: Canvas sofort sichtbar, aber Größe begrenzt
        $no_splash_css = '';
        if ( ! $splash ) {
            $no_splash_css = <<<NOSPLASH

/* No-Splash-Modus: Canvas sofort sichtbar + Größenbegrenzung */
#{$cid} {
    display:flex !important;
    max-height:{$maxH}px !important;
    height:{$maxH}px !important;
    overflow:hidden !important;
    margin-top:12px !important;
}
#div_block-73-242070 { display:flex !important }

/* Tablet */
@media (max-width: 1024px) {
    #{$cid} {
        max-height:500px !important;
        height:500px !important;
    }
}
/* Mobile */
@media (max-width: 767px) {
    #{$cid} {
        max-height:400px !important;
        height:400px !important;
    }
}
/* Kleine Phones */
@media (max-width: 480px) {
    #{$cid} {
        max-height:320px !important;
        height:320px !important;
    }
}
NOSPLASH;
        }
        return <<<CSS
<style id="mh-lazy-konfig-css">
/* Canvas + Toolbar verstecken bis geladen */
#{$cid}, #div_block-73-242070 { display:none!important }
.mh-lazy-konfig--loaded #{$cid},
.mh-lazy-konfig--loaded #div_block-73-242070 { display:flex!important }
.mh-lazy-konfig--loaded .mh-lazy-konfig { display:none!important }

/* Größenbegrenzung nach Laden */
.mh-lazy-konfig--loaded #{$cid} {
    max-height:{$maxH}px !important;
    height:{$maxH}px !important;
    overflow:hidden !important;
    margin-top:12px !important;
}

/* ── PLACEHOLDER ── */
.mh-lazy-konfig {
    position:relative; width:100%; aspect-ratio:4/3;
    max-height:1000px; cursor:pointer; overflow:hidden;
    background:#1a1a1a;
    margin-top:12px;
}
.mh-lazy-konfig__bg { position:absolute; inset:0 }
.mh-lazy-konfig__bg img { width:100%; height:100%; object-fit:cover; opacity:.3 }
.mh-lazy-konfig__overlay {
    position:absolute; inset:0;
    background:linear-gradient(135deg,rgba(0,0,0,.5) 0%,rgba(0,0,0,.2) 100%);
}
.mh-lazy-konfig__content {
    position:absolute; inset:0;
    display:flex; flex-direction:column;
    align-items:center; justify-content:center;
    gap:10px; padding:16px; text-align:center;
}
.mh-lazy-konfig__icon svg {
    width:36px; height:36px; fill:none;
    stroke:#fff; stroke-width:1.5; stroke-linecap:round; stroke-linejoin:round;
}
.mh-lazy-konfig__title {
    color:#fff; font-size:18px; font-weight:700;
    margin:0; text-shadow:0 2px 8px rgba(0,0,0,.3);
}
.mh-lazy-konfig__subtitle { color:rgba(255,255,255,.8); font-size:13px; margin:0 }
.mh-lazy-konfig__btn {
    display:inline-flex; align-items:center; gap:8px;
    background:#FAA41A; color:#fff; border:none;
    padding:10px 22px; font-size:14px; font-weight:600;
    border-radius:6px; cursor:pointer;
    transition:background .2s,transform .15s;
    box-shadow:0 4px 15px rgba(250,164,26,.4);
}
.mh-lazy-konfig__btn:hover { background:#FF9000; transform:translateY(-1px) }
.mh-lazy-konfig__btn svg { width:16px; height:16px; fill:#fff }
.mh-lazy-konfig__hint { color:rgba(255,255,255,.5); font-size:11px; margin:0 }

/* Loading state */
.mh-lazy-konfig.mh-loading .mh-lazy-konfig__content { display:none }
.mh-lazy-konfig.mh-loading .mh-lazy-konfig__overlay { background:none }

.mh-skeleton { display:none; position:absolute; inset:0; background:#1a1a1a }
.mh-lazy-konfig.mh-loading .mh-skeleton { display:flex; flex-direction:column }
.mh-skeleton__viewport {
    flex:1; position:relative; overflow:hidden;
    background:linear-gradient(135deg,#222 0%,#2a2a2a 100%);
}
.mh-skeleton__grid {
    position:absolute; inset:0;
    background-image:
        linear-gradient(rgba(255,255,255,.03) 1px,transparent 1px),
        linear-gradient(90deg,rgba(255,255,255,.03) 1px,transparent 1px);
    background-size:40px 40px;
}
.mh-skeleton__shape {
    position:absolute; top:50%; left:50%; transform:translate(-50%,-50%);
    width:60%; height:55%; border:1px solid rgba(255,255,255,.08);
    border-radius:4px; background:rgba(255,255,255,.03);
}
.mh-skeleton__shimmer { position:absolute; inset:0; overflow:hidden }
.mh-skeleton__shimmer::after {
    content:''; position:absolute; inset:0;
    background:linear-gradient(90deg,transparent 0%,rgba(255,255,255,.04) 50%,transparent 100%);
    animation:mhShimmer 1.8s ease-in-out infinite;
}
@keyframes mhShimmer { 0%{transform:translateX(-100%)} 100%{transform:translateX(100%)} }
.mh-skeleton__toolbar {
    display:flex; gap:8px; padding:10px 14px;
    background:#111; border-top:1px solid rgba(255,255,255,.06);
}
.mh-skeleton__tbtn { width:28px; height:28px; border-radius:50%; background:rgba(255,255,255,.08) }
.mh-skeleton__status {
    position:absolute; bottom:50px; left:50%; transform:translateX(-50%);
    display:flex; align-items:center; gap:8px;
    color:rgba(255,255,255,.5); font-size:12px; font-weight:500;
}
.mh-skeleton__dot {
    width:5px; height:5px; border-radius:50%; background:#FAA41A;
    animation:mhPulse 1.2s ease-in-out infinite;
}
.mh-skeleton__dot:nth-child(2) { animation-delay:.2s }
.mh-skeleton__dot:nth-child(3) { animation-delay:.4s }
@keyframes mhPulse { 0%,100%{opacity:.3;transform:scale(.8)} 50%{opacity:1;transform:scale(1.1)} }

/* Fade-out */
.mh-lazy-konfig.mh-fade-out {
    opacity:0; transform:scale(.98);
    transition:opacity .4s,transform .4s; pointer-events:none;
}

/* Close-Button */
.mh-close-konfig {
    display:none; position:absolute; top:20px; left:8px; z-index:9999;
    background:rgba(0,0,0,.65); color:#fff;
    border:1px solid rgba(255,255,255,.2);
    border-radius:8px; padding:6px 12px 6px 8px;
    font-size:12px; font-weight:500; cursor:pointer;
    backdrop-filter:blur(4px); transition:background .2s,transform .15s;
    align-items:center; gap:5px; line-height:1; font-family:inherit;
}
.mh-close-konfig:hover { background:rgba(200,40,40,.8); transform:scale(1.03) }
.mh-close-konfig svg { width:12px; height:12px; stroke:#fff; stroke-width:2; fill:none }
.mh-lazy-konfig--loaded .mh-close-konfig { display:inline-flex }

/* ═══════════════════════════════════════
   MOBILE RESPONSIVE
   ═══════════════════════════════════════ */

/* Tablet */
@media (max-width: 1024px) {
    .mh-lazy-konfig {
        aspect-ratio:16/9;
        max-height:500px;
    }
    .mh-lazy-konfig--loaded #{$cid} {
        max-height:500px !important;
        height:500px !important;
    }
}

/* Mobile */
@media (max-width: 767px) {
    .mh-lazy-konfig {
        aspect-ratio:4/3;
        max-height:360px;
    }
    .mh-lazy-konfig--loaded #{$cid} {
        max-height:400px !important;
        height:400px !important;
    }
    .mh-lazy-konfig__title { font-size:16px }
    .mh-lazy-konfig__subtitle { font-size:12px }
    .mh-lazy-konfig__btn { padding:8px 18px; font-size:13px }
    .mh-lazy-konfig__hint { font-size:10px }
    .mh-close-konfig { font-size:11px; padding:5px 10px 5px 7px }
}

/* Kleine Phones */
@media (max-width: 480px) {
    .mh-lazy-konfig {
        max-height:280px;
    }
    .mh-lazy-konfig--loaded #{$cid} {
        max-height:320px !important;
        height:320px !important;
    }
}
{$no_splash_css}
</style>
CSS;
    }

    /* ──────────────────────────────────────────
       PLACEHOLDER HTML
       ────────────────────────────────────────── */
    private function placeholder( string $img ): string {
        return <<<HTML
<div class="mh-lazy-konfig" id="mhLazyKonfig" onclick="mhStartConfigurator()">
    <div class="mh-lazy-konfig__bg">
        <img src="{$img}" alt="3D-Konfigurator Vorschau" loading="eager" width="800" height="600">
    </div>
    <div class="mh-lazy-konfig__overlay"></div>
    <div class="mh-lazy-konfig__content">
        <div class="mh-lazy-konfig__icon">
            <svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
        </div>
        <h3 class="mh-lazy-konfig__title">3D-Konfigurator</h3>
        <p class="mh-lazy-konfig__subtitle">Konfigurieren Sie Ihre Mülltonnenbox interaktiv in 3D</p>
        <button class="mh-lazy-konfig__btn" type="button">
            <svg class="play" viewBox="0 0 24 24"><polygon points="5 3 19 12 5 21 5 3"/></svg>
            Konfigurator starten
        </button>
        <p class="mh-lazy-konfig__hint">Lädt den interaktiven 3D-Viewer</p>
    </div>
    <div class="mh-skeleton">
        <div class="mh-skeleton__viewport">
            <div class="mh-skeleton__grid"></div>
            <div class="mh-skeleton__shape"></div>
            <div class="mh-skeleton__shimmer"></div>
            <div class="mh-skeleton__status">
                <span class="mh-skeleton__dot"></span>
                <span class="mh-skeleton__dot"></span>
                <span class="mh-skeleton__dot"></span>
                <span>3D-Konfigurator wird geladen</span>
            </div>
        </div>
        <div class="mh-skeleton__toolbar">
            <div class="mh-skeleton__tbtn"></div>
            <div class="mh-skeleton__tbtn"></div>
            <div class="mh-skeleton__tbtn"></div>
            <div class="mh-skeleton__tbtn"></div>
            <div class="mh-skeleton__tbtn"></div>
        </div>
    </div>
</div>
HTML;
    }

    /* ──────────────────────────────────────────
       JAVASCRIPT
       ────────────────────────────────────────── */
    private function js( string $cid, string $pid, int $maxH, string $wcPrice, string $wcRegularPrice, bool $debug, bool $splash = true ): string {
        $dbg            = $debug ? 'console.log("[MH Lazy v7.2] "+window.mhLazyScripts.length+" scripts deferred, WC price: "+MH_WC_PRICE+", splash: "+MH_SHOW_SPLASH);' : '';
        $priceJson      = wp_json_encode( $wcPrice );
        $regularJson    = wp_json_encode( $wcRegularPrice );
        $splashJson     = $splash ? 'true' : 'false';

        return <<<JS
<script>
(function(){
    var MH_STORAGE_KEY     = 'mh_konfig_autostart';
    var MH_MAX_H           = {$maxH};
    var MH_SHOW_SPLASH     = {$splashJson};
    var MH_WC_PRICE        = {$priceJson};         // Aktueller Preis (Angebotspreis) inkl. MwSt
    var MH_WC_REGULAR      = {$regularJson};        // Originalpreis (UVP) inkl. MwSt — leer wenn kein Angebot
    {$dbg}

    /* ── WooCommerce-Preis als Platzhalter ── */
    function showWcPrice() {
        var el = document.getElementById('{$pid}');
        if (!el) return;
        var nums = el.textContent.replace(/[^0-9]/g, '');
        if (!nums || parseInt(nums,10) === 0) {
            if (MH_WC_PRICE) {
                var html = MH_WC_PRICE;
                // Wenn Angebotspreis → Originalpreis durchgestrichen daneben
                if (MH_WC_REGULAR) {
                    html += ' <span style="font-size:0.7em;color:#999;text-decoration:line-through;font-weight:400;margin-left:6px">' + MH_WC_REGULAR + '</span>';
                }
                el.innerHTML = html;
                el.setAttribute('data-mh-wc', '1');
            } else {
                el.innerHTML = '<span style="font-size:14px;opacity:.55;font-style:italic">Preis wird im Konfigurator berechnet</span>';
                el.setAttribute('data-mh-wc', '1');
            }
        }
    }
    function clearWcPrice() {
        var el = document.getElementById('{$pid}');
        if (el && el.getAttribute('data-mh-wc') === '1') {
            el.innerHTML = '';
            el.removeAttribute('data-mh-wc');
        }
    }

    /* Sofort ausführen */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', showWcPrice);
    } else {
        showWcPrice();
    }

    /* ── Scripts laden ── */
    function loadScripts(callback) {
        var scripts = window.mhLazyScripts || [];
        if (!scripts.length) { console.error('[MH Lazy] Keine Scripts'); return; }
        var i = 0;
        function loadNext() {
            if (i >= scripts.length) { callback(); return; }
            var s = document.createElement('script');
            s.src = scripts[i];
            s.setAttribute('data-mh-lazy','true');
            s.onload  = function(){ i++; loadNext(); };
            s.onerror = function(){ console.error('[MH Lazy] Fehler: ' + scripts[i]); i++; loadNext(); };
            document.body.appendChild(s);
        }
        loadNext();
    }

    /* ── Canvas-Größe erzwingen ── */
    function enforceCanvasSize() {
        var container = document.getElementById('{$cid}');
        if (!container) return;

        container.style.maxHeight = MH_MAX_H + 'px';
        container.style.height = MH_MAX_H + 'px';
        container.style.overflow = 'hidden';

        var canvas = container.querySelector('canvas');
        if (canvas) canvas.style.maxHeight = MH_MAX_H + 'px';

        // BabylonJS resize nach Verzögerung
        function resizeEngine() {
            try {
                if (window.engine) window.engine.resize();
                else if (window.BABYLON && BABYLON.Engine && BABYLON.Engine.LastCreatedEngine) {
                    BABYLON.Engine.LastCreatedEngine.resize();
                }
            } catch(e){}
        }

        setTimeout(function(){
            container.style.maxHeight = MH_MAX_H + 'px';
            container.style.height = MH_MAX_H + 'px';
            var c = container.querySelector('canvas');
            if (c) c.style.maxHeight = MH_MAX_H + 'px';
            resizeEngine();
        }, 1500);

        setTimeout(function(){
            container.style.maxHeight = MH_MAX_H + 'px';
            container.style.height = MH_MAX_H + 'px';
            resizeEngine();
        }, 3000);
    }

    /* ── Konfigurator fertig ── */
    function onLoaded() {
        var container = document.getElementById('{$cid}');
        if (container) {
            var p = container.closest('.ct-div-block');
            if (p) p.classList.add('mh-lazy-konfig--loaded');
        }

        clearWcPrice(); // WC-Preis entfernen, BabylonJS setzt seinen eigenen
        enforceCanvasSize();

        // Close-Button (nur im Splash-Modus — wenn man "starten" musste, braucht man auch "schließen")
        if (MH_SHOW_SPLASH && !document.getElementById('mhCloseKonfig') && container) {
            var pb = container.closest('.ct-div-block');
            if (pb) {
                pb.style.position = 'relative';
                var btn = document.createElement('button');
                btn.id = 'mhCloseKonfig';
                btn.className = 'mh-close-konfig';
                btn.type = 'button';
                btn.innerHTML = '<svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg> Konfigurator schließen';
                btn.onclick = window.mhCloseConfigurator;
                pb.appendChild(btn);
            }
        }

        // Placeholder ausblenden (nur im Splash-Modus)
        if (MH_SHOW_SPLASH) {
            var w = document.getElementById('mhLazyKonfig');
            if (w) {
                w.classList.add('mh-fade-out');
                setTimeout(function(){
                    w.style.display = 'none';
                    w.classList.remove('mh-loading','mh-fade-out');
                }, 500);
            }
        }

        try { sessionStorage.removeItem(MH_STORAGE_KEY); } catch(e){}
    }

    /* ── Konfigurator starten ── */
    window.mhStartConfigurator = function() {
        if (MH_SHOW_SPLASH) {
            var w = document.getElementById('mhLazyKonfig');
            if (!w || w.classList.contains('mh-loading')) return;
            w.classList.add('mh-loading');
        }

        if (window._mhLoadingStarted) return;
        window._mhLoadingStarted = true;

        loadScripts(onLoaded);

        // Timeout-Fallback
        setTimeout(function(){
            var c = document.getElementById('{$cid}');
            if (c) {
                var p = c.closest('.ct-div-block');
                if (p && !p.classList.contains('mh-lazy-konfig--loaded')) {
                    p.classList.add('mh-lazy-konfig--loaded');
                    if (MH_SHOW_SPLASH) {
                        var w = document.getElementById('mhLazyKonfig');
                        if (w) { w.style.display = 'none'; w.classList.remove('mh-loading'); }
                    }
                    clearWcPrice();
                    enforceCanvasSize();
                }
            }
        }, 25000);
    };

    /* ── Schließen ── */
    window.mhCloseConfigurator = function() {
        window.location.reload();
    };

    /* ── Auto-Start ── */
    function checkAutoStart() {
        // Kein Splash → sofort laden
        if (!MH_SHOW_SPLASH) {
            setTimeout(function(){ window.mhStartConfigurator(); }, 100);
            return;
        }
        // Splash-Modus: Auto-Start nach Schließen
        try {
            if (sessionStorage.getItem(MH_STORAGE_KEY) === '1') {
                sessionStorage.removeItem(MH_STORAGE_KEY);
                setTimeout(function(){ window.mhStartConfigurator(); }, 200);
            }
        } catch(e){}
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', checkAutoStart);
    } else {
        checkAutoStart();
    }
})();
</script>
JS;
    }

    private function page_has_configurator( string $html ): bool {
        return str_contains( $html, 'id="' . MH_KONFIG_CANVAS_ID . '"' )
            || str_contains( $html, 'babylon-canvas' );
    }

    private function is_oxygen_editor(): bool {
        return isset( $_GET['ct_builder'] ) || isset( $_GET['oxygen_iframe'] ); // phpcs:ignore
    }
}

if ( ! is_admin() ) {
    ( new MH_Lazy_Konfigurator_V7() )->init();
}
