<?php include '../_base.php';
auth('Member');
$id = req('id');
//cancel all defaults
$stm = $_db->prepare("UPDATE shipping_address SET is_default=0 WHERE user_id=?");
$stm->execute([$_user->id]);
//set the current one as default
$stm = $_db->prepare("UPDATE shipping_address SET is_default=1 WHERE id=? AND user_id=?");
$stm->execute([$id,$_user->id]);
redirect('address-list.php');
