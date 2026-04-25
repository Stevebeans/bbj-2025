<?php
/**
 * Admin pane: Roadmap & page coverage audit.
 *
 * Glance page for the BBJ owner — shows what's shipped, partial, or not
 * started across the bbj-v2-theme migration. Hardcoded snapshot of the
 * data in .claude/project/roadmap.md (#Page coverage audit). Update both
 * when the audit changes.
 */

if (!defined('ABSPATH')) {
    exit;
}

bbj_v2_require_admin();

// Status enum: 'shipped' | 'partial' | 'not_started'
$sections = [
    [
        'title'   => 'Public pages',
        'rows'    => [
            ['/',                                'Homepage',                'shipped',     null,  ''],
            ['/<post-slug>/',                    'Single post',             'shipped',     null,  ''],
            ['/<page-slug>/',                    'Generic page',            'shipped',     null,  ''],
            ['/feed-updates/',                   'Feed Updates Hub',        'partial',     70,    'Sprint D skeleton; voting / auto-refresh / pinning / chips / trending / quotes deferred'],
            ['/feed-updates/<slug>/',            'Single feed update',      'partial',     50,    'Template exists from earlier work; design pass not done'],
            ['/bigbrother-players/',             'Player directory',        'partial',     30,    'Sprint C skeleton; no design yet'],
            ['/bigbrother-players/<slug>/',      'Single player',           'shipped',     null,  'Sprint B'],
            ['/bigbrother-seasons/',             'Seasons directory',       'partial',     30,    'Sprint C skeleton; no design yet'],
            ['/bigbrother-seasons/<slug>/',      'Single season',           'shipped',     null,  'Sprint B'],
            ['/search/',                         'Search results',          'partial',     10,    'Default WP stub; Sprint M'],
            ['/404',                             '404 page',                'partial',     10,    'Default WP stub; Sprint M'],
            ['/stats',                           'Site-wide stats',         'not_started', null,  'Sprint J — replaces legacy /big-brother-stats/'],
            ['/compare',                         'Player compare picker',   'not_started', null,  'Sprint K'],
            ['/compare/<a-vs-b>/',               'Specific matchup',        'not_started', null,  'Sprint K — shareable URL'],
            ['/player-map',                      'Geographic map',          'not_started', null,  'Sprint L'],
            ['/users/<username>/',               'Public user profile',     'not_started', null,  'Sprint F — shares data with /dashboard?tab=profile'],
            ['/contact/',                        'Contact form',            'not_started', null,  'Sprint N — shortcode-based'],
            ['/power-rankings/',                 'Power rankings',          'not_started', null,  'Referenced in nav but no template'],
            ['/forums/',                         'Forums',                  'not_started', null,  'Referenced in nav but no template'],
            ['/watch-feeds/',                    'Watch live feeds CTA',    'not_started', null,  'Referenced in nav'],
        ],
    ],
    [
        'title'   => 'Auth / account / transactional',
        'rows'    => [
            ['/login/',              'Custom login page',          'not_started', null, 'Default WP — bbj-app had a custom design, not ported'],
            ['/reset-password/',     'Password reset',             'not_started', null, 'Default WP — bbj-app had custom UX'],
            ['/email/confirm/',      'Email confirmation',         'not_started', null, 'bbj-app reference'],
            ['/unsubscribe/',        'Newsletter unsubscribe',     'not_started', null, 'bbj-app reference'],
            ['/become-supporter/',   'Premium upsell',             'not_started', null, 'OLD page-become-supporter.php exists; needs MemberPress wiring (Sprint G area)'],
            ['/checkout/success/',   'Checkout success',           'not_started', null, 'Stripe / MemberPress flow'],
            ['/checkout/cancel/',    'Checkout cancel',            'not_started', null, 'Stripe / MemberPress flow'],
            ['/privacy-policy/',     'Privacy policy',             'not_started', null, 'Likely a WP page; verify on prod'],
        ],
    ],
    [
        'title'   => 'Admin tabs (/admin/?tab=…)',
        'rows'    => [
            ['?tab=overview',         'Overview',                'shipped',     null, ''],
            ['?tab=feed-updates',     'Feed Updates',            'partial',     80,   'Sprint I MVP; image edit, search, filter, N+1 tune deferred'],
            ['?tab=settings',         'Settings',                'shipped',     null, ''],
            ['?tab=seasons',          'Seasons',                 'partial',     50,   'Basic info + spoiler bar shipped; images / winners / roster stubbed'],
            ['?tab=spoiler-bar',      'Spoiler Bar editor',      'shipped',     null, 'Folded into seasons edit'],
            ['?tab=comp-types',       'Comp Types',              'shipped',     null, 'Sprint R — game data CRUD'],
            ['?tab=roadmap',          'Roadmap (this page)',     'shipped',     null, 'Owner-only glance page'],
            ['?tab=posts',            'Posts manager',           'not_started', null, 'Sprint O — connects to Sprint P composer'],
            ['?tab=comments',         'Comments moderation',     'not_started', null, 'Sprint O'],
            ['?tab=players',          'Players CRUD',            'not_started', null, 'Sprint O — currently still in old bbj-v2 plugin UI'],
            ['?tab=announcements',    'Broadcast notifications', 'not_started', null, 'Sprint O'],
            ['?tab=content-engine',   'FB post generator',       'not_started', null, 'Sprint O low priority'],
            ['?tab=users',            'Users + bulk cleanup',    'not_started', null, 'Sprint O'],
            ['?tab=stats',            'GA4 + GSC dashboard',     'not_started', null, 'Sprint J'],
            ['?tab=ads',              'Ad slots hub',            'not_started', null, 'Sprint O low priority'],
            ['?tab=preview-as',       'Role impersonation',      'not_started', null, 'Sprint O'],
            ['?tab=bug-reports',      'Bug reports inbox',       'not_started', null, 'bbj-app reference, not on roadmap yet'],
        ],
    ],
    [
        'title'   => 'User dashboard tabs (/dashboard/?tab=…)',
        'rows'    => [
            ['?tab=overview',        'Overview',           'partial',     30,   'Basic shell, no real activity data'],
            ['?tab=activity',        'Activity feed',      'not_started', null, 'Sprint F'],
            ['?tab=saved',           'Saved posts',        'not_started', null, 'Sprint F — depends on save-button infra'],
            ['?tab=notifications',   'Notifications',      'not_started', null, 'Sprint E'],
            ['?tab=profile',         'Public profile',     'not_started', null, 'Sprint F'],
            ['?tab=premium',         'MemberPress status', 'not_started', null, 'Sprint G'],
            ['?tab=settings',        'Account settings',   'not_started', null, 'Sprint F'],
            ['?tab=feeds-blog',      'Feeds Blog',         'not_started', null, 'Listed in sidebar but not in any sprint'],
            ['?tab=power-rankings',  'Power Rankings',     'not_started', null, 'Listed in sidebar but not in any sprint'],
            ['?tab=leaderboard',     'Leaderboard',        'not_started', null, 'Listed in sidebar but not in any sprint'],
        ],
    ],
    [
        'title'   => 'Editor / composer (Sprint P)',
        'rows'    => [
            ['/editor/',         'Editor landing',     'not_started', null, 'Sprint P'],
            ['/editor/new/',     'New post composer',  'not_started', null, 'Sprint P'],
            ['/editor/<id>/',    'Edit existing post', 'not_started', null, 'Sprint P'],
            ['/preview/<id>/',   'Preview drafts',     'not_started', null, 'bbj-app had this'],
        ],
    ],
    [
        'title'   => 'Legacy BBJ theme — decisions pending',
        'rows'    => [
            ['page-big-brother-stats.php',       '/big-brother-stats/',                'not_started', null, 'Replace with /stats (Sprint J)'],
            ['page-all-seasons.php',             '/all-seasons/',                      'not_started', null, 'Trash — /bigbrother-seasons/ covers it'],
            ['page-season-list.php',             '/season-list/',                      'not_started', null, 'Trash — duplicate of /all-seasons/'],
            ['page-player-directory.php',        '/player-directory/',                 'shipped',     null, 'Replaced — /bigbrother-players/ covers it'],
            ['page-player-relationships.php',    '/player-relationships/',             'not_started', null, 'Decision needed — port, redesign, or trash?'],
            ['page-my-profile.php',              '/my-profile/',                       'not_started', null, 'Replace with /dashboard?tab=profile (Sprint F)'],
            ['page-user-dashboard.php',          '/user-dashboard/',                   'shipped',     null, 'Replaced — /dashboard/ covers it'],
            ['page-feed-updates.php',            '/feed-updates/',                     'shipped',     null, 'CPT archive wins now; orphaned WP page can be trashed'],
            ['page-login.php',                   '/login/',                            'not_started', null, 'Decision needed — keep custom or use default WP login'],
            ['page-become-supporter.php',        '/become-supporter/',                 'not_started', null, 'Port to bbj-v2-theme (Sprint G area)'],
            ['page-stripe-test.php',             '/stripe-test/',                      'not_started', null, 'Trash — dev-only'],
            ['page-testing.php / testtwo / testingtwo', 'dev scratchpads',             'not_started', null, 'Trash'],
        ],
    ],
];

// Sprint → page mapping. Each row is [section title, slug] so it
// resolves unambiguously even when the same slug exists in two sections
// (e.g. ?tab=settings is in both admin and dashboard).
$pub  = 'Public pages';
$auth = 'Auth / account / transactional';
$adm  = 'Admin tabs (/admin/?tab=…)';
$dash = 'User dashboard tabs (/dashboard/?tab=…)';
$edt  = 'Editor / composer (Sprint P)';

$sprints = [
    ['key' => 'foundation', 'name' => 'Foundation',
     'subtitle' => 'Pre-sprint scaffolding — homepage, single, page',
     'pages' => [[$pub,'/'], [$pub,'/<post-slug>/'], [$pub,'/<page-slug>/']]],
    ['key' => 'A', 'name' => 'Sprint A',
     'subtitle' => 'Site Settings + Spoiler Bar Manager + admin shell',
     'pages' => [[$adm,'?tab=overview'], [$adm,'?tab=settings'], [$adm,'?tab=seasons'], [$adm,'?tab=spoiler-bar'], [$dash,'?tab=overview']]],
    ['key' => 'B', 'name' => 'Sprint B',
     'subtitle' => 'Player + Season profiles',
     'pages' => [[$pub,'/bigbrother-players/<slug>/'], [$pub,'/bigbrother-seasons/<slug>/']]],
    ['key' => 'C', 'name' => 'Sprint C',
     'subtitle' => 'Houseguests + Seasons archives',
     'pages' => [[$pub,'/bigbrother-players/'], [$pub,'/bigbrother-seasons/']]],
    ['key' => 'D', 'name' => 'Sprint D',
     'subtitle' => 'Feed Update Hub',
     'pages' => [[$pub,'/feed-updates/'], [$pub,'/feed-updates/<slug>/']]],
    ['key' => 'E', 'name' => 'Sprint E',
     'subtitle' => 'Notifications system',
     'pages' => [[$dash,'?tab=notifications']]],
    ['key' => 'F', 'name' => 'Sprint F',
     'subtitle' => 'Dashboard cluster — Settings / Profile / Saved / Activity',
     'pages' => [[$dash,'?tab=settings'], [$dash,'?tab=profile'], [$dash,'?tab=saved'], [$dash,'?tab=activity'], [$pub,'/users/<username>/']]],
    ['key' => 'G', 'name' => 'Sprint G',
     'subtitle' => 'Premium pane + paywall',
     'pages' => [[$dash,'?tab=premium'], [$auth,'/become-supporter/'], [$auth,'/checkout/success/'], [$auth,'/checkout/cancel/']]],
    ['key' => 'H', 'name' => 'Sprint H',
     'subtitle' => 'Comments redesign (parked — no slug yet)',
     'pages' => []],
    ['key' => 'I', 'name' => 'Sprint I',
     'subtitle' => 'Feed Updates admin pane',
     'pages' => [[$adm,'?tab=feed-updates']]],
    ['key' => 'R', 'name' => 'Sprint R',
     'subtitle' => 'Weekly Tracker — junction tables + admin form + Week-by-Week display',
     'pages' => [[$adm,'?tab=comp-types']]],
    ['key' => 'J', 'name' => 'Sprint J',
     'subtitle' => 'Stats — public + admin dashboard',
     'pages' => [[$pub,'/stats'], [$adm,'?tab=stats']]],
    ['key' => 'K', 'name' => 'Sprint K',
     'subtitle' => 'Compare — picker + matchup pages',
     'pages' => [[$pub,'/compare'], [$pub,'/compare/<a-vs-b>/']]],
    ['key' => 'L', 'name' => 'Sprint L',
     'subtitle' => 'Player Map',
     'pages' => [[$pub,'/player-map']]],
    ['key' => 'M', 'name' => 'Sprint M',
     'subtitle' => 'Search + 404',
     'pages' => [[$pub,'/search/'], [$pub,'/404']]],
    ['key' => 'N', 'name' => 'Sprint N',
     'subtitle' => 'Contact page',
     'pages' => [[$pub,'/contact/']]],
    ['key' => 'O', 'name' => 'Sprint O',
     'subtitle' => 'Remaining admin panes',
     'pages' => [[$adm,'?tab=posts'], [$adm,'?tab=comments'], [$adm,'?tab=players'], [$adm,'?tab=announcements'], [$adm,'?tab=content-engine'], [$adm,'?tab=users'], [$adm,'?tab=ads'], [$adm,'?tab=preview-as']]],
    ['key' => 'P', 'name' => 'Sprint P',
     'subtitle' => 'Custom post composer ("old-lady-friendly" editor)',
     'pages' => [[$edt,'/editor/'], [$edt,'/editor/new/'], [$edt,'/editor/<id>/'], [$edt,'/preview/<id>/']]],
];

// Shipped pages still need a final polish pass once the rest of the site
// is built — so we cap their contribution at 90% rather than 100%.
const FUH_SHIPPED_PCT = 90;

// Compute totals.
$totals = ['shipped' => 0, 'partial' => 0, 'not_started' => 0, 'total' => 0];
$weighted_progress = 0;
foreach ($sections as $section) {
    foreach ($section['rows'] as $row) {
        $totals['total']++;
        $status = $row[2];
        $totals[$status]++;
        if ($status === 'shipped') {
            $weighted_progress += FUH_SHIPPED_PCT;
        } elseif ($status === 'partial') {
            $weighted_progress += $row[3] !== null ? (int) $row[3] : 50;
        }
    }
}
$overall_pct = $totals['total'] > 0 ? (int) round($weighted_progress / $totals['total']) : 0;

// Status pill helper.
$status_meta = [
    'shipped'     => ['label' => 'Shipped',     'classes' => 'bg-green-100 text-green-900'],
    'partial'     => ['label' => 'Partial',     'classes' => 'bg-amber-100 text-amber-900'],
    'not_started' => ['label' => 'Not started', 'classes' => 'bg-slate-100 text-slate-700'],
];

// Flat row lookup keyed by "Section title|slug" so sprint pages can resolve
// to their status / pct without duplicating the source data.
$row_lookup = [];
foreach ($sections as $section) {
    foreach ($section['rows'] as $row) {
        $row_lookup[$section['title'] . '|' . $row[0]] = $row;
    }
}

$row_pct = function (array $row) {
    if ($row[2] === 'shipped')                              return FUH_SHIPPED_PCT;
    if ($row[2] === 'partial' && $row[3] !== null)          return (int) $row[3];
    if ($row[2] === 'partial')                              return 50;
    return 0;
};

$row_chip_classes = function (array $row): string {
    if ($row[2] === 'shipped')     return 'bg-green-100 text-green-900 border-green-200';
    if ($row[2] === 'partial')     return 'bg-amber-100 text-amber-900 border-amber-200';
    return 'bg-slate-100 text-slate-700 border-slate-200';
};
?>

<div class="space-y-6">

  <!-- Header + summary -->
  <header class="flex flex-wrap items-end justify-between gap-4 border-b-2 border-stone-900 pb-4">
    <div>
      <p class="font-mono text-[11px] uppercase tracking-[0.12em] text-stone-500">bbj-v2-theme migration</p>
      <h1 class="font-osw text-3xl text-primary-500">Roadmap & page coverage</h1>
      <p class="mt-1 text-sm text-stone-600">Snapshot of every page in the new theme, what's shipped, what's partial, what's left.</p>
    </div>
    <div class="text-right">
      <p class="font-mono text-[11px] uppercase tracking-[0.08em] text-stone-500">Overall coverage</p>
      <p class="font-osw text-4xl text-primary-500 leading-none"><?php echo $overall_pct; ?>%</p>
      <p class="mt-1 text-xs text-stone-600">across <?php echo (int) $totals['total']; ?> pages tracked</p>
    </div>
  </header>

  <!-- Stat cards -->
  <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
    <div class="rounded-md bg-white border border-stone-200 p-4">
      <p class="font-mono text-[10px] uppercase tracking-[0.08em] text-stone-500">Total</p>
      <p class="font-osw text-2xl text-stone-900"><?php echo (int) $totals['total']; ?></p>
    </div>
    <div class="rounded-md bg-green-50 border border-green-200 p-4">
      <p class="font-mono text-[10px] uppercase tracking-[0.08em] text-green-800">Shipped</p>
      <p class="font-osw text-2xl text-green-900"><?php echo (int) $totals['shipped']; ?></p>
    </div>
    <div class="rounded-md bg-amber-50 border border-amber-200 p-4">
      <p class="font-mono text-[10px] uppercase tracking-[0.08em] text-amber-800">Partial</p>
      <p class="font-osw text-2xl text-amber-900"><?php echo (int) $totals['partial']; ?></p>
    </div>
    <div class="rounded-md bg-slate-50 border border-slate-200 p-4">
      <p class="font-mono text-[10px] uppercase tracking-[0.08em] text-slate-700">Not started</p>
      <p class="font-osw text-2xl text-slate-900"><?php echo (int) $totals['not_started']; ?></p>
    </div>
  </div>

  <!-- Sections -->
  <?php foreach ($sections as $section):
    // Per-section progress
    $sec_total = count($section['rows']);
    $sec_shipped = 0;
    $sec_partial = 0;
    foreach ($section['rows'] as $row) {
      if ($row[2] === 'shipped') $sec_shipped++;
      elseif ($row[2] === 'partial') $sec_partial++;
    }
  ?>
    <section class="rounded-md bg-white border border-stone-200 overflow-hidden">
      <header class="flex flex-wrap items-baseline justify-between gap-3 px-5 py-3 bg-stone-50 border-b border-stone-200">
        <h2 class="font-osw text-lg text-primary-500"><?php echo esc_html($section['title']); ?></h2>
        <p class="font-mono text-[10px] uppercase tracking-[0.08em] text-stone-500">
          <?php echo (int) $sec_shipped; ?> shipped · <?php echo (int) $sec_partial; ?> partial · <?php echo (int) ($sec_total - $sec_shipped - $sec_partial); ?> not started
        </p>
      </header>
      <table class="w-full text-sm">
        <thead class="bg-stone-50 border-b border-stone-200">
          <tr class="font-mono text-[10px] uppercase tracking-[0.08em] text-stone-600">
            <th class="text-left font-semibold px-5 py-2 w-1/4">Slug / template</th>
            <th class="text-left font-semibold px-5 py-2 w-1/5">Page name</th>
            <th class="text-left font-semibold px-5 py-2 w-[140px]">Status</th>
            <th class="text-left font-semibold px-5 py-2 w-[120px]">Progress</th>
            <th class="text-left font-semibold px-5 py-2">Notes</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($section['rows'] as $row):
            [$slug, $name, $status, $pct, $note] = $row;
            $meta = $status_meta[$status];
          ?>
            <tr class="border-t border-stone-100 align-top">
              <td class="px-5 py-2 font-mono text-[12px] text-stone-700"><?php echo esc_html($slug); ?></td>
              <td class="px-5 py-2 text-stone-900"><?php echo esc_html($name); ?></td>
              <td class="px-5 py-2">
                <span class="inline-block px-2 py-0.5 text-[11px] font-osw uppercase tracking-wider rounded <?php echo esc_attr($meta['classes']); ?>">
                  <?php echo esc_html($meta['label']); ?>
                </span>
              </td>
              <td class="px-5 py-2">
                <?php if ($status === 'shipped'): ?>
                  <div class="flex items-center gap-2">
                    <div class="flex-1 h-1.5 rounded-full bg-stone-200 overflow-hidden">
                      <div class="h-full bg-green-500" style="width:<?php echo (int) FUH_SHIPPED_PCT; ?>%"></div>
                    </div>
                    <span class="font-mono text-[10px] text-stone-600"><?php echo (int) FUH_SHIPPED_PCT; ?>%</span>
                  </div>
                  <p class="mt-1 font-mono text-[9px] uppercase tracking-[0.06em] text-amber-700 italic">
                    Final polish pending
                  </p>
                <?php elseif ($status === 'partial' && $pct !== null): ?>
                  <div class="flex items-center gap-2">
                    <div class="flex-1 h-1.5 rounded-full bg-stone-200 overflow-hidden">
                      <div class="h-full bg-amber-500" style="width:<?php echo (int) $pct; ?>%"></div>
                    </div>
                    <span class="font-mono text-[10px] text-stone-600"><?php echo (int) $pct; ?>%</span>
                  </div>
                <?php else: ?>
                  <span class="font-mono text-[10px] text-stone-400">—</span>
                <?php endif; ?>
              </td>
              <td class="px-5 py-2 text-[13px] text-stone-600"><?php echo esc_html($note); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </section>
  <?php endforeach; ?>

  <!-- Sprints -->
  <section class="rounded-md bg-white border border-stone-200 overflow-hidden">
    <header class="px-5 py-3 bg-stone-900 text-white">
      <h2 class="font-osw text-lg uppercase tracking-wide">Sprint coverage</h2>
      <p class="font-mono text-[10px] uppercase tracking-[0.08em] text-stone-400 mt-0.5">
        Each sprint and the pages it owns. Percent = average completion across the sprint's pages.
      </p>
    </header>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-px bg-stone-200">
      <?php foreach ($sprints as $sprint):
        $pcts = [];
        $resolved_rows = [];
        foreach ($sprint['pages'] as [$section_title, $slug]) {
          $key = $section_title . '|' . $slug;
          if (isset($row_lookup[$key])) {
            $resolved_rows[] = $row_lookup[$key];
            $pcts[] = $row_pct($row_lookup[$key]);
          }
        }
        $sprint_pct = !empty($pcts) ? (int) round(array_sum($pcts) / count($pcts)) : 0;
        $bar_color = $sprint_pct >= 80 ? 'bg-green-500'
                   : ($sprint_pct >= 30 ? 'bg-amber-500'
                   : ($sprint_pct > 0   ? 'bg-stone-400' : 'bg-stone-200'));
      ?>
        <article class="bg-white p-4 flex flex-col gap-3">
          <header class="flex items-baseline justify-between gap-3">
            <div>
              <h3 class="font-osw text-base text-primary-500"><?php echo esc_html($sprint['name']); ?></h3>
              <p class="text-[12px] text-stone-600 leading-snug"><?php echo esc_html($sprint['subtitle']); ?></p>
            </div>
            <span class="font-osw text-xl text-stone-900 shrink-0"><?php echo $sprint_pct; ?>%</span>
          </header>
          <div class="h-1.5 rounded-full bg-stone-200 overflow-hidden">
            <div class="h-full <?php echo esc_attr($bar_color); ?>" style="width:<?php echo (int) $sprint_pct; ?>%"></div>
          </div>
          <?php if (!empty($resolved_rows)): ?>
            <div class="flex flex-wrap gap-1.5 mt-1">
              <?php foreach ($resolved_rows as $r):
                $chip = $row_chip_classes($r);
              ?>
                <span class="inline-block px-2 py-0.5 text-[11px] font-mono border rounded <?php echo esc_attr($chip); ?>" title="<?php echo esc_attr($r[1]); ?>">
                  <?php echo esc_html($r[0]); ?>
                </span>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <p class="text-[11px] text-stone-400 italic">No pages assigned yet.</p>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>
  </section>

  <div class="pt-2 space-y-1 text-xs text-stone-500 font-mono uppercase tracking-[0.08em]">
    <p><span class="text-amber-700">Final polish pending</span> · shipped pages render at <?php echo (int) FUH_SHIPPED_PCT; ?>% to reflect a polish pass owed once the majority of pages are complete</p>
    <p>Source of truth: <code class="text-stone-700">.claude/project/roadmap.md</code> · update both when the audit changes</p>
  </div>

</div>
