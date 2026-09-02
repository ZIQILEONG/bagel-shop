<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config.php';
include '../_base.php';

// ----------------------------------------------------------------------------

auth('Member');

$id = req('id');

$stm = $_db->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ? AND status = 'Awaiting Payment'");
$stm->execute([$id, $_user->id]);
$o = $stm->fetch();

if (!$o) {
    redirect('history.php');
}

\Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

$session = \Stripe\Checkout\Session::create([
    'payment_method_types' => ['card', 'fpx'],
    'line_items' => [[
        'price_data' => [
            'currency' => 'myr',
            'product_data' => ['name' => "Pululu Bagel Shop Order #{$o->id}"],
            'unit_amount' => round($o->total * 100),
        ],
        'quantity' => 1,
    ]],
    'mode' => 'payment',
    'metadata' => ['order_id' => $o->id],
    'success_url' => 'http://localhost:8000/order/payment-success.php?session_id={CHECKOUT_SESSION_ID}',
    'cancel_url' => 'http://localhost:8000/order/payment-cancel.php?order_id=' . $o->id,
]);

redirect($session->url);