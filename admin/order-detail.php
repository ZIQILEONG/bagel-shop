<?php
include '../_base.php';

// ----------------------------------------------------------------------------

// (1) Authorization (admin)
auth('Admin');

$statuses = [
    'Pending'    => 'Pending',
    'Preparing'  => 'Preparing',
    'Ready'      => 'Ready',
    'Completed'  => 'Completed',
    'Cancelled'  => 'Cancelled',
];

// (2) Return order (based on id) - no user restriction, admin can view any order
$id = req('id');

$stm = $_db->prepare("SELECT o.*, u.name, u.email, u.is_deleted FROM orders o JOIN user u ON o.user_id = u.id WHERE o.id = ?");
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
    if ($o->status == 'Cancelled') {
        // only runs when someone actually submits the form (a POST request)
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

    if (!$_err) {
        $oldStatus = $o->status;

        $stm = $_db->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stm->execute([$status, $o->id]);

        // Send notification email to the customer (best-effort, never blocks the update)
        if ($status !== $oldStatus) {
            send_order_status_email($o, $status);
        }

        temp('info', 'Order status updated.');
        redirect('order-detail.php?id=' . $o->id);
    }
}

// ----------------------------------------------------------------------------

/**
 * Email the customer that their order status has changed.
 * Uses the same PHPMailer setup as user/forgot_password.php.
 * Failures are logged only - they must never block the status update.
 */
function send_order_status_email($order, $newStatus) {
    require_once '../PHPMailer-master/src/PHPMailer.php';
    require_once '../PHPMailer-master/src/SMTP.php';
    require_once '../PHPMailer-master/src/Exception.php';
    require_once '../config.php';

    if (!defined('SMTP_USERNAME') || !defined('SMTP_PASSWORD')) {
        return;
    }

    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);

        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('pululubagelshop@gmail.com', 'Pululu Bagel');
        $mail->addAddress($order->email, $order->name);

        $mail->Subject = "Order #{$order->id} Update - {$newStatus}";
        $mail->Body =
            "Dear {$order->name},\n\n" .
            "Your order #{$order->id} status has been updated to: {$newStatus}\n\n" .
            "Order Total: RM " . number_format($order->total, 2) . "\n\n" .
            "Thank you for shopping with Pululu Bagel!\n";

        $mail->send();
    }
    catch (\Exception $e) {
        error_log('Order status email failed: ' . $e->getMessage());
    }
}

$_title = 'Order | Detail (Admin)';
include '../_head.php';
?>

<form class="form">
    <label>Order Id</label>
    <b><?= $o->id ?></b>
    <br>

    <label>Member</label>
    <div><?= $o->name ?><?= $o->is_deleted ? " <span style='color:#b5192b;font-weight:bold;' title='Account disabled'>&#10071;</span>" : '' ?> (<?= $o->email ?>)</div>
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