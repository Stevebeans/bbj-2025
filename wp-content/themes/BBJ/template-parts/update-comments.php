<?php
$post_id = get_query_var('post_id', 1);

$roles_to_fetch = [
    'admin' => ['administrator', 'updater', 'second_in_command'],
    'supporter' => ['supporter', 'comment_mod']
];

$comments_data = [];

foreach ($roles_to_fetch as $key => $roles) {
    $users = [];
    foreach ($roles as $role) {
        $users = array_merge($users, get_users(['role' => $role]));
    }

    $user_ids = array_map(function ($user) {
        return $user->ID;
    }, $users);

    if ($key === 'admin') {
        $comments_data[$key] = get_comments([
            'author__in' => $user_ids,
            'post_id' => $post_id,
            'number' => 3,
            'orderby' => 'comment_date',
            'order' => 'DESC'
        ]);
    } else {
        $comments_data[$key] = get_comments([
            'author__in' => $user_ids,
            'post_id' => $post_id,
            'number' => 1,
            'orderby' => 'comment_date',
            'order' => 'DESC'
        ]);
    }
}

if (!empty($comments_data['admin'])):
?>
    <div class="border-l border-r border-b border-yellow-400 bg-yellow-50 w-[95%] mx-auto flex flex-col p-2">
        <div class="font-ibm text-sm"><i class="fa-solid fa-crown"></i> Admin Comments</div>
        <?php foreach ($comments_data['admin'] as $comment): ?>
            <div class="admin-comment flex border-b border-yellow-100 mt-1">
                <div><img src="<?= esc_url(get_avatar_url($comment->user_id)) ?>" class="rounded-full w-4 h-4 mr-2" alt=""></div>
                <div class="text-xs font-ibm mr-2"><?= esc_html(get_the_author_meta('display_name', $comment->user_id)) ?></div>
                <div class="text-xs"><?= esc_html($comment->comment_content) ?></div>
            </div>
        <?php endforeach; ?>
    </div>
<?php
endif;

if (!empty($comments_data['supporter'])):
    $comment = array_shift($comments_data['supporter']);
?>
    <div class="border-l border-r border-b border-gray-400 bg-sky-50 rounded-b-md w-[95%] mx-auto flex flex-col p-2">
        <div class="font-ibm text-sm"><i class="fa-solid fa-award"></i> Recent Supporter Comment <a href="/become-supporter/" class="text-xs underline visited:underline">(Become A Supporter)</a></div>
        <div class="flex mt-1">
            <div><img src="<?= esc_url(get_avatar_url($comment->user_id)) ?>" class="rounded-full w-4 h-4 mr-2" alt=""></div>
            <div class="text-xs font-ibm mr-2"><?= esc_html(get_the_author_meta('display_name', $comment->user_id)) ?></div>
            <div class="text-xs"><?= esc_html($comment->comment_content) ?></div>
        </div>
    </div>
<?php
endif;
?>
