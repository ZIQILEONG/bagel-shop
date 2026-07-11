<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config.php';
include '../_base.php';

// ----------------------------------------------------------------------------

auth('Member');

\Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

$session_id = req('session_id');
$session = \Stripe\Checkout\Session::retrieve($session_id);

if ($session->payment_status !== 'paid') {
    redirect('payment-cancel.php');
}

// Ask Stripe for the payment method, paid by card or FPX
$intent = \Stripe\PaymentIntent::retrieve($session->payment_intent);
$method = strtoupper($intent->payment_method_types[0]);

$cart = get_cart();

if (empty($cart)) {
    redirect('history.php');
}

// (A) Begin transaction
$_db->beginTransaction();

// (B) Insert order
$stm = $_db->prepare("INSERT INTO orders (datetime, count, total, status, user_id) VALUES (NOW(), 0, 0, 'Pending', ?)");
$stm->execute([$_user->id]);
$order_id = $_db->lastInsertId();

// (C) Insert items
$count = 0;
$total = 0;

foreach ($cart as $product_id => $unit) {
    $stm = $_db->prepare("SELECT * FROM product WHERE id = ?");
    $stm->execute([$product_id]);
    $product = $stm->fetch();

    $subtotal = $product->price * $unit;

    $stm = $_db->prepare("INSERT INTO order_item (order_id, product_id, price, unit, subtotal) VALUES (?, ?, ?, ?, ?)");
    $stm->execute([$order_id, $product_id, $product->price, $unit, $subtotal]);

    $count += $unit;
    $total += $subtotal;
}

// (D) Update order totals
$stm = $_db->prepare("UPDATE orders SET count = ?, total = ? WHERE id = ?");
$stm->execute([$count, $total, $order_id]);

// (E) Insert payment record
$stm = $_db->prepare("INSERT INTO payment (order_id, method, amount, status, transaction_id, datetime) VALUES (?, ?, ?, 'Paid', ?, NOW())");
$stm->execute([$order_id, $method, $total, $session->payment_intent]);

// (F) Commit
$_db->commit();

// ----------------------------------------------------------------------------

set_cart();

temp('info', 'Payment successful! Your order has been placed.');
redirect("detail.php?id=$order_id");