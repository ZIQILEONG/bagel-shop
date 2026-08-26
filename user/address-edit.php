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
    <div style="margin‑bottom:12px;">
        <button type="button" id="getLocationBtn">Use My Current Location</button>
    </div>
    <div>
        <label>Recipient Name :</label><br>
        <input type="text" name="recipient_name" value="<?= $addr->recipient_name ?>" required style="width:100%;padding:6px;">
    </div>
    <div style="margin-top:8px;">
        <label>Phone :</label><br>
        <input type="text" name="phone" value="<?= $addr->phone ?>" required style="width:100%;padding:6px;">
    </div>
    <div style="margin-top:8px;">
        <label>Address :</label><br>
        <input type="text" name="address_line1" value="<?= $addr->address_line1 ?>" required style="width:100%;padding:6px;">
    </div>
    <div style="margin-top:8px;">
        <label>City :</label><br>
        <input type="text" name="city" value="<?= $addr->city ?>" required style="width:100%;padding:6px;">
    </div>
    <div style="margin-top:8px;">
        <label>State :</label><br>
        <input type="text" name="state" value="<?= $addr->state ?>" required style="width:100%;padding:6px;">
    </div>
    <div style="margin-top:8px;">
        <label>Postcode :</label><br>
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
<script>
const getLocationBtn = document.getElementById('getLocationBtn');
getLocationBtn.addEventListener('click', async ()=>{
    if (!navigator.geolocation) {
        alert("Your browser does not support geolocation");
        return;
    }
    navigator.geolocation.getCurrentPosition(
        async (position)=>{
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;
            try{
                // 增加countrycodes=my 限定马来西亚，减少错误数据
                const res = await fetch(`https://nominatim.openstreetmap.org/reverse?lat=${lat}&lon=${lng}&format=json&accept-language=en&countrycodes=my`);
                const data = await res.json();
                const adr = data.address;

                // ✅只有拿到有效值才填入，否则保留表单原本内容，不会清空旧地址
                if(adr.road || adr.house_number){
                    document.querySelector('[name="address_line1"]').value = `${adr.road??''} ${adr.house_number??''}`.trim();
                }
                if(adr.city || adr.town || adr.village){
                    document.querySelector('[name="city"]').value = adr.city ?? adr.town ?? adr.village;
                }
                if(adr.state){
                    document.querySelector('[name="state"]').value = adr.state;
                }
                if(adr.postcode){
                    document.querySelector('[name="postcode"]').value = adr.postcode;
                }

                const countrySelect = document.querySelector('[name="country"]');
                if(adr.country === "Malaysia") countrySelect.value = "Malaysia";

            }catch(err){
                alert("Location retrieved, but address parsing failed. Please fill in address manually.");
                console.error(err);
            }
        },
        (error)=>{
            let popupMsg = "";
            switch(error.code){
                case error.PERMISSION_DENIED:
                    popupMsg = "Location permission is disabled.\nPlease enable location permission for this website in your device settings and try again.";
                    break;
                case error.POSITION_UNAVAILABLE:
                    popupMsg = "Unable to retrieve your location";
                    break;
                case error.TIMEOUT:
                    popupMsg = "Location request timed out. Please check your network and location settings.";
                    break;
                default:
                    popupMsg = "An error occurred while fetching location";
            }
            alert(popupMsg);
        },
        {
            enableHighAccuracy:true,
            timeout:10000,
            maximumAge:0
        }
    );
})
</script>
<?php include '../_foot.php'; ?>
