<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config.php';
include '../_base.php';

// ----------------------------------------------------------------------------

auth('Member');

$cart = $_SESSION['checkout_cart'] ?? get_cart();

if (empty($cart)) {
    redirect('cart.php');
}

// Calculate total
$count = 0;
$total = 0;

foreach ($cart as $product_id => $unit) {
    $stm = $_db->prepare("SELECT * FROM product WHERE id = ?");
    $stm->execute([$product_id]);
    $product = $stm->fetch();

    $subtotal = $product->price * $unit;

    $count += $unit;
    $total += $subtotal;
}

// Apply voucher
$voucher = $_SESSION['voucher'] ?? null;
$discount = 0;
$voucher_code = null;

if ($voucher) {
    $discount = round($total * $voucher['percent'] / 100, 2);
    $voucher_code = $voucher['code'];
    $total -= $discount;
}

// Create order NOW, status 'Awaiting Payment'
$_db->beginTransaction();

$stm = $_db->prepare("INSERT INTO orders (datetime, count, total, discount, voucher_code, status, user_id) VALUES (NOW(), ?, ?, ?, ?, 'Awaiting Payment', ?)");
$stm->execute([$count, $total, $discount, $voucher_code, $_user->id]);
$order_id = $_db->lastInsertId();

foreach ($cart as $product_id => $unit) {
    $stm = $_db->prepare("SELECT * FROM product WHERE id = ?");
    $stm->execute([$product_id]);
    $product = $stm->fetch();

    $subtotal = $product->price * $unit;

    $stm = $_db->prepare("INSERT INTO order_item (order_id, product_id, price, unit, subtotal) VALUES (?, ?, ?, ?, ?)");
    $stm->execute([$order_id, $product_id, $product->price, $unit, $subtotal]);
}

$_db->commit();

// Create Stripe session, tagged with this order_id
\Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

$session = \Stripe\Checkout\Session::create([
    'payment_method_types' => ['card', 'fpx'],
    'line_items' => [[
        'price_data' => [
            'currency' => 'myr',
            'product_data' => ['name' => "Yami Bagel Shop Order #$order_id"],
            'unit_amount' => round($total * 100),
        ],
        'quantity' => 1,
    ]],
    'mode' => 'payment',
    'metadata' => ['order_id' => $order_id],
    'success_url' => 'http://localhost:8000/order/payment-success.php?session_id={CHECKOUT_SESSION_ID}',
    'cancel_url' => 'http://localhost:8000/order/payment-cancel.php?order_id=' . $order_id,
]);

redirect($session->url);