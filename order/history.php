<?php
include '../_base.php';

// ----------------------------------------------------------------------------

// (1) Authorization (member)
auth('Member');

// (1b) Handle Reorder: replay this order's items into the current cart
if (is_post() && req('btn') == 'reorder') {
    $order_id = req('order_id');

    // Verify the order belongs to this user
    $stm = $_db->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
    $stm->execute([$order_id, $_user->id]);
    $order = $stm->fetch();

    if ($order) {
        $stm = $_db->prepare("SELECT product_id, unit FROM order_item WHERE order_id = ?");
        $stm->execute([$order->id]);
        $items = $stm->fetchAll();

        foreach ($items as $item) {
            // Cap at 10 since update_cart() only accepts 1-10 (see _base.php)
            update_cart($item->product_id, min($item->unit, 10));
        }

        temp('info', 'Items from order #' . $order->id . ' have been added to your cart.');
    }

    redirect('cart.php');
}

// (2) Return orders belong to the user (descending)
// SELECT ... FROM ... WHERE ... ORDER BY ...
$stm = $_db->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC");
$stm->execute([$_user->id]);
$arr = $stm->fetchAll();

// ----------------------------------------------------------------------------

$_title = 'Order | History';
include '../_head.php';
?>

<!-- (B) EXTRA: CSS -->  
<!-- TODO -->

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
            <button data-post="history.php?btn=reorder&order_id=<?= $o->id ?>">Reorder</button>
            <!-- (A) EXTRA: Product photos -->
            <!-- TODO -->
        </td>
    </tr>
    <?php endforeach ?>
</table>

<?php
include '../_foot.php';