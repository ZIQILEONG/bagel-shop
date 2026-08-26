<?php include '../_base.php';
auth('Member');
$id = req('id');
//先全部取消默认
$stm = $_db->prepare("UPDATE shipping_address SET is_default=0 WHERE user_id=?");
$stm->execute([$_user->id]);
//设置当前这条为默认
$stm = $_db->prepare("UPDATE shipping_address SET is_default=1 WHERE id=? AND user_id=?");
$stm->execute([$id,$_user->id]);
redirect('address-list.php');
