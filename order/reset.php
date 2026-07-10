<?php
include '../_base.php';

// ----------------------------------------------------------------------------

if (is_post()) {
    // (1) Delete orders (and items). Reset auto increment
    $stm  = $_db->prepare("DELETE FROM order_item WHERE order_id IN (SELECT id FROM orders WHERE user_id = ?)");
    $stm->execute([$_user->id]);

    $stm = $_db->prepare("DELETE FROM orders WHERE user_id = ?");
    $stm->execute([$_user->id]);

    temp('info', 'Your order history has been reset.');
}

redirect('history.php');

// ----------------------------------------------------------------------------
