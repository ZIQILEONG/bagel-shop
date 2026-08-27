<?php
include '../_base.php';
// ----------------------------------------------------------------------------
auth('Member');
// Handle reorder request
if (is_post() && req('btn') == 'reorder') {
    $order_id = req('order_id');
    // Make sure the order belongs to this member
    $stm = $_db->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
    $stm->execute([$order_id, $_user->id]);
    $o = $stm->fetch();
    if (!$o) {
        temp('info', 'Order not found.');
        redirect('history.php');
    }
    // Fetch items belonging to the order (join product to check current stock/existence)
    $stm = $_db->prepare("
        SELECT i.product_id, i.unit, p.name, p.stock
        FROM order_item i
        LEFT JOIN product p ON i.product_id = p.id
        WHERE i.order_id = ?
    ");
    $stm->execute([$o->id]);
    $items = $stm->fetchAll();
    $added = 0;
    $skipped = [];
    foreach ($items as $item) {
        // Product may have been deleted since the original order
        if (!$item->name) {
            $skipped[] = "Product #{$item->product_id} (no longer available)";
            continue;
        }
        // Product may now be out of stock
        if ($item->stock <= 0) {
            $skipped[] = "{$item->name} (out of stock)";
            continue;
        }
        // Cap the reordered quantity to whatever is currently in stock (and the 1-10 cart limit)
        $cart = get_cart();
        $existing_unit = $cart[$item->product_id] ?? 0;
        $unit_to_add = min($item->unit, $item->stock, 10);
        $new_unit = min($existing_unit + $unit_to_add, 10, $item->stock);
        if ($new_unit <= $existing_unit) {
            $skipped[] = "{$item->name} (already at max quantity in cart)";
            continue;
        }
        update_cart($item->product_id, $new_unit);
        $added++;
    }
    if ($added > 0) {
        $msg = "$added item(s) added to your cart from Order #{$o->id}.";
        if ($skipped) {
            $msg .= ' Skipped: ' . implode('; ', $skipped);
        }
        temp('info', $msg);
        redirect('cart.php');
    }
    else {
        temp('info', 'Could not reorder any items - ' . ($skipped ? implode('; ', $skipped) : 'unknown error.'));
        redirect('history.php');
    }
}
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
            <?php if ($o->status != 'Cancelled'): ?>
            <button data-post="history.php?order_id=<?= $o->id ?>&btn=reorder" data-confirm="Add all items from Order #<?= $o->id ?> back into your cart?">Reorder</button>
            <?php endif ?>
        </td>
    </tr>
    <?php endforeach ?>
</table>
<?php else: ?>
<p>📦 No orders found yet. <a href="/product/list.php">Start shopping</a>!</p>
<?php endif ?>
<?php
include '../_foot.php';