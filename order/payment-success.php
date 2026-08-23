<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config.php';
include '../_base.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ----------------------------------------------------------------------------

auth('Member');

\Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

$session_id = req('session_id');
$session = \Stripe\Checkout\Session::retrieve($session_id);

if ($session->payment_status !== 'paid') {
    redirect('payment-cancel.php');
}

$intent = \Stripe\PaymentIntent::retrieve($session->payment_intent);
$method = strtoupper($intent->payment_method_types[0]);

$cart = get_cart();

if (empty($cart)) {
    redirect('history.php');
}

// Begin transaction
$_db->beginTransaction();

// Insert order
$stm = $_db->prepare("INSERT INTO orders (datetime, count, total, status, user_id) VALUES (NOW(), 0, 0, 'Pending', ?)");
$stm->execute([$_user->id]);
$order_id = $_db->lastInsertId();

// Insert items
$count = 0;
$total = 0;

foreach ($cart as $product_id => $unit) {
    $stm = $_db->prepare("SELECT * FROM product WHERE id = ?");
    $stm->execute([$product_id]);
    $product = $stm->fetch();

    $subtotal = $product->price * $unit;

    $stm = $_db->prepare("INSERT INTO order_item (order_id, product_id, price, unit, subtotal) VALUES (?, ?, ?, ?, ?)");
    $stm->execute([$order_id, $product_id, $product->price, $unit, $subtotal]);

    // reduce stock by the quantity purchased
    $stm = $_db->prepare("UPDATE product SET stock = stock - ? WHERE id = ?");
    $stm->execute([$unit, $product_id]);

    $count += $unit;
    $total += $subtotal;
}

// discount caculation (recalculate independently for security)
$voucher = $_SESSION['voucher'] ?? null;
$discount = 0;
$voucher_code = null;

if ($voucher) {
    $discount = round($total * $voucher['percent'] / 100, 2);
    $voucher_code = $voucher['code'];
    $total -= $discount;
}

// update orders total
$stm = $_db->prepare("UPDATE orders SET count = ?, total = ?, discount = ?, voucher_code = ? WHERE id = ?");
$stm->execute([$count, $total, $discount, $voucher_code, $order_id]);

// Insert payment record
$stm = $_db->prepare("INSERT INTO payment (order_id, method, amount, status, transaction_id, datetime) VALUES (?, ?, ?, 'Paid', ?, NOW())");
$stm->execute([$order_id, $method, $total, $session->payment_intent]);

// Transaction commit
$_db->commit();

// send e-recipt
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
    $body .= "Total: RM " . number_format($total, 2) . "\n";
    $body .= "Payment Method: $method\n\n";
    $body .= "Items:\n";

    foreach ($cart as $product_id => $unit) {
        $stm = $_db->prepare("SELECT name, price FROM product WHERE id = ?");
        $stm->execute([$product_id]);
        $item = $stm->fetch();
        $body .= "- {$item->name} x$unit = RM " . number_format($item->price * $unit, 2) . "\n";
    }

    $mail->Body = $body;
    $mail->send();
}
catch (Exception $e) {
    // email failed, log it, but no block order form completing
    error_log("Receipt email failed: " . $mail->ErrorInfo);
}

// ----------------------------------------------------------------------------

set_cart();
unset($_SESSION['voucher']);

temp('info', 'Payment successful! Your order has been placed.');
redirect("detail.php?id=$order_id");