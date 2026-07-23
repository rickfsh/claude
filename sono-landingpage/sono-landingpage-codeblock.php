<?php
/* ============================================================
   MEGA FLEX SONO – Landingpage  (v6)
   Oxygen Builder Code Block  →  Panel "PHP & HTML"
   Namespace: .sono-*  |  JS: ES5 only
   ------------------------------------------------------------
   v6: Die Modelle-Sektion kommt jetzt AUTOMATISCH aus dem Plugin
   "MH SONO Grid" (Shortcode [mh_sono_grid category="sono"]).
   Die alte Hand-Liste ($sono_models) und der Oberflaechen-
   Umschalter sind aus diesem Block entfernt.

   Produkte erscheinen automatisch, wenn sie:
     1) in der Produktkategorie "SONO" liegen (Tore zusaetzlich
        in ihrer Unterkategorie, z.B. Einzeltore / Doppeltore),
     2) die Attribute Modell / Hoehe / Oberflaeche gepflegt haben.
   Details: README des Plugins mh-sono-grid.

   PFLEGE in DIESEM Block nur noch:
   1) $sono_hero_img  = URL deines breiten Ambiente-Bilds (Mediathek)
   2) $sono_heights   = Bild-URLs fuer die 4 Hoehen-Karten
   Die Zaehler (Designs / Hoehen / Oberflaechen) rechnen sich selbst.
   ============================================================ */

$sono_hero_img = 'https://mega-holz.de/wp-content/uploads/2026/07/german-backyard-privacy-screen-dinner.webp';

$sono_heights = array(
  180 => array('img' => 'https://mega-holz.de/wp-content/uploads/2026/07/939231_Product-1024x1024.webp', 'label' => 'Voller Sichtschutz',   'text' => 'Maximale Privatsphäre für Terrasse und Garten.'),
  150 => array('img' => 'https://mega-holz.de/wp-content/uploads/2026/07/german-backyard-privacy-screen-dinner-2-1.webp', 'label' => 'Sichtschutz mit Luft', 'text' => 'Geschützt im Sitzen, offen im Stehen.'),
  120 => array('img' => 'https://mega-holz.de/wp-content/uploads/2026/07/german-front-garden-fence-scene-1.webp', 'label' => 'Gartenzaun',           'text' => 'Klare Grundstücksgrenze mit Durchblick.'),
  90  => array('img' => 'https://mega-holz.de/wp-content/uploads/2026/07/90cmVorgartenzaun-scaled.webp', 'label' => 'Vorgartenzaun',        'text' => 'Niedrige Einfassung für den Eingangsbereich.'),
);

/* Layout der Hoehen-Sektion zum Vergleichen umschalten:
   'mosaic' = asymmetrisches Bild-Mosaik (180 gross)
   'split'  = grosses Bild + Auswahlliste (Magazin-Selector) */
$sono_heights_layout = 'mosaic';

/* ------------------------------------------------------------
   Ab hier nichts mehr anpassen.
   ------------------------------------------------------------ */

/* Live-Zaehler aus dem MH-SONO-Grid-Plugin; Fallback, falls das
   Plugin deaktiviert ist (dann bleiben die letzten bekannten Werte). */
if ( ! function_exists( 'sono_count' ) ) {
  function sono_count( $what, $fallback ) {
    if ( shortcode_exists( 'mh_sono_count' ) ) {
      $out = do_shortcode( '[mh_sono_count what="' . $what . '" category="sono"]' );
      if ( '' !== trim( $out ) ) {
        return $out;
      }
    }
    return $fallback;
  }
}
$sono_n_models   = sono_count( 'models', '13' );
$sono_n_heights  = sono_count( 'heights', '4' );
$sono_n_finishes = sono_count( 'finishes', '3' );

?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600&display=swap" rel="stylesheet">
<style>
/* ========== SONO Landingpage ========== */
.sono-lp{
  --sono-orange:#FAA41A;
  --sono-orange-hover:#FF9000;
  --sono-black:#080704;
  --sono-navy:#080704;
  --sono-text:#444444;
  --sono-head:#303030;
  --sono-bg:#F8F8F8;
  --sono-line:#E8E8E8;
  --sono-serif:'Cormorant Garamond',Georgia,'Times New Roman',serif;
  font-family:'Open Sans',Arial,sans-serif;
  color:var(--sono-text);
  line-height:1.6;
}
.sono-lp *{box-sizing:border-box;margin:0;padding:0}
.sono-lp img{max-width:100%;display:block}
.sono-wrap{max-width:1200px;margin:0 auto;padding:0 20px}
.sono-lp h1,.sono-lp h2,.sono-lp h3,.sono-lp h4{color:var(--sono-head);font-weight:700;line-height:1.25}
.sono-btn{display:inline-block;background:var(--sono-orange);color:#fff !important;font-weight:700;font-size:15px;padding:13px 28px;border-radius:4px;text-decoration:none !important;border:none;cursor:pointer;transition:background .15s ease,transform .15s ease,box-shadow .15s ease}
.sono-btn:hover{background:var(--sono-orange-hover);transform:translateY(-1px);box-shadow:0 4px 12px rgba(250,164,26,.30)}
.sono-btn:active{transform:translateY(0);box-shadow:none}

/* ---------- HERO ---------- */
.sono-hero{position:relative;background-color:var(--sono-black);background-size:cover;background-position:center;color:#fff;padding:120px 0}
.sono-hero:before{
  content:"";position:absolute;inset:0;
  background:
    linear-gradient(90deg, rgba(8,7,4,.72) 0%, rgba(8,7,4,.40) 42%, rgba(8,7,4,0) 72%),
    linear-gradient(180deg, rgba(8,7,4,.30) 0%, rgba(8,7,4,0) 22%);
}
.sono-hero .sono-wrap{position:relative}
.sono-hero-copy{max-width:620px}
.sono-hero h1{color:#fff;font-size:44px;margin-bottom:18px}
.sono-hero p{font-size:18px;color:rgba(255,255,255,.9);margin-bottom:28px}
.sono-hero-ctas{display:flex;gap:12px;flex-wrap:wrap}
.sono-btn--ghost{background:transparent;border:2px solid rgba(255,255,255,.6);color:#fff !important}
.sono-btn--ghost:hover{background:rgba(255,255,255,.1);border-color:#fff;box-shadow:none}
.sono-hero-facts{display:flex;gap:32px;margin-top:38px;flex-wrap:wrap;font-size:14px;color:rgba(255,255,255,.85)}
.sono-hero-facts b{color:var(--sono-orange);font-size:22px;margin-right:6px}

/* ---------- SECTIONS ---------- */
.sono-sec{padding:72px 0}
.sono-sec--alt{background:var(--sono-bg)}
.sono-sec-head{max-width:760px;margin:0 auto 44px;text-align:center}
.sono-eyebrow{display:block;font-size:13px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--sono-orange-hover);margin-bottom:10px}
.sono-sec-head h2{font-size:30px;margin-bottom:12px}
.sono-sec-head p{font-size:16px}

/* ---------- SO FUNKTIONIERT'S ---------- */
.sono-steps{display:grid;grid-template-columns:repeat(4,1fr);gap:22px;position:relative}
.sono-steps:before{content:"";position:absolute;top:14px;left:12.5%;right:12.5%;height:2px;background:rgba(250,164,26,.30);z-index:0}
.sono-step{text-align:center;position:relative;z-index:1;padding:0 8px}
.sono-step-num{display:flex;width:30px;height:30px;border-radius:50%;background:var(--sono-orange);color:#fff;font-weight:700;font-size:14px;align-items:center;justify-content:center;margin:0 auto 16px}
.sono-step h3{font-size:17px;margin-bottom:8px}
.sono-step p{font-size:14px}
.sono-step-icon{display:block;height:52px;margin:0 auto 14px}
.sono-system-note{max-width:800px;margin:44px auto 0;background:var(--sono-navy);color:#fff;border-radius:8px;padding:26px 32px;font-size:15px;display:flex;gap:20px;align-items:flex-start}
.sono-system-note svg{flex:0 0 40px;margin-top:2px}
.sono-system-note p{flex:1;color:rgba(255,255,255,.88);line-height:1.6}
.sono-system-note b{color:var(--sono-orange)}
.sono-pstrip{display:grid;grid-template-columns:230px 1fr;gap:36px;align-items:center;max-width:760px;margin:40px auto 0}
.sono-pstrip-vis{background:var(--sono-bg);border-radius:10px;padding:22px 26px}
.sono-pstrip-bars{display:flex;flex-direction:column;gap:11px}
.sono-pstrip-bars i{display:block;width:100%;background:linear-gradient(180deg,#454b50,#31363a);border-radius:2px;box-shadow:inset 0 1px 0 rgba(255,255,255,.12)}
.sono-pstrip-cap{font-size:11px;color:#9a9a9a;text-align:center;margin-top:14px}
.sono-pstrip-legend{display:flex;flex-direction:column}
.sono-pleg{display:flex;gap:14px;align-items:flex-start;padding:12px 0;border-bottom:1px solid var(--sono-line);font-size:13.5px}
.sono-pleg:last-child{border-bottom:none}
.sono-pleg i{flex:0 0 42px;background:linear-gradient(180deg,#454b50,#31363a);border-radius:2px;margin-top:5px;box-shadow:inset 0 1px 0 rgba(255,255,255,.12)}
.sono-pleg b{color:var(--sono-head);font-size:15px}
/* ---- Sub-Überschrift innerhalb einer Sektion ---- */
.sono-subhead{max-width:760px;margin:64px auto 0;text-align:center}
.sono-subhead h3{font-size:24px;color:var(--sono-head);margin-bottom:10px}
.sono-subhead p{font-size:15px}
.sono-subhead--left{max-width:none;margin:0;text-align:left}
/* ---- Abstandhalter: eigenes helles Kapitel-Panel ---- */
.sono-panel{background:var(--sono-bg);border-radius:16px;padding:40px 40px 44px;max-width:1000px;margin:64px auto 0}
.sono-panel-top{display:grid;grid-template-columns:1fr 1fr;gap:40px;align-items:center;margin-bottom:34px}
.sono-panel-note{font-size:12px;color:#6b7280;text-align:center;margin-top:14px;line-height:1.5}
.sono-spacer-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:18px}
.sono-spacer-card{border:none;border-radius:10px;padding:20px 18px 22px;text-align:center;background:#fff}
.sono-spacer-card svg{width:100%;max-width:150px;height:auto;display:block;margin:0 auto 14px}
.sono-spacer-card h4{font-size:16px;color:var(--sono-head);margin-bottom:6px;font-weight:700}
.sono-spacer-card p{font-size:13.5px}
.sono-spacer-legend{display:flex;justify-content:center;align-items:center;gap:9px;margin:18px auto 0;font-size:13px;color:#6b7280}
.sono-spacer-legend i{display:inline-block;width:20px;height:9px;border-radius:2px;background:var(--sono-orange);flex:0 0 auto}
.sono-gaplist{background:#fff;border-radius:12px;padding:14px 22px;display:grid;grid-template-columns:1fr 1fr;gap:0 30px}
.sono-gap-col{display:flex;flex-direction:column}
.sono-gap-row{display:flex;align-items:center;gap:14px;padding:9px 0;border-bottom:1px solid var(--sono-line)}
.sono-gap-col .sono-gap-row:last-child{border-bottom:none}
.sono-gap-diag{flex:0 0 52px;width:52px}
.sono-gap-diag i{display:block;border-radius:1px}
.sono-gap-diag i:nth-child(1),.sono-gap-diag i:nth-child(3){height:9px;background:#383E42}
.sono-gap-diag i:nth-child(2){background:var(--sono-orange);box-shadow:inset 0 1px 0 rgba(255,255,255,.35)}
.sono-gap-row b{display:block;color:var(--sono-head);font-size:14px;font-variant-numeric:tabular-nums;line-height:1.2}
.sono-gap-lab{display:block;color:#6b7280;font-size:12px;margin-top:1px}

/* ---------- HÖHEN: Mosaik ---------- */
.sono-hmo-grid{display:grid;grid-template-columns:1.7fr 1fr 1fr;grid-template-rows:180px 180px;gap:16px}
.sono-hmo-t{position:relative;border-radius:10px;overflow:hidden;text-decoration:none !important;background:#3a3a34 center/cover no-repeat;box-shadow:0 1px 3px rgba(0,0,0,.08);transition:box-shadow .15s ease,transform .15s ease;min-height:160px}
.sono-hmo-t:hover{box-shadow:0 8px 22px rgba(0,0,0,.14);transform:translateY(-2px)}
.sono-hmo-t:after{content:"";position:absolute;inset:0;background:linear-gradient(180deg,rgba(0,0,0,0) 42%,rgba(15,13,10,.66) 100%)}
.sono-hmo-in{position:absolute;left:18px;bottom:15px;z-index:2}
.sono-hmo-num{font-family:var(--sono-serif);font-weight:600;color:#fff;line-height:.95;font-size:28px}
.sono-hmo-num span{font-size:14px}
.sono-hmo-lab{font-size:11px;letter-spacing:.09em;text-transform:uppercase;color:#FAB94A;font-weight:700;margin-top:3px}
.sono-hmo-big{grid-column:1;grid-row:1 / span 2}
.sono-hmo-big .sono-hmo-num{font-size:46px}
.sono-hmo-big .sono-hmo-num span{font-size:19px}
.sono-hmo-wide{grid-column:2 / span 2;grid-row:1}
.sono-hmo-noimg{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:#e8e4dc;font-size:13px;z-index:1}

/* ---------- HÖHEN: Editorial-Split ---------- */
.sono-hsp-grid{display:grid;grid-template-columns:1.15fr 1fr;gap:22px;align-items:stretch}
.sono-hsp-img{border-radius:10px;min-height:360px;background:#3a3a34 center/cover no-repeat;position:relative;overflow:hidden;transition:background-image .2s ease}
.sono-hsp-cap{position:absolute;left:16px;bottom:14px;font-size:12px;color:#fff;background:rgba(15,13,10,.45);padding:4px 12px;border-radius:20px;z-index:2}
.sono-hsp-list{display:flex;flex-direction:column;gap:10px;justify-content:center}
.sono-hsp-item{display:flex;align-items:center;gap:14px;padding:14px 16px;border:1px solid var(--sono-line);border-radius:10px;text-decoration:none !important;color:var(--sono-text);transition:border-color .12s ease,background .12s ease}
.sono-hsp-item:hover,.sono-hsp-item.is-on{border-color:var(--sono-orange);background:#FFF8EE}
.sono-hsp-num{font-family:var(--sono-serif);font-size:30px;font-weight:600;color:var(--sono-black);line-height:1;min-width:76px}
.sono-hsp-num span{font-size:14px}
.sono-hsp-meta{flex:1}
.sono-hsp-meta b{display:block;font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:var(--sono-orange-hover);font-weight:700;margin-bottom:2px}
.sono-hsp-meta p{font-size:13px;color:#6b6b6b;line-height:1.4}
.sono-hsp-chev{color:#c4c4c4;font-size:18px}
.sono-hsp-item:hover .sono-hsp-chev,.sono-hsp-item.is-on .sono-hsp-chev{color:var(--sono-orange)}
.sono-hsp-thumb{display:none;width:64px;height:64px;flex:0 0 64px;border-radius:8px;background:#3a3a34 center/cover no-repeat}

/* ---------- OBERFLÄCHEN ---------- */
.sono-swatches{display:grid;grid-template-columns:repeat(3,1fr);gap:24px;max-width:960px;margin:0 auto}
.sono-swatch{border:1px solid var(--sono-line);border-radius:12px;overflow:hidden;background:#fff;text-align:center;transition:box-shadow .15s ease,transform .15s ease}
.sono-swatch:hover{box-shadow:0 6px 20px rgba(0,0,0,.08);transform:translateY(-2px)}
.sono-swatch-color{height:150px}
.sono-swatch-anthrazit{background:linear-gradient(160deg,#434a4f 0%,#2e3439 100%)}
.sono-swatch-silber{background:linear-gradient(160deg,#c7cad0 0%,#a9adb5 100%)}
.sono-swatch-laerche{background:repeating-linear-gradient(178.5deg,rgba(255,250,243,.30) 0 1px,transparent 1px 2px,rgba(186,140,101,.16) 2px 3px,transparent 3px 5px,rgba(205,163,124,.13) 5px 6px,transparent 6px 9px),linear-gradient(170deg,#f0d3b4 0%,#e7c4a1 55%,#dfb894 100%)}
.sono-swatch-body{padding:18px 18px 22px}
.sono-swatch-body h3{font-size:17px;margin-bottom:5px}
.sono-swatch-body p{font-size:13.5px}

/* ---------- FAQ ---------- */
.sono-faq-list{max-width:820px;margin:0 auto}
.sono-faq-item{background:#fff;border:1px solid var(--sono-line);border-radius:4px;margin-bottom:12px;overflow:hidden}
.sono-faq-q{width:100%;text-align:left;background:none;border:none;cursor:pointer;padding:18px 52px 18px 22px;font-family:inherit;font-size:16px;font-weight:700;color:var(--sono-head);position:relative}
.sono-faq-q:after{content:"+";position:absolute;right:20px;top:50%;transform:translateY(-50%);font-size:24px;font-weight:400;color:var(--sono-orange);transition:transform .2s ease}
.sono-faq-item.sono-open .sono-faq-q:after{transform:translateY(-50%) rotate(45deg)}
.sono-faq-a{max-height:0;overflow:hidden;transition:max-height .25s ease}
.sono-faq-a-inner{padding:0 22px 20px;font-size:14.5px}

/* ---------- FINAL ---------- */
.sono-final{background:var(--sono-navy);color:#fff;text-align:center;padding:88px 0}
.sono-final h2{color:#fff;font-size:30px;margin-bottom:14px}
.sono-final>div>p{font-size:16px;color:rgba(255,255,255,.85);max-width:520px;margin:0 auto 28px}
.sono-final-facts{display:flex;justify-content:center;gap:36px;margin:0 auto 32px;flex-wrap:wrap}
.sono-final-facts span{font-size:13px;color:rgba(255,255,255,.7)}
.sono-final-facts b{color:var(--sono-orange);font-size:20px;margin-right:5px}
.sono-final .sono-btn{display:block;width:fit-content;margin:0 auto 0}
.sono-planer-teaser{display:block;border-top:1px solid rgba(255,255,255,.15);max-width:480px;margin:44px auto 0;padding-top:28px;text-align:left}
.sono-planer-teaser b{color:var(--sono-orange);font-size:14px}
.sono-planer-teaser p{font-size:13px;margin:4px 0 0;color:rgba(255,255,255,.6)}

/* ---------- RESPONSIVE ---------- */
@media(max-width:980px){
  .sono-steps{grid-template-columns:repeat(2,1fr)}
  .sono-steps:before{display:none}
  .sono-hero h1{font-size:34px}
  .sono-panel-top{grid-template-columns:1fr;gap:24px}
  .sono-subhead--left{text-align:center;max-width:640px;margin:0 auto}
}
@media(max-width:640px){
  .sono-steps,.sono-swatches,.sono-spacer-grid{grid-template-columns:1fr}
  .sono-pstrip{grid-template-columns:1fr;gap:22px}
  .sono-pstrip-vis{max-width:320px;margin:0 auto}
  .sono-panel{padding:26px 20px 30px}
  .sono-gaplist{grid-template-columns:1fr;gap:0}
  .sono-hero{padding:76px 0}
  .sono-hero h1{font-size:28px}
  .sono-sec{padding:52px 0}
  .sono-sec-head h2{font-size:25px}
  .sono-hmo-grid{grid-template-columns:1fr;grid-template-rows:none}
  .sono-hmo-big,.sono-hmo-wide{grid-column:auto;grid-row:auto}
  .sono-hsp-grid{grid-template-columns:1fr}
  .sono-hsp-img{display:none}
  .sono-hsp-thumb{display:block}
  .sono-hsp-item{padding:12px}
  .sono-hsp-num{min-width:auto;font-size:26px}
  .sono-hsp-chev{display:none}
  .sono-hsp-img{min-height:220px}
}
</style>

<div class="sono-lp">

  <!-- ============ HERO ============ -->
  <section class="sono-hero"<?php if ( $sono_hero_img ) { echo ' style="background-image:url(' . esc_url( $sono_hero_img ) . ')"'; } ?>>
    <div class="sono-wrap">
      <div class="sono-hero-copy">
        <h1>MEGA FLEX SONO<br>Sichtschutz nach deinem Muster</h1>
        <p>Drei Aluminium-Profile, ein Stecksystem: SONO gibt es als <?php echo esc_html( $sono_n_models ); ?> fertige Designs in vier Höhen. Oder du stellst dir deine eigene Profilfolge zusammen.</p>
        <div class="sono-hero-ctas">
          <a class="sono-btn" href="#sono-modelle">Alle <?php echo esc_html( $sono_n_models ); ?> Designs ansehen</a>
          <a class="sono-btn sono-btn--ghost" href="#sono-system">Wie funktioniert SONO?</a>
        </div>
        <div class="sono-hero-facts">
          <span><b><?php echo esc_html( $sono_n_models ); ?></b>Designs</span>
          <span><b><?php echo esc_html( $sono_n_heights ); ?></b>Höhen</span>
          <span><b>3</b>Profil-Breiten</span>
          <span><b><?php echo esc_html( $sono_n_finishes ); ?></b>Oberflächen</span>
        </div>
      </div>
    </div>
  </section>

  <!-- ============ SO FUNKTIONIERT SONO ============ -->
  <section class="sono-sec" id="sono-system">
    <div class="sono-wrap">
      <div class="sono-sec-head">
        <span class="sono-eyebrow">Das System</span>
        <h2>So funktioniert SONO</h2>
        <p>SONO ist kein fertiges Zaunfeld, sondern ein Baukasten. Du stapelst Aluminium-Profile in drei Breiten und bestimmst damit selbst, wie dicht oder offen dein Zaun wird.</p>
      </div>
      <div class="sono-steps">
        <div class="sono-step">
          <span class="sono-step-num">1</span>
          <svg class="sono-step-icon" viewBox="0 0 90 56"><rect x="14" y="6" width="62" height="16" rx="3" fill="#383E42"/><rect x="14" y="27" width="62" height="9" rx="3" fill="#383E42"/><rect x="14" y="41" width="62" height="5" rx="2" fill="#383E42"/></svg>
          <h3>Profile wählen</h3>
          <p>Drei Breiten stehen zur Auswahl: 18 cm, 10 cm und 5 cm. Alle Profile bestehen aus pulverbeschichtetem Aluminium.</p>
        </div>
        <div class="sono-step">
          <span class="sono-step-num">2</span>
          <svg class="sono-step-icon" viewBox="0 0 90 56"><rect x="20" y="4" width="50" height="8" rx="2" fill="#383E42"/><rect x="20" y="5" width="50" height="1.5" fill="#565c61"/><rect x="20" y="15" width="50" height="8" rx="2" fill="#383E42"/><rect x="20" y="16" width="50" height="1.5" fill="#565c61"/><rect x="20" y="33" width="50" height="8" rx="2" fill="#383E42"/><rect x="20" y="34" width="50" height="1.5" fill="#565c61"/><rect x="20" y="48" width="50" height="8" rx="2" fill="#383E42"/><rect x="20" y="49" width="50" height="1.5" fill="#565c61"/><path d="M13 12 v3 M77 12 v3" stroke="#FAA41A" stroke-width="2" stroke-linecap="round"/><path d="M13 23 v10 M77 23 v10" stroke="#FAA41A" stroke-width="2" stroke-linecap="round"/><path d="M13 41 v7 M77 41 v7" stroke="#FAA41A" stroke-width="2" stroke-linecap="round"/></svg>
          <h3>Muster stapeln</h3>
          <p>Mit Abstandhaltern legst du fest, wo dein Zaun blickdicht ist und wo Luft und Licht durchkommen.</p>
        </div>
        <div class="sono-step">
          <span class="sono-step-num">3</span>
          <svg class="sono-step-icon" viewBox="0 0 90 56"><rect x="18" y="20" width="8" height="34" rx="2" fill="#FAA41A"/><rect x="64" y="20" width="8" height="34" rx="2" fill="#FAA41A"/><rect x="30" y="30" width="30" height="10" rx="2" fill="#383E42"/><rect x="30" y="31" width="30" height="1.6" fill="#565c61"/><rect x="30" y="43" width="30" height="10" rx="2" fill="#383E42" opacity=".35"/><rect x="37" y="3" width="16" height="10" rx="2" fill="#383E42"/><rect x="37" y="4" width="16" height="1.6" fill="#565c61"/><path d="M45 14 v9" stroke="#303030" stroke-width="2" stroke-linecap="round"/><path d="M41 19 l4 4 l4 -4" fill="none" stroke="#303030" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          <h3>Einschieben</h3>
          <p>Die Profile werden von oben in die Nuten der MEGA FLEX Pfosten eingeschoben. Ohne Spezialwerkzeug, kompatibel mit deinem bestehenden MEGA FLEX Zaun.</p>
        </div>
        <div class="sono-step">
          <span class="sono-step-num">4</span>
          <svg class="sono-step-icon" viewBox="0 0 90 56"><rect x="12" y="8" width="8" height="42" rx="2" fill="#FAA41A"/><rect x="70" y="8" width="8" height="42" rx="2" fill="#FAA41A"/><rect x="24" y="11" width="44" height="10" rx="2" fill="#383E42"/><rect x="24" y="25" width="44" height="6" rx="2" fill="#383E42"/><rect x="24" y="35" width="44" height="10" rx="2" fill="#383E42"/><circle cx="72" cy="12" r="9" fill="#FAA41A" stroke="#fff" stroke-width="2"/><path d="M68 12 l3 3 l5 -6" stroke="#fff" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>
          <h3>Fertig montiert</h3>
          <p>Befestigungsset anbringen, fertig. Später umbauen geht auch: Profile raus, neu stapeln.</p>
        </div>
      </div>
      <div class="sono-system-note">
        <svg viewBox="0 0 40 40" width="40" height="40"><circle cx="20" cy="20" r="19" fill="none" stroke="#FAA41A" stroke-width="2"/><path d="M20 11 v12 M20 27.5 v2" stroke="#FAA41A" stroke-width="3" stroke-linecap="round"/></svg>
        <p><b>Die <?php echo esc_html( $sono_n_models ); ?> Designs sind fertige Profilfolgen unserer Zaunbauer</b> &ndash; benannt nach italienischen Städten, mit eigenem Charakter. Du kannst sie so bestellen, wie sie sind, oder als Startpunkt für dein eigenes Muster nehmen.</p>
      </div>

      <div class="sono-pstrip">
        <div class="sono-pstrip-vis">
          <div class="sono-pstrip-bars">
            <i style="height:28px"></i>
            <i style="height:16px"></i>
            <i style="height:8px"></i>
          </div>
          <div class="sono-pstrip-cap">im echten Gr&ouml;&szlig;enverh&auml;ltnis</div>
        </div>
        <div class="sono-pstrip-legend">
          <div class="sono-pleg"><i style="height:14px"></i><div><b>Profil 18</b> &ndash; das Fundament. Breite Fl&auml;chen f&uuml;r Ruhe und maximale Blickdichte.</div></div>
          <div class="sono-pleg"><i style="height:9px"></i><div><b>Profil 10</b> &ndash; der Allrounder. Bringt Struktur, ohne zu dominieren.</div></div>
          <div class="sono-pleg"><i style="height:5px"></i><div><b>Profil 5</b> &ndash; der Akzent. Feine Linien, die dem Zaun Spannung geben.</div></div>
        </div>
      </div>

      <!-- ---- Abstandhalter (eigenes helles Kapitel-Panel) ---- -->
      <div class="sono-panel">
        <div class="sono-panel-top">
          <div class="sono-subhead sono-subhead--left">
            <span class="sono-eyebrow">Abstandhalter</span>
            <h3>Dein Regler f&uuml;r Blickdichte</h3>
            <p>Ein Abstandhalter ist ein kurzes Aluminium-St&uuml;ck, das zwischen zwei Profilen in die Pfostennut geschoben wird. Er h&auml;lt die Profile auf gleichm&auml;&szlig;igem Abstand &ndash; und genau dieser Abstand entscheidet, wie viel Blick, Licht und Wind durch deinen Zaun gehen. Es gibt ihn in f&uuml;nf Gr&ouml;&szlig;en von 1 cm bis 6,5 cm.</p>
          </div>
          <div class="sono-panel-scale">
            <div class="sono-gaplist">
              <div class="sono-gap-col">
                <div class="sono-gap-row"><span class="sono-gap-diag"><i></i><i style="height:3px"></i><i></i></span><div><b>1 cm</b><span class="sono-gap-lab">feinste Fuge</span></div></div>
                <div class="sono-gap-row"><span class="sono-gap-diag"><i></i><i style="height:5px"></i><i></i></span><div><b>1,75 cm</b><span class="sono-gap-lab">Schattenfuge</span></div></div>
                <div class="sono-gap-row"><span class="sono-gap-diag"><i></i><i style="height:7px"></i><i></i></span><div><b>2,25 cm</b><span class="sono-gap-lab">Schattenfuge</span></div></div>
              </div>
              <div class="sono-gap-col">
                <div class="sono-gap-row"><span class="sono-gap-diag"><i></i><i style="height:11px"></i><i></i></span><div><b>3,5 cm</b><span class="sono-gap-lab">sichtbare Luft</span></div></div>
                <div class="sono-gap-row"><span class="sono-gap-diag"><i></i><i style="height:18px"></i><i></i></span><div><b>6,5 cm</b><span class="sono-gap-lab">maximal offen</span></div></div>
              </div>
            </div>
            <p class="sono-panel-note">F&uuml;nf Gr&ouml;&szlig;en im Gr&ouml;&szlig;enverh&auml;ltnis zueinander. Frei kombinierbar &ndash; unten dicht, oben luftig ist eines der beliebtesten Muster.</p>
          </div>
        </div>
        <div class="sono-spacer-grid">
        <div class="sono-spacer-card">
          <svg viewBox="0 0 140 116" role="img" aria-label="Zaunfeld ohne Abstandhalter, zehn Profile liegen direkt aufeinander">
            <rect x="6" y="0" width="8" height="116" rx="2" fill="#FAA41A"/><rect x="126" y="0" width="8" height="116" rx="2" fill="#FAA41A"/>
            <g fill="#383E42"><rect x="20" y="4" width="100" height="10.8" rx="1"/><rect x="20" y="14.8" width="100" height="10.8" rx="1"/><rect x="20" y="25.6" width="100" height="10.8" rx="1"/><rect x="20" y="36.4" width="100" height="10.8" rx="1"/><rect x="20" y="47.2" width="100" height="10.8" rx="1"/><rect x="20" y="58" width="100" height="10.8" rx="1"/><rect x="20" y="68.8" width="100" height="10.8" rx="1"/><rect x="20" y="79.6" width="100" height="10.8" rx="1"/><rect x="20" y="90.4" width="100" height="10.8" rx="1"/><rect x="20" y="101.2" width="100" height="10.8" rx="1"/></g>
            <g fill="#565c61"><rect x="20" y="5.1" width="100" height="1"/><rect x="20" y="15.9" width="100" height="1"/><rect x="20" y="26.7" width="100" height="1"/><rect x="20" y="37.5" width="100" height="1"/><rect x="20" y="48.3" width="100" height="1"/><rect x="20" y="59.1" width="100" height="1"/><rect x="20" y="69.9" width="100" height="1"/><rect x="20" y="80.7" width="100" height="1"/><rect x="20" y="91.5" width="100" height="1"/><rect x="20" y="102.3" width="100" height="1"/></g>
          </svg>
          <h4>Ohne Abstandhalter</h4>
          <p>Profil auf Profil, ohne Luft dazwischen. Maximal blickdicht und windgesch&uuml;tzt &ndash; die ruhigste, geschlossenste Optik.</p>
        </div>
        <div class="sono-spacer-card">
          <svg viewBox="0 0 140 116" role="img" aria-label="Zaunfeld mit schmalen Abstandhaltern, feine Schattenfugen zwischen den Profilen">
            <rect x="6" y="0" width="8" height="116" rx="2" fill="#FAA41A"/><rect x="126" y="0" width="8" height="116" rx="2" fill="#FAA41A"/>
            <g fill="#383E42"><rect x="20" y="4.6" width="100" height="10.8" rx="1"/><rect x="20" y="16.6" width="100" height="10.8" rx="1"/><rect x="20" y="28.6" width="100" height="10.8" rx="1"/><rect x="20" y="40.6" width="100" height="10.8" rx="1"/><rect x="20" y="52.6" width="100" height="10.8" rx="1"/><rect x="20" y="64.6" width="100" height="10.8" rx="1"/><rect x="20" y="76.6" width="100" height="10.8" rx="1"/><rect x="20" y="88.6" width="100" height="10.8" rx="1"/><rect x="20" y="100.6" width="100" height="10.8" rx="1"/></g>
            <g fill="#565c61"><rect x="20" y="5.7" width="100" height="1"/><rect x="20" y="17.7" width="100" height="1"/><rect x="20" y="29.7" width="100" height="1"/><rect x="20" y="41.7" width="100" height="1"/><rect x="20" y="53.7" width="100" height="1"/><rect x="20" y="65.7" width="100" height="1"/><rect x="20" y="77.7" width="100" height="1"/><rect x="20" y="89.7" width="100" height="1"/><rect x="20" y="101.7" width="100" height="1"/></g>
            <g fill="#FAA41A"><rect x="20" y="15.4" width="16" height="1.2"/><rect x="104" y="15.4" width="16" height="1.2"/><rect x="20" y="27.4" width="16" height="1.2"/><rect x="104" y="27.4" width="16" height="1.2"/><rect x="20" y="39.4" width="16" height="1.2"/><rect x="104" y="39.4" width="16" height="1.2"/><rect x="20" y="51.4" width="16" height="1.2"/><rect x="104" y="51.4" width="16" height="1.2"/><rect x="20" y="63.4" width="16" height="1.2"/><rect x="104" y="63.4" width="16" height="1.2"/><rect x="20" y="75.4" width="16" height="1.2"/><rect x="104" y="75.4" width="16" height="1.2"/><rect x="20" y="87.4" width="16" height="1.2"/><rect x="104" y="87.4" width="16" height="1.2"/><rect x="20" y="99.4" width="16" height="1.2"/><rect x="104" y="99.4" width="16" height="1.2"/></g>
          </svg>
          <h4>Schmal &middot; 1 bis 2,25 cm</h4>
          <p>Feine Schattenfuge zwischen den Profilen. Aus normalem Betrachtungsabstand kaum durchsichtig, wirkt aber deutlich leichter.</p>
        </div>
        <div class="sono-spacer-card">
          <svg viewBox="0 0 140 116" role="img" aria-label="Zaunfeld mit 6,5 cm Abstandhaltern, offene Lamellenoptik mit sieben Profilen">
            <rect x="6" y="0" width="8" height="116" rx="2" fill="#FAA41A"/><rect x="126" y="0" width="8" height="116" rx="2" fill="#FAA41A"/>
            <g fill="#383E42"><rect x="20" y="8.5" width="100" height="10.8" rx="1"/><rect x="20" y="23.2" width="100" height="10.8" rx="1"/><rect x="20" y="37.9" width="100" height="10.8" rx="1"/><rect x="20" y="52.6" width="100" height="10.8" rx="1"/><rect x="20" y="67.3" width="100" height="10.8" rx="1"/><rect x="20" y="82" width="100" height="10.8" rx="1"/><rect x="20" y="96.7" width="100" height="10.8" rx="1"/></g>
            <g fill="#565c61"><rect x="20" y="9.6" width="100" height="1"/><rect x="20" y="24.3" width="100" height="1"/><rect x="20" y="39" width="100" height="1"/><rect x="20" y="53.7" width="100" height="1"/><rect x="20" y="68.4" width="100" height="1"/><rect x="20" y="83.1" width="100" height="1"/><rect x="20" y="97.8" width="100" height="1"/></g>
            <g fill="#FAA41A"><rect x="20" y="19.3" width="16" height="3.9" rx="1"/><rect x="104" y="19.3" width="16" height="3.9" rx="1"/><rect x="20" y="34" width="16" height="3.9" rx="1"/><rect x="104" y="34" width="16" height="3.9" rx="1"/><rect x="20" y="48.7" width="16" height="3.9" rx="1"/><rect x="104" y="48.7" width="16" height="3.9" rx="1"/><rect x="20" y="63.4" width="16" height="3.9" rx="1"/><rect x="104" y="63.4" width="16" height="3.9" rx="1"/><rect x="20" y="78.1" width="16" height="3.9" rx="1"/><rect x="104" y="78.1" width="16" height="3.9" rx="1"/><rect x="20" y="92.8" width="16" height="3.9" rx="1"/><rect x="104" y="92.8" width="16" height="3.9" rx="1"/></g>
          </svg>
          <h4>Breit &middot; 3,5 bis 6,5 cm</h4>
          <p>Sichtbare Luft zwischen den Profilen. Offene Lamellen-Optik &ndash; Licht und Wind gehen durch.</p>
        </div>
      </div>
      <p class="sono-spacer-legend"><i></i> Orange markiert = Abstandhalter. Alle Beispiele zeigen ein 180-cm-Feld im gleichen Ma&szlig;stab: Je breiter der Abstandhalter, desto weniger Profile brauchst du f&uuml;r dieselbe H&ouml;he.</p>
      </div>

      <!-- ---- Oberflächen ---- -->
      <div class="sono-subhead">
        <h3>Drei Oberfl&auml;chen</h3>
        <p>Alle SONO-Profile sind pulverbeschichtet: matt, schmutzunempfindlich und ohne Streichen oder &Ouml;len dauerhaft sch&ouml;n.</p>
      </div>
      <div class="sono-swatches">
        <div class="sono-swatch">
          <div class="sono-swatch-color sono-swatch-anthrazit"></div>
          <div class="sono-swatch-body"><h3>Anthrazit RAL 7016</h3><p>Der moderne Klassiker, passend zu Fensterrahmen und Fassade.</p></div>
        </div>
        <div class="sono-swatch">
          <div class="sono-swatch-color sono-swatch-silber"></div>
          <div class="sono-swatch-body"><h3>Silber</h3><p>Hell und metallisch. Setzt Akzente, besonders neben dunklen Pfosten.</p></div>
        </div>
        <div class="sono-swatch">
          <div class="sono-swatch-color sono-swatch-laerche"></div>
          <div class="sono-swatch-body"><h3>L&auml;rchenoptik</h3><p>Warme Holzt&ouml;ne mit sichtbarer Maserung &ndash; pflegefrei wie Aluminium.</p></div>
        </div>
      </div>
    </div>
  </section>

  <!-- ============ HÖHEN ============ -->
  <section class="sono-sec sono-sec--alt">
    <div class="sono-wrap">
      <div class="sono-sec-head">
        <span class="sono-eyebrow">Erste Entscheidung</span>
        <h2>Wie viel Sichtschutz brauchst du?</h2>
        <p>SONO gibt es in vier Höhen. Wähle deine Höhe und sieh dir die passenden Designs an.</p>
      </div>
      <?php if ( 'split' === $sono_heights_layout ) :
        $sono_hfirst = $sono_heights[ 180 ]; ?>
      <div class="sono-hsp-grid">
        <div class="sono-hsp-img"<?php echo $sono_hfirst['img'] ? ' style="background-image:url(\'' . esc_url( $sono_hfirst['img'] ) . '\')"' : ''; ?>>
          <span class="sono-hsp-cap">180 cm &middot; <?php echo esc_html( $sono_hfirst['label'] ); ?></span>
        </div>
        <div class="sono-hsp-list">
          <?php foreach ( array( 180, 150, 120, 90 ) as $hi => $h ) : $hc = $sono_heights[ $h ]; ?>
          <a class="sono-hsp-item<?php echo 0 === $hi ? ' is-on' : ''; ?>" href="#sono-h<?php echo (int) $h; ?>" data-sono-himg="<?php echo esc_url( $hc['img'] ); ?>" data-sono-hcap="<?php echo esc_attr( $h . ' cm · ' . $hc['label'] ); ?>">
            <span class="sono-hsp-thumb"<?php echo $hc['img'] ? ' style="background-image:url(\'' . esc_url( $hc['img'] ) . '\')"' : ''; ?>></span>
            <div class="sono-hsp-num"><?php echo (int) $h; ?><span>&nbsp;cm</span></div>
            <div class="sono-hsp-meta"><b><?php echo esc_html( $hc['label'] ); ?></b><p><?php echo esc_html( $hc['text'] ); ?></p></div>
            <span class="sono-hsp-chev">&#8250;</span>
          </a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php else :
        $sono_mo_cls = array( 180 => 'sono-hmo-big', 150 => 'sono-hmo-wide', 120 => '', 90 => '' ); ?>
      <div class="sono-hmo-grid">
        <?php foreach ( array( 180, 150, 120, 90 ) as $h ) :
          $hc  = $sono_heights[ $h ];
          $cls = $sono_mo_cls[ $h ];
          $sty = $hc['img'] ? ' style="background-image:url(\'' . esc_url( $hc['img'] ) . '\')"' : ''; ?>
        <a class="sono-hmo-t<?php echo $cls ? ' ' . $cls : ''; ?>" href="#sono-h<?php echo (int) $h; ?>"<?php echo $sty; ?>>
          <?php if ( ! $hc['img'] ) : ?><span class="sono-hmo-noimg">Bild <?php echo (int) $h; ?> cm</span><?php endif; ?>
          <div class="sono-hmo-in">
            <div class="sono-hmo-num"><?php echo (int) $h; ?><span>&nbsp;cm</span></div>
            <div class="sono-hmo-lab"><?php echo esc_html( $hc['label'] ); ?></div>
          </div>
        </a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </section>

  <!-- ============ MODELLE (automatisch aus dem Plugin MH SONO Grid) ============ -->
  <section class="sono-sec" id="sono-modelle">
    <div class="sono-wrap">
      <?php
      if ( shortcode_exists( 'mh_sono_grid' ) ) {
        echo do_shortcode( '[mh_sono_grid category="sono"]' );
      } else {
        echo '<!-- MH SONO Grid Plugin nicht aktiv -->';
      }
      ?>
    </div>
  </section>


  <!-- ============ FAQ ============ -->
  <section class="sono-sec sono-sec--alt">
    <div class="sono-wrap">
      <div class="sono-sec-head">
        <span class="sono-eyebrow">Gut zu wissen</span>
        <h2>Häufige Fragen zu SONO</h2>
      </div>
      <div class="sono-faq-list" id="sono-faq-list">
        <div class="sono-faq-item">
          <button class="sono-faq-q" type="button">Sind die Pfosten im Lieferumfang enthalten?</button>
          <div class="sono-faq-a"><div class="sono-faq-a-inner">Nein, die MEGA FLEX Alu-Pfosten bestellst du separat, passend zu deiner Feldanzahl. Wenn du schon ein MEGA FLEX System hast, kannst du deine vorhandenen Pfosten weiterverwenden. Faustregel: ein Pfosten mehr als Zaunfelder (3 Felder = 4 Pfosten).</div></div>
        </div>
        <div class="sono-faq-item">
          <button class="sono-faq-q" type="button">Warum wird meine Bestellung auf volle Verpackungen aufgerundet?</button>
          <div class="sono-faq-a"><div class="sono-faq-a-inner">Die Profile werden in festen Verpackungseinheiten geliefert, um Transportschäden zu vermeiden. Dein Warenkorb rundet automatisch auf volle Verpackungen auf. Übrige Profile kannst du als Reserve behalten oder für spätere Erweiterungen nutzen.</div></div>
        </div>
        <div class="sono-faq-item">
          <button class="sono-faq-q" type="button">Kann ich ein Modell abwandeln oder ein eigenes Muster bauen?</button>
          <div class="sono-faq-a"><div class="sono-faq-a-inner">Ja. Die <?php echo esc_html( $sono_n_models ); ?> Modelle sind erprobte Profilfolgen, du kannst aber jedes Profil einzeln bestellen und deine eigene Reihenfolge zusammenstellen. Ein 3D-Planer mit SONO-Unterstützung ist in Arbeit.</div></div>
        </div>
        <div class="sono-faq-item">
          <button class="sono-faq-q" type="button">Passt SONO in mein bestehendes MEGA FLEX System?</button>
          <div class="sono-faq-a"><div class="sono-faq-a-inner">Ja. SONO-Profile nutzen dieselben Pfostennuten wie alle MEGA FLEX Füllungen. Du kannst SONO-Felder mit WPC- oder Rhombus-Feldern im selben Zaunverlauf kombinieren oder einzelne Felder später tauschen.</div></div>
        </div>
        <div class="sono-faq-item">
          <button class="sono-faq-q" type="button">Wie pflegeintensiv ist der SONO Sichtschutz?</button>
          <div class="sono-faq-a"><div class="sono-faq-a-inner">Das pulverbeschichtete Aluminium ist UV-stabil, rostfrei und schmutzunempfindlich. Gelegentliches Abwischen mit Wasser genügt. Streichen, Ölen oder Lasieren entfällt komplett.</div></div>
        </div>
        <div class="sono-faq-item">
          <button class="sono-faq-q" type="button">Wie schwer ist die Montage?</button>
          <div class="sono-faq-a"><div class="sono-faq-a-inner">SONO ist ein Stecksystem: Pfosten setzen, Profile und Abstandhalter von oben einschieben, Befestigungsset montieren. Zu zweit schafft man ein Zaunfeld ohne Vorerfahrung in unter einer Stunde.</div></div>
        </div>
      </div>
    </div>
  </section>

  <!-- ============ ABSCHLUSS ============ -->
  <section class="sono-sec sono-final">
    <div class="sono-wrap">
      <h2>Finde dein SONO Design</h2>
      <p>Am besten startest du bei der Höhe, die zu deinem Garten passt — und wählst dann dein Muster.</p>
      <div class="sono-final-facts">
        <span><b><?php echo esc_html( $sono_n_models ); ?></b>Designs</span>
        <span><b><?php echo esc_html( $sono_n_heights ); ?></b>Höhen</span>
        <span><b><?php echo esc_html( $sono_n_finishes ); ?></b>Oberflächen</span>
      </div>
      <a class="sono-btn" href="#sono-modelle">Alle <?php echo esc_html( $sono_n_models ); ?> Designs ansehen</a>
      <div class="sono-planer-teaser">
        <b>Bald verfügbar: der SONO 3D-Planer.</b>
        <p>Eigene Muster zusammenstellen, in 3D ansehen und direkt bestellen.</p>
      </div>
    </div>
  </section>

</div>

<script>
/* SONO LP: FAQ-Accordion + Smooth Scroll (ES5).
   Oberflaechen-Umschalter + Filter liegen im Plugin MH SONO Grid. */
(function(){
  'use strict';

  function initFaq(){
    var list = document.getElementById('sono-faq-list');
    if(!list){ return; }
    var items = list.getElementsByClassName('sono-faq-item');
    var i;
    for(i=0;i<items.length;i++){
      (function(item, idx){
        var btn = item.getElementsByClassName('sono-faq-q')[0];
        var panel = item.getElementsByClassName('sono-faq-a')[0];
        var pid = 'sono-faq-panel-' + idx;
        panel.setAttribute('id', pid);
        panel.setAttribute('role', 'region');
        btn.setAttribute('aria-expanded', 'false');
        btn.setAttribute('aria-controls', pid);
        btn.addEventListener('click', function(){
          var open = item.className.indexOf('sono-open') !== -1;
          if(open){
            item.className = item.className.replace(' sono-open','');
            panel.style.maxHeight = '0px';
            btn.setAttribute('aria-expanded', 'false');
          } else {
            item.className += ' sono-open';
            panel.style.maxHeight = panel.scrollHeight + 'px';
            btn.setAttribute('aria-expanded', 'true');
          }
        });
      })(items[i], i);
    }
  }

  function initScroll(){
    var links = document.querySelectorAll('.sono-lp a[href^="#sono-"]');
    var i;
    for(i=0;i<links.length;i++){
      links[i].addEventListener('click', function(e){
        var id = this.getAttribute('href').substring(1);
        var target = document.getElementById(id);
        if(target){
          e.preventDefault();
          target.scrollIntoView({behavior:'smooth', block:'start'});
        }
      });
    }
  }

  if(document.readyState === 'loading'){
    document.addEventListener('DOMContentLoaded', function(){ initFaq(); initScroll(); });
  } else {
    initFaq(); initScroll();
  }
})();
</script>
<script>
/* SONO Höhen Editorial-Split: Bild + Caption beim Hover/Fokus der Liste wechseln (ES5) */
(function(){
  var grid = document.querySelector('.sono-hsp-grid');
  if(!grid){ return; }
  var img   = grid.querySelector('.sono-hsp-img');
  var cap   = grid.querySelector('.sono-hsp-cap');
  var items = grid.querySelectorAll('.sono-hsp-item');
  function activate(el){
    var i;
    for(i=0;i<items.length;i++){ items[i].classList.remove('is-on'); }
    el.classList.add('is-on');
    var u = el.getAttribute('data-sono-himg');
    if(img && u){ img.style.backgroundImage = "url('" + u + "')"; }
    var c = el.getAttribute('data-sono-hcap');
    if(cap && c){ cap.textContent = c; }
  }
  var j;
  for(j=0;j<items.length;j++){
    (function(el){
      el.addEventListener('mouseenter', function(){ activate(el); });
      el.addEventListener('focus', function(){ activate(el); });
    })(items[j]);
  }
})();
</script>
