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
    // TODO

    // (D) Update order (count and total)
    // TODO

    // (E) Commit transcation
    $_db->commit();  

    // ------------------------------------------

    // (3) Clear shopping cart
    // TODO

    // (4) Redirect to detail.php?id=XXX
    // TODO
    temp('info', 'TODO');
}

redirect('cart.php');

// ----------------------------------------------------------------------------
