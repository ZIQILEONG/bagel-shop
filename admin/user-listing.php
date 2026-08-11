<?php
include '../_base.php';
require '../lib/SimplePager.php';
auth('Admin');

if (is_post() && req('btn') == 'delete_selected') {
    $ids = post('ids', []);
    $ids = array_diff($ids, [(string) $_user->id]); // never delete yourself
    if (count($ids) > 0) {
        $in  = implode(',', array_fill(0, count($ids), '?'));
        $stm = $_db->prepare("DELETE FROM user WHERE id IN ($in)");
        $stm->execute(array_values($ids));
        temp('info', count($ids) . ' member(s) deleted.');
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

$where  = '';
$params = [];
if ($search != '') {
    $where = 'WHERE name LIKE ? OR email LIKE ?';
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query = "SELECT * FROM user $where ORDER BY $sort $dir";
$pager = new SimplePager($query, $params, '10', $page);
$arr   = $pager->result;
$_title = 'Member | Listing (Admin)';
include '../_head.php';
?>
<p><?= $pager->item_count ?> record(s)</p>
<p>
    <button data-get="user-detail.php">Create New Member</button>
</p>
<form method="get" class="form">
    <?= html_search('search', 'placeholder="Search by name or email"') ?>
    <?= html_hidden('sort') ?>
    <?= html_hidden('dir') ?>
    <button type="submit">Search</button>
</form>
<form method="post">
<table class="table">
    <tr>
        <th></th>
        <th>Photo</th>
        <?= table_headers(['id' => 'Id', 'name' => 'Name', 'email' => 'Email', 'role' => 'Role'], $sort, $dir, 'search=' . encode($search)) ?>
        <th>Phone No</th>
        <th></th>
    </tr>
    <?php foreach ($arr as $u): ?>
    <tr>
        <td><input type="checkbox" name="ids[]" value="<?= $u->id ?>" <?= $u->id == $_user->id ? 'disabled' : '' ?>></td>
        <td><img src="/photos/<?= $u->photo ?>" width="50" height="50"></td>
        <td><?= $u->id ?></td>
        <td><?= $u->name ?></td>
        <td><?= $u->email ?></td>
        <td><?= $u->role ?></td>
        <td><?= $u->phone_no ?></td>
        <td>
            <button data-get="user-detail.php?id=<?= $u->id ?>">Detail</button>
        </td>
    </tr>
    <?php endforeach ?>
</table>
<button name="btn" value="delete_selected" data-confirm="Delete selected members?">Delete Selected</button>
</form>
<?= $pager->html('search=' . encode($search) . '&sort=' . $sort . '&dir=' . $dir) ?>
<?php
include '../_foot.php';