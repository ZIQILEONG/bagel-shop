<?php
include '../_base.php';

// ----------------------------------------------------------------------------

auth('Member');

$stm = $_db->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC");
$stm->execute([$_user->id]);
$arr = $stm->fetchAll();

// ----------------------------------------------------------------------------

$_title = 'Order | History';
include '../_head.php';
?>

<?php if ($arr): ?>
<p><?= count($arr) ?> record(s)</p>

<table class="table">
    <tr>
        <th>Id</th>
        <th>Datetime</th>
        <th>Count</th>
        <th>Total (RM)</th>
        <th>Status</th>
        <th></th>
    </tr>

    <?php foreach ($arr as $o): ?>
    <tr>
        <td><?= $o->id ?></td>
        <td><?= $o->datetime ?></td>
        <td class="right"><?= $o->count ?></td>
        <td class="right"><?= $o->total ?></td>
        <td><?= $o->status ?></td>
        <td>
            <button data-get="detail.php?id=<?= $o->id ?>">Detail</button>
        </td>
    </tr>
    <?php endforeach ?>
</table>
<?php else: ?>
<p>📦 No orders found yet. <a href="/product/list.php">Start shopping</a>!</p>
<?php endif ?>

<?php
include '../_foot.php';