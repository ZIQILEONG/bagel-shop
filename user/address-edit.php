<?php include '../_base.php';
auth('Member');
$id = req('id');
$stm = $_db->prepare("SELECT * FROM shipping_address WHERE id=? AND user_id=?");
$stm->execute([$id,$_user->id]);
$addr = $stm->fetch();
if(!$addr){
    redirect('address-list.php');
}

if(is_post()){
    $recipient_name = req('recipient_name');
    $phone = req('phone');
    $address_line1 = req('address_line1');
    $address_line2 = req('address_line2');
    $city = req('city');
    $state = req('state');
    $postcode = req('postcode');
    $country = req('country');

    $stm = $_db->prepare("UPDATE shipping_address SET recipient_name=?,phone=?,address_line1=?,address_line2=?,city=?,state=?,postcode=?,country=? WHERE id=? AND user_id=?");
    $stm->execute([$recipient_name,$phone,$address_line1,$address_line2,$city,$state,$postcode,$country,$id,$_user->id]);
    redirect('address-list.php');
}
$_title = "Edit Address";
include '../_head.php';
?>
<h2>Edit Shipping Address</h2>
<form method="post">
    <div>
        <label>Recipient Name</label><br>
        <input type="text" name="recipient_name" value="<?= $addr->recipient_name ?>" required style="width:100%;padding:6px;">
    </div>
    <div style="margin-top:8px;">
        <label>Phone</label><br>
        <input type="text" name="phone" value="<?= $addr->phone ?>" required style="width:100%;padding:6px;">
    </div>
    <div style="margin-top:8px;">
        <label>Address Line 1</label><br>
        <input type="text" name="address_line1" value="<?= $addr->address_line1 ?>" required style="width:100%;padding:6px;">
    </div>
    <div style="margin-top:8px;">
        <label>Address Line 2</label><br>
        <input type="text" name="address_line2" value="<?= $addr->address_line2 ?>" style="width:100%;padding:6px;">
    </div>
    <div style="margin-top:8px;">
        <label>City</label><br>
        <input type="text" name="city" value="<?= $addr->city ?>" required style="width:100%;padding:6px;">
    </div>
    <div style="margin-top:8px;">
        <label>State</label><br>
        <input type="text" name="state" value="<?= $addr->state ?>" required style="width:100%;padding:6px;">
    </div>
    <div style="margin-top:8px;">
        <label>Postcode</label><br>
        <input type="text" name="postcode" value="<?= $addr->postcode ?>" required style="width:100%;padding:6px;">
    </div>
    <div style="margin-top:8px;">
        <label>Country</label><br>
        <select name="country" style="width:100%;padding:6px;">
            <option value="Malaysia" <?= $addr->country=='Malaysia'?'selected':'' ?>>Malaysia</option>
            <option value="Singapore" <?= $addr->country=='Singapore'?'selected':'' ?>>Singapore</option>
            <option value="Thailand" <?= $addr->country=='Thailand'?'selected':'' ?>>Thailand</option>
            <option value="Indonesia" <?= $addr->country=='Indonesia'?'selected':'' ?>>Indonesia</option>
            <option value="Brunei" <?= $addr->country=='Brunei'?'selected':'' ?>>Brunei</option>
            <option value="Philippines" <?= $addr->country=='Philippines'?'selected':'' ?>>Philippines</option>
            <option value="Vietnam" <?= $addr->country=='Vietnam'?'selected':'' ?>>Vietnam</option>
        </select>
    </div>
    <div style="margin-top:12px;">
        <button type="submit">Update</button>
        <a href="address-list.php">Cancel</a>
    </div>
</form>
<?php include '../_foot.php'; ?>
