<?php
include '../_base.php';

// ----------------------------------------------------------------------------

// Authorization (admin)
auth('Admin');

// return all orders, joined with user (name), newest first
$stm = $_db->prepare("SELECT o.*, u.name, u.is_deleted FROM orders o JOIN user u ON o.user_id = u.id ORDER BY o.id DESC");
$stm->execute([]);
$arr = $stm-> fetchAll();

// ----------------------------------------------------------------------------
// ---------------- Pagination ----------------
$sort = get('sort', 'id');
$dir  = get('dir', 'desc') == 'asc' ? 'asc' : 'desc';
$page = get('page', '1');
$sorts = ['id', 'datetime', 'count', 'total', 'status'];
if (!in_array($sort, $sorts)) {
    $sort = 'id';
}
$query  = "SELECT * FROM orders WHERE user_id = ? ORDER BY $sort $dir";
$params = [$_user->id];
$pager  = new SimplePager($query, $params, '10', $page);
$arr    = $pager->result;
// ----------------------------------------------------------------------------

$_title = 'Order | Listing (Admin)';
include '../_head.php';
?>

<p><?= count($arr) ?> record(s)</p>

<table class="table">
    <tr>
        <th>Id</th>
        <th>Datetime</th>
        <th>Member</th>
        <th>Count</th>
        <th>Total (RM)</th>
        <th>Status</th>
        <th></th>
    </tr>

    <?php foreach ($arr as $o): ?>
    <tr>
        <td><?= $o->id ?></td>
        <td><?= $o->datetime ?></td>
        <td><?= $o->name ?><?= $o->is_deleted ? " <span style='color:#b5192b;font-weight:bold;' title='Account disabled'>&#10071;</span>" : '' ?></td>
        <td class="right"><?= $o->count ?></td>
        <td class="right"><?= $o->total ?></td>
        <td><?= $o->status ?></td>
        <td>
            <button data-get="order-detail.php?id=<?= $o->id ?>">Detail</button>
        </td>
    </tr>
    <?php endforeach ?>
</table>

<?php
include '../_foot.php';