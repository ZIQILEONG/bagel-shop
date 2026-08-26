<?php
include '../_base.php';
auth('Member');
$userId = $_user->id;
$stm = $_db->prepare("SELECT * FROM shipping_address WHERE user_id=? ORDER BY is_default DESC, id DESC");
$stm->execute([$userId]);
$addresses = $stm->fetchAll();
$_title = "My Shipping Addresses";
include '../_head.php';
?>
<h2>My Shipping Address</h2>

<!-- Add New Address box, same border style, compact size, no underline -->
<div style="border:1px solid #aaa;padding:10px 14px;margin:8px 0;max-width:600px;">
    <a href="address-create.php" style="text-decoration:none;">[+] Add New Address</a>
</div>

<hr>
<?php if(!$addresses): ?>
    <p>You do not have any saved shipping address.</p>
<?php endif; ?>
<?php foreach($addresses as $addr): ?>
<div style="border:1px solid #aaa;padding:12px;margin:8px 0;max-width:600px;">
    <?php if($addr->is_default): ?><span style="color:green;">【DEFAULT】</span><?php endif ?>
    <div>Name: <?= $addr->recipient_name ?></div>
    <div>Phone: <?= $addr->phone ?></div>
    <div><?= $addr->address_line1 ?> <?= $addr->address_line2 ?></div>
    <div><?= $addr->city ?>, <?= $addr->state ?> <?= $addr->postcode ?>, <?= $addr->country ?></div>
    <div style="margin-top:8px;">
        <a href="address-edit.php?id=<?= $addr->id ?>">Edit</a>
        <a href="address-delete.php?id=<?= $addr->id ?>" onclick="return confirm('Delete this address?')">Delete</a>
        <?php if(!$addr->is_default): ?>
            <a href="address-set-default.php?id=<?= $addr->id ?>">Set As Default</a>
        <?php endif ?>
    </div>
</div>
<?php endforeach; ?>
<?php include '../_foot.php'; ?>
