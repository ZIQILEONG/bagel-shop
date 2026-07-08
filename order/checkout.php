<?php
include '../_base.php';

// ----------------------------------------------------------------------------

// (1) Authorization (member)
auth('Member');

if (is_post()) {
    // (2) Get shopping cart (reject if empty)
    $cart = get_cart();

    if (empty($cart)) {
        redirect('cart.php');
    }

    // ------------------------------------------
    // DB transaction (insert order and items)
    // ------------------------------------------

    // (A) Begin transaction
    $_db->beginTransaction();

    // (B) Insert order, keep order id
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

        $count += $unit;        // $count = $count + $unit
        $total += $subtotal;    // $total = $total + $subtotal
    }

    // (D) Update order (count and total)
    $stm = $_db->prepare("UPDATE orders SET count = ?, total = ? WHERE id = ?");
    $stm->execute([$count, $total, $order_id]);

    // (E) Commit transcation
    $_db->commit();  

    // ------------------------------------------

    // (3) Clear shopping cart
    set_cart();

    // (4) Redirect to detail.php?id=XXX
    redirect("detail.php?id=$order_id");
    temp('info', 'TODO');
}

redirect('cart.php');

// ----------------------------------------------------------------------------
