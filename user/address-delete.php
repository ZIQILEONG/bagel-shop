<?php include '../_base.php';
auth('Member');
$id = req('id');
$stm = $_db->prepare("DELETE FROM shipping_address WHERE id=? AND user_id=?");
$stm->execute([$id,$_user->id]);
redirect('address-list.php');
