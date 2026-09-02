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

$delivery_method = $_SESSION['delivery_method'] ?? 'Pickup';
$address_id      = $_SESSION['address_id'] ?? null;

if (!$delivery_method) {
    redirect('checkout-options.php');
}

// Calculate items & base total
$count = 0;
$total = 0.00;

foreach ($cart as $product_id => $rawUnit) {
    $unit = is_array($rawUnit) ? (int)($rawUnit['qty'] ?? 1) : (int)$rawUnit;

    $stm = $_db->prepare("SELECT * FROM product WHERE id = ?");
    $stm->execute([$product_id]);
    $product = $stm->fetch();

    if ($product) {
        $subtotal = (float)$product->price * $unit;
        $count   += $unit;
        $total   += $subtotal;
    }
}

// Voucher Discount
$voucher      = $_SESSION['voucher'] ?? null;
$discount     = 0.00;
$voucher_code = null;

if ($voucher) {
    $discount     = round($total * (float)($voucher['percent'] ?? 0) / 100, 2);
    $voucher_code = $voucher['code'];
    $total       -= $discount;
}

// Points Discount
$use_points  = $_SESSION['use_points'] ?? false;
$points_used = 0;

if ($use_points && ($_user->points ?? 0) > 0) {
    $points_value_available = $_user->points / 100;
    $points_value           = min($points_value_available, $total);
    $points_value           = round($points_value, 2);
    $points_used            = (int) round($points_value * 100);
    $discount               += $points_value;
    $total                  -= $points_value;
}

// Delivery Fee (Added after voucher/points)
$delivery_fee  = ($delivery_method === 'Delivery') ? 7.00 : 0.00;
$total        += $delivery_fee;

// Create order in Database
try {
    $_db->beginTransaction();

    // check stock before creating order
    $stock_check_stm = $_db->prepare("SELECT stock, name FROM product WHERE id = ? FOR UPDATE");

    foreach ($cart as $product_id => $rawUnit) {
        $unit = is_array($rawUnit) ? (int)($rawUnit['qty'] ?? 1) : (int)$rawUnit;
        $stock_check_stm->execute([$product_id]);
        $prod_stock = $stock_check_stm->fetch();

        // Product no longer exists at all
        if (!$prod_stock) {
            throw new Exception("Sorry, one of the items in your cart is no longer available.");
        }
        if ((int)$prod_stock->stock < 1) {
            throw new Exception("Sorry, '{$prod_stock->name}' is currently out of stock.");
        }
        if ((int)$prod_stock->stock < $unit) {
            throw new Exception("Sorry, '{$prod_stock->name}' only has {$prod_stock->stock} item(s) left in stock.");
        }
    }

    $stm = $_db->prepare("
        INSERT INTO orders
        (datetime, count, total, voucher_code, discount, status, user_id, points_earned, points_used, delivery_method, delivery_fee, address_id)
        VALUES (NOW(), ?, ?, ?, ?, 'Awaiting Payment', ?, 0, ?, ?, ?, ?)
    ");
    $stm->execute([
        $count,
        $total,
        $voucher_code,
        $discount,
        $_user->id,
        $points_used,
        $delivery_method,
        $delivery_fee,
        $address_id ?: null
    ]);

    $order_id = (int)$_db->lastInsertId();

    // Insert line items
    foreach ($cart as $product_id => $rawUnit) {
        $unit = is_array($rawUnit) ? (int)($rawUnit['qty'] ?? 1) : (int)$rawUnit;

        $stm = $_db->prepare("SELECT price FROM product WHERE id = ?");
        $stm->execute([$product_id]);
        $prod = $stm->fetch();

        if ($prod) {
            $itemSubtotal = (float)$prod->price * $unit;
            $itemStm = $_db->prepare("
                INSERT INTO order_item (order_id, product_id, price, unit, subtotal)
                VALUES (?, ?, ?, ?, ?)
            ");
            $itemStm->execute([$order_id, $product_id, $prod->price, $unit, $itemSubtotal]);
        }
    }

    // Deduct user points if used
    if ($points_used > 0) {
        $stm = $_db->prepare("UPDATE user SET points = points - ? WHERE id = ?");
        $stm->execute([$points_used, $_user->id]);
        $_user->points -= $points_used;
        $_SESSION['user'] = $_user;
    }

    $_db->commit();

} catch (Exception $e) {
    if ($_db->inTransaction()) {
        $_db->rollBack();
    }
    temp('info', 'Checkout failed: ' . $e->getMessage());
    redirect('cart.php');
}

// Clean up cart sessions
$full_cart = get_cart();
foreach ($cart as $product_id => $unit) {
    unset($full_cart[$product_id]);
}
set_cart($full_cart);

if (function_exists('save_cart_to_db')) {
    save_cart_to_db($_user->id, $_db);
}

unset($_SESSION['checkout_cart']);
unset($_SESSION['voucher']);
unset($_SESSION['use_points']);
unset($_SESSION['delivery_method']);
unset($_SESSION['address_id']);
unset($_SESSION['delivery_fee']);

// If order is RM 0.00
if ($total <= 0) {
    $stm = $_db->prepare("UPDATE orders SET status = 'Pending' WHERE id = ?");
    $stm->execute([$order_id]);
    redirect("payment-success.php?free_order={$order_id}");
}

// Connect to Stripe with Card + FPX Online Banking
try {
    \Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
    $host     = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
    $baseUrl  = $protocol . $host;

    $session = \Stripe\Checkout\Session::create([
        'payment_method_types' => ['card', 'fpx'],
        'line_items' => [[
            'price_data' => [
                'currency'     => 'myr',
                'product_data' => [
                    'name'        => "Pululu Bagel Order #{$order_id}",
                    'description' => "Method: {$delivery_method}",
                ],
                'unit_amount'  => (int) round($total * 100),
            ],
            'quantity'   => 1,
        ]],
        'mode'        => 'payment',
        'metadata'    => [
            'order_id' => (string)$order_id,
            'user_id'  => (string)$_user->id,
        ],
        'success_url' => "{$baseUrl}/order/payment-success.php?session_id={CHECKOUT_SESSION_ID}",
        'cancel_url'  => "{$baseUrl}/order/payment-cancel.php?order_id={$order_id}",
    ]);

    redirect($session->url);

} catch (\Exception $e) {
    temp('info', 'Stripe checkout error: ' . $e->getMessage());
    redirect('cart.php');
}