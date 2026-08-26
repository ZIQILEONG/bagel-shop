<?php
include '../_base.php';

// ----------------------------------------------------------------------------

auth('Member');

$id = req('id');

$stm = $_db->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ? AND status IN ('Pending', 'Awaiting Payment')");
$stm->execute([$id, $_user->id]);
$o = $stm->fetch();

if (!$o) {
    redirect('history.php');
}

// If this order was still awaiting payment, refund any points that were reserved for it
if ($o->status == 'Awaiting Payment' && $o->points_used > 0) {
    $stm = $_db->prepare("UPDATE user SET points = points + ? WHERE id = ?");
    $stm->execute([$o->points_used, $_user->id]);

    $_user->points += $o->points_used;
    $_SESSION['user'] = $_user;
}

$stm = $_db->prepare("UPDATE orders SET status = 'Cancelled' WHERE id = ?");
$stm->execute([$o->id]);

temp('info', 'Order cancelled successfully.');
redirect('detail.php?id=' . $id);