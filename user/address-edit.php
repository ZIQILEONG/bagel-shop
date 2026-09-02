<?php
include '../_base.php';
auth('Member');

$id = req('id');
$stm = $_db->prepare("SELECT * FROM shipping_address WHERE id = ? AND user_id = ?");
$stm->execute([$id, $_user->id]);
$addr = $stm->fetch();

if (!$addr) {
    temp('error', 'Shipping address not found.');
    redirect('address-list.php');
}

$error = '';

$phoneRules = [
    'Malaysia'    => ['min' => 9, 'max' => 10],
    'Singapore'   => ['min' => 8, 'max' => 8],
    'Thailand'    => ['min' => 9, 'max' => 9],
    'Indonesia'   => ['min' => 9, 'max' => 12],
    'Brunei'      => ['min' => 7, 'max' => 7],
    'Philippines' => ['min' => 9, 'max' => 10],
    'Vietnam'     => ['min' => 9, 'max' => 10],
];

if (is_post()) {
    $recipient_name     = trim(req('recipient_name'));
    $phone_country_code = trim(req('phone_country_code', $addr->phone_country_code ?? 'Malaysia'));
    $phone              = trim(req('phone'));
    $address_line1      = trim(req('address_line1'));
    $address_line2      = trim(req('address_line2'));
    $city               = trim(req('city'));
    $state              = trim(req('state'));
    $postcode           = trim(req('postcode'));
    $country            = req('country');

    if ($address_line1 === '') {
        $error = "Address Line 1 cannot be empty! Please enter your house number and street name.";
    }

    if ($error === '') {
        if (!ctype_digit($phone)) {
            $error = "Phone number must only contain digits.";
        } else {
            $rule = $phoneRules[$phone_country_code] ?? null;
            if ($rule) {
                $len = strlen($phone);
                if ($len < $rule['min'] || $len > $rule['max']) {
                    $error = "{$phone_country_code} phone number must be {$rule['min']}–{$rule['max']} digits.";
                }
            }
        }
    }

    if ($error === '') {
        $stm = $_db->prepare("
            UPDATE shipping_address 
            SET recipient_name = ?, phone_country_code = ?, phone = ?, address_line1 = ?, address_line2 = ?, city = ?, state = ?, postcode = ?, country = ? 
            WHERE id = ? AND user_id = ?
        ");
        $stm->execute([$recipient_name, $phone_country_code, $phone, $address_line1, $address_line2, $city, $state, $postcode, $country, $id, $_user->id]);
        
        temp('info', 'Shipping address updated successfully!');
        redirect('address-list.php');
    }
}

$_title = "Edit Shipping Address | Pululu Bagel";
include '../_head.php';
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<link rel="stylesheet" href="<?= app_url('css/user-address-edit.css') ?>">

<div class="pl-addr-edit-wrap">
    <!-- Breadcrumb -->
    <div class="pl-breadcrumb">
        <a href="/">Home</a>
        <span>&rsaquo;</span>
        <a href="/user/profile.php">My Profile</a>
        <span>&rsaquo;</span>
        <a href="address-list.php">Shipping Addresses</a>
        <span>&rsaquo;</span>
        <span class="il-4-8a27e5">Edit Address</span>
    </div>

    <!-- Page Header -->
    <div class="pl-form-header">
        <h1>Edit Shipping Address</h1>
        <p>Update your delivery details or re-pin your location on the map.</p>
    </div>

    <?php if ($error): ?>
        <div class="pl-error-box">
            <span>⚠️</span>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <div class="pl-addr-grid">
        <!-- Left: OpenStreetMap Card -->
        <div class="pl-map-card">
            <div class="pl-map-card-head">
                <span>🗺️ Interactive Map Location</span>
            </div>
            <div id="map"></div>
            <p class="pl-map-hint">
                💡 <b>Tip:</b> Click <i>"Use Current Location"</i> to auto-update street details, then verify your specific house or building number.
            </p>
        </div>

        <!-- Right: Edit Address Form -->
        <div class="pl-form-card">
            <form method="post" id="editForm">
                <!-- Recipient Name -->
                <div class="pl-field-group">
                    <label>Recipient Name</label>
                    <input type="text" name="recipient_name" required value="<?= htmlspecialchars(post('recipient_name', $addr->recipient_name)) ?>" placeholder="Full Name">
                </div>

                <!-- Phone -->
                <div class="pl-field-group">
                    <label>Contact Phone Number</label>
                    <div class="pl-phone-flex">
                        <?php $selectedCountryCode = post('phone_country_code', $addr->phone_country_code ?? 'Malaysia'); ?>
                        <select name="phone_country_code" id="phoneCountryCode" class="pl-phone-code-select">
                            <option value="Malaysia" <?= $selectedCountryCode === 'Malaysia' ? 'selected' : '' ?>>Malaysia (+60)</option>
                            <option value="Singapore" <?= $selectedCountryCode === 'Singapore' ? 'selected' : '' ?>>Singapore (+65)</option>
                            <option value="Thailand" <?= $selectedCountryCode === 'Thailand' ? 'selected' : '' ?>>Thailand (+66)</option>
                            <option value="Indonesia" <?= $selectedCountryCode === 'Indonesia' ? 'selected' : '' ?>>Indonesia (+62)</option>
                            <option value="Brunei" <?= $selectedCountryCode === 'Brunei' ? 'selected' : '' ?>>Brunei (+673)</option>
                            <option value="Philippines" <?= $selectedCountryCode === 'Philippines' ? 'selected' : '' ?>>Philippines (+63)</option>
                            <option value="Vietnam" <?= $selectedCountryCode === 'Vietnam' ? 'selected' : '' ?>>Vietnam (+84)</option>
                        </select>
                        <input type="tel" name="phone" id="phoneNumber" required value="<?= htmlspecialchars(post('phone', $addr->phone)) ?>" placeholder="Enter phone number">
                    </div>
                    <small id="phoneHint" class="pl-phone-hint"></small>
                </div>

                <!-- Address Line 1 -->
                <div class="pl-field-group">
                    <label>Address Line 1 <small>(House / Unit No., Building, Street)</small></label>
                    <input type="text" name="address_line1" required value="<?= htmlspecialchars(post('address_line1', $addr->address_line1)) ?>" placeholder="e.g. No. 12, Jalan Ampang">
                </div>

                <!-- Address Line 2 -->
                <div class="pl-field-group">
                    <label>Address Line 2 <small>(Optional &bull; Suite, Floor, Landmark)</small></label>
                    <input type="text" name="address_line2" value="<?= htmlspecialchars(post('address_line2', $addr->address_line2 ?? '')) ?>" placeholder="e.g. Level 3, Tower B">
                </div>

                <!-- City & State -->
                <div class="pl-form-row-2">
                    <div class="pl-field-group">
                        <label>City</label>
                        <input type="text" name="city" required value="<?= htmlspecialchars(post('city', $addr->city)) ?>" placeholder="e.g. Kuala Lumpur">
                    </div>
                    <div class="pl-field-group">
                        <label>State / Province</label>
                        <input type="text" name="state" required value="<?= htmlspecialchars(post('state', $addr->state)) ?>" placeholder="e.g. Selangor">
                    </div>
                </div>

                <!-- Postcode & Country -->
                <div class="pl-form-row-2">
                    <div class="pl-field-group">
                        <label>Postcode</label>
                        <input type="text" name="postcode" required value="<?= htmlspecialchars(post('postcode', $addr->postcode)) ?>" placeholder="e.g. 50450">
                    </div>
                    <div class="pl-field-group">
                        <label>Country</label>
                        <?php $selectedCountry = post('country', $addr->country ?? 'Malaysia'); ?>
                        <select name="country" id="addressCountry">
                            <option value="Malaysia" <?= $selectedCountry === 'Malaysia' ? 'selected' : '' ?>>Malaysia</option>
                            <option value="Singapore" <?= $selectedCountry === 'Singapore' ? 'selected' : '' ?>>Singapore</option>
                            <option value="Thailand" <?= $selectedCountry === 'Thailand' ? 'selected' : '' ?>>Thailand</option>
                            <option value="Indonesia" <?= $selectedCountry === 'Indonesia' ? 'selected' : '' ?>>Indonesia</option>
                            <option value="Brunei" <?= $selectedCountry === 'Brunei' ? 'selected' : '' ?>>Brunei</option>
                            <option value="Philippines" <?= $selectedCountry === 'Philippines' ? 'selected' : '' ?>>Philippines</option>
                            <option value="Vietnam" <?= $selectedCountry === 'Vietnam' ? 'selected' : '' ?>>Vietnam</option>
                        </select>
                    </div>
                </div>

                <!-- Actions -->
                <div class="pl-form-actions">
                    <button type="submit" class="pl-btn-save-addr">Update Address &rarr;</button>
                    <button type="button" class="pl-btn-cancel-addr" id="cancelBtn">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const phoneRuleMap = {
    'Malaysia':    {min: 9, max: 10},
    'Singapore':   {min: 8, max: 8},
    'Thailand':    {min: 9, max: 9},
    'Indonesia':   {min: 9, max: 12},
    'Brunei':      {min: 7, max: 7},
    'Philippines': {min: 9, max: 10},
    'Vietnam':     {min: 9, max: 10},
};

const phoneCountrySel   = document.getElementById('phoneCountryCode');
const phoneInput        = document.getElementById('phoneNumber');
const phoneHint         = document.getElementById('phoneHint');
const addressCountrySel = document.getElementById('addressCountry');
const form              = document.getElementById('editForm');
const cancelBtn         = document.getElementById('cancelBtn');

// Track initial form values for changes
const initialSerializedForm = new URLSearchParams(new FormData(form)).toString();

addressCountrySel.addEventListener('change', function() {
    phoneCountrySel.value = this.value;
    updatePhonePlaceholder();
});

phoneCountrySel.addEventListener('change', updatePhonePlaceholder);

function updatePhonePlaceholder() {
    const c = phoneCountrySel.value;
    const rule = phoneRuleMap[c] || {min: 8, max: 12};
    phoneInput.placeholder = `Local number (${rule.min}–${rule.max} digits)`;
    phoneHint.textContent = `Requires ${rule.min}–${rule.max} digits for ${c}`;
}
updatePhonePlaceholder();

// Client Validation
form.addEventListener('submit', function(e) {
    const num = phoneInput.value.trim();
    const country = phoneCountrySel.value;
    const rule = phoneRuleMap[country] || {min: 8, max: 12};

    if (!/^\d+$/.test(num)) {
        alert("Phone number must only contain digits.");
        e.preventDefault();
        return;
    }
    const len = num.length;
    if (len < rule.min || len > rule.max) {
        alert(`${country} phone number must be ${rule.min}–${rule.max} digits.`);
        e.preventDefault();
        return;
    }
});

// SweetAlert Cancel/Discard Handler
cancelBtn.addEventListener('click', function() {
    const currentSerializedForm = new URLSearchParams(new FormData(form)).toString();
    const isDirty = initialSerializedForm !== currentSerializedForm;

    if (!isDirty) {
        window.location.href = 'address-list.php';
        return;
    }

    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: 'Discard Changes?',
            text: 'You have unsaved changes. Are you sure you want to abandon your edits?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#cf7953',
            cancelButtonColor: '#968377',
            confirmButtonText: 'Discard & Exit',
            cancelButtonText: 'Keep Editing',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'address-list.php';
            }
        });
    } else {
        if (confirm('Discard changes and return to address list?')) {
            window.location.href = 'address-list.php';
        }
    }
});

// Leaflet Map Initialization
let map;
let locationMarker = null;

document.addEventListener('DOMContentLoaded', function() {
    map = L.map('map', {
        worldCopyJump: false,
        inertia: false,
        minZoom: 3,
        maxZoom: 18
    }).setView([3.1390, 101.6869], 6);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
        noWrap: true
    }).addTo(map);

    const locationBtn = L.control({position: 'topleft'});
    locationBtn.onAdd = function(map) {
        const div = L.DomUtil.create('div', 'leaflet-control');
        const btn = L.DomUtil.create('button', 'leaflet-control-mybutton', div);
        btn.innerHTML = "📍 Use Current Location";
        L.DomEvent.on(btn, 'click', function(e) {
            L.DomEvent.stopPropagation(e);
            getCurrentLocation();
        });
        return div;
    };
    locationBtn.addTo(map);

    map.on('drag', function() {
        let c = map.getCenter();
        c.lat = Math.max(-84, Math.min(84, c.lat));
        map.setCenter(c, {animate: false});
    });
});

function getCurrentLocation() {
    if (!navigator.geolocation) {
        alert("Your browser does not support geolocation");
        return;
    }
    navigator.geolocation.getCurrentPosition(
        async (position) => {
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;
            map.setView([lat, lng], 16);

            if (locationMarker) {
                locationMarker.setLatLng([lat, lng]);
            } else {
                locationMarker = L.marker([lat, lng]).addTo(map);
            }

            try {
                const res = await fetch(`https://nominatim.openstreetmap.org/reverse?lat=${lat}&lon=${lng}&format=json&accept-language=en&zoom=16`);
                const data = await res.json();
                
                if (!data || !data.address) return;
                
                const adr = data.address;
                let parts = [];
                if (adr.house_number) parts.push(adr.house_number);
                if (adr.road) parts.push(adr.road);
                let line1 = parts.join(' ').trim();
                
                if (line1) document.querySelector('[name="address_line1"]').value = line1;
                
                let line2 = `${adr.suburb ?? ''} ${adr.neighbourhood ?? ''}`.trim();
                document.querySelector('[name="address_line2"]').value = line2;
                document.querySelector('[name="city"]').value = adr.city ?? adr.town ?? adr.village ?? '';
                document.querySelector('[name="state"]').value = adr.state ?? adr.region ?? '';
                document.querySelector('[name="postcode"]').value = adr.postcode ?? '';

                const countrySelect = document.querySelector('[name="country"]');
                const countryName = adr.country;
                for (let opt of countrySelect.options) {
                    if (opt.textContent === countryName) {
                        countrySelect.value = opt.value;
                        phoneCountrySel.value = opt.value;
                        updatePhonePlaceholder();
                        break;
                    }
                }
            } catch (err) {
                console.error("Reverse geocode error:", err);
            }
        },
        (error) => {
            let popupMsg = "";
            switch (error.code) {
                case error.PERMISSION_DENIED:
                    popupMsg = "Location permission is disabled in your device settings.";
                    break;
                case error.POSITION_UNAVAILABLE:
                    popupMsg = "Unable to retrieve your location.";
                    break;
                case error.TIMEOUT:
                    popupMsg = "Location request timed out. Please check your network connection.";
                    break;
                default:
                    popupMsg = "Error fetching location.";
            }
            alert(popupMsg);
        },
        {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 0
        }
    );
}
</script>

<?php include '../_foot.php'; ?>