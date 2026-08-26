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

// Calculate subtotal
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

// Apply reward points (1 point = RM0.01), capped so it can't take total below 0
$use_points = $_SESSION['use_points'] ?? false;
$points_used = 0;

if ($use_points && $_user->points > 0) {
    $points_value_available = $_user->points / 100;
    $points_value = min($points_value_available, $total);
    $points_value = round($points_value, 2);
    $points_used = (int) round($points_value * 100);
    $total -= $points_value;
}

// (A) Create order NOW, status 'Awaiting Payment'
$_db->beginTransaction();

$stm = $_db->prepare("INSERT INTO orders (datetime, count, total, discount, voucher_code, points_earned, points_used, status, user_id) VALUES (NOW(), ?, ?, ?, ?, 0, ?, 'Awaiting Payment', ?)");
$stm->execute([$count, $total, $discount, $voucher_code, $points_used, $_user->id]);
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

// (B) Create Stripe session, tagged with this order_id
\Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

// Stripe requires amount > 0 - if points/voucher fully cover the cost, charge a minimum RM1 placeholder is not ideal,
// so we guard: if total rounds to 0, skip Stripe entirely and mark as paid directly.
if ($total <= 0) {
    $stm = $_db->prepare("UPDATE orders SET status = 'Pending' WHERE id = ?");
    $stm->execute([$order_id]);
    redirect("payment-success.php?free_order=$order_id");
}

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

// ----------------------------------------------------------------------------