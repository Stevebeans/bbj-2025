<?php
/**
 * Live Feed Updates archive — BBJ Feed Updates Hub.
 *
 * Design: .claude/claude-design/bbj-home-page/project/BBJ Feed Updates Hub.html
 *
 * Skeleton scope: server-render layout, real data where it exists, DOM filter
 * for categories. Deferred (per Sprint D scope): auto-refresh poll (Premium),
 * vote interactivity, pinned mechanic, houseguest chips per update,
 * trending hashtag system, inline quote blocks.
 */

$paged       = max(1, (int) get_query_var('paged'));
$per_page    = 20;
$skip_first  = ($paged === 1);

$season_meta = bbj_v2_fuh_current_season_meta();
$counts      = bbj_v2_fuh_stat_counts();
$featured    = ($paged === 1) ? bbj_v2_fuh_featured_update() : null;
$thread      = bbj_v2_fuh_thread_updates($paged, $per_page, $skip_first);
$cats        = bbj_v2_fuh_category_counts();
$hot_posts   = bbj_v2_fuh_hot_posts(4);
$live        = bbj_v2_fuh_live_status();

$brand_abbr  = $season_meta['abbr'] ?: 'BBJ';
$brand_name  = $season_meta['name'] ?: 'Big Brother';

// Group thread rows by date_key (YYYY-MM-DD).
$grouped = [];
foreach ($thread['rows'] as $row) {
    $key = $row['date_key'];
    $grouped[$key][] = $row;
}

get_header();
?>

<style>
  /* Feed Updates Hub — scoped under .fuh- prefix to avoid theme/Tailwind class collisions */
  .fuh-page{background:#FBFAF6;color:#15181E;font-family:'Inter Tight',system-ui,sans-serif;font-size:15px;line-height:1.5;padding:22px 0 60px}
  .fuh-page *{box-sizing:border-box}
  .fuh-page .fuh-wrap{max-width:1280px;margin:0 auto;padding:0 20px}
  .fuh-page a{color:inherit;text-decoration:none}

  /* Page header */
  .fuh-page-h{display:flex;align-items:flex-end;justify-content:space-between;gap:20px;padding-bottom:14px;margin-bottom:20px;border-bottom:2px solid #15181E;flex-wrap:wrap}
  .fuh-page-h .fuh-kk{font-family:'IBM Plex Mono',monospace;font-size:11px;letter-spacing:.12em;text-transform:uppercase;color:#D23B2B;font-weight:600;display:inline-flex;align-items:center;gap:6px}
  .fuh-page-h .fuh-kk::before{content:"";width:7px;height:7px;background:#D23B2B;border-radius:50%;animation:fuh-blink 1.2s infinite}
  @keyframes fuh-blink{50%{opacity:.3}}
  .fuh-page-h h1{font-family:'Oswald',sans-serif;font-size:44px;font-weight:600;letter-spacing:-.01em;line-height:1.05;margin:6px 0 0;text-wrap:balance}
  .fuh-page-h h1 em{font-style:normal;color:#35546e;border-bottom:3px solid #FFBF0F;padding-bottom:2px}
  .fuh-page-h .fuh-meta{font-family:'IBM Plex Mono',monospace;font-size:11px;color:#6B7280;letter-spacing:.04em;text-transform:uppercase;margin-top:8px;display:flex;gap:12px;flex-wrap:wrap}
  .fuh-page-h .fuh-meta span b{color:#15181E;font-weight:600;margin-right:4px}
  .fuh-page-h .fuh-actions{display:flex;gap:8px}
  .fuh-btn-prim{padding:10px 16px;background:#35546e;color:#fff;font-family:'Oswald',sans-serif;text-transform:uppercase;font-weight:600;font-size:13px;letter-spacing:.05em;border-radius:3px;display:inline-flex;align-items:center;gap:6px}
  .fuh-btn-prim:hover{background:#233C52}
  .fuh-btn-ghost{padding:10px 14px;border:1px solid #D6DADF;background:#fff;font-family:'IBM Plex Mono',monospace;font-size:11px;letter-spacing:.06em;text-transform:uppercase;color:#3A404B;border-radius:3px;font-weight:600;display:inline-flex;gap:6px;align-items:center;cursor:pointer}
  .fuh-btn-ghost:hover{border-color:#35546e;color:#35546e}

  /* Live status banner */
  .fuh-livebar{background:#15181E;color:#fff;border-radius:6px;padding:14px 18px;margin-bottom:22px;display:grid;grid-template-columns:auto 1fr auto;gap:18px;align-items:center;position:relative;overflow:hidden}
  .fuh-livebar::before{content:"";position:absolute;top:-40px;right:-20px;width:180px;height:180px;background:radial-gradient(circle,#FFBF0F,transparent 70%);opacity:.18}
  .fuh-livebar .fuh-pulse{display:inline-flex;align-items:center;gap:8px;font-family:'IBM Plex Mono',monospace;font-size:10px;letter-spacing:.12em;text-transform:uppercase;color:#FFBF0F;font-weight:700}
  .fuh-livebar .fuh-pulse::before{content:"";width:10px;height:10px;background:#D23B2B;border-radius:50%;animation:fuh-blink 1.2s infinite;box-shadow:0 0 0 4px rgba(210,59,43,.25)}
  .fuh-livebar .fuh-summary{font-family:'Source Serif 4',Georgia,serif;font-size:15px;line-height:1.4}
  .fuh-livebar .fuh-summary b{color:#FFBF0F;font-weight:600}
  .fuh-livebar .fuh-stats{display:flex;gap:22px;font-family:'IBM Plex Mono',monospace;font-size:10px;letter-spacing:.08em;text-transform:uppercase;color:rgba(255,255,255,.7)}
  .fuh-livebar .fuh-stats span b{display:block;font-family:'Oswald',sans-serif;font-size:22px;color:#fff;letter-spacing:.01em;line-height:1;font-weight:600;margin-bottom:2px}

  /* Layout grid */
  .fuh-grid{display:grid;grid-template-columns:minmax(0,1fr) 320px;gap:28px}
  .fuh-grid .fuh-main{min-width:0}

  /* Featured / latest thread */
  .fuh-featured{background:#fff;border:1px solid #E5E7EB;border-radius:6px;overflow:hidden;margin-bottom:22px;display:grid;grid-template-columns:280px 1fr;gap:0}
  .fuh-featured .fuh-ph{aspect-ratio:1/1;background:linear-gradient(135deg,#1E3448 0%,#35546e 55%,#567B96 100%);position:relative;display:flex;align-items:flex-end;padding:14px;color:#fff;overflow:hidden}
  .fuh-featured .fuh-ph img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;z-index:0}
  .fuh-featured .fuh-ph::before{content:"";position:absolute;inset:0;background:radial-gradient(120% 70% at 30% 40%,rgba(255,255,255,.18),transparent 60%), repeating-linear-gradient(45deg,rgba(255,255,255,.03) 0 10px,transparent 10px 20px);z-index:1}
  .fuh-featured .fuh-ph .fuh-pill{position:absolute;top:14px;left:14px;background:#D23B2B;color:#fff;font-family:'IBM Plex Mono',monospace;font-size:10px;letter-spacing:.12em;text-transform:uppercase;padding:4px 8px;font-weight:600;z-index:2}
  .fuh-featured .fuh-ph .fuh-name{position:relative;font-family:'Oswald',sans-serif;font-size:14px;letter-spacing:.05em;text-transform:uppercase;opacity:.9;z-index:2;text-shadow:0 1px 2px rgba(0,0,0,.4)}
  .fuh-featured .fuh-bd{padding:22px 24px;display:flex;flex-direction:column;justify-content:center}
  .fuh-featured .fuh-bd .fuh-kk{font-family:'IBM Plex Mono',monospace;font-size:10px;letter-spacing:.12em;text-transform:uppercase;color:#6B7280;font-weight:600}
  .fuh-featured .fuh-bd .fuh-kk b{color:#D23B2B;margin-right:6px}
  .fuh-featured .fuh-bd h2{font-family:'Oswald',sans-serif;font-size:28px;font-weight:600;line-height:1.1;margin:8px 0 10px;text-wrap:balance}
  .fuh-featured .fuh-bd h2 a:hover{color:#35546e}
  .fuh-featured .fuh-bd p{font-family:'Source Serif 4',Georgia,serif;font-size:15px;color:#3A404B;line-height:1.55;max-width:58ch;text-wrap:pretty}
  .fuh-featured .fuh-bd .fuh-foot{display:flex;align-items:center;gap:14px;margin-top:14px;font-family:'IBM Plex Mono',monospace;font-size:11px;color:#6B7280;letter-spacing:.04em;text-transform:uppercase;flex-wrap:wrap}
  .fuh-featured .fuh-bd .fuh-foot .fuh-by{color:#15181E;font-weight:600}
  .fuh-featured .fuh-bd .fuh-foot .fuh-read{color:#35546e;font-weight:600;margin-left:auto}

  /* Toolbar */
  .fuh-toolbar{background:#fff;border:1px solid #E5E7EB;border-radius:6px;padding:12px 14px;display:flex;align-items:center;gap:14px;flex-wrap:wrap;margin-bottom:18px}
  .fuh-toolbar .fuh-tabs{display:flex;gap:4px;padding:3px;background:#F3F1EA;border-radius:999px}
  .fuh-toolbar .fuh-tabs a{padding:6px 12px;font-family:'Oswald',sans-serif;font-size:12px;font-weight:600;letter-spacing:.05em;text-transform:uppercase;color:#3A404B;border-radius:999px;display:inline-flex;gap:5px;align-items:center;cursor:pointer}
  .fuh-toolbar .fuh-tabs a .fuh-ct{font-family:'IBM Plex Mono',monospace;font-size:10px;opacity:.7}
  .fuh-toolbar .fuh-tabs a:hover{color:#35546e}
  .fuh-toolbar .fuh-tabs a.on{background:#35546e;color:#fff}
  .fuh-toolbar .fuh-tabs a.on .fuh-ct{opacity:.95}
  .fuh-toolbar .fuh-drop{position:relative;font-family:'IBM Plex Mono',monospace;font-size:11px;letter-spacing:.04em;color:#6B7280;text-transform:uppercase;display:inline-flex;align-items:center;gap:6px}
  .fuh-toolbar .fuh-drop select{appearance:none;background:#fff;border:1px solid #D6DADF;padding:6px 26px 6px 10px;border-radius:3px;font-family:'Oswald',sans-serif;font-size:12px;letter-spacing:.04em;text-transform:uppercase;color:#15181E;font-weight:600;cursor:pointer}
  .fuh-toolbar .fuh-drop::after{content:"\25BE";position:absolute;right:10px;top:50%;transform:translateY(-50%);color:#3A404B;pointer-events:none;font-size:10px}
  .fuh-toolbar .fuh-right{margin-left:auto;display:flex;gap:8px;align-items:center}
  .fuh-toolbar .fuh-search{position:relative}
  .fuh-toolbar .fuh-search input{width:200px;padding:7px 10px 7px 30px;border:1px solid #D6DADF;border-radius:3px;font-family:'IBM Plex Mono',monospace;font-size:12px;background:#F3F1EA}
  .fuh-toolbar .fuh-search input:focus{outline:2px solid #35546e;outline-offset:1px;background:#fff}
  .fuh-toolbar .fuh-search svg{position:absolute;left:9px;top:50%;transform:translateY(-50%);width:13px;height:13px;color:#6B7280}

  /* Day divider */
  .fuh-daybar{display:flex;align-items:center;gap:12px;margin:20px 0 12px;position:sticky;top:0;background:#FBFAF6;padding:8px 0;z-index:2}
  .fuh-daybar .fuh-tag{display:inline-flex;align-items:center;gap:8px;background:#15181E;color:#fff;font-family:'Oswald',sans-serif;font-size:13px;letter-spacing:.05em;text-transform:uppercase;font-weight:600;padding:6px 12px;border-radius:3px}
  .fuh-daybar .fuh-tag b{color:#FFBF0F;font-weight:600}
  .fuh-daybar .fuh-date{font-family:'IBM Plex Mono',monospace;font-size:10px;letter-spacing:.08em;text-transform:uppercase;color:#6B7280}
  .fuh-daybar .fuh-line{flex:1;height:1px;background:#E5E7EB}
  .fuh-daybar .fuh-count{font-family:'IBM Plex Mono',monospace;font-size:10px;color:#6B7280;letter-spacing:.04em;text-transform:uppercase}
  .fuh-daybar .fuh-count b{color:#15181E;font-weight:600}

  /* Update cards */
  .fuh-thread{display:grid;gap:10px}
  .fuh-upd{background:#fff;border:1px solid #E5E7EB;border-radius:5px;padding:14px 16px 14px 18px;display:grid;grid-template-columns:44px 1fr;gap:12px;position:relative;transition:border-color .15s}
  .fuh-upd::before{content:"";position:absolute;left:0;top:12px;bottom:12px;width:3px;border-radius:0 3px 3px 0;background:#FFBF0F}
  .fuh-upd[data-cat="drama"]::before{background:#D23B2B}
  .fuh-upd[data-cat="ceremony"]::before,
  .fuh-upd[data-cat="eviction"]::before,
  .fuh-upd[data-cat="competition"]::before{background:#1D8A5C}
  .fuh-upd[data-cat="strategy"]::before,
  .fuh-upd[data-cat="alliance"]::before{background:#35546e}
  .fuh-upd[data-cat="chitchat"]::before,
  .fuh-upd[data-cat="showmance"]::before{background:#D6DADF}
  .fuh-upd[data-cat="punishment"]::before,
  .fuh-upd[data-cat="reward"]::before{background:#6E4A9E}
  .fuh-upd:hover{border-color:#35546e}

  .fuh-upd .fuh-vote{display:flex;flex-direction:column;align-items:center;gap:2px;padding-top:2px;user-select:none}
  .fuh-upd .fuh-vote button{width:28px;height:24px;display:grid;place-items:center;color:#6B7280;border-radius:3px;font-size:13px;background:transparent;border:0;cursor:pointer}
  .fuh-upd .fuh-vote button:hover{background:#F3F1EA;color:#35546e}
  .fuh-upd .fuh-vote .fuh-n{font-family:'Oswald',sans-serif;font-size:15px;font-weight:600;color:#15181E;line-height:1}

  .fuh-upd .fuh-body{min-width:0}
  .fuh-upd .fuh-head{display:flex;align-items:center;gap:8px;flex-wrap:wrap;font-family:'IBM Plex Mono',monospace;font-size:10px;color:#6B7280;letter-spacing:.04em;text-transform:uppercase;margin-bottom:4px}
  .fuh-upd .fuh-head .fuh-av{width:22px;height:22px;border-radius:50%;background:linear-gradient(135deg,#35546e,#1E3448);color:#fff;display:grid;place-items:center;font-family:'Oswald',sans-serif;font-size:10px;font-weight:600;text-transform:uppercase}
  .fuh-upd .fuh-head .fuh-by{color:#15181E;font-weight:600;text-transform:none;font-family:'Inter Tight',system-ui,sans-serif;font-size:12px;letter-spacing:0}
  .fuh-upd .fuh-head .fuh-t{margin-left:auto}
  .fuh-upd .fuh-head .fuh-t b{color:#15181E;font-weight:600}
  .fuh-upd .fuh-cat{display:inline-block;font-family:'IBM Plex Mono',monospace;font-size:9px;letter-spacing:.08em;text-transform:uppercase;padding:2px 6px;border-radius:2px;font-weight:600;background:#F3F1EA;color:#3A404B}
  .fuh-upd[data-cat="drama"] .fuh-cat{background:#FDE8E6;color:#9B2315}
  .fuh-upd[data-cat="ceremony"] .fuh-cat,
  .fuh-upd[data-cat="eviction"] .fuh-cat,
  .fuh-upd[data-cat="competition"] .fuh-cat{background:#DCF2E6;color:#0F5E3E}
  .fuh-upd[data-cat="strategy"] .fuh-cat,
  .fuh-upd[data-cat="alliance"] .fuh-cat{background:#E3ECFA;color:#233C52}
  .fuh-upd[data-cat="punishment"] .fuh-cat,
  .fuh-upd[data-cat="reward"] .fuh-cat{background:#EFE6F8;color:#4A2D7A}
  .fuh-upd .fuh-loc{display:inline-block;font-family:'IBM Plex Mono',monospace;font-size:9px;letter-spacing:.08em;text-transform:uppercase;padding:2px 6px;border-radius:2px;font-weight:600;background:transparent;border:1px solid #D6DADF;color:#6B7280}
  .fuh-upd h3{font-family:'Source Serif 4',Georgia,serif;font-size:18px;font-weight:600;line-height:1.25;color:#15181E;text-wrap:pretty;margin-bottom:4px}
  .fuh-upd h3:hover{color:#35546e}
  .fuh-upd p{font-family:'Source Serif 4',Georgia,serif;font-size:14px;line-height:1.55;color:#3A404B;margin-top:2px;text-wrap:pretty}
  .fuh-upd .fuh-foot{display:flex;align-items:center;gap:14px;margin-top:10px;padding-top:10px;border-top:1px dashed #D6DADF;font-family:'IBM Plex Mono',monospace;font-size:11px;color:#6B7280;letter-spacing:.02em;flex-wrap:wrap}
  .fuh-upd .fuh-foot a{color:#6B7280;display:inline-flex;align-items:center;gap:4px}
  .fuh-upd .fuh-foot a:hover{color:#35546e}
  .fuh-upd .fuh-foot .fuh-cmt{color:#35546e;font-weight:600}
  .fuh-upd .fuh-thumb{margin-top:10px;max-width:520px;border-radius:4px;overflow:hidden;background:#F3F1EA}
  .fuh-upd .fuh-thumb img{display:block;width:100%;height:auto}

  .fuh-loadmore{text-align:center;margin-top:18px}
  .fuh-loadmore a{display:inline-block;padding:12px 26px;background:#fff;border:1px solid #D6DADF;border-radius:4px;font-family:'Oswald',sans-serif;font-size:13px;font-weight:600;letter-spacing:.05em;text-transform:uppercase;color:#3A404B}
  .fuh-loadmore a:hover{border-color:#35546e;color:#35546e}
  .fuh-loadmore .fuh-hint{display:block;margin-top:6px;font-family:'IBM Plex Mono',monospace;font-size:10px;color:#6B7280;letter-spacing:.06em;text-transform:uppercase}

  /* Sidebar */
  .fuh-grid aside{min-width:0}
  .fuh-stick{position:sticky;top:16px;display:grid;gap:16px}
  .fuh-card{background:#fff;border:1px solid #E5E7EB;border-radius:6px;padding:16px 18px}
  .fuh-card h4{font-family:'Oswald',sans-serif;font-size:14px;letter-spacing:.08em;text-transform:uppercase;color:#15181E;font-weight:600;margin:0 0 12px;padding-bottom:10px;border-bottom:2px solid #15181E}
  .fuh-card.fuh-live-mini{background:linear-gradient(135deg,#35546e,#233C52);color:#fff;border:0}
  .fuh-card.fuh-live-mini h4{color:#fff;border-color:rgba(255,255,255,.2);display:flex;align-items:center;gap:8px}
  .fuh-card.fuh-live-mini h4::before{content:"";width:7px;height:7px;background:#D23B2B;border-radius:50%;animation:fuh-blink 1.2s infinite}
  .fuh-card.fuh-live-mini .fuh-row{display:grid;grid-template-columns:58px 1fr;gap:12px;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.08);font-family:'IBM Plex Mono',monospace;font-size:10px;letter-spacing:.08em;text-transform:uppercase;align-items:center}
  .fuh-card.fuh-live-mini .fuh-row:last-child{border-bottom:0}
  .fuh-card.fuh-live-mini .fuh-row .fuh-k{color:rgba(255,255,255,.65);font-weight:600}
  .fuh-card.fuh-live-mini .fuh-row .fuh-v{color:#fff;font-family:'Oswald',sans-serif;font-size:15px;letter-spacing:.02em;font-weight:600}
  .fuh-card.fuh-live-mini .fuh-cta{margin-top:12px;display:inline-flex;align-items:center;gap:6px;background:#FFBF0F;color:#15181E;padding:9px 14px;border-radius:3px;font-family:'Oswald',sans-serif;text-transform:uppercase;font-weight:600;font-size:12px;letter-spacing:.04em}
  .fuh-card.fuh-live-mini .fuh-cta:hover{background:#fff}
  .fuh-card.fuh-fcard h4{display:flex;justify-content:space-between;align-items:center}
  .fuh-card.fuh-fcard h4 small{font-family:'IBM Plex Mono',monospace;font-size:9px;color:#6B7280;letter-spacing:.06em;font-weight:500;text-transform:uppercase}
  .fuh-card.fuh-fcard label{display:flex;align-items:center;gap:10px;padding:6px 0;font-family:'Source Serif 4',Georgia,serif;font-size:14px;color:#3A404B;cursor:pointer}
  .fuh-card.fuh-fcard label input{accent-color:#35546e;width:14px;height:14px}
  .fuh-card.fuh-fcard label .fuh-ct{font-family:'IBM Plex Mono',monospace;font-size:10px;color:#6B7280;letter-spacing:.04em;margin-left:auto}
  .fuh-card.fuh-fcard label .fuh-dot{width:8px;height:8px;border-radius:50%;margin-left:6px}
  .fuh-card.fuh-fcard .fuh-glab{font-family:'IBM Plex Mono',monospace;font-size:9px;letter-spacing:.1em;text-transform:uppercase;color:#6B7280;font-weight:600;margin-bottom:4px}
  .fuh-card.fuh-follow .fuh-socials{display:flex;gap:8px}
  .fuh-card.fuh-follow .fuh-socials a{flex:1;display:grid;place-items:center;height:44px;border:1px solid #D6DADF;border-radius:4px;color:#3A404B;font-family:'Oswald',sans-serif;font-weight:600;letter-spacing:.04em;font-size:13px}
  .fuh-card.fuh-follow .fuh-socials a:hover{background:#35546e;color:#fff;border-color:#35546e}
  .fuh-card.fuh-nl p{font-family:'Source Serif 4',Georgia,serif;font-size:13px;color:#3A404B;line-height:1.45;margin-bottom:10px}
  .fuh-card.fuh-nl input{width:100%;padding:10px 12px;border:1px solid #D6DADF;border-radius:3px;font:inherit;background:#fff;margin-bottom:8px}
  .fuh-card.fuh-nl input:focus{outline:2px solid #35546e;outline-offset:1px}
  .fuh-card.fuh-nl button{width:100%;padding:10px;background:#FFBF0F;color:#15181E;font-family:'Oswald',sans-serif;font-weight:600;letter-spacing:.05em;text-transform:uppercase;border-radius:3px;font-size:13px;border:0;cursor:pointer}
  .fuh-card.fuh-nl button:hover{background:#E6A800}
  .fuh-card.fuh-nl small{display:block;margin-top:8px;font-family:'IBM Plex Mono',monospace;font-size:10px;color:#6B7280;letter-spacing:.02em;line-height:1.4}
  .fuh-card.fuh-hotposts ol{list-style:none;padding:0;margin:0;counter-reset:fuh-hp;display:grid;gap:10px}
  .fuh-card.fuh-hotposts li{counter-increment:fuh-hp;display:grid;grid-template-columns:22px 1fr;gap:10px;padding-bottom:10px;border-bottom:1px dashed #D6DADF}
  .fuh-card.fuh-hotposts li:last-child{border-bottom:0;padding-bottom:0}
  .fuh-card.fuh-hotposts li::before{content:counter(fuh-hp,decimal-leading-zero);font-family:'Oswald',sans-serif;font-size:18px;color:#35546e;font-weight:600;line-height:1}
  .fuh-card.fuh-hotposts h5{font-family:'Source Serif 4',Georgia,serif;font-size:14px;font-weight:600;line-height:1.3;color:#15181E;text-wrap:pretty;margin:0}
  .fuh-card.fuh-hotposts h5:hover{color:#35546e}
  .fuh-card.fuh-hotposts .fuh-m{font-family:'IBM Plex Mono',monospace;font-size:10px;color:#6B7280;letter-spacing:.04em;text-transform:uppercase;margin-top:3px}
  .fuh-ad{position:relative;background:#F3F1EA;border:1px solid #E5E7EB;border-radius:4px;padding:18px;text-align:center;font-family:'IBM Plex Mono',monospace;font-size:10px;color:#6B7280;letter-spacing:.08em;text-transform:uppercase;min-height:260px;display:flex;flex-direction:column;justify-content:center}
  .fuh-ad::before{content:"Advertisement";position:absolute;top:-9px;left:12px;background:#FBFAF6;padding:0 6px;color:#6B7280;font-size:9px;letter-spacing:.1em}
  .fuh-ad .fuh-mock{font-family:'Oswald',sans-serif;font-size:22px;color:#3A404B;letter-spacing:.04em;font-weight:500}

  /* Premium-only refresh button — surfaced as disabled-looking until paywall lands */
  .fuh-refresh-locked{opacity:.55;cursor:not-allowed;position:relative}
  .fuh-refresh-locked::after{content:"★";margin-left:6px;color:#FFBF0F}

  @media (max-width:1000px){
    .fuh-grid{grid-template-columns:1fr;gap:22px}
    .fuh-featured{grid-template-columns:1fr}
    .fuh-featured .fuh-ph{aspect-ratio:16/9}
    .fuh-stick{position:static}
    .fuh-page-h h1{font-size:32px}
  }
</style>

<main class="fuh-page">
  <div class="fuh-wrap">

    <!-- Header -->
    <div class="fuh-page-h">
      <div class="fuh-ttl">
        <span class="fuh-kk">Live thread · auto-refresh coming to Premium</span>
        <h1><?php echo esc_html($brand_abbr); ?> <em>Live Feed Updates</em></h1>
        <div class="fuh-meta">
          <span><b>Season</b><?php echo esc_html($brand_name ?: 'Big Brother'); ?></span>
          <?php if ($season_meta['week'] > 0) : ?>
            <span>·</span>
            <span><b>Current week</b>Week <?php echo (int) $season_meta['week']; ?></span>
          <?php endif; ?>
          <?php if ($season_meta['day'] > 0) : ?>
            <span>·</span>
            <span><b>Day</b><?php echo (int) $season_meta['day']; ?></span>
          <?php endif; ?>
          <span>·</span>
          <span><b>Updates today</b><?php echo (int) $counts['today']; ?></span>
        </div>
      </div>
      <div class="fuh-actions">
        <?php if (is_user_logged_in() && current_user_can('edit_posts')) : ?>
          <a class="fuh-btn-prim" href="<?php echo esc_url(home_url('/admin/?tab=feed-updates')); ?>">⊕ New Update</a>
        <?php endif; ?>
        <button class="fuh-btn-ghost fuh-refresh-locked" type="button" title="Coming soon to Premium">⟳ Auto-refresh</button>
      </div>
    </div>

    <!-- Live status banner -->
    <div class="fuh-livebar">
      <span class="fuh-pulse">On the Feeds Now</span>
      <div class="fuh-summary">
        <?php if ($live['hoh'] !== '—' || $live['veto'] !== '—') : ?>
          HoH <b><?php echo esc_html($live['hoh']); ?></b> · Veto holder <b><?php echo esc_html($live['veto']); ?></b><?php if ($live['block'] !== '—') : ?> · On the block <b><?php echo esc_html($live['block']); ?></b><?php endif; ?>
        <?php else : ?>
          Feeds rolling — <?php echo (int) $counts['today']; ?> updates posted today.
        <?php endif; ?>
      </div>
      <div class="fuh-stats">
        <span><b><?php echo (int) $counts['today']; ?></b>today</span>
        <span><b><?php echo (int) $counts['week']; ?></b>this week</span>
        <span><b><?php echo (int) $counts['season']; ?></b>season</span>
      </div>
    </div>

    <div class="fuh-grid">

      <!-- MAIN -->
      <div class="fuh-main">

        <?php if ($featured) :
          $fp = $featured['post'];
          $f_excerpt = $fp->post_excerpt !== ''
            ? $fp->post_excerpt
            : wp_trim_words(strip_shortcodes($fp->post_content), 38, '…');
          $f_perma = get_permalink($fp->ID);
        ?>
        <div class="fuh-featured">
          <div class="fuh-ph">
            <?php if (has_post_thumbnail($fp)) : ?>
              <?php echo get_the_post_thumbnail($fp, 'large', ['loading' => 'lazy', 'alt' => esc_attr($fp->post_title)]); ?>
            <?php endif; ?>
            <span class="fuh-pill">Latest update</span>
            <?php if ($featured['type']) : ?>
              <span class="fuh-name"><?php echo esc_html($featured['type']['name']); ?></span>
            <?php endif; ?>
          </div>
          <div class="fuh-bd">
            <span class="fuh-kk"><b>Newest</b><?php echo esc_html($featured['relative']); ?></span>
            <h2><a href="<?php echo esc_url($f_perma); ?>"><?php echo esc_html($fp->post_title); ?></a></h2>
            <?php if ($f_excerpt !== '') : ?>
              <p><?php echo esc_html($f_excerpt); ?></p>
            <?php endif; ?>
            <div class="fuh-foot">
              <span>By <span class="fuh-by"><?php echo esc_html($featured['author']); ?></span></span>
              <span>·</span>
              <span><?php echo esc_html($featured['relative']); ?></span>
              <?php if ($featured['comments'] > 0) : ?>
                <span>·</span>
                <span><?php echo (int) $featured['comments']; ?> comments</span>
              <?php endif; ?>
              <a class="fuh-read" href="<?php echo esc_url($f_perma); ?>">Read full update →</a>
            </div>
          </div>
        </div>
        <?php endif; ?>

        <!-- Toolbar -->
        <div class="fuh-toolbar">
          <div class="fuh-tabs" role="tablist">
            <a class="on" href="#" data-fuh-cat="">All <span class="fuh-ct"><?php echo (int) $counts['today']; ?></span></a>
            <?php foreach ($cats as $cat) : ?>
              <a href="#" data-fuh-cat="<?php echo esc_attr($cat['slug']); ?>">
                <?php echo esc_html($cat['name']); ?> <span class="fuh-ct"><?php echo (int) $cat['count']; ?></span>
              </a>
            <?php endforeach; ?>
          </div>
          <div class="fuh-drop"><span>Sort</span>
            <select id="fuh-sort">
              <option value="newest">Newest</option>
              <option value="oldest">Oldest</option>
              <option value="commented">Most commented</option>
            </select>
          </div>
          <div class="fuh-right">
            <div class="fuh-search">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
              <input id="fuh-search" type="search" placeholder="In this thread…" autocomplete="off">
            </div>
          </div>
        </div>

        <!-- Day-grouped thread -->
        <?php if (empty($grouped)) : ?>
          <p style="font-family:'IBM Plex Mono',monospace;font-size:12px;color:#6B7280;text-align:center;padding:40px 0;text-transform:uppercase;letter-spacing:.06em;">No updates yet.</p>
        <?php else :
          $today_key = current_time('Y-m-d');
          $yest_key  = date('Y-m-d', strtotime('-1 day', current_time('timestamp')));
          foreach ($grouped as $date_key => $rows) :
            if ($date_key === $today_key)      $day_label = 'Today';
            elseif ($date_key === $yest_key)   $day_label = 'Yesterday';
            else                               $day_label = $rows[0]['date_label_long'];
        ?>
          <div class="fuh-daybar">
            <span class="fuh-tag"><?php echo esc_html($day_label); ?></span>
            <span class="fuh-date"><?php echo esc_html($rows[0]['date_label_short']); ?></span>
            <span class="fuh-line"></span>
            <span class="fuh-count"><b><?php echo count($rows); ?></b> updates</span>
          </div>

          <div class="fuh-thread" data-fuh-thread>
            <?php foreach ($rows as $row) :
              $rp        = $row['post'];
              $cat_slug  = $row['type']['slug'] ?? '';
              $cat_name  = $row['type']['name'] ?? '';
              $loc_name  = $row['location']['name'] ?? '';
              $perma     = get_permalink($rp->ID);
              $excerpt   = $rp->post_excerpt !== ''
                ? $rp->post_excerpt
                : wp_trim_words(strip_shortcodes($rp->post_content), 32, '…');
              $author_init = strtoupper(substr($row['author'], 0, 2));
              $search_blob = strtolower($rp->post_title . ' ' . $excerpt . ' ' . $row['author']);
            ?>
              <article
                class="fuh-upd"
                data-cat="<?php echo esc_attr($cat_slug); ?>"
                data-blob="<?php echo esc_attr($search_blob); ?>"
              >
                <div class="fuh-vote">
                  <button type="button" aria-label="Upvote">▲</button>
                  <span class="fuh-n"><?php echo (int) $row['comments']; ?></span>
                  <button type="button" aria-label="Downvote">▼</button>
                </div>
                <div class="fuh-body">
                  <div class="fuh-head">
                    <span class="fuh-av"><?php echo esc_html($author_init); ?></span>
                    <span class="fuh-by"><?php echo esc_html($row['author']); ?></span>
                    <?php if ($cat_name) : ?>
                      <span class="fuh-cat"><?php echo esc_html($cat_name); ?></span>
                    <?php endif; ?>
                    <?php if ($loc_name) : ?>
                      <span class="fuh-loc"><?php echo esc_html($loc_name); ?></span>
                    <?php endif; ?>
                    <span class="fuh-t" data-nosnippet><?php echo esc_html($row['time_12h']); ?> · <b><?php echo esc_html($row['relative']); ?></b></span>
                  </div>
                  <h3><a href="<?php echo esc_url($perma); ?>"><?php echo esc_html($rp->post_title); ?></a></h3>
                  <?php if ($excerpt !== '') : ?>
                    <p><?php echo esc_html($excerpt); ?></p>
                  <?php endif; ?>
                  <?php if (has_post_thumbnail($rp)) : ?>
                    <a class="fuh-thumb" href="<?php echo esc_url($perma); ?>" aria-hidden="true" tabindex="-1">
                      <?php echo get_the_post_thumbnail($rp, 'medium', ['loading' => 'lazy', 'alt' => '']); ?>
                    </a>
                  <?php endif; ?>
                  <div class="fuh-foot">
                    <?php if ($loc_name) : ?>
                      <span><?php echo esc_html($loc_name); ?></span>
                    <?php endif; ?>
                    <a class="fuh-cmt" href="<?php echo esc_url($perma . '#comments'); ?>">💬 <?php echo (int) $row['comments']; ?> comments</a>
                    <a href="<?php echo esc_url($perma); ?>">↗ Open</a>
                  </div>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endforeach; endif; ?>

        <?php if ($thread['has_more']) :
          $next_url = get_pagenum_link($paged + 1);
          $remaining = max(0, $thread['total'] - ($paged * $per_page));
        ?>
          <div class="fuh-loadmore">
            <a href="<?php echo esc_url($next_url); ?>">Load <?php echo (int) min($remaining, $per_page); ?> older updates ↓</a>
            <small class="fuh-hint">Showing page <?php echo (int) $paged; ?> · <?php echo (int) $thread['total']; ?> total this season</small>
          </div>
        <?php endif; ?>

      </div>

      <!-- SIDEBAR -->
      <aside>
        <div class="fuh-stick">

          <div class="fuh-card fuh-live-mini">
            <h4>Live Right Now</h4>
            <div class="fuh-row"><span class="fuh-k">HoH</span><span class="fuh-v"><?php echo esc_html($live['hoh']); ?></span></div>
            <div class="fuh-row"><span class="fuh-k">Veto</span><span class="fuh-v"><?php echo esc_html($live['veto']); ?></span></div>
            <div class="fuh-row"><span class="fuh-k">Block</span><span class="fuh-v"><?php echo esc_html($live['block']); ?></span></div>
            <div class="fuh-row"><span class="fuh-k">Next evict</span><span class="fuh-v"><?php echo esc_html($live['next_evict']); ?></span></div>
            <a class="fuh-cta" href="<?php echo esc_url($live['watch_url']); ?>" rel="nofollow noopener" target="_blank">Watch on Paramount+ →</a>
          </div>

          <?php if (!empty($cats)) : ?>
          <div class="fuh-card fuh-fcard">
            <h4>Filter <small><?php echo (int) $counts['today']; ?> today</small></h4>
            <div class="fuh-glab">Category</div>
            <?php foreach ($cats as $cat) : ?>
              <label>
                <input type="checkbox" data-fuh-fcat="<?php echo esc_attr($cat['slug']); ?>" checked>
                <?php echo esc_html($cat['name']); ?>
                <span class="fuh-ct"><?php echo (int) $cat['count']; ?></span>
              </label>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>

          <div class="fuh-card fuh-follow">
            <h4>Follow Us</h4>
            <div class="fuh-socials">
              <a href="https://x.com/bbjunkies" rel="nofollow noopener" target="_blank" title="X">𝕏</a>
              <a href="https://instagram.com/bigbrotherjunkies" rel="nofollow noopener" target="_blank" title="Instagram">IG</a>
              <a href="https://facebook.com/bigbrotherjunkies" rel="nofollow noopener" target="_blank" title="Facebook">FB</a>
              <a href="https://bsky.app/profile/bigbrotherjunkies.com" rel="nofollow noopener" target="_blank" title="Bluesky">BS</a>
            </div>
          </div>

          <div class="fuh-card fuh-nl">
            <h4>Don't miss an update</h4>
            <p>Get morning &amp; evening feed digests + breaking push alerts on ceremony days.</p>
            <input type="email" placeholder="you@example.com" disabled>
            <button type="button" disabled>Subscribe</button>
            <small>Newsletter signup wiring lands in a follow-up.</small>
          </div>

          <?php if (!empty($hot_posts)) : ?>
          <div class="fuh-card fuh-hotposts">
            <h4>Hot This Week</h4>
            <ol>
              <?php foreach ($hot_posts as $hp) : ?>
                <li>
                  <div>
                    <h5><a href="<?php echo esc_url($hp['permalink']); ?>"><?php echo esc_html($hp['title']); ?></a></h5>
                    <div class="fuh-m"><?php echo (int) $hp['comments']; ?> comments · <?php echo esc_html($hp['relative']); ?></div>
                  </div>
                </li>
              <?php endforeach; ?>
            </ol>
          </div>
          <?php endif; ?>

          <div class="fuh-ad">
            <div class="fuh-mock">300 × 600<br><small style="font-size:9px;font-family:'IBM Plex Mono',monospace;letter-spacing:.06em;">Half-page · sticky rail</small></div>
          </div>

        </div>
      </aside>

    </div>
  </div>
</main>

<script>
(function(){
  var threadEls = document.querySelectorAll('[data-fuh-thread]');
  if (!threadEls.length) return;
  var cards = [];
  threadEls.forEach(function(t){
    Array.prototype.forEach.call(t.querySelectorAll('.fuh-upd'), function(el){ cards.push(el); });
  });
  var tabs = document.querySelectorAll('.fuh-tabs a[data-fuh-cat]');
  var fcats = document.querySelectorAll('[data-fuh-fcat]');
  var search = document.getElementById('fuh-search');
  var sort = document.getElementById('fuh-sort');
  var activeTab = ''; // empty = All

  function applyFilters(){
    var q = (search && search.value || '').trim().toLowerCase();
    var enabledFcats = {};
    fcats.forEach(function(cb){ enabledFcats[cb.getAttribute('data-fuh-fcat')] = cb.checked; });
    var anyFcatChecked = Array.prototype.some.call(fcats, function(cb){ return cb.checked; });

    cards.forEach(function(c){
      var cat = c.getAttribute('data-cat') || '';
      var blob = c.getAttribute('data-blob') || '';
      var tabMatch = !activeTab || cat === activeTab;
      var fcatMatch = !fcats.length || !anyFcatChecked || enabledFcats[cat] !== false;
      var searchMatch = !q || blob.indexOf(q) !== -1;
      c.style.display = (tabMatch && fcatMatch && searchMatch) ? '' : 'none';
    });

    // Hide day dividers whose group has zero visible cards.
    document.querySelectorAll('.fuh-daybar').forEach(function(bar){
      var nextThread = bar.nextElementSibling;
      if (!nextThread) return;
      var visible = nextThread.querySelectorAll('.fuh-upd:not([style*="display: none"])').length;
      bar.style.display = visible === 0 ? 'none' : '';
    });
  }

  function applySort(){
    if (!sort) return;
    var key = sort.value;
    threadEls.forEach(function(thread){
      var children = Array.prototype.slice.call(thread.querySelectorAll('.fuh-upd'));
      children.sort(function(a, b){
        if (key === 'oldest') {
          return (a.getAttribute('data-blob') || '').localeCompare(b.getAttribute('data-blob') || '');
        }
        if (key === 'commented') {
          var ac = parseInt(a.querySelector('.fuh-vote .fuh-n').textContent, 10) || 0;
          var bc = parseInt(b.querySelector('.fuh-vote .fuh-n').textContent, 10) || 0;
          return bc - ac;
        }
        return 0; // 'newest' = server order
      });
      // 'oldest' is just a reverse of server order; do that explicitly
      if (key === 'oldest') {
        children = children.reverse();
      }
      children.forEach(function(c){ thread.appendChild(c); });
    });
  }

  tabs.forEach(function(tab){
    tab.addEventListener('click', function(e){
      e.preventDefault();
      tabs.forEach(function(t){ t.classList.remove('on'); });
      tab.classList.add('on');
      activeTab = tab.getAttribute('data-fuh-cat') || '';
      applyFilters();
    });
  });
  fcats.forEach(function(cb){ cb.addEventListener('change', applyFilters); });
  if (search) search.addEventListener('input', applyFilters);
  if (sort)   sort.addEventListener('change', applySort);
})();
</script>

<?php get_footer(); ?>
