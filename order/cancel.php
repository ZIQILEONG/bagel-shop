<?php
include '../_base.php';

// ----------------------------------------------------------------------------

// Authorization (member)
auth('Member');

// Get order id, verify it belongs to this user AND is still Pending
$id = req('id');

$stm = $_db->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ? AND status = 'Pending'");
$stm->execute([$id, $_user->id]);
$o = $stm->fetch();

if (!$o) {
    redirect('history.php');
}

// Update status to 'Cancelled'
$stm = $_db->prepare("UPDATE orders SET status = 'Cancelled' WHERE id = ?");
$stm->execute([$o->id]);

temp('info', 'Order cancelled successfully.');
redirect('detail.php?id=' . $id);
