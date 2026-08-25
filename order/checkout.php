<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config.php';
include '../_base.php';

// ----------------------------------------------------------------------------

auth('Member');

$cart = $_SESSION['checkout_cart'] ?? [];

if (empty($cart)) {
    redirect('cart.php');
}

// total calculation
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

// voucher
$voucher = $_SESSION['voucher'] ?? null;
$discount = 0;

if ($voucher) {
    $discount = round($total * $voucher['percent'] / 100, 2);
    $total -= $discount;
}

\Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

$session = \Stripe\Checkout\Session::create([
    'payment_method_types' => ['card', 'fpx'],
    'line_items' => [[
        'price_data' => [
            'currency' => 'myr',
            'product_data' => ['name' => 'Yami Bagel Shop Order'],
            'unit_amount' => round($total * 100),
        ],
        'quantity' => 1,
    ]],
    'mode' => 'payment',
    'success_url' => 'http://localhost:8000/order/payment-success.php?session_id={CHECKOUT_SESSION_ID}',
    'cancel_url' => 'http://localhost:8000/order/payment-cancel.php',
]);

redirect($session->url);