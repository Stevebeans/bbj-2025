<?php
/**
 * Single Season Profile — magazine-style redesign.
 *
 * Spec:   docs/superpowers/specs/2026-04-24-season-profile-design.md
 * Design: .claude/claude-design/bbj-season-profile/bbj-home-page/project/BBJ Season Profile.html
 */

if (!have_posts()) {
    get_header();
    echo '<main class="wrap"><p>Season not found.</p></main>';
    get_footer();
    return;
}

the_post();
$post_id   = get_the_ID();
$season    = bbj_v2_season_profile_data($post_id);
$facts     = bbj_v2_season_profile_facts($season);
$neighbors = bbj_v2_season_profile_neighbors($post_id, 5);
$cast      = bbj_v2_season_profile_cast($post_id);
$evictions = bbj_v2_season_profile_evictions($post_id);
$comps     = bbj_v2_season_profile_comps($post_id);
$top_feed  = bbj_v2_season_profile_top_feed_updates($post_id, 9);
$articles  = bbj_v2_season_profile_articles($post_id, 4);

// Determine winner / runner-up / AFP from the cast set
$winner = $runner_up = $afp = null;
foreach ($cast as $cm) {
    if ((int) $cm['finish_place'] === 1) $winner    = $cm;
    if ((int) $cm['finish_place'] === 2) $runner_up = $cm;
}
$afp_id = (int) ($season['afp_id'] ?? 0);
if ($afp_id > 0) {
    foreach ($cast as $cm) {
        if ((int) $cm['player_post_id'] === $afp_id) { $afp = $cm; break; }
    }
}

// Build section list — order matters, matches scroll order.
// Future tasks will append: memories, articles.
$sections = [];
if (!empty($season['content']) || !empty($facts)) {
    $sections[] = ['id' => 'overview', 'label' => 'Overview',   'count' => null];
}
if ($winner || $runner_up) {
    $sections[] = ['id' => 'winners',  'label' => 'Top 3 & AFP', 'count' => null];
}
if (!empty($cast)) {
    $sections[] = ['id' => 'cast',     'label' => 'Cast',        'count' => count($cast)];
}
if (!empty($evictions)) {
    $sections[] = ['id' => 'evictions', 'label' => 'Evictions',    'count' => count($evictions)];
}
if (!empty($comps)) {
    $sections[] = ['id' => 'comps',     'label' => 'Comp Winners', 'count' => count($comps)];
}
if (!empty($top_feed)) {
    $sections[] = ['id' => 'memories', 'label' => 'Memorable Moments', 'count' => count($top_feed)];
}
if (!empty($articles)) {
    $sections[] = ['id' => 'articles', 'label' => 'Articles',          'count' => count($articles)];
}

get_header();
?>

<main class="wrap">

  <!-- Breadcrumb -->
  <nav class="crumb" aria-label="Breadcrumb">
    <a href="<?php echo esc_url(home_url('/')); ?>">Home</a><span class="sep">/</span>
    <a href="<?php echo esc_url(home_url('/bigbrother-seasons/')); ?>">Seasons</a><span class="sep">/</span>
    <b><?php echo esc_html($season['title'] ?: get_the_title()); ?></b>
  </nav>

  <!-- HERO -->
  <div class="hero">
    <div class="inner">
      <div>
        <div class="kk"><b>Season <?php echo (int) $season['number']; ?></b>USA · CBS</div>
        <h1><?php echo esc_html($season['name']); ?></h1>
        <?php if (!empty($season['content'])) : ?>
          <p class="sub"><?php echo esc_html(wp_trim_words(wp_strip_all_tags($season['content'] ?? ''), 38)); ?></p>
        <?php endif; ?>
        <div class="stripstats">
          <?php if ($season['winner_name']) : ?>
            <div class="s"><span class="k">Winner</span><span class="v"><?php echo esc_html($season['winner_name']); ?></span></div>
          <?php endif; ?>
          <?php if ($season['prize']) : ?>
            <div class="s"><span class="k">Prize</span><span class="v"><b><?php echo esc_html($season['prize']); ?></b></span></div>
          <?php endif; ?>
          <?php if ($season['days']) : ?>
            <div class="s"><span class="k">Days</span><span class="v"><?php echo (int) $season['days']; ?></span></div>
          <?php endif; ?>
          <?php if ($season['hg_count']) : ?>
            <div class="s"><span class="k">Houseguests</span><span class="v"><?php echo (int) $season['hg_count']; ?></span></div>
          <?php endif; ?>
        </div>
        <div class="actions">
          <a class="b prim" href="<?php echo esc_url(home_url('/feed-updates/')); ?>">&#9654; Live Feed Updates</a>
        </div>
      </div>
      <div class="poster">
        <span class="tag">Season</span>
        <div class="num"><?php echo (int) $season['number']; ?></div>
        <div class="ttl"><?php echo esc_html($season['abbr']); ?></div>
        <div class="chip"><?php echo esc_html($season['abbr']); ?> · <?php echo esc_html(date_i18n('F Y', strtotime($season['start_date'] ?? $season['post_date']))); ?></div>
      </div>
    </div>
  </div>

  <?php if (!empty($neighbors)) : ?>
  <div class="switcher">
    <span class="k">Switch season</span>
    <div class="pills">
      <?php foreach ($neighbors as $n) : ?>
        <a href="<?php echo esc_url($n['url']); ?>" class="<?php echo esc_attr($n['is_current'] ? 'on' : ''); ?>">
          <?php echo esc_html($n['abbreviation']); ?>
        </a>
      <?php endforeach; ?>
    </div>
    <a class="all" href="<?php echo esc_url(home_url('/bigbrother-seasons/')); ?>">All seasons &rarr;</a>
  </div>
  <?php endif; ?>

  <?php if (!empty($sections)) : ?>
  <div class="sectionnav">
    <?php foreach ($sections as $i => $sec) : ?>
      <a href="#<?php echo esc_attr($sec['id']); ?>" class="<?php echo esc_attr($i === 0 ? 'on' : ''); ?>">
        <?php echo esc_html($sec['label']); ?>
        <?php if ($sec['count'] !== null) : ?>
          <span class="ct"><?php echo esc_html((string) $sec['count']); ?></span>
        <?php endif; ?>
      </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div class="grid">
    <!-- MAIN -->
    <div>

      <!-- OVERVIEW -->
      <?php if (in_array('overview', array_column($sections, 'id'), true)) : ?>
      <section id="overview">
        <div class="sech">
          <h2>Season Overview</h2>
          <span class="sub">At a glance</span>
        </div>
        <div class="overview">
          <div class="copy">
            <?php if (!empty($season['content'])) : ?>
              <?php echo apply_filters('the_content', $season['content']); ?>
            <?php else : ?>
              <p class="lead">Season recap coming soon.</p>
            <?php endif; ?>
          </div>
          <?php if (!empty($facts)) : ?>
          <div class="facts">
            <h4>Season Facts</h4>
            <dl>
              <?php foreach ($facts as $f) : ?>
                <dt><?php echo esc_html($f[0]); ?></dt><dd><?php echo esc_html($f[1]); ?></dd>
              <?php endforeach; ?>
            </dl>
          </div>
          <?php endif; ?>
        </div>
      </section>
      <?php endif; ?>

      <!-- WINNERS PODIUM -->
      <?php if ($winner || $runner_up) : ?>
      <section id="winners">
        <div class="sech">
          <h2>Top 3 &amp; AFP</h2>
          <span class="sub">How the season ended</span>
        </div>
        <div class="podium">
          <?php if ($runner_up) :
            $ru_full = trim($runner_up['first_name'] . ' ' . $runner_up['last_name']);
            $ru_init = strtoupper(substr($ru_full ?: 'XX', 0, 2));
          ?>
            <div class="p r">
              <a href="<?php echo esc_url(get_permalink((int) $runner_up['player_post_id'])); ?>">
                <div class="pc"<?php echo empty($runner_up['profile_picture']) ? ' data-i="' . esc_attr($ru_init) . '"' : ''; ?>>
                  <?php if (!empty($runner_up['profile_picture'])) {
                      echo wp_get_attachment_image((int) $runner_up['profile_picture'], 'medium', false, [
                          'alt'   => esc_attr($ru_full),
                          'style' => 'width:100%;height:100%;object-fit:cover;position:absolute;inset:0;',
                      ]);
                  } ?>
                  <span class="lbl">2nd · Runner-up</span>
                </div>
                <div class="body">
                  <div class="name"><?php echo esc_html($ru_full); ?></div>
                  <div class="role">Final 2</div>
                  <div class="nums">
                    <span><b><?php echo (int) $runner_up['bbj_total_hoh']; ?></b>HoH</span>
                    <span><b><?php echo (int) $runner_up['bbj_total_pov']; ?></b>PoV</span>
                    <span><b><?php echo (int) $runner_up['bbj_total_nom']; ?></b>Nom</span>
                  </div>
                </div>
              </a>
            </div>
          <?php endif; ?>

          <?php if ($winner) :
            $w_full = trim($winner['first_name'] . ' ' . $winner['last_name']);
            $w_init = strtoupper(substr($w_full ?: 'XX', 0, 2));
          ?>
            <div class="p w">
              <a href="<?php echo esc_url(get_permalink((int) $winner['player_post_id'])); ?>">
                <div class="pc"<?php echo empty($winner['profile_picture']) ? ' data-i="' . esc_attr($w_init) . '"' : ''; ?>>
                  <?php if (!empty($winner['profile_picture'])) {
                      echo wp_get_attachment_image((int) $winner['profile_picture'], 'medium', false, [
                          'alt'   => esc_attr($w_full),
                          'style' => 'width:100%;height:100%;object-fit:cover;position:absolute;inset:0;',
                      ]);
                  } ?>
                  <span class="lbl">&#9733; Winner</span>
                </div>
                <div class="body">
                  <div class="name"><?php echo esc_html($w_full); ?></div>
                  <div class="role"><?php echo esc_html($season['abbr']); ?> Winner</div>
                  <div class="nums">
                    <span><b><?php echo (int) $winner['bbj_total_hoh']; ?></b>HoH</span>
                    <span><b><?php echo (int) $winner['bbj_total_pov']; ?></b>PoV</span>
                    <span><b><?php echo (int) $winner['bbj_total_nom']; ?></b>Nom</span>
                  </div>
                  <?php if (!empty($season['prize'])) : ?>
                    <div class="prize"><?php echo esc_html($season['prize']); ?></div>
                  <?php endif; ?>
                </div>
              </a>
            </div>
          <?php endif; ?>

          <?php if ($afp) :
            $afp_full = trim($afp['first_name'] . ' ' . $afp['last_name']);
            $afp_init = strtoupper(substr($afp_full ?: 'XX', 0, 2));
            $afp_url  = get_permalink((int) $afp['player_post_id']);
          ?>
            <div class="p a">
              <a href="<?php echo esc_url($afp_url); ?>">
                <div class="pc"<?php echo empty($afp['profile_picture']) ? ' data-i="' . esc_attr($afp_init) . '"' : ''; ?>>
                  <?php if (!empty($afp['profile_picture'])) {
                      echo wp_get_attachment_image((int) $afp['profile_picture'], 'medium', false, [
                          'alt'   => esc_attr($afp_full),
                          'style' => 'width:100%;height:100%;object-fit:cover;position:absolute;inset:0;',
                      ]);
                  } ?>
                  <span class="lbl">AFP</span>
                </div>
                <div class="body">
                  <div class="name"><?php echo esc_html($afp_full); ?></div>
                  <div class="role">America's Favorite</div>
                  <div class="nums">
                    <span><b><?php echo (int) $afp['bbj_total_hoh']; ?></b>HoH</span>
                    <span><b><?php echo (int) $afp['bbj_total_pov']; ?></b>PoV</span>
                    <span><b><?php echo (int) $afp['bbj_total_nom']; ?></b>Nom</span>
                  </div>
                </div>
              </a>
            </div>
          <?php endif; ?>
        </div>
      </section>
      <?php endif; ?>

      <!-- CAST GRID -->
      <?php if (!empty($cast)) : ?>
      <section id="cast">
        <div class="sech">
          <h2>Cast of <?php echo esc_html($season['abbr']); ?></h2>
          <span class="sub"><?php echo (int) count($cast); ?> houseguests</span>
        </div>
        <div class="castgrid">
          <?php foreach ($cast as $cm) :
            $cm_full    = trim($cm['first_name'] . ' ' . $cm['last_name']);
            $cm_display = $cm['official_nickname'] ?: ($cm['first_name'] ?: $cm_full);
            $cm_url     = get_permalink((int) $cm['player_post_id']);
            $cm_finish  = (int) ($cm['finish_place'] ?? 0);

            $tag_class = 'pre';
            $tag_text  = 'Out';
            $is_afp    = ($afp_id > 0 && (int) $cm['player_post_id'] === $afp_id);
            if ($cm_finish === 1)                  { $tag_class = 'win';  $tag_text = 'Winner'; }
            elseif ($cm_finish === 2)              { $tag_class = 'ru';   $tag_text = '2nd'; }
            elseif ($is_afp)                       { $tag_class = 'afp';  $tag_text = 'AFP'; }
            elseif (!empty($cm['current_jury']))   { $tag_class = 'jury'; $tag_text = 'Jury'; }

            $days = '';
            if (!empty($cm['bbj_evicted_date']) && !empty($season['start_date'])) {
                try {
                    $d1 = new DateTime($season['start_date']);
                    $d2 = new DateTime($cm['bbj_evicted_date']);
                    $days = max(0, (int) $d1->diff($d2)->days) . 'd';
                } catch (Exception $e) {}
            }
            $sub = $cm_finish > 0
                ? bbj_v2_season_profile_ordinal($cm_finish) . ($days ? ' · ' . $days : '')
                : 'Active';

            $cm_init = strtoupper(substr($cm_display ?: $cm_full ?: 'XX', 0, 2));
          ?>
          <a class="c" href="<?php echo esc_url($cm_url); ?>" title="<?php echo esc_attr($cm_full); ?>">
            <div class="face"<?php echo empty($cm['profile_picture']) ? ' data-i="' . esc_attr($cm_init) . '"' : ''; ?>>
              <?php if (!empty($cm['profile_picture'])) {
                  echo wp_get_attachment_image((int) $cm['profile_picture'], 'thumbnail', false, [
                      'alt'   => esc_attr($cm_full),
                      'style' => 'width:100%;height:100%;object-fit:cover;position:absolute;inset:0;',
                  ]);
              } ?>
              <span class="tag <?php echo esc_attr($tag_class); ?>"><?php echo esc_html($tag_text); ?></span>
            </div>
            <div class="n"><?php echo esc_html($cm_display); ?></div>
            <div class="s"><?php echo esc_html($sub); ?></div>
          </a>
          <?php endforeach; ?>
        </div>
      </section>
      <?php endif; ?>

      <!-- EVICTION ORDER -->
      <?php if (!empty($evictions)) : ?>
      <section id="evictions">
        <div class="sech">
          <h2>Eviction Order</h2>
          <span class="sub">Week by week</span>
        </div>
        <div class="evtable">
          <table>
            <thead><tr><th>Wk</th><th>Houseguest</th><th>Day</th><th>Vote</th><th>Type</th><th>Evicted By</th></tr></thead>
            <tbody>
              <?php foreach ($evictions as $e) :
                $type_class = 'reg';
                if ($e['type'] === 'Double') $type_class = 'db';
                if ($e['type'] === 'Finale') $type_class = 'fin';
                $initials = strtoupper(substr($e['name'] ?: 'XX', 0, 2));
                $url = get_permalink($e['player_id']);
              ?>
              <tr>
                <td class="wk"><?php echo esc_html($e['week_label']); ?></td>
                <td>
                  <a class="hg" href="<?php echo esc_url($url); ?>">
                    <span class="a"><?php echo esc_html($initials); ?></span><?php echo esc_html($e['name']); ?>
                  </a>
                </td>
                <td class="day"><?php echo esc_html($e['day']); ?></td>
                <td class="vote"><?php echo esc_html($e['vote']); ?></td>
                <td><span class="typ <?php echo esc_attr($type_class); ?>"><?php echo esc_html($e['type']); ?></span></td>
                <td><?php echo $e['hoh_name'] ? esc_html('HoH ' . $e['hoh_name']) : ''; ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
      <?php endif; ?>

      <!-- COMP WINNERS -->
      <?php if (!empty($comps)) : ?>
      <section id="comps">
        <div class="sech">
          <h2>Competition Winners</h2>
          <span class="sub">HoH &amp; Veto each week</span>
        </div>
        <div class="comptable">
          <table>
            <thead><tr><th>Week</th><th>Head of Household</th><th>Power of Veto</th><th>Nominees</th><th>Veto Used On</th></tr></thead>
            <tbody>
              <?php foreach ($comps as $c) : ?>
              <tr>
                <td class="wkhead">W<?php echo (int) $c['week_num']; ?></td>
                <td>
                  <?php if (!empty($c['hoh'])) : ?>
                    <?php foreach ($c['hoh'] as $name) : ?>
                      <span class="cell"><span class="a"><?php echo esc_html(strtoupper(substr($name, 0, 2))); ?></span><?php echo esc_html($name); ?></span>
                    <?php endforeach; ?>
                  <?php else : ?>
                    <span class="cell empty">—</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if (!empty($c['pov'])) : ?>
                    <?php foreach ($c['pov'] as $name) : ?>
                      <span class="cell"><span class="a"><?php echo esc_html(strtoupper(substr($name, 0, 2))); ?></span><?php echo esc_html($name); ?></span>
                    <?php endforeach; ?>
                  <?php else : ?>
                    <span class="cell empty">—</span>
                  <?php endif; ?>
                </td>
                <td><?php echo esc_html(implode(' · ', $c['nom'])); ?></td>
                <td>
                  <?php if (!empty($c['saved'])) : ?>
                    <?php foreach ($c['saved'] as $name) : ?>
                      <span class="cell"><span class="a"><?php echo esc_html(strtoupper(substr($name, 0, 2))); ?></span><?php echo esc_html($name); ?></span>
                    <?php endforeach; ?>
                  <?php else : ?>
                    <span class="cell empty">Not used</span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
      <?php endif; ?>

      <!-- TOP FEED UPDATES (Memorable Moments) -->
      <?php if (!empty($top_feed)) : ?>
      <section id="memories">
        <div class="sech">
          <h2>Memorable Moments</h2>
          <span class="sub">Top fan-rated feed updates</span>
        </div>
        <div class="memories">
          <?php foreach ($top_feed as $u) :
            $week = '';
            if (!empty($season['start_date'])) {
                try {
                    $d1 = new DateTime($season['start_date']);
                    $d2 = new DateTime((string) ($u['post_date'] ?? ''));
                    $diff_days = (int) $d1->diff($d2)->days;
                    $week = 'Week ' . max(1, (int) floor($diff_days / 7) + 1);
                } catch (Exception $e) {}
            }
            // Quote precedence: post_excerpt → post_content (trimmed) → post_title.
            // Many older feed updates only used the subject line (post_title) with
            // an empty body, so title is the safety-net to avoid blank quote cards.
            $quote = trim((string) ($u['post_excerpt'] ?? ''));
            if ($quote === '') {
                $quote = trim(wp_trim_words(wp_strip_all_tags((string) ($u['post_content'] ?? '')), 28));
            }
            if ($quote === '') {
                $quote = trim((string) ($u['post_title'] ?? ''));
            }
          ?>
          <div class="mem">
            <div class="qt"><?php echo esc_html($quote); ?></div>
            <div class="att">
              <span><?php echo esc_html(date_i18n('M j', strtotime((string) ($u['post_date'] ?? '')))); ?></span>
              <b><?php echo esc_html($week); ?></b>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </section>
      <?php endif; ?>

      <!-- ARTICLES -->
      <?php if (!empty($articles)) : ?>
      <section id="articles">
        <div class="sech">
          <h2>Articles About <?php echo esc_html($season['abbr']); ?></h2>
          <span class="sub"><?php echo (int) count($articles); ?> on the BBJ blog</span>
        </div>
        <div class="artrow">
          <?php foreach ($articles as $a) : ?>
          <a class="art" href="<?php echo esc_url($a['url']); ?>">
            <div class="thm" data-label="Article" <?php
              if (!empty($a['thumb'])) {
                  $style = 'background-image:url(' . esc_url($a['thumb']) . ');background-size:cover;background-position:center';
                  echo 'style="' . esc_attr($style) . '"';
              }
            ?>></div>
            <div class="txt">
              <span class="k"><?php echo esc_html($a['category']); ?></span>
              <h3><?php echo esc_html($a['title']); ?></h3>
              <span class="m"><?php echo esc_html(human_time_diff(strtotime((string) ($a['date'] ?? '')), current_time('timestamp')) . ' ago'); ?></span>
            </div>
          </a>
          <?php endforeach; ?>
        </div>
      </section>
      <?php endif; ?>

      <!-- additional sections: sidebar at task 8, caching at task 9 -->

    </div>

    <!-- SIDEBAR -->
    <aside>
      <div class="stick">

        <?php if (!empty($sections)) : ?>
        <div class="card toc">
          <h4>On This Page</h4>
          <ul>
            <?php foreach ($sections as $sec) : ?>
              <li>
                <a href="#<?php echo esc_attr($sec['id']); ?>">
                  <span><?php echo esc_html($sec['label']); ?></span>
                  <?php if ($sec['count'] !== null) : ?>
                    <span><?php echo esc_html((string) $sec['count']); ?></span>
                  <?php endif; ?>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
        <?php endif; ?>

        <?php if (!empty($facts)) : ?>
        <div class="card facts-card">
          <h4>Quick Facts</h4>
          <dl>
            <?php foreach ($facts as $f) : ?>
              <dt><?php echo esc_html($f[0]); ?></dt>
              <dd><?php echo esc_html($f[1]); ?></dd>
            <?php endforeach; ?>
          </dl>
        </div>
        <?php endif; ?>

        <?php
        // More Seasons — reuse $neighbors but exclude current and limit to 4
        $more = array_values(array_filter($neighbors, function ($n) { return !$n['is_current']; }));
        $more = array_slice($more, 0, 4);
        ?>
        <?php if (!empty($more)) : ?>
        <div class="card">
          <h4>More Seasons</h4>
          <div class="seas-mini">
            <?php foreach ($more as $n) : ?>
              <a href="<?php echo esc_url($n['url']); ?>">
                <span class="num"><?php echo (int) $n['season_number']; ?></span>
                <span class="win">Season<b><?php echo esc_html($n['abbreviation']); ?></b></span>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <div class="ad"><div class="mock">300 × 600</div></div>

      </div>
    </aside>
  </div>

</main>

<?php
get_footer();
