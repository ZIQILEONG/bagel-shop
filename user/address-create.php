<?php
include '../_base.php';
auth('Member');
$error = '';
// Telephone numbering rules by country (key = country name; min, max = local number digit lengths)
$phoneRules = [
    'Malaysia'    => ['min' =>9, 'max'=>10],
    'Singapore'   => ['min' =>8, 'max'=>8],
    'Thailand'    => ['min' =>9, 'max'=>9],
    'Indonesia'   => ['min' =>9, 'max'=>12],
    'Brunei'      => ['min' =>7, 'max'=>7],
    'Philippines' => ['min' =>9, 'max'=>10],
    'Vietnam'     => ['min' =>9, 'max'=>10],
];
if(is_post()){
    $recipient_name = trim(req('recipient_name'));
    $phone_country_code = trim(req('phone_country_code'));
    $phone = trim(req('phone'));
    $address_line1 = trim(req('address_line1'));
    $address_line2 = trim(req('address_line2'));
    $city = trim(req('city'));
    $state = trim(req('state'));
    $postcode = trim(req('postcode'));
    $country = req('country');
    // 1. address line1
    if ($address_line1 === '') {
        $error = "Address Line 1 cannot be empty! Please fill in house number and street name.";
    }
    // 2. Phone number validation: Only digits allowed.
    if($error === ''){
        if(!ctype_digit($phone)){
            $error = "Phone number only accept digits.";
        }else{
            $rule = $phoneRules[$phone_country_code] ?? null;
            if($rule){
                $len = strlen($phone);
                if($len < $rule['min'] || $len > $rule['max']){
                    $error = "{$phone_country_code} phone number must be {$rule['min']}‑{$rule['max']} digits.";
                }
            }
        }
    }
    if ($error === '') {
        $stm = $_db->prepare("INSERT INTO shipping_address(user_id,recipient_name,phone_country_code,phone,address_line1,address_line2,city,state,postcode,country) VALUES (?,?,?,?,?,?,?,?,?,?)");
        $stm->execute([$_user->id,$recipient_name,$phone_country_code,$phone,$address_line1,$address_line2,$city,$state,$postcode,$country]);
        redirect('address-list.php');
    }
}
$_title = "Add New Address";
include '../_head.php';
?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<style>
#map {
    height:380px;
    width:100%;
    border:1px solid #cccccc;
    border-radius:4px;
    margin-bottom:10px;
}
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
.phone-row{
    display:flex;
    gap:8px;
}
.phone-code-select{
    width:160px;
    padding:6px;
}
.phone-input{
    flex‑grow:1;
    padding:6px;
}
</style>
<h2>Add New Shipping Address</h2>
<?php if ($error): ?>
    <div style="color:red; border:1px solid red; padding:10px; margin:10px 0;">
        <?= $error ?>
    </div>
<?php endif ?>
<p style="font-size:13px; color:#666;">*Note: Auto‑location may not detect house number, please double‑check your full address before saving.</p>
<form method="post" id="addressForm">
    <div id="map"></div>
    <div>
        <label>Recipient Name</label><br>
        <input type="text" name="recipient_name" required style="width:100%;padding:6px;" value="<?= post('recipient_name','') ?>">
    </div>
    <div style="margin-top:8px;">
        <label>Phone Number</label><br>
        <div class="phone-row">
            <select name="phone_country_code" id="phoneCountryCode" class="phone-code-select">
                <option value="Malaysia" <?= post('phone_country_code','Malaysia')==='Malaysia'?'selected':'' ?>>Malaysia (+60)</option>
                <option value="Singapore" <?= post('phone_country_code')==='Singapore'?'selected':'' ?>>Singapore (+65)</option>
                <option value="Thailand" <?= post('phone_country_code')==='Thailand'?'selected':'' ?>>Thailand (+66)</option>
                <option value="Indonesia" <?= post('phone_country_code')==='Indonesia'?'selected':'' ?>>Indonesia (+62)</option>
                <option value="Brunei" <?= post('phone_country_code')==='Brunei'?'selected':'' ?>>Brunei (+673)</option>
                <option value="Philippines" <?= post('phone_country_code')==='Philippines'?'selected':'' ?>>Philippines (+63)</option>
                <option value="Vietnam" <?= post('phone_country_code')==='Vietnam'?'selected':'' ?>>Vietnam (+84)</option>
            </select>
            <input type="tel" name="phone" id="phoneNumber" class="phone-input" required value="<?= post('phone','') ?>" placeholder="Enter local phone number">
        </div>
        <small id="phoneHint" style="color:#666"></small>
    </div>
    <div style="margin-top:8px;">
        <label>Address Line 1 <small>(House No. & Street, REQUIRED)</small></label><br>
        <input type="text" name="address_line1" required style="width:100%;padding:6px;" value="<?= post('address_line1','') ?>">
    </div>
    <div style="margin-top:8px;">
        <label>Address Line 2 <small>(Apartment / Block / Unit, OPTIONAL‑can leave blank)</small></label><br>
        <input type="text" name="address_line2" style="width:100%;padding:6px;" value="<?= post('address_line2','') ?>">
    </div>
    <div style="margin-top:8px;">
        <label>City</label><br>
        <input type="text" name="city" required style="width:100%;padding:6px;" value="<?= post('city','') ?>">
    </div>
    <div style="margin-top:8px;">
        <label>State</label><br>
        <input type="text" name="state" required style="width:100%;padding:6px;" value="<?= post('state','') ?>">
    </div>
    <div style="margin-top:8px;">
        <label>Postcode</label><br>
        <input type="text" name="postcode" required style="width:100%;padding:6px;" value="<?= post('postcode','') ?>">
    </div>
    <div style="margin-top:8px;">
        <label>Country</label><br>
        <select name="country" id="addressCountry" style="width:100%;padding:6px;">
            <option value="Malaysia" <?= post('country','Malaysia')==='Malaysia'?'selected':'' ?>>Malaysia</option>
            <option value="Singapore" <?= post('country')==='Singapore'?'selected':'' ?>>Singapore</option>
            <option value="Thailand" <?= post('country')==='Thailand'?'selected':'' ?>>Thailand</option>
            <option value="Indonesia" <?= post('country')==='Indonesia'?'selected':'' ?>>Indonesia</option>
            <option value="Brunei" <?= post('country')==='Brunei'?'selected':'' ?>>Brunei</option>
            <option value="Philippines" <?= post('country')==='Philippines'?'selected':'' ?>>Philippines</option>
            <option value="Vietnam" <?= post('country')==='Vietnam'?'selected':'' ?>>Vietnam</option>
        </select>
    </div>
    <div style="margin-top:12px;">
        <button type="submit" class="btn-primary">Save Address</button>
        <a href="address-list.php" class="btn-cancel">Cancel</a>
    </div>
</form>
<script>
const phoneRuleMap = {
    'Malaysia':    {min:9, max:10},
    'Singapore':   {min:8, max:8},
    'Thailand':    {min:9, max:9},
    'Indonesia':   {min:9, max:12},
    'Brunei':      {min:7, max:7},
    'Philippines': {min:9, max:10},
    'Vietnam':     {min:9, max:10},
};
const phoneCountrySel = document.getElementById('phoneCountryCode');
const phoneInput = document.getElementById('phoneNumber');
const phoneHint = document.getElementById('phoneHint');
const addressCountrySel = document.getElementById('addressCountry');
const form = document.getElementById('addressForm');

addressCountrySel.addEventListener('change',function(){
    phoneCountrySel.value = this.value;
    updatePhonePlaceholder();
});
phoneCountrySel.addEventListener('change',updatePhonePlaceholder);
function updatePhonePlaceholder(){
    const c = phoneCountrySel.value;
    const rule = phoneRuleMap[c];
    phoneInput.placeholder = `Local phone number (${rule.min}-${rule.max} digits)`;
    phoneHint.textContent = `Requires ${rule.min}‑${rule.max} digits for ${c}`;
}
updatePhonePlaceholder();

form.addEventListener('submit',function(e){
    const num = phoneInput.value.trim();
    const country = phoneCountrySel.value;
    const rule = phoneRuleMap[country];
    if(!/^\d+$/.test(num)){
        alert("Phone number only accept digits.");
        e.preventDefault();
        return;
    }
    const len = num.length;
    if(len < rule.min || len > rule.max){
        alert(`${country} phone number must be ${rule.min}‑${rule.max} digits.`);
        e.preventDefault();
        return;
    }
});

let map;
let locationMarker = null;
document.addEventListener('DOMContentLoaded', function(){
    map = L.map('map', {
        worldCopyJump: false,
        inertia: false,
        minZoom:3,
        maxZoom:18
    }).setView([3.1390, 101.6869], 6);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
        noWrap:true
    }).addTo(map);
    const locationBtn = L.control({position:'topleft'});
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
    map.on('drag',function(){
        let c = map.getCenter();
        c.lat = Math.max(-84, Math.min(84, c.lat));
        map.setCenter(c,{animate:false});
    })
});

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
                // Increasing the zoom level to 16 results in better street detail resolution for the Malaysia region.
                const res = await fetch(`https://nominatim.openstreetmap.org/reverse?lat=${lat}&lon=${lng}&format=json&accept-language=en&zoom=16`);
                const data = await res.json();
                console.log("reverse geocode result:", data);
                if(!data || !data.address){
                    console.warn("Nominatim return empty address");
                    return;
                }
                const adr = data.address;
                let parts = [];
                if(adr.house_number) parts.push(adr.house_number);
                if(adr.road) parts.push(adr.road);
                let line1 = parts.join(' ').trim();
                document.querySelector('[name="address_line1"]').value = line1;
                let line2 = `${adr.suburb??''} ${adr.neighbourhood??''}`.trim();
                document.querySelector('[name="address_line2"]').value = line2;
                document.querySelector('[name="city"]').value = adr.city ?? adr.town ?? adr.village ?? '';
                document.querySelector('[name="state"]').value = adr.state ?? adr.region ?? '';
                document.querySelector('[name="postcode"]').value = adr.postcode ?? '';
                const countrySelect = document.querySelector('[name="country"]');
                const countryName = adr.country;
                for(let opt of countrySelect.options){
                    if(opt.textContent === countryName){
                        countrySelect.value = opt.value;
                        phoneCountrySel.value = opt.value;
                        updatePhonePlaceholder();
                        break;
                    }
                }
            }catch(err){
                console.error("Reverse geocode error:", err);
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
