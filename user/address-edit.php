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
<!-- Leaflet CSS & JS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<style>
#map {
    height:260px;
    width:100%;
    border:1px solid #cccccc;
    border-radius:4px;
    margin-bottom:10px;
}
.btn-primary{
    background:#d1603d;
    color:#ffffff;
    border:none;
    padding:12px 28px;
    border-radius:14px;
    font-size:16px;
    cursor:pointer;
}
.btn-primary:hover{
    background:#b85030;
}
.btn-cancel{
    display:inline-block;
    color:#444444;
    text-decoration:none;
    padding:12px 28px;
    border-radius:14px;
    border:1px solid #bbbbbb;
    font-size:16px;
    margin-left:10px;
    cursor:pointer;
}
.btn-cancel:hover{
    background:#f3f3f3;
}
/* Confirmation dialog styles */.modal-overlay{
    position:fixed;
    top:0;
    left:0;
    width:100%;
    height:100%;
    background:rgba(0,0,0,0.45);
    display:none;
    align-items:center;
    justify-content:center;
    z-index:9999;
}
.modal-box{
    background:#fff;
    padding:24px 30px;
    border-radius:12px;
    min-width:30px;
}
.modal-box h3{
    margin-top:0;
}
.modal-buttons{
    margin-top:10px;
    display:flex;
    gap:12px;
    justify-content:space-between;
}
/* Button size adjustments */
.modal-box .btn-primary,
.modal-box .btn-discard{
    padding:9px 18px;
    font-size:14px;
    border-radius:8px;
}
.btn-discard{
    background:#882222;
    color:white;
    border:none;
    padding:9px 18px;
    border-radius:8px;
    font-size:14px;
    cursor:pointer;
}
.btn-discard:hover{
    background:#661818;
}
</style>
<h2>Edit Shipping Address</h2>
<form method="post" id="editForm">
    <div id="map"></div>
    <div style="margin-bottom:12px;">
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
        <button type="submit" class="btn-primary">Update</button>
        <span class="btn-cancel" id="cancelBtn">Cancel</span>
    </div>
</form>
<!-- Discard edit confirmation popup --><div class="modal-overlay" id="confirmModal">
    <div class="modal-box">
        <h3>Are you sure you want to abandon your edits?</h3>
        <p>Unsaved changes will be lost.</p>
        <div class="modal-buttons">
            <button type="button" class="btn-primary" id="modalSave">Save</button>
            <button type="button" class="btn-discard" id="modalDiscard">Discard</button>
        </div>
    </div>
</div>
<script>
// Initialize the map, defaulting to the center point of Malaysia.
const map = L.map('map').setView([3.1390, 101.6869], 6);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors'
}).addTo(map);
let locationMarker = null;
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
            map.setView([lat, lng],14);
            if(locationMarker){
                locationMarker.setLatLng([lat,lng]);
            }else{
                locationMarker = L.marker([lat,lng]).addTo(map);
            }
            try{
                const res = await fetch(`https://nominatim.openstreetmap.org/reverse?lat=${lat}&lon=${lng}&format=json&accept-language=en&countrycodes=my`);
                const data = await res.json();
                const adr = data.address;
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
// Popup logic
const cancelBtn = document.getElementById('cancelBtn');
const confirmModal = document.getElementById('confirmModal');
const modalSave = document.getElementById('modalSave');
const modalDiscard = document.getElementById('modalDiscard');
const editForm = document.getElementById('editForm');
cancelBtn.addEventListener('click', ()=>{
    confirmModal.style.display = 'flex';
})
// Save
modalSave.addEventListener('click', ()=>{
    editForm.submit();
})
// Discard
modalDiscard.addEventListener('click', ()=>{
    window.location.href = 'address-list.php';
})
</script>
<?php include '../_foot.php'; ?>
