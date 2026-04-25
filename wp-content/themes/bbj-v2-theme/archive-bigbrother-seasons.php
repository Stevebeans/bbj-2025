<?php
/**
 * Season archive — skeleton.
 *
 * Flat table of every season with winner / runner-up / AFP joined.
 * No filtering for skeleton — there are only ~27 seasons total.
 */

$seasons = bbj_v2_archive_all_seasons();

get_header();
?>

<main class="wrap" style="background:#fafaf9;min-height:60vh;">
  <div style="max-width:1100px;margin:0 auto;padding:24px 16px;">

    <header style="display:flex;flex-wrap:wrap;gap:12px;align-items:baseline;justify-content:space-between;margin-bottom:20px;border-bottom:1px solid #e7e5e4;padding-bottom:14px;">
      <h1 style="font-family:'Yanone Kaffeesatz',sans-serif;font-size:38px;line-height:1.05;color:#2D4B65;margin:0;">
        All Seasons
      </h1>
      <p style="margin:0;font-size:13px;color:#6b7280;letter-spacing:.05em;text-transform:uppercase;">
        <?php echo count($seasons); ?> seasons
      </p>
    </header>

    <?php if (empty($seasons)) : ?>
      <p style="margin:32px 0;text-align:center;color:#6b7280;font-size:14px;">No seasons found.</p>
    <?php else : ?>
      <div style="overflow-x:auto;background:#fff;border:1px solid #e7e5e4;">
        <table class="sa-table" style="width:100%;border-collapse:collapse;font-size:14px;min-width:720px;">
          <thead>
            <tr style="background:#f5f5f4;border-bottom:1px solid #e7e5e4;">
              <?php
              $headers = ['Season', 'Start Date', 'End Date', 'Winner', 'AFP', 'Runner-up'];
              foreach ($headers as $h) : ?>
                <th style="text-align:left;padding:10px 14px;font-family:'Oswald',sans-serif;font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:#374151;font-weight:600;">
                  <?php echo esc_html($h); ?>
                </th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($seasons as $s) :
              $perma   = get_permalink((int) $s['post_id']);
              $name    = $s['display_name'];
              $start   = $s['start_date'] && $s['start_date'] !== '0000-00-00' ? date('M j, Y', strtotime($s['start_date'])) : '—';
              $end     = $s['end_date']   && $s['end_date']   !== '0000-00-00' ? date('M j, Y', strtotime($s['end_date']))   : '—';
            ?>
              <tr class="sa-row" style="border-bottom:1px solid #f5f5f4;cursor:pointer;" onclick="window.location='<?php echo esc_js($perma); ?>'">
                <td style="padding:12px 14px;">
                  <a href="<?php echo esc_url($perma); ?>" style="font-family:'Oswald',sans-serif;font-size:15px;color:#2D4B65;text-decoration:none;font-weight:500;">
                    <?php echo esc_html($name); ?>
                  </a>
                  <?php if (!empty($s['season_abbr']) && $s['season_abbr'] !== $name) : ?>
                    <span style="display:block;font-size:11px;color:#9ca3af;letter-spacing:.05em;margin-top:1px;">
                      <?php echo esc_html($s['season_abbr']); ?>
                    </span>
                  <?php endif; ?>
                </td>
                <td style="padding:12px 14px;color:#4b5563;white-space:nowrap;"><?php echo esc_html($start); ?></td>
                <td style="padding:12px 14px;color:#4b5563;white-space:nowrap;"><?php echo esc_html($end); ?></td>
                <td style="padding:12px 14px;">
                  <?php if (!empty($s['winner_name'])) : ?>
                    <a href="<?php echo esc_url(home_url('/bigbrother-players/' . $s['winner_slug'] . '/')); ?>" style="color:#2D4B65;text-decoration:none;" onclick="event.stopPropagation()">
                      <?php echo esc_html($s['winner_name']); ?>
                    </a>
                  <?php else : ?>
                    <span style="color:#d1d5db;">—</span>
                  <?php endif; ?>
                </td>
                <td style="padding:12px 14px;">
                  <?php if (!empty($s['afp_name'])) : ?>
                    <a href="<?php echo esc_url(home_url('/bigbrother-players/' . $s['afp_slug'] . '/')); ?>" style="color:#2D4B65;text-decoration:none;" onclick="event.stopPropagation()">
                      <?php echo esc_html($s['afp_name']); ?>
                    </a>
                  <?php else : ?>
                    <span style="color:#d1d5db;">—</span>
                  <?php endif; ?>
                </td>
                <td style="padding:12px 14px;">
                  <?php if (!empty($s['runner_up_name'])) : ?>
                    <a href="<?php echo esc_url(home_url('/bigbrother-players/' . $s['runner_up_slug'] . '/')); ?>" style="color:#2D4B65;text-decoration:none;" onclick="event.stopPropagation()">
                      <?php echo esc_html($s['runner_up_name']); ?>
                    </a>
                  <?php else : ?>
                    <span style="color:#d1d5db;">—</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

  </div>
</main>

<style>
  .sa-row:hover { background: #fafaf9; }
  .sa-row:hover a { text-decoration: underline; }
</style>

<?php get_footer(); ?>
