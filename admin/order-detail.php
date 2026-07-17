<?php
include '../_base.php';

// ----------------------------------------------------------------------------

// (1) Authorization (admin)
auth('Admin');

$statuses = ['Pending' => 'Pending', 'Shipped' => 'Shipped', 'Completed' => 'Completed', 'Cancelled' => 'Cancelled'];

// (2) Return order (based on id) - no user restriction, admin can view any order
$id = req('id');

$stm = $_db->prepare("SELECT o.*, u.name, u.email FROM orders o JOIN user u ON o.user_id = u.id WHERE o.id = ?");
$stm->execute([$id]);
$o = $stm->fetch();

if (!$o) {
    redirect('order-list.php');
}

// (3) Return items (and products) belong to the order
$stm = $_db->prepare("SELECT i.*, p.name, p.photo FROM order_item i JOIN product p ON i.product_id = p.id WHERE i.order_id = ?");
$stm->execute([$o->id]);
$arr = $stm->fetchAll();

// (4) Handle status update
if (is_post()) {
    if (is_post()) {
    if ($o->status == 'Cancelled') {
        temp('info', 'This order has been cancelled and cannot be updated.');
        redirect('order-detail.php?id=' . $o->id);
    }

    $status = req('status');

    if ($status == '') {
        $_err['status'] = 'Required';
    }
    else if (!array_key_exists($status, $statuses)) {
        $_err['status'] = 'Invalid value';
    }
}

    if (!$_err) {
        $stm = $_db->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stm->execute([$status, $o->id]);

        temp('info', 'Order status updated.');
        redirect('order-detail.php?id=' . $o->id);
    }
}

// ----------------------------------------------------------------------------

$_title = 'Order | Detail (Admin)';
include '../_head.php';
?>

<form class="form">
    <label>Order Id</label>
    <b><?= $o->id ?></b>
    <br>

    <label>Member</label>
    <div><?= $o->name ?> (<?= $o->email ?>)</div>
    <br>

    <label>Datetime</label>
    <div><?= $o->datetime ?></div>
    <br>
    
    <label>Count</label>
    <div><?= $o->count ?></div>
    <br>

    <label>Total</label>
    <div>RM <?= $o->total ?></div>
    <br>
</form>

<p><?= count($arr) ?> item(s)</p>

<!-- Status Dropdown -->
<?php $status = $o->status; ?>

<?php if ($o->status != 'Cancelled'): ?>
<form method="post" class="form">
    <label for="status">Update Status</label>
    <?= html_select('status', $statuses, null) ?>
    <?= err('status') ?>

    <button>Update</button>
</form>
<?php else: ?>
<p><b>This order has been cancelled and can no longer be updated.</b></p>
<?php endif ?>

<!-- Item Table -->
<table class="table">
    <tr>
        <th>Product Id</th>
        <th>Product Name</th>
        <th>Price (RM)</th>
        <th>Unit</th>
        <th>Subtotal (RM)</th>
    </tr>

    <?php foreach ($arr as $i): ?>
    <tr>
        <td><?= $i->product_id ?></td>
        <td><?= $i->name ?></td>
        <td class="right"><?= $i->price ?></td>
        <td class="right"><?= $i->unit ?></td>
        <td class="right"><?= $i->subtotal ?></td>
    </tr>
    <?php endforeach ?>
</table>

<p>
    <button data-get="order-list.php">Back to Listing</button>
</p>

<?php
include '../_foot.php';