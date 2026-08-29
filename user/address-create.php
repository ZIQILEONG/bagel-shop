<?php
include '../_base.php';
auth('Member');

$error = '';

// Telephone numbering rules by country (key = country name; min, max = local number digit lengths)
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
    $phone_country_code = trim(req('phone_country_code'));
    $phone              = trim(req('phone'));
    $address_line1      = trim(req('address_line1'));
    $address_line2      = trim(req('address_line2'));
    $city               = trim(req('city'));
    $state              = trim(req('state'));
    $postcode           = trim(req('postcode'));
    $country            = req('country');

    // 1. Address line1 check
    if ($address_line1 === '') {
        $error = "Address Line 1 cannot be empty! Please fill in house number and street name.";
    }

    // 2. Phone number validation: Only digits allowed.
    if ($error === '') {
        if (!ctype_digit($phone)) {
            $error = "Phone number only accepts digits.";
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
            INSERT INTO shipping_address (user_id, recipient_name, phone_country_code, phone, address_line1, address_line2, city, state, postcode, country) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stm->execute([$_user->id, $recipient_name, $phone_country_code, $phone, $address_line1, $address_line2, $city, $state, $postcode, $country]);
        
        temp('info', 'New shipping address added successfully!');
        redirect('address-list.php');
    }
}

$_title = "Add Shipping Address | Pululu Bagel";
include '../_head.php';
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<style>
/* =========================================================
   PULULU ADDRESS CREATE MODERN UI/UX
   ========================================================= */
:root {
    --pl-primary: #cf7953;
    --pl-primary-hover: #b86440;
    --pl-brown-dark: #3e2619;
    --pl-text: #4a3b32;
    --pl-muted: #968377;
    --pl-border: #ebdcd5;
    --pl-card-bg: #ffffff;
    --pl-accent: #fbf5ef;
    --pl-green: #2b7a4b;
    --pl-red: #c0392b;
}

body {
    background-color: #faf5f0;
    color: var(--pl-text);
}

.pl-addr-create-wrap {
    max-width: 1040px;
    margin: 32px auto 80px;
    padding: 0 20px;
    box-sizing: border-box;
}

/* Breadcrumb */
.pl-breadcrumb {
    font-size: 13px;
    color: var(--pl-muted);
    margin-bottom: 22px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.pl-breadcrumb a {
    color: var(--pl-muted);
    text-decoration: none;
    transition: color 0.15s ease;
}
.pl-breadcrumb a:hover {
    color: var(--pl-primary);
}

/* Header */
.pl-form-header {
    margin-bottom: 24px;
}
.pl-form-header h1 {
    font-size: 28px;
    font-weight: 800;
    color: var(--pl-brown-dark);
    margin: 0 0 6px;
}
.pl-form-header p {
    font-size: 14px;
    color: var(--pl-muted);
    margin: 0;
}

/* Error Callout Box */
.pl-error-box {
    background: #fdf2f2;
    border: 1px solid #f8cfcf;
    border-left: 4px solid var(--pl-red);
    color: var(--pl-red);
    padding: 14px 18px;
    border-radius: 12px;
    font-size: 13.5px;
    font-weight: 600;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 10px;
}

/* 2-Column Split Grid */
.pl-addr-grid {
    display: grid;
    grid-template-columns: 1fr 1.25fr;
    gap: 28px;
    align-items: start;
}

/* Map Card Column */
.pl-map-card {
    background: var(--pl-card-bg);
    border: 1px solid var(--pl-border);
    border-radius: 20px;
    padding: 20px;
    box-shadow: 0 4px 18px rgba(62, 38, 25, 0.03);
    position: sticky;
    top: 24px;
}
.pl-map-card-head {
    font-size: 15px;
    font-weight: 800;
    color: var(--pl-brown-dark);
    margin: 0 0 10px;
    display: flex;
    align-items: center;
    gap: 8px;
}
#map {
    height: 380px;
    width: 100%;
    border-radius: 14px;
    border: 1.5px solid var(--pl-border);
    overflow: hidden;
    background: #faf6f0;
}
.pl-map-hint {
    font-size: 12px;
    color: var(--pl-muted);
    margin-top: 10px;
    line-height: 1.45;
}

/* Leaflet Custom Geolocation Control Button */
.leaflet-control-mybutton {
    background: var(--pl-primary) !important;
    color: #ffffff !important;
    border: none !important;
    padding: 8px 14px !important;
    border-radius: 8px !important;
    font-size: 12.5px !important;
    font-weight: 700 !important;
    cursor: pointer !important;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2) !important;
    display: flex !important;
    align-items: center !important;
    gap: 6px !important;
    transition: background 0.15s ease !important;
}
.leaflet-control-mybutton:hover {
    background: var(--pl-primary-hover) !important;
}

/* Right Form Card */
.pl-form-card {
    background: var(--pl-card-bg);
    border: 1px solid var(--pl-border);
    border-radius: 20px;
    padding: 30px 32px;
    box-shadow: 0 4px 18px rgba(62, 38, 25, 0.03);
}

.pl-field-group {
    margin-bottom: 18px;
}
.pl-field-group label {
    display: block;
    font-size: 12px;
    font-weight: 800;
    color: var(--pl-brown-dark);
    text-transform: uppercase;
    letter-spacing: 0.04em;
    margin-bottom: 6px;
}
.pl-field-group label small {
    font-size: 11px;
    text-transform: none;
    color: var(--pl-muted);
    font-weight: normal;
    letter-spacing: normal;
}

.pl-field-group input,
.pl-field-group select {
    width: 100%;
    padding: 11px 14px;
    box-sizing: border-box;
    border: 1.5px solid var(--pl-border);
    border-radius: 12px;
    background: #fffdfc;
    font-size: 14px;
    color: var(--pl-brown-dark);
    outline: none;
    transition: all 0.2s ease;
    font-family: inherit;
}
.pl-field-group input:focus,
.pl-field-group select:focus {
    border-color: var(--pl-primary);
    background: #ffffff;
    box-shadow: 0 0 0 3px rgba(207, 121, 83, 0.12);
}

/* Phone Layout */
.pl-phone-flex {
    display: flex;
    gap: 10px;
}
.pl-phone-code-select {
    width: 160px !important;
    flex-shrink: 0;
}
.pl-phone-hint {
    font-size: 11.5px;
    color: var(--pl-muted);
    margin-top: 5px;
    display: block;
}

/* 2-Column Inputs Grid (City/State, Postcode/Country) */
.pl-form-row-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
}

/* Actions */
.pl-form-actions {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-top: 26px;
    padding-top: 20px;
    border-top: 1px solid #f5ebe4;
}

.pl-btn-save-addr {
    background: var(--pl-primary);
    color: #ffffff;
    border: none;
    padding: 13px 26px;
    border-radius: 12px;
    font-size: 14.5px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s ease;
    box-shadow: 0 4px 14px rgba(207, 121, 83, 0.25);
}
.pl-btn-save-addr:hover {
    background: var(--pl-primary-hover);
    transform: translateY(-1px);
    box-shadow: 0 6px 18px rgba(207, 121, 83, 0.35);
}

.pl-btn-cancel-addr {
    display: inline-flex;
    align-items: center;
    background: transparent;
    color: var(--pl-muted);
    padding: 12px 20px;
    border-radius: 12px;
    border: 1.5px solid var(--pl-border);
    font-size: 14px;
    font-weight: 700;
    text-decoration: none;
    transition: all 0.15s ease;
}
.pl-btn-cancel-addr:hover {
    background: var(--pl-accent);
    color: var(--pl-brown-dark);
    border-color: #d8c2b5;
}

@media (max-width: 860px) {
    .pl-addr-grid {
        grid-template-columns: 1fr;
    }
    .pl-map-card {
        position: static;
    }
    #map {
        height: 280px;
    }
}
</style>

<div class="pl-addr-create-wrap">
    <!-- Breadcrumb -->
    <div class="pl-breadcrumb">
        <a href="/">Home</a>
        <span>&rsaquo;</span>
        <a href="/user/profile.php">My Profile</a>
        <span>&rsaquo;</span>
        <a href="address-list.php">Shipping Addresses</a>
        <span>&rsaquo;</span>
        <span style="color: var(--pl-brown-dark); font-weight: 600;">Add New</span>
    </div>

    <!-- Page Header -->
    <div class="pl-form-header">
        <h1>Add Shipping Address</h1>
        <p>Pinpoint your delivery location on the map or enter your address manually.</p>
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
                💡 <b>Tip:</b> Click <i>"Use Current Location"</i> to auto-fill your street and city details, then double-check your unit or house number.
            </p>
        </div>

        <!-- Right: Address Form -->
        <div class="pl-form-card">
            <form method="post" id="addressForm">
                <!-- Recipient Name -->
                <div class="pl-field-group">
                    <label>Recipient Name</label>
                    <input type="text" name="recipient_name" placeholder="Full name (e.g., Lai)" required value="<?= post('recipient_name', '') ?>">
                </div>

                <!-- Phone -->
                <div class="pl-field-group">
                    <label>Contact Phone Number</label>
                    <div class="pl-phone-flex">
                        <select name="phone_country_code" id="phoneCountryCode" class="pl-phone-code-select">
                            <option value="Malaysia" <?= post('phone_country_code', 'Malaysia') === 'Malaysia' ? 'selected' : '' ?>>Malaysia (+60)</option>
                            <option value="Singapore" <?= post('phone_country_code') === 'Singapore' ? 'selected' : '' ?>>Singapore (+65)</option>
                            <option value="Thailand" <?= post('phone_country_code') === 'Thailand' ? 'selected' : '' ?>>Thailand (+66)</option>
                            <option value="Indonesia" <?= post('phone_country_code') === 'Indonesia' ? 'selected' : '' ?>>Indonesia (+62)</option>
                            <option value="Brunei" <?= post('phone_country_code') === 'Brunei' ? 'selected' : '' ?>>Brunei (+673)</option>
                            <option value="Philippines" <?= post('phone_country_code') === 'Philippines' ? 'selected' : '' ?>>Philippines (+63)</option>
                            <option value="Vietnam" <?= post('phone_country_code') === 'Vietnam' ? 'selected' : '' ?>>Vietnam (+84)</option>
                        </select>
                        <input type="tel" name="phone" id="phoneNumber" required value="<?= post('phone', '') ?>" placeholder="Enter local phone number">
                    </div>
                    <small id="phoneHint" class="pl-phone-hint"></small>
                </div>

                <!-- Address Line 1 -->
                <div class="pl-field-group">
                    <label>Address Line 1 <small>(House / Unit No., Building, Street)</small></label>
                    <input type="text" name="address_line1" placeholder="e.g., No. 12, Jalan Ampang" required value="<?= post('address_line1', '') ?>">
                </div>

                <!-- Address Line 2 -->
                <div class="pl-field-group">
                    <label>Address Line 2 <small>(Optional &bull; Suite, Floor, Landmark)</small></label>
                    <input type="text" name="address_line2" placeholder="e.g., Level 3, Tower B" value="<?= post('address_line2', '') ?>">
                </div>

                <!-- City & State -->
                <div class="pl-form-row-2">
                    <div class="pl-field-group">
                        <label>City</label>
                        <input type="text" name="city" placeholder="e.g., Kuala Lumpur" required value="<?= post('city', '') ?>">
                    </div>
                    <div class="pl-field-group">
                        <label>State / Province</label>
                        <input type="text" name="state" placeholder="e.g., Selangor" required value="<?= post('state', '') ?>">
                    </div>
                </div>

                <!-- Postcode & Country -->
                <div class="pl-form-row-2">
                    <div class="pl-field-group">
                        <label>Postcode</label>
                        <input type="text" name="postcode" placeholder="e.g., 50450" required value="<?= post('postcode', '') ?>">
                    </div>
                    <div class="pl-field-group">
                        <label>Country</label>
                        <select name="country" id="addressCountry">
                            <option value="Malaysia" <?= post('country', 'Malaysia') === 'Malaysia' ? 'selected' : '' ?>>Malaysia</option>
                            <option value="Singapore" <?= post('country') === 'Singapore' ? 'selected' : '' ?>>Singapore</option>
                            <option value="Thailand" <?= post('country') === 'Thailand' ? 'selected' : '' ?>>Thailand</option>
                            <option value="Indonesia" <?= post('country') === 'Indonesia' ? 'selected' : '' ?>>Indonesia</option>
                            <option value="Brunei" <?= post('country') === 'Brunei' ? 'selected' : '' ?>>Brunei</option>
                            <option value="Philippines" <?= post('country') === 'Philippines' ? 'selected' : '' ?>>Philippines</option>
                            <option value="Vietnam" <?= post('country') === 'Vietnam' ? 'selected' : '' ?>>Vietnam</option>
                        </select>
                    </div>
                </div>

                <!-- Actions -->
                <div class="pl-form-actions">
                    <button type="submit" class="pl-btn-save-addr">Save Address &rarr;</button>
                    <a href="address-list.php" class="pl-btn-cancel-addr">Cancel</a>
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
const form              = document.getElementById('addressForm');

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
                    popupMsg = "Location permission is disabled.\nPlease enable location permissions in your browser and try again.";
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