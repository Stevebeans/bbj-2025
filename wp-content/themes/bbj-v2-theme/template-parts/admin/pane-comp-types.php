<?php
/**
 * Admin pane: Comp Types CRUD.
 *
 * Lists all comp types from wp_bbj_comp_types. Add / edit / archive forms
 * post to admin-post.php handlers wired in Task 5.
 */

if (!defined('ABSPATH')) {
    exit;
}

bbj_v2_require_admin();

$types = bbj_v2_comp_types_all();
?>

<div class="space-y-6">
  <header class="flex flex-wrap items-end justify-between gap-4 border-b-2 border-stone-900 pb-4">
    <div>
      <p class="font-mono text-[11px] uppercase tracking-[0.12em] text-stone-500">Game data</p>
      <h1 class="font-osw text-3xl text-primary-500">Comp Types</h1>
      <p class="mt-1 text-sm text-stone-600">Categories used when logging weekly comp wins. Add seasonal regulars as they appear.</p>
    </div>
  </header>

  <section class="rounded-md bg-white border border-stone-200 overflow-hidden">
    <table class="w-full text-sm">
      <thead class="bg-stone-50 border-b border-stone-200">
        <tr class="font-mono text-[10px] uppercase tracking-[0.08em] text-stone-600">
          <th class="text-left font-semibold px-5 py-2 w-[180px]">Name</th>
          <th class="text-left font-semibold px-5 py-2 w-[140px]">Slug</th>
          <th class="text-left font-semibold px-5 py-2 w-[80px]">Sort</th>
          <th class="text-left font-semibold px-5 py-2 w-[100px]">Status</th>
          <th class="text-right font-semibold px-5 py-2">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($types)): ?>
          <tr><td colspan="5" class="px-5 py-6 text-center text-stone-500 italic">No comp types yet.</td></tr>
        <?php else: foreach ($types as $t): ?>
          <tr class="border-t border-stone-100">
            <td class="px-5 py-2 text-stone-900"><?php echo esc_html($t['name']); ?></td>
            <td class="px-5 py-2 font-mono text-[12px] text-stone-700"><?php echo esc_html($t['slug']); ?></td>
            <td class="px-5 py-2 text-stone-600"><?php echo (int) $t['sort_order']; ?></td>
            <td class="px-5 py-2">
              <?php if ((int) $t['is_archived'] === 1): ?>
                <span class="inline-block px-2 py-0.5 text-[11px] font-osw uppercase tracking-wider rounded bg-stone-100 text-stone-700">Archived</span>
              <?php else: ?>
                <span class="inline-block px-2 py-0.5 text-[11px] font-osw uppercase tracking-wider rounded bg-green-100 text-green-900">Active</span>
              <?php endif; ?>
            </td>
            <td class="px-5 py-2 text-right text-stone-400 italic text-[12px]">edit (Task 5)</td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </section>
</div>
