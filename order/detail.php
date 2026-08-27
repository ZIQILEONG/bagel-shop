<?php
include '../_base.php';

// ----------------------------------------------------------------------------

// Authorization (member)
auth('Member');

// Return order (based on id) belong to the user
$id = req('id');

$stm = $_db->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
$stm->execute([$id, $_user->id]);
$o = $stm->fetch();

if (!$o) {
    redirect('history.php');
}

// Return items (and products) belong to the order
$stm = $_db->prepare("SELECT i.*, p.name, p.photo FROM order_item i JOIN product p ON i.product_id = p.id WHERE i.order_id = ?");
$stm->execute([$o->id]);
$arr = $stm-> fetchAll();

// ----------------------------------------------------------------------------

$_title = 'Order | Detail';
include '../_head.php';
?>

<style>
    .popup {
        width: 100px;
        height: 100px;
    }
</style>

<form class="form">
    <label>Order Id</label>
    <b><?= $o->id ?></b>
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

    <?php if ($o->voucher_code): ?>
    <label>Voucher Used</label>
    <div><?= $o->voucher_code ?> (- RM <?= number_format($o->discount, 2) ?>)</div>
    <br>
    <?php endif ?>

    <label>Points Earned</label>
    <div>+<?= $o->points_earned ?> points</div>
    <br>

    <label>Delivery Method</label>
    <div><?= $o->delivery_method ?><?= $o->delivery_fee > 0 ? ' (+RM ' . number_format($o->delivery_fee, 2) . ')' : '' ?></div>
    <br>

    <?php if ($o->delivery_method == 'Delivery' && $o->address_id): ?>
    <?php
    $stm = $_db->prepare("SELECT * FROM shipping_address WHERE id = ?");
    $stm->execute([$o->address_id]);
    $addr = $stm->fetch();
    ?>
    <?php if ($addr): ?>
    <label>Delivery Address</label>
    <div>
        <?= $addr->recipient_name ?> (<?= $addr->phone ?>)<br>
        <?= $addr->address_line1 ?> <?= $addr->address_line2 ?><br>
        <?= $addr->city ?>, <?= $addr->state ?> <?= $addr->postcode ?>, <?= $addr->country ?>
    </div>
    <br>
    <?php endif ?>
    <?php endif ?>
</form>

<p><?= count($arr) ?> item(s)</p>

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
        <td class="right">
            <?= $i->subtotal ?>
            <img src="/products/<?= $i->photo ?>" class="popup">
        </td>
    </tr>
    <?php endforeach ?>

    <tr>
        <th colspan="3"></th>
        <th class="right"><?= $o->count ?></th>
        <th class="right"><?= $o->total ?></th>
    </tr>
</table>

<p>
    <button data-get="history.php">History</button>
</p>

<?php if ($o->status == 'Awaiting Payment'): ?>
<p>
    <button data-get="pay.php?id=<?= $o->id ?>">Pay Now</button>
    <button data-post="cancel.php?id=<?= $o->id ?>" data-confirm>Cancel Order</button>
</p>
<?php elseif ($o->status == 'Pending'): ?>
<p>
    <button data-post="cancel.php?id=<?= $o->id ?>" data-confirm>Cancel Order</button>
</p>
<?php endif ?>

<?php
include '../_foot.php';