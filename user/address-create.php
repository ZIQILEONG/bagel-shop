<?php
include '../_base.php';
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
/* Custom button control overlaid on the map */
.leaflet-control-mybutton {
    background:#d1603d;
    color:white;
    border:none;
    padding:6px 12px;
    border-radius:6px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.3);
    font-size:14px;
}
.leaflet-control-mybutton:hover {
    background:#b85030;
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
    color:#333333;
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
</style>
<h2>Add New Shipping Address</h2>
<form method="post">
    <div id="map"></div>
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
        <button type="submit" class="btn-primary">Save Address</button>
        <a href="address-list.php" class="btn-cancel">Cancel</a>
    </div>
</form>
<script>
const map = L.map('map', {
    worldCopyJump: false,
    inertia: false,
    minZoom:3,
    maxZoom:18
}).setView([3.1390, 101.6869], 6);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors',
    noWrap:true
}).addTo(map);
// ✅ The button is created as a Leaflet Control and added directly to the map layers, sharing the same layer as the map.const locationBtn = L.control({position:'topleft'});
locationBtn.onAdd = function(map) {
    const div = L.DomUtil.create('div','leaflet-control');
    const btn = L.DomUtil.create('button','leaflet-control-mybutton',div);
    btn.textContent = "Use My Current Location";
    L.DomEvent.on(btn,'click',function(e){
        L.DomEvent.stopPropagation(e); 
        getCurrentLocation();
    })
    return div;
}
locationBtn.addTo(map);
// Real-time latitude locking logic
map.on('drag',function(){
    let c = map.getCenter();
    c.lat = Math.max(-84, Math.min(84, c.lat));
    map.setCenter(c,{animate:false});
})
let locationMarker = null;
function getCurrentLocation(){
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
                    popupMsg = "Error fetching location";
            }
            alert(popupMsg);
        },
        {
            enableHighAccuracy:true,
            timeout:10000,
            maximumAge:0
        }
    );
}
</script>
<?php include '../_foot.php'; ?>
