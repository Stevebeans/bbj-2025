<?php
/**
 * Single Player Profile — editorial redesign (Sprint B).
 *
 * Spec: docs/superpowers/specs/2026-04-24-player-profile-design.md
 * Design: .claude/claude-design/bbj-player-profile/bbj-home-page/project/BBJ Player Profile.html
 */

if (!have_posts()) {
    get_header();
    echo '<main class="wrap"><p>Player not found.</p></main>';
    get_footer();
    return;
}

the_post();
$post_id  = get_the_ID();
$player   = bbj_v2_player_profile_player_data($post_id);
$seasons  = bbj_v2_player_profile_seasons($post_id);
$totals   = bbj_v2_player_profile_career_totals($seasons);
$derived  = bbj_v2_player_profile_derive($player, $seasons);
$latest   = $derived['latest_season'] ?? null;

// --- JSON-LD: Person + BreadcrumbList ---
$person_schema = [
    '@context' => 'https://schema.org',
    '@type'    => 'Person',
    'name'     => $player['full_name'] ?: get_the_title(),
    'url'      => get_permalink($post_id),
];
if (!empty($player['date_of_birth']))          $person_schema['birthDate']   = $player['date_of_birth'];
if (!empty($player['occupation']))             $person_schema['jobTitle']    = $player['occupation'];
if (!empty($player['hometown']))               $person_schema['homeLocation'] = ['@type' => 'Place', 'name' => $player['hometown']];
if (!empty($player['profile_picture']))        $person_schema['image']       = wp_get_attachment_image_url($player['profile_picture'], 'full') ?: null;
if (!empty($player['socials']))                $person_schema['sameAs']      = array_values($player['socials']);

$breadcrumb_items = [
    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home',        'item' => home_url('/')],
    ['@type' => 'ListItem', 'position' => 2, 'name' => 'Houseguests', 'item' => home_url('/bigbrother-players/')],
];
if ($latest && !empty($latest['season_slug'])) {
    $breadcrumb_items[] = [
        '@type' => 'ListItem', 'position' => 3,
        'name'  => $latest['season_abbr'] ?: $latest['season_name'],
        'item'  => home_url('/bigbrother-seasons/' . $latest['season_slug'] . '/'),
    ];
    $breadcrumb_items[] = [
        '@type' => 'ListItem', 'position' => 4,
        'name'  => $player['full_name'] ?: get_the_title(),
        'item'  => get_permalink($post_id),
    ];
} else {
    $breadcrumb_items[] = [
        '@type' => 'ListItem', 'position' => 3,
        'name'  => $player['full_name'] ?: get_the_title(),
        'item'  => get_permalink($post_id),
    ];
}

$breadcrumb_schema = [
    '@context'         => 'https://schema.org',
    '@type'            => 'BreadcrumbList',
    'itemListElement'  => $breadcrumb_items,
];

get_header();
?>

<main class="wrap">

  <script type="application/ld+json"><?php echo wp_json_encode($person_schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?></script>
  <script type="application/ld+json"><?php echo wp_json_encode($breadcrumb_schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?></script>

  <!-- Breadcrumb -->
  <nav class="crumb" aria-label="Breadcrumb">
    <a href="<?php echo esc_url(home_url('/')); ?>">Home</a><span class="sep">/</span>
    <a href="<?php echo esc_url(home_url('/bigbrother-players/')); ?>">Houseguests</a><span class="sep">/</span>
    <?php if ($latest && !empty($latest['season_slug'])) : ?>
      <a href="<?php echo esc_url(home_url('/bigbrother-seasons/' . $latest['season_slug'] . '/')); ?>">
        <?php echo esc_html($latest['season_abbr'] ?: $latest['season_name']); ?>
      </a>
      <span class="sep">/</span>
    <?php endif; ?>
    <b><?php echo esc_html($player['full_name'] ?: get_the_title()); ?></b>
  </nav>

  <!-- HERO -->
  <div class="hero">
    <div class="inner">
      <?php
      $initials = strtoupper(
          substr($player['first_name'] ?? '', 0, 1)
          . substr($player['last_name'] ?? '', 0, 1)
      ) ?: '??';
      ?>
      <div class="portrait"<?php echo $player['profile_picture'] ? '' : ' data-i="' . esc_attr($initials) . '"'; ?>>
        <?php if ($player['profile_picture']) : ?>
          <?php echo wp_get_attachment_image($player['profile_picture'], 'bbj_v2_profile_image', false, [
              'alt'   => sprintf('%s, %s houseguest', $player['full_name'], $latest['season_abbr'] ?? 'Big Brother'),
              'style' => 'width:100%;height:100%;object-fit:cover;position:absolute;inset:0;',
          ]); ?>
        <?php endif; ?>
        <?php if (!empty($derived['is_afp_anywhere']) && $latest) : ?>
          <div class="badge-placement">AFP<small>Season <?php echo esc_html(preg_replace('/^BB/', '', $latest['season_abbr'] ?? '')); ?></small></div>
        <?php endif; ?>
      </div>

      <div class="meta">
        <span class="kk"><span class="dot"></span><?php echo esc_html($derived['status_kicker']); ?></span>
        <h1><?php echo esc_html($player['full_name'] ?: get_the_title()); ?></h1>
        <?php if (!empty($player['nickname'])) : ?>
          <div class="nick">&ldquo;<?php echo esc_html($player['nickname']); ?>&rdquo;</div>
        <?php endif; ?>
        <div class="hgmeta">
          <?php if (!empty($player['hometown'])) : ?>
            <span><span class="k">From</span><b><?php echo esc_html($player['hometown']); ?></b></span>
          <?php endif; ?>
          <?php if (!empty($derived['age_in_house'])) : ?>
            <span><span class="k">Age</span><b><?php echo (int) $derived['age_in_house']; ?></b></span>
          <?php endif; ?>
          <?php if (!empty($player['occupation'])) : ?>
            <span><span class="k">Occupation</span><b><?php echo esc_html($player['occupation']); ?></b></span>
          <?php endif; ?>
          <?php if (!empty($derived['days_in_house'])) : ?>
            <span><span class="k">Days in house</span><b><?php echo (int) $derived['days_in_house']; ?></b></span>
          <?php endif; ?>
        </div>
        <?php if (!empty($derived['chips'])) : ?>
          <div class="tags">
            <?php foreach ($derived['chips'] as $chip) : ?>
              <span class="t <?php echo esc_attr($chip['class']); ?>"><?php echo esc_html($chip['text']); ?></span>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="actions">
        <span class="b prim" aria-disabled="true" title="Coming soon">⇆ Compare</span>
        <?php if ($latest && !empty($latest['season_slug'])) : ?>
          <a class="b alt" href="<?php echo esc_url(home_url('/bigbrother-seasons/' . $latest['season_slug'] . '/')); ?>">
            ↗ View <?php echo esc_html($latest['season_abbr'] ?: 'Season'); ?>
          </a>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- BIO STRIP -->
  <?php
  $strip_cells = array_filter([
    !empty($player['hometown'])            ? ['k' => 'Hometown',      'v' => $player['hometown']]                         : null,
    !empty($player['occupation'])          ? ['k' => 'Occupation',    'v' => $player['occupation']]                       : null,
    !empty($derived['age_in_house'])       ? ['k' => 'Age in house',  'v' => (string) $derived['age_in_house']]           : null,
    !empty($derived['placement_label'])    ? ['k' => 'Placement',     'v' => $derived['placement_label']]                 : null,
    ($latest && !empty($derived['eviction_day']))
      ? ['k' => 'Eviction', 'v' => sprintf('Day %d · Week %d', $derived['eviction_day'], $derived['eviction_week'])]
      : ($latest ? ['k' => 'Status', 'v' => 'Still in house'] : null),
  ]);
  if (!empty($strip_cells)) : ?>
    <div class="biostrip" style="grid-template-columns:repeat(<?php echo count($strip_cells); ?>,1fr);">
      <?php foreach ($strip_cells as $cell) : ?>
        <div class="c"><div class="k"><?php echo esc_html($cell['k']); ?></div><div class="v"><?php echo esc_html($cell['v']); ?></div></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="player-page-grid">

    <!-- MAIN COLUMN -->
    <div>
      <!-- BIO & BACKGROUND -->
      <section>
        <div class="sech">
          <h2>Bio &amp; Background</h2>
          <span class="sub">The long version</span>
        </div>
        <div class="biocard">
          <?php
          $glance = array_filter([
              !empty($player['hometown'])      ? ['k' => 'Hometown',   'v' => $player['hometown']]                                : null,
              !empty($player['date_of_birth'])
                  ? ['k' => 'Birthday',   'v' => date_i18n('M j, Y', strtotime($player['date_of_birth']))]
                  : null,
              !empty($player['occupation'])    ? ['k' => 'Occupation', 'v' => $player['occupation']]                              : null,
          ]);
          ?>
          <?php if (!empty($glance)) : ?>
            <aside class="at-a-glance">
              <h4>At a glance</h4>
              <dl>
                <?php foreach ($glance as $row) : ?>
                  <dt><?php echo esc_html($row['k']); ?></dt>
                  <dd><?php echo esc_html($row['v']); ?></dd>
                <?php endforeach; ?>
              </dl>
            </aside>
          <?php endif; ?>
          <div class="copy">
            <?php
            $content = get_the_content();
            if (trim($content) === '') {
                echo '<p class="lead">Bio coming soon.</p>';
            } else {
                echo apply_filters('the_content', $content);
            }
            ?>
          </div>
        </div>
      </section>

      <!-- CAREER STATS -->
      <section>
        <div class="sech">
          <h2>Career Statistics</h2>
          <span class="sub">Across <?php echo (int) $totals['season_count']; ?> season<?php echo $totals['season_count'] === 1 ? '' : 's'; ?></span>
        </div>
        <div class="statgrid">
          <div class="stat"><span class="delta na">—</span><div class="n"><?php echo (int) $totals['season_count']; ?></div><div class="k">Seasons</div></div>
          <div class="stat hoh"><span class="delta na">—</span><div class="n"><?php echo (int) $totals['hoh']; ?></div><div class="k">HoH wins</div></div>
          <div class="stat pov"><span class="delta na">—</span><div class="n"><?php echo (int) $totals['pov']; ?></div><div class="k">PoV wins</div></div>
          <div class="stat nom"><span class="delta na">—</span><div class="n"><?php echo (int) $totals['nom']; ?></div><div class="k">Nominated</div></div>
          <div class="stat"><span class="delta na">—</span><div class="n"><?php echo (int) $totals['votes']; ?></div><div class="k">Jury votes</div></div>
          <div class="stat afp"><span class="delta na">—</span><div class="n"><?php echo (int) $totals['days']; ?></div><div class="k">Days</div></div>
        </div>
      </section>

      <!-- SEASON HISTORY -->
      <?php if (!empty($seasons)) : ?>
      <section>
        <div class="sech">
          <h2>Season History</h2>
          <span class="sub">Finale placements</span>
        </div>
        <div class="seasons">
          <table>
            <thead>
              <tr><th>Season</th><th>Age</th><th>HoH</th><th>PoV</th><th>Nom</th><th>Votes</th><th>Days</th><th>Progress</th><th>Result</th></tr>
            </thead>
            <tbody>
              <?php foreach ($seasons as $row) :
                $season_url = !empty($row['season_slug']) ? home_url('/bigbrother-seasons/' . $row['season_slug'] . '/') : '#';
                $age_at_season = null;
                if (!empty($player['date_of_birth']) && !empty($row['season_start'])) {
                    try {
                        $age_at_season = (new DateTime($player['date_of_birth']))->diff(new DateTime($row['season_start']))->y;
                    } catch (Exception $e) {}
                }
                $days_this_season = 0;
                if (!empty($row['season_start'])) {
                    $end = $row['bbj_evicted_date'] ?: ($row['season_end'] ?: date('Y-m-d'));
                    try {
                        $days_this_season = max(0, (new DateTime($row['season_start']))->diff(new DateTime($end))->days);
                    } catch (Exception $e) {}
                }
                $season_size = (int) ($row['season_size'] ?? 0);
                $finish = (int) ($row['finish_place'] ?? 0);
                $progress = ($season_size > 0 && $finish > 0) ? round((($season_size - $finish + 1) / $season_size) * 100) : 0;

                // Result pill label. Prefer finish_place (always present in the
                // junction table) over the season_winner/runner_up post-pointers
                // (which require a wp_bbj_seasons row that modern seasons skip).
                $result_label = '';
                $result_class = '';
                $is_winner_ptr   = (int) ($row['season_winner'] ?? 0) === (int) $post_id;
                $is_runnerup_ptr = (int) ($row['runner_up']     ?? 0) === (int) $post_id;
                $is_afp_ptr      = (int) ($row['afp']           ?? 0) === (int) $post_id;
                if ($finish === 1 || $is_winner_ptr) {
                    $result_label = 'Winner'; $result_class = 'winner';
                } elseif ($finish === 2 || $is_runnerup_ptr) {
                    $result_label = 'Runner-up · 2nd'; $result_class = 'runnerup';
                } elseif ($is_afp_ptr) {
                    $result_label = 'AFP' . ($finish ? ' · ' . bbj_v2_player_profile_ordinal($finish) : '');
                    $result_class = 'afp';
                } elseif (!empty($row['current_jury'])) {
                    $result_label = 'Jury' . ($finish ? ' · ' . bbj_v2_player_profile_ordinal($finish) : '');
                    $result_class = 'jury';
                } elseif ($finish > 0) {
                    $result_label = 'Evicted · ' . bbj_v2_player_profile_ordinal($finish);
                } elseif (!empty($row['bbj_evicted_date'])) {
                    $result_label = 'Evicted';
                } elseif (!empty($row['season_end']) && strtotime($row['season_end']) < time()) {
                    $result_label = 'Finished';
                } else {
                    $result_label = 'Active';
                }
              ?>
                <tr>
                  <td><a class="season" href="<?php echo esc_url($season_url); ?>"><?php echo esc_html($row['season_name']); ?></a></td>
                  <td class="stat-n"><?php echo $age_at_season !== null ? (int) $age_at_season : '—'; ?></td>
                  <td class="stat-n"><?php echo (int) $row['bbj_total_hoh']; ?></td>
                  <td class="stat-n"><?php echo (int) $row['bbj_total_pov']; ?></td>
                  <td class="stat-n"><?php echo (int) $row['bbj_total_nom']; ?></td>
                  <td class="stat-n"><?php echo (int) $row['bbj_votes_received']; ?></td>
                  <td class="stat-n"><?php echo (int) $days_this_season; ?></td>
                  <td><div class="progbar"><div class="bar"><b style="width:<?php echo (int) $progress; ?>%"></b></div><span class="p"><?php echo (int) $progress; ?>%</span></div></td>
                  <td class="result"><span class="pill <?php echo esc_attr($result_class); ?>"><?php echo esc_html($result_label); ?></span></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
      <?php endif; ?>

      <!-- WEEK BY WEEK -->
      <?php
      if (!empty($seasons) && is_array($seasons)) {
          foreach ($seasons as $season) {
              $season_post_id = (int) ($season['bbj_season'] ?? 0);
              if ($season_post_id <= 0) continue;
              $season_label = (string) ($season['season_name'] ?? '');

              get_template_part('template-parts/player/week-by-week', null, [
                  'player_post_id' => $post_id,
                  'season_post_id' => $season_post_id,
                  'season_label'   => $season_label,
              ]);
          }
      }
      ?>

      <!-- CASTMATES -->
      <?php
      $castmates = ($latest && !empty($latest['bbj_season']))
          ? bbj_v2_player_profile_castmates($post_id, (int) $latest['bbj_season'])
          : [];
      if (!empty($castmates)) :
        $season_abbr = $latest['season_abbr'] ?: 'Big Brother';
      ?>
      <section>
        <div class="sech">
          <h2>Castmates · <?php echo esc_html($season_abbr); ?></h2>
          <span class="sub">Who they played with</span>
          <?php if (!empty($latest['season_slug'])) : ?>
            <span class="spacer"></span>
            <a class="link" href="<?php echo esc_url(home_url('/bigbrother-seasons/' . $latest['season_slug'] . '/')); ?>">Full cast →</a>
          <?php endif; ?>
        </div>
        <div class="cast-grid">
          <?php foreach ($castmates as $cm) :
            $cm_full = trim(($cm['first_name'] ?? '') . ' ' . ($cm['last_name'] ?? ''));
            $cm_display = $cm['official_nickname'] ?: ($cm['first_name'] ?: $cm_full);
            $cm_url = !empty($cm['player_slug']) ? home_url('/bigbrother-players/' . $cm['player_slug'] . '/') : '#';
            $cm_finish = (int) ($cm['finish_place'] ?? 0);

            // Same fallback rationale as the result-pill above: prefer
            // finish_place (always set in the junction) over season-pointer
            // fields that are NULL when wp_bbj_seasons has no row.
            $tag_class = 'pre';
            $tag_text = 'Out';
            $is_winner_ptr   = (int) ($cm['season_winner'] ?? 0) === (int) $cm['player_post_id'];
            $is_runnerup_ptr = (int) ($cm['runner_up']     ?? 0) === (int) $cm['player_post_id'];
            $is_afp_ptr      = (int) ($cm['afp']           ?? 0) === (int) $cm['player_post_id'];
            if ($cm_finish === 1 || $is_winner_ptr) {
                $tag_class = 'win'; $tag_text = 'Winner';
            } elseif ($cm_finish === 2 || $is_runnerup_ptr) {
                $tag_class = 'win'; $tag_text = '2nd';
            } elseif ($is_afp_ptr) {
                $tag_class = 'jury'; $tag_text = 'AFP';
            } elseif (!empty($cm['current_jury'])) {
                $tag_class = 'jury'; $tag_text = 'Jury';
            }
          ?>
            <a class="cm" href="<?php echo esc_url($cm_url); ?>" title="<?php echo esc_attr($cm_full); ?>">
              <div class="face"<?php echo !empty($cm['profile_picture']) ? '' : ' data-i="' . esc_attr(strtoupper(substr($cm_display, 0, 2))) . '"'; ?>>
                <?php if (!empty($cm['profile_picture'])) : ?>
                  <?php echo wp_get_attachment_image((int) $cm['profile_picture'], 'thumbnail', false, [
                      'alt'   => sprintf('%s, %s', $cm_full, $season_abbr),
                      'style' => 'width:100%;height:100%;object-fit:cover;position:absolute;inset:0;',
                  ]); ?>
                <?php endif; ?>
                <span class="tag <?php echo esc_attr($tag_class); ?>"><?php echo esc_html($tag_text); ?></span>
              </div>
              <div class="n"><?php echo esc_html($cm_display); ?></div>
            </a>
          <?php endforeach; ?>
        </div>
      </section>
      <?php endif; ?>
    </div>

    <!-- SIDEBAR -->
    <aside>
      <div class="stick">
        <!-- AFP Odds placeholder -->
        <div class="card odds-card">
          <h4>AFP Odds <small>Coming soon</small></h4>
          <div class="odds-big">
            <div class="k">Voting system</div>
            <div class="n" style="font-size:28px;-webkit-text-stroke:0;text-shadow:none;">—</div>
            <div class="d">AFP voting runs all season. Custom polling system in the works — not Jokers'.</div>
          </div>
        </div>

        <!-- Fan Affinity placeholder -->
        <div class="card fan">
          <h4>Fan Affinity <small>Awaiting votes</small></h4>
          <p style="font-family:var(--serif);font-size:13px;color:var(--ink-2);line-height:1.45;">Needs 10+ fan ratings to display. Ratings open once the voting system ships.</p>
        </div>

        <!-- Fan Ranking placeholder -->
        <div class="card ranks">
          <h4><?php echo esc_html($latest['season_abbr'] ?? 'Season'); ?> Fan Ranking</h4>
          <p style="font-family:var(--serif);font-size:13px;color:var(--ink-2);line-height:1.45;">Season ranking opens once enough affinity scores accumulate.</p>
        </div>

        <!-- Follow card -->
        <?php if (!empty($player['socials'])) :
          $social_labels = [
            'twitter'   => ['ic' => '𝕏',   'label' => 'X / Twitter'],
            'instagram' => ['ic' => '📷', 'label' => 'Instagram'],
            'facebook'  => ['ic' => 'f',   'label' => 'Facebook'],
            'tiktok'    => ['ic' => '♪',   'label' => 'TikTok'],
          ];
        ?>
        <div class="card social">
          <h4>Follow <?php echo esc_html($player['first_name'] ?: $player['full_name']); ?></h4>
          <div class="socials">
            <?php foreach ($player['socials'] as $platform => $url) :
              $meta = $social_labels[$platform] ?? ['ic' => '↗', 'label' => ucfirst($platform)];
            ?>
              <a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener">
                <span class="ic"><?php echo esc_html($meta['ic']); ?></span>
                <span><?php echo esc_html($meta['label']); ?></span>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- Sticky ad rail -->
        <?php get_template_part('template-parts/components/ad-placeholder', null, [
          'slot'        => 'player_profile_sidebar',
          'size'        => '300x600',
          'mobile_size' => '300x250',
          'note'        => __('Single player profile · right rail', 'bbj-v2-theme'),
        ]); ?>
      </div>
    </aside>

  </div>

</main>

<?php get_footer(); ?>
