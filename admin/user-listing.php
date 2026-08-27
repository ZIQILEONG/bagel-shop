<?php
include '../_base.php';
require '../lib/SimplePager.php';
auth('Admin');

if (is_post() && req('btn') == 'delete_selected') {
    $ids = post('ids', []);
    $before = count($ids);
    $ids = array_diff($ids, [(string) $_user->id]); // never delete yourself
    if (count($ids) > 0) {
        $in  = implode(',', array_fill(0, count($ids), '?'));
        $stm = $_db->prepare("UPDATE user SET is_deleted = 1 WHERE id IN ($in)");
        $stm->execute(array_values($ids));
        temp('info', count($ids) . ' member(s) deactivated.');
    }
    
    else if ($before > 0) {
        temp('info', 'No members deactivated — you cannot deactivate your own account.');
    }
    redirect('user-listing.php');
}

$search = get('search', '');
$sort   = get('sort', 'id');
$dir    = get('dir', 'asc') == 'desc' ? 'desc' : 'asc';
$page   = get('page', '1');

$sorts = ['id', 'name', 'email', 'role'];
if (!in_array($sort, $sorts)) {
    $sort = 'id';
}

$where  = 'WHERE is_deleted = 0';
$params = [];
if ($search != '') {
    $where .= ' AND (name LIKE ? OR email LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query = "SELECT * FROM user $where ORDER BY $sort $dir";

$query = "SELECT * FROM user $where ORDER BY $sort $dir";
$pager = new SimplePager($query, $params, '10', $page);
$arr   = $pager->result;
if (get('ajax') == '1') {
    include 'user-listing-results.php';
    exit();
}
$_title = 'Member | Listing (Admin)';
include '../_head.php';
?>
<form method="get" class="form" id="searchForm">
    <?= html_search('search', 'placeholder="Search by name or email" autocomplete="off"') ?>
    <?= html_hidden('sort') ?>
    <?= html_hidden('dir') ?>
    <button type="submit">Search</button>
</form>
<div id="resultsWrap">
<?php include 'user-listing-results.php'; ?>
</div>
<script>
$(function () {
    let timer = null;
    function loadResults(page) {
        $.get('user-listing.php', {
            ajax: 1,
            search: $('#search').val(),
            sort: $('#sort').val(),
            dir: $('#dir').val(),
            page: page || 1
        }, html => $('#resultsWrap').html(html));
    }
    $('#search').on('input', function () {
        clearTimeout(timer);
        timer = setTimeout(() => loadResults(1), 350); // debounce
    });
    $('#resultsWrap').on('click', '.pager a', function (e) {
        e.preventDefault();
        loadResults(new URL(this.href).searchParams.get('page'));
    });
    $('#searchForm').on('submit', e => { e.preventDefault(); loadResults(1); });
});
</script>

<?php
include '../_foot.php';