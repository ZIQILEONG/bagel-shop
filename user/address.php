<?php
require '../config.php';
require '../_base.php';
auth();

$uid = $_user->user_id;

if(is_post()){
    $action = $_POST['action']??'';
    if($action === 'add'){
        $stmt = $_db->prepare("INSERT INTO shipping_address(user_id,receiver_name,phone,address_line,city,postcode,state,is_default) VALUES(?,?,?,?,?,?,?,0)");
        $stmt->execute([
            $uid,
            $_POST['rname'],
            $_POST['phone'],
            $_POST['addr'],
            $_POST['city'],
            $_POST['postcode'],
            $_POST['state']
        ]);
    }elseif($action === 'delete'){
        $aid = $_POST['addr_id'];
        $stmt = $_db->prepare("DELETE FROM shipping_address WHERE addr_id=? AND user_id=?");
        $stmt->execute([$aid,$uid]);
    }elseif($action === 'set_default'){
        $_db->prepare("UPDATE shipping_address SET is_default=0 WHERE user_id=?")->execute([$uid]);
        $aid = $_POST['addr_id'];
        $_db->prepare("UPDATE shipping_address SET is_default=1 WHERE addr_id=? AND user_id=?")->execute([$aid,$uid]);
    }
}

$stmt = $_db->prepare("SELECT * FROM shipping_address WHERE user_id=?");
$stmt->execute([$uid]);
$addresses = $stmt->fetchAll();

$_title = "My Shipping Address";
include '../_head.php';
?>

<h2>My Shipping Address</h2>

<h3>Saved Addresses</h3>
<?php foreach($addresses as $a): ?>
<div style="border:1px solid #aaa;padding:10px;margin:6px 0;">
    <p><?= htmlspecialchars($a->receiver_name) ?> | <?= htmlspecialchars($a->phone) ?></p>
    <p><?= htmlspecialchars($a->address_line) ?>, <?= htmlspecialchars($a->city) ?> <?= htmlspecialchars($a->postcode) ?>, <?= htmlspecialchars($a->state) ?></p>
    <?php if($a->is_default): ?><strong>[Default Address]</strong><?php endif; ?>
    <form method="post" style="display:inline">
        <input type="hidden" name="action" value="set_default">
        <input type="hidden" name="addr_id" value="<?= $a->addr_id ?>">
        <button type="submit">Set as Default</button>
    </form>
    <form method="post" style="display:inline" onsubmit="return confirm('Delete this address?')">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="addr_id" value="<?= $a->addr_id ?>">
        <button type="submit">Delete</button>
    </form>
</div>
<?php endforeach ?>

<h3>Add New Address</h3>
<form method="post">
    <input type="hidden" name="action" value="add">
    <div>Receiver Name: <input type="text" name="rname" required></div>
    <div>Phone: <input type="text" name="phone" required></div>
    <div>Address Line: <textarea name="addr" required></textarea></div>
    <div>City: <input type="text" name="city" required></div>
    <div>Postcode: <input type="text" name="postcode" required></div>
    <div>State: <input type="text" name="state" required></div>
    <button type="submit">Save Address</button>
</form>

<?php include '../_foot.php'; ?>
