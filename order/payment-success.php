<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config.php';
include '../_base.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ----------------------------------------------------------------------------

auth('Member');

$free_order_id = req('free_order');

if ($free_order_id) {
    $order_id = $free_order_id;
    $method = 'FREE (Voucher/Points)';
    $transaction_id = 'N/A';

    $stm = $_db->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
    $stm->execute([$order_id, $_user->id]);
    $o = $stm->fetch();

    if (!$o) {
        redirect('history.php');
    }
}
else {
    \Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

    $session_id = req('session_id');
    $session = \Stripe\Checkout\Session::retrieve($session_id);

    if ($session->payment_status !== 'paid') {
        redirect('payment-cancel.php');
    }

    $order_id = $session->metadata->order_id;

    $stm = $_db->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
    $stm->execute([$order_id, $_user->id]);
    $o = $stm->fetch();

    if (!$o) {
        redirect('history.php');
    }

    $intent = \Stripe\PaymentIntent::retrieve($session->payment_intent);
    $method = strtoupper($intent->payment_method_types[0]);
    $transaction_id = $session->payment_intent;
}

// Guard against double-processing (e.g. page refresh)
if ($o->status !== 'Awaiting Payment') {
    redirect("detail.php?id=$order_id");
}

$_db->beginTransaction();

// Mark order as paid, award points earned on final amount paid
$points_earned = floor($o->total);

$stm = $_db->prepare("UPDATE orders SET status = 'Pending', points_earned = ? WHERE id = ?");
$stm->execute([$points_earned, $order_id]);

$stm = $_db->prepare("UPDATE user SET points = points + ? WHERE id = ?");
$stm->execute([$points_earned, $_user->id]);

// Decrement stock for each item
$stm = $_db->prepare("SELECT * FROM order_item WHERE order_id = ?");
$stm->execute([$order_id]);
$items = $stm->fetchAll();

foreach ($items as $item) {
    $stm = $_db->prepare("UPDATE product SET stock = stock - ? WHERE id = ?");
    $stm->execute([$item->unit, $item->product_id]);
}

// Record payment
$stm = $_db->prepare("INSERT INTO payment (order_id, method, amount, status, transaction_id, datetime) VALUES (?, ?, ?, 'Paid', ?, NOW())");
$stm->execute([$order_id, $method, $o->total, $transaction_id]);

$_db->commit();

$_user->points += $points_earned;
$_SESSION['user'] = $_user;

// Send e-receipt
try {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = MAIL_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = MAIL_USERNAME;
    $mail->Password = MAIL_PASSWORD;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;

    $mail->setFrom(MAIL_USERNAME, MAIL_FROM_NAME);
    $mail->addAddress($_user->email, $_user->name);
    $mail->Subject = "Your Bagel Shop Receipt - Order #$order_id";

    $body = "Hi {$_user->name},\n\nThank You for your order!\n\n";
    $body .= "Order #: $order_id\n";
    $body .= "Total Paid: RM " . number_format($o->total, 2) . "\n";
    $body .= "Payment Method: $method\n\n";
    $body .= "Items:\n";

    foreach ($items as $item) {
        $stm = $_db->prepare("SELECT name FROM product WHERE id = ?");
        $stm->execute([$item->product_id]);
        $p = $stm->fetch();
        $body .= "- {$p->name} x{$item->unit} = RM " . number_format($item->subtotal, 2) . "\n";
    }

    $mail->Body = $body;
    $mail->send();
}
catch (Exception $e) {
    error_log("Receipt email failed: " . $mail->ErrorInfo);
}

// ----------------------------------------------------------------------------

temp('info', 'Payment successful! Your order has been placed.');
redirect("detail.php?id=$order_id");