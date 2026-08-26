<?php include '../_base.php';
auth('Member');
$error = '';
if(is_post()){
    $recipient_name = req('recipient_name');
    $phone = req('phone');
    $address_line1 = req('address_line1');
    $address_line2 = req('address_line2');
    $city = req('city');
    $state = req('state');
    $postcode = req('postcode');
    $country = req('country');

    $stm = $_db->prepare("INSERT INTO shipping_address(user_id,recipient_name,phone,address_line1,address_line2,city,state,postcode,country) VALUES (?,?,?,?,?,?,?,?,?)");
    $stm->execute([$_user->id,$recipient_name,$phone,$address_line1,$address_line2,$city,$state,$postcode,$country]);
    redirect('address-list.php');
}
$_title = "Add New Address";
include '../_head.php';
?>
<h2>Add New Shipping Address</h2>
<form method="post">
    <div>
        <label>Recipient Name</label><br>
        <input type="text" name="recipient_name" required style="width:100%;padding:6px;">
    </div>
    <div style="margin-top:8px;">
        <label>Phone</label><br>
        <input type="text" name="phone" required style="width:100%;padding:6px;">
    </div>
    <div style="margin-top:8px;">
        <label>Address Line 1</label><br>
        <input type="text" name="address_line1" required style="width:100%;padding:6px;">
    </div>
    <div style="margin-top:8px;">
        <label>Address Line 2</label><br>
        <input type="text" name="address_line2" style="width:100%;padding:6px;">
    </div>
    <div style="margin-top:8px;">
        <label>City</label><br>
        <input type="text" name="city" required style="width:100%;padding:6px;">
    </div>
    <div style="margin-top:8px;">
        <label>State</label><br>
        <input type="text" name="state" required style="width:100%;padding:6px;">
    </div>
    <div style="margin-top:8px;">
        <label>Postcode</label><br>
        <input type="text" name="postcode" required style="width:100%;padding:6px;">
    </div>
    <div style="margin-top:8px;">
        <label>Country</label><br>
        <select name="country" style="width:100%;padding:6px;">
            <option value="Malaysia" selected>Malaysia</option>
            <option value="Singapore">Singapore</option>
            <option value="Thailand">Thailand</option>
            <option value="Indonesia">Indonesia</option>
            <option value="Brunei">Brunei</option>
            <option value="Philippines">Philippines</option>
            <option value="Vietnam">Vietnam</option>
        </select>
    </div>
    <div style="margin-top:12px;">
        <button type="submit">Save Address</button>
        <a href="address-list.php">Cancel</a>
    </div>
</form>
<?php include '../_foot.php'; ?>
