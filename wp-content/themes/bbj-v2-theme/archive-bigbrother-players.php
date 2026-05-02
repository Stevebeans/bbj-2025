<?php
/**
 * Houseguest archive — skeleton.
 *
 * Renders all players server-side; vanilla JS filters DOM by name search,
 * season, status, and sort. No pagination, no AJAX — this is a directory
 * for browsing 365-ish players, not a content feed.
 */

$players = bbj_v2_archive_all_players();
$seasons = bbj_v2_archive_all_seasons();

get_header();
?>

<main class="wrap" style="background:#fafaf9;min-height:60vh;">
  <div style="max-width:1200px;margin:0 auto;padding:24px 16px;">

    <header style="display:flex;flex-wrap:wrap;gap:12px;align-items:baseline;justify-content:space-between;margin-bottom:20px;border-bottom:1px solid #e7e5e4;padding-bottom:14px;">
      <h1 style="font-family:'Yanone Kaffeesatz',sans-serif;font-size:38px;line-height:1.05;color:#2D4B65;margin:0;">
        Houseguest Directory
      </h1>
      <p style="margin:0;font-size:13px;color:#6b7280;letter-spacing:.05em;text-transform:uppercase;">
        <span data-pa-counter><?php echo count($players); ?></span> of <?php echo count($players); ?> players
      </p>
    </header>

    <!-- Filter bar -->
    <div class="pa-filters" style="display:grid;grid-template-columns:1fr;gap:10px;margin-bottom:20px;padding:14px;background:#fff;border:1px solid #e7e5e4;">
      <input
        type="search"
        id="pa-search"
        placeholder="Search players by name…"
        autocomplete="off"
        style="width:100%;padding:10px 12px;border:1px solid #d6d3d1;font-size:15px;font-family:inherit;background:#fff;"
      />
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px;">
        <select id="pa-season" style="padding:9px 10px;border:1px solid #d6d3d1;background:#fff;font-size:14px;font-family:inherit;">
          <option value="">All seasons</option>
          <?php foreach ($seasons as $s) : ?>
            <option value="<?php echo (int) $s['post_id']; ?>">
              <?php echo esc_html($s['season_abbr'] ?: $s['display_name']); ?>
            </option>
          <?php endforeach; ?>
        </select>
        <select id="pa-status" style="padding:9px 10px;border:1px solid #d6d3d1;background:#fff;font-size:14px;font-family:inherit;">
          <option value="">All statuses</option>
          <option value="winner">Winners</option>
          <option value="runner-up">Runners-up</option>
          <option value="played">Played</option>
        </select>
        <select id="pa-sort" style="padding:9px 10px;border:1px solid #d6d3d1;background:#fff;font-size:14px;font-family:inherit;">
          <option value="name-asc">Name (A → Z)</option>
          <option value="name-desc">Name (Z → A)</option>
          <option value="hoh-desc">Most HOH wins</option>
          <option value="pov-desc">Most POV wins</option>
          <option value="seasons-desc">Most seasons played</option>
        </select>
      </div>
    </div>

    <!-- Player grid -->
    <div id="pa-grid" class="pa-grid" style="display:grid;grid-template-columns:repeat(2,1fr);gap:12px;">
      <?php foreach ($players as $p) :
        $name      = $p['full_name'];
        $first     = $p['first_name'];
        $last      = $p['last_name'];
        $age       = $p['age'];
        $location  = $p['location'];
        $status    = $p['status']; // winner|runner-up|played
        $pic_id    = (int) ($p['profile_picture'] ?? 0);
        $perma     = get_permalink((int) $p['post_id']);
        $season_ids_attr = implode(',', $p['season_ids']);
        $initials  = strtoupper(substr($first, 0, 1) . substr($last, 0, 1)) ?: '??';

        $status_label = '';
        $status_color = '';
        if ($status === 'winner')         { $status_label = 'Winner';    $status_color = '#FFBF0F'; }
        elseif ($status === 'runner-up')  { $status_label = 'Runner-up'; $status_color = '#9ca3af'; }
      ?>
        <a
          class="pa-card"
          href="<?php echo esc_url($perma); ?>"
          data-name="<?php echo esc_attr(strtolower($name)); ?>"
          data-seasons="<?php echo esc_attr($season_ids_attr); ?>"
          data-status="<?php echo esc_attr($status); ?>"
          data-hoh="<?php echo (int) $p['total_hoh']; ?>"
          data-pov="<?php echo (int) $p['total_pov']; ?>"
          data-season-count="<?php echo (int) $p['season_count']; ?>"
          style="display:flex;flex-direction:column;background:#fff;border:1px solid #e7e5e4;text-decoration:none;color:inherit;transition:border-color .15s ease,transform .15s ease;"
        >
          <div style="aspect-ratio:1/1;background:#f5f5f4;position:relative;overflow:hidden;">
            <?php if ($pic_id) :
              echo wp_get_attachment_image($pic_id, 'bbj_v2_profile_image', false, [
                'loading' => 'lazy',
                'style'   => 'width:100%;height:100%;object-fit:cover;display:block;',
                'alt'     => esc_attr($name),
              ]);
            else : ?>
              <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-family:'Oswald',sans-serif;font-size:42px;color:#a8a29e;letter-spacing:.05em;">
                <?php echo esc_html($initials); ?>
              </div>
            <?php endif; ?>
            <?php if ($status_label) : ?>
              <span style="position:absolute;top:8px;left:8px;background:<?php echo esc_attr($status_color); ?>;color:#1a1a1a;font-family:'Oswald',sans-serif;font-size:10px;letter-spacing:.08em;text-transform:uppercase;padding:3px 8px;">
                <?php echo esc_html($status_label); ?>
              </span>
            <?php endif; ?>
          </div>

          <div style="padding:10px 12px 12px;flex:1;display:flex;flex-direction:column;gap:6px;">
            <h3 style="margin:0;font-family:'Oswald',sans-serif;font-size:16px;line-height:1.15;color:#2D4B65;">
              <?php echo esc_html($name); ?>
            </h3>
            <p style="margin:0;font-size:12px;color:#6b7280;line-height:1.3;min-height:1.3em;">
              <?php
              $meta_bits = [];
              if ($age)      $meta_bits[] = 'Age ' . $age;
              if ($location) $meta_bits[] = $location;
              echo esc_html(implode(' • ', $meta_bits));
              ?>
            </p>
            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:4px;margin-top:auto;padding-top:8px;border-top:1px solid #f5f5f4;">
              <?php
              $stats = [
                ['HOH',   (int) $p['total_hoh']],
                ['POV',   (int) $p['total_pov']],
                ['NOM',   (int) $p['total_nom']],
                ['VOTES', (int) $p['total_votes']],
              ];
              foreach ($stats as [$label, $val]) : ?>
                <div style="text-align:center;">
                  <div style="font-family:'Oswald',sans-serif;font-size:14px;color:#1f2937;line-height:1;"><?php echo $val; ?></div>
                  <div style="font-size:9px;color:#9ca3af;letter-spacing:.06em;margin-top:2px;"><?php echo $label; ?></div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>

    <p id="pa-empty" hidden style="margin:32px 0;text-align:center;color:#6b7280;font-size:14px;">
      No players match those filters.
    </p>

  </div>
</main>

<style>
  /* Responsive grid bumps */
  @media (min-width: 640px)  { .pa-grid { grid-template-columns: repeat(3, 1fr) !important; } }
  @media (min-width: 900px)  { .pa-grid { grid-template-columns: repeat(4, 1fr) !important; } }
  @media (min-width: 1200px) { .pa-grid { grid-template-columns: repeat(5, 1fr) !important; } }
  .pa-card:hover { border-color: #2D4B65 !important; transform: translateY(-1px); }
</style>

<script>
(function(){
  var search  = document.getElementById('pa-search');
  var season  = document.getElementById('pa-season');
  var status  = document.getElementById('pa-status');
  var sort    = document.getElementById('pa-sort');
  var grid    = document.getElementById('pa-grid');
  var empty   = document.getElementById('pa-empty');
  var counter = document.querySelector('[data-pa-counter]');
  if (!grid) return;

  var cards = Array.prototype.slice.call(grid.querySelectorAll('.pa-card'));
  var total = cards.length;

  function applyFilters(){
    var q  = (search.value || '').trim().toLowerCase();
    var sId = season.value;
    var st  = status.value;
    var visible = 0;

    cards.forEach(function(card){
      var name     = card.getAttribute('data-name') || '';
      var seasons  = card.getAttribute('data-seasons') || '';
      var cStatus  = card.getAttribute('data-status') || '';
      var nameMatch    = !q  || name.indexOf(q) !== -1;
      var seasonMatch  = !sId || (',' + seasons + ',').indexOf(',' + sId + ',') !== -1;
      var statusMatch  = !st  || cStatus === st;
      var show = nameMatch && seasonMatch && statusMatch;
      card.style.display = show ? '' : 'none';
      if (show) visible++;
    });

    if (counter) counter.textContent = visible;
    if (empty) empty.hidden = visible !== 0;
  }

  function applySort(){
    var key = sort.value;
    var sorted = cards.slice().sort(function(a, b){
      switch (key) {
        case 'name-desc':
          return (b.getAttribute('data-name') || '').localeCompare(a.getAttribute('data-name') || '');
        case 'hoh-desc':
          return (parseInt(b.getAttribute('data-hoh'), 10) || 0) - (parseInt(a.getAttribute('data-hoh'), 10) || 0);
        case 'pov-desc':
          return (parseInt(b.getAttribute('data-pov'), 10) || 0) - (parseInt(a.getAttribute('data-pov'), 10) || 0);
        case 'seasons-desc':
          return (parseInt(b.getAttribute('data-season-count'), 10) || 0) - (parseInt(a.getAttribute('data-season-count'), 10) || 0);
        case 'name-asc':
        default:
          return (a.getAttribute('data-name') || '').localeCompare(b.getAttribute('data-name') || '');
      }
    });
    sorted.forEach(function(card){ grid.appendChild(card); });
  }

  search.addEventListener('input', applyFilters);
  season.addEventListener('change', applyFilters);
  status.addEventListener('change', applyFilters);
  sort.addEventListener('change', applySort);
})();
</script>

<?php get_footer(); ?>
