<?php
include '../_base.php';

// ----------------------------------------------------------------------------

auth('Member');

$cart = $_SESSION['checkout_cart'] ?? get_cart();

if (empty($cart)) {
    redirect('cart.php');
}

if (is_post()) {
    $method = req('delivery_method');

    if ($method == 'Delivery') {
        $address_id = req('address_id');

        if (!$address_id) {
            $_err['address_id'] = 'Please select a delivery address.';
        }
        else {
            $stm = $_db->prepare("SELECT * FROM shipping_address WHERE id = ? AND user_id = ?");
            $stm->execute([$address_id, $_user->id]);
            if (!$stm->fetch()) {
                $_err['address_id'] = 'Invalid address selected.';
            }
        }
    }

    if (!$_err) {
        $_SESSION['delivery_method'] = $method;
        $_SESSION['address_id'] = ($method == 'Delivery') ? $address_id : null;
        redirect('checkout.php');
    }
}

// Calculate the full preview: subtotal -> voucher -> points -> (delivery added via JS)
$subtotal = 0;
foreach ($cart as $product_id => $unit) {
    $stm = $_db->prepare("SELECT price FROM product WHERE id = ?");
    $stm->execute([$product_id]);
    $p = $stm->fetch();
    $subtotal += ($p->price ?? 0) * (is_array($unit) ? ($unit['qty'] ?? 1) : $unit);
}

$voucher = $_SESSION['voucher'] ?? null;
$voucher_discount = 0;
if ($voucher) {
    $voucher_discount = round($subtotal * ($voucher['percent'] ?? 0) / 100, 2);
}
$after_voucher = $subtotal - $voucher_discount;

$use_points = $_SESSION['use_points'] ?? false;
$points_discount = 0;
if ($use_points && ($_user->points ?? 0) > 0) {
    $points_value_available = $_user->points / 100;
    $points_discount = min($points_value_available, $after_voucher);
    $points_discount = round($points_discount, 2);
}
$after_points = $after_voucher - $points_discount;

$stm = $_db->prepare("SELECT * FROM shipping_address WHERE user_id = ? ORDER BY is_default DESC, id DESC");
$stm->execute([$_user->id]);
$addresses = $stm->fetchAll();

// ----------------------------------------------------------------------------

$_title = 'Order | Delivery Options';
include '../_head.php';
?>

<style>
/* =========================================================
   PULULU DELIVERY OPTIONS MODERN UI/UX
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
}

body {
    background-color: #faf5f0;
    color: var(--pl-text);
}

.pl-checkout-wrap {
    max-width: 980px;
    margin: 24px auto 70px;
    padding: 0 20px;
    box-sizing: border-box;
}

/* Breadcrumb */
.pl-breadcrumb {
    font-size: 13px;
    color: var(--pl-muted);
    margin-bottom: 20px;
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

/* Page Title Section */
.pl-section-head {
    margin-bottom: 24px;
}
.pl-section-head h1 {
    font-size: 26px;
    font-weight: 800;
    color: var(--pl-brown-dark);
    margin: 0 0 6px;
}
.pl-section-head p {
    font-size: 14px;
    color: var(--pl-muted);
    margin: 0;
}

/* 2-Column Split Layout */
.pl-delivery-grid {
    display: grid;
    grid-template-columns: 1.4fr 1fr;
    gap: 28px;
    align-items: start;
}

/* Card Container */
.pl-panel-card {
    background: var(--pl-card-bg);
    border: 1px solid var(--pl-border);
    border-radius: 20px;
    padding: 28px;
    box-shadow: 0 4px 20px rgba(62, 38, 25, 0.04);
    margin-bottom: 24px;
}

.pl-panel-card h2 {
    font-size: 17px;
    font-weight: 800;
    color: var(--pl-brown-dark);
    margin: 0 0 16px;
    display: flex;
    align-items: center;
    gap: 8px;
}

/* Method Selection Cards */
.pl-method-options {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
    margin-bottom: 20px;
}

.pl-method-label {
    display: flex;
    flex-direction: column;
    padding: 18px;
    border: 2px solid var(--pl-border);
    border-radius: 14px;
    background: #fffdfc;
    cursor: pointer;
    transition: all 0.2s ease;
    position: relative;
}

.pl-method-label:hover {
    border-color: #d8c2b5;
    background: #fff8f5;
}

.pl-method-label.active {
    border-color: var(--pl-primary);
    background: #fff7f2;
    box-shadow: 0 4px 12px rgba(207, 121, 83, 0.12);
}

.pl-method-label input[type="radio"] {
    position: absolute;
    top: 16px;
    right: 16px;
    accent-color: var(--pl-primary);
}

.pl-method-icon {
    font-size: 26px;
    margin-bottom: 8px;
}

.pl-method-title {
    font-size: 15px;
    font-weight: 700;
    color: var(--pl-brown-dark);
    margin-bottom: 4px;
}

.pl-method-price {
    font-size: 13px;
    font-weight: 600;
    color: var(--pl-primary);
}

.pl-method-desc {
    font-size: 12px;
    color: var(--pl-muted);
    margin-top: 4px;
    line-height: 1.4;
}

/* Address List UI */
.pl-address-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-top: 14px;
}

.pl-address-card {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    padding: 16px 18px;
    border: 1.5px solid var(--pl-border);
    border-radius: 14px;
    background: #ffffff;
    cursor: pointer;
    transition: all 0.2s ease;
}

.pl-address-card:hover {
    border-color: #d8c2b5;
    background: #fffdfc;
}

.pl-address-card.selected {
    border-color: var(--pl-primary);
    background: #fff9f6;
}

.pl-address-card input[type="radio"] {
    margin-top: 4px;
    accent-color: var(--pl-primary);
}

.pl-address-info {
    flex: 1;
}

.pl-recipient-name {
    font-size: 14px;
    font-weight: 700;
    color: var(--pl-brown-dark);
    display: flex;
    align-items: center;
    gap: 8px;
}

.pl-badge-default {
    background: #eaf5ee;
    color: var(--pl-green);
    font-size: 11px;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 6px;
}

.pl-address-text {
    font-size: 13px;
    color: var(--pl-text);
    margin-top: 4px;
    line-height: 1.45;
}

.pl-add-address-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-top: 14px;
    font-size: 13px;
    font-weight: 700;
    color: var(--pl-primary);
    text-decoration: none;
}
.pl-add-address-btn:hover {
    text-decoration: underline;
}

/* Right Column: Order Summary Card */
.pl-summary-card {
    background: #ffffff;
    border: 1px solid var(--pl-border);
    border-radius: 20px;
    padding: 24px;
    box-shadow: 0 4px 20px rgba(62, 38, 25, 0.04);
    position: sticky;
    top: 24px;
}

.pl-summary-title {
    font-size: 17px;
    font-weight: 800;
    color: var(--pl-brown-dark);
    margin: 0 0 16px;
    padding-bottom: 12px;
    border-bottom: 1px solid #f5ebe4;
}

.pl-summary-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 13.5px;
    color: var(--pl-text);
    margin-bottom: 10px;
}

.pl-summary-row.discount {
    color: var(--pl-green);
    font-weight: 600;
}

.pl-summary-divider {
    height: 1px;
    background: #f5ebe4;
    margin: 14px 0;
}

.pl-summary-total {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    font-size: 18px;
    font-weight: 800;
    color: var(--pl-brown-dark);
    margin-top: 14px;
}

.pl-total-amount {
    font-size: 22px;
    color: var(--pl-primary);
}

.pl-btn-continue {
    width: 100%;
    margin-top: 20px;
    background: var(--pl-primary);
    color: #ffffff;
    border: none;
    padding: 13px 20px;
    border-radius: 12px;
    font-size: 15px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s ease;
    box-shadow: 0 4px 14px rgba(207, 121, 83, 0.25);
}

.pl-btn-continue:hover {
    background: var(--pl-primary-hover);
    transform: translateY(-1px);
    box-shadow: 0 6px 18px rgba(207, 121, 83, 0.35);
}

/* Centered Top Floating Toast Notification */
.pl-toast {
    position: fixed;
    top: 24px;
    left: 50%;
    transform: translate(-50%, -20px) scale(0.95);
    background: #3e2619;
    color: #fff;
    padding: 12px 24px;
    border-radius: 12px;
    font-size: 13.5px;
    font-weight: 600;
    box-shadow: 0 8px 24px rgba(62, 38, 25, 0.25);
    display: flex;
    align-items: center;
    gap: 10px;
    z-index: 99999;
    opacity: 0;
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    pointer-events: none;
    border-bottom: 3px solid #cf7953;
}

.pl-toast.show {
    opacity: 1;
    transform: translate(-50%, 0) scale(1);
    pointer-events: auto;
}

@media (max-width: 768px) {
    .pl-delivery-grid {
        grid-template-columns: 1fr;
    }
    .pl-method-options {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="pl-checkout-wrap">
    <!-- Breadcrumb -->
    <div class="pl-breadcrumb">
        <a href="/">Home</a>
        <span>&rsaquo;</span>
        <a href="cart.php">Shopping Cart</a>
        <span>&rsaquo;</span>
        <span style="color: var(--pl-brown-dark); font-weight: 600;">Delivery Options</span>
    </div>

    <!-- Section Head -->
    <div class="pl-section-head">
        <h1>Fulfillment Options</h1>
        <p>Choose how you would like to receive your freshly baked bagels.</p>
    </div>

    <form method="post" id="deliveryForm">
        <div class="pl-delivery-grid">
            <!-- Left: Method & Address -->
            <div class="pl-left-col">
                <div class="pl-panel-card">
                    <h2>🥯 Receiving Method</h2>
                    
                    <div class="pl-method-options">
                        <!-- Self Pickup -->
                        <label class="pl-method-label active" id="label-pickup">
                            <input type="radio" name="delivery_method" value="Pickup" checked onchange="toggleAddress()">
                            <div class="pl-method-icon">🏪</div>
                            <div class="pl-method-title">Self Pickup</div>
                            <div class="pl-method-price">FREE</div>
                            <div class="pl-method-desc">Collect fresh from Pululu Bakery store</div>
                        </label>

                        <!-- Delivery -->
                        <label class="pl-method-label" id="label-delivery">
                            <input type="radio" name="delivery_method" value="Delivery" onchange="toggleAddress()">
                            <div class="pl-method-icon">🚚</div>
                            <div class="pl-method-title">Home Delivery</div>
                            <div class="pl-method-price">+ RM 7.00</div>
                            <div class="pl-method-desc">Direct to your doorstep via local courier</div>
                        </label>
                    </div>

                    <!-- Address Section (Toggled dynamically) -->
                    <div id="address-section" style="display:none;">
                        <h2>📍 Delivery Address</h2>
                        
                        <?php if (!$addresses): ?>
                            <div style="background: var(--pl-accent); padding: 16px; border-radius: 12px; font-size: 13.5px; border: 1px solid var(--pl-border);">
                                You have no saved shipping addresses. 
                                <a href="/user/address-create.php" style="color: var(--pl-primary); font-weight: 700; text-decoration: none;">+ Add an address now</a>
                            </div>
                        <?php else: ?>
                            <div class="pl-address-list">
                                <?php foreach ($addresses as $a): ?>
                                    <label class="pl-address-card <?= $a->is_default ? 'selected' : '' ?>" onclick="selectAddressCard(this)">
                                        <input type="radio" name="address_id" value="<?= $a->id ?>" <?= $a->is_default ? 'checked' : '' ?>>
                                        <div class="pl-address-info">
                                            <div class="pl-recipient-name">
                                                <?= htmlspecialchars($a->recipient_name) ?>
                                                <?php if ($a->is_default): ?>
                                                    <span class="pl-badge-default">Default</span>
                                                <?php endif ?>
                                            </div>
                                            <div class="pl-address-text">
                                                <?= htmlspecialchars($a->address_line1) ?><br>
                                                <?= htmlspecialchars($a->city) ?>, <?= htmlspecialchars($a->state) ?> <?= htmlspecialchars($a->postcode) ?>
                                            </div>
                                        </div>
                                    </label>
                                <?php endforeach ?>
                            </div>
                            <a href="/user/address-create.php" class="pl-add-address-btn">＋ Add another delivery address</a>
                            <?= err('address_id') ?>
                        <?php endif ?>
                    </div>
                </div>
            </div>

            <!-- Right: Order Summary -->
            <div class="pl-right-col">
                <div class="pl-summary-card">
                    <div class="pl-summary-title">Order Summary</div>

                    <div class="pl-summary-row">
                        <span>Bagels Subtotal</span>
                        <span>RM <?= number_format($subtotal, 2) ?></span>
                    </div>

                    <?php if ($voucher): ?>
                        <div class="pl-summary-row discount">
                            <span>Voucher (<?= htmlspecialchars($voucher['percent'] ?? 0) ?>%)</span>
                            <span>- RM <?= number_format($voucher_discount, 2) ?></span>
                        </div>
                    <?php endif ?>

                    <?php if ($points_discount > 0): ?>
                        <div class="pl-summary-row discount">
                            <span>Points Redemption</span>
                            <span>- RM <?= number_format($points_discount, 2) ?></span>
                        </div>
                    <?php endif ?>

                    <div class="pl-summary-row" id="delivery-fee-row" style="display:none;">
                        <span>Delivery Fee</span>
                        <span>RM <span id="delivery-fee-display">7.00</span></span>
                    </div>

                    <div class="pl-summary-divider"></div>

                    <div class="pl-summary-total">
                        <span>Total Payable</span>
                        <span class="pl-total-amount">RM <span id="total-display"><?= number_format($after_points, 2) ?></span></span>
                    </div>

                    <button type="submit" class="pl-btn-continue">Continue to Payment &rarr;</button>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Floating Toast Notification Element -->
<div id="plToast" class="pl-toast">
    <span>⚠️</span>
    <span id="plToastMsg">Please select a shipping address before proceeding.</span>
</div>

<script>
let baseTotal = <?= (float)$after_points ?>;
let toastTimer = null;

function showToast(message) {
    const toast = document.getElementById('plToast');
    const toastMsg = document.getElementById('plToastMsg');
    if (!toast || !toastMsg) return;

    toastMsg.textContent = message;
    toast.classList.add('show');

    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => {
        toast.classList.remove('show');
    }, 3500);
}

function toggleAddress() {
    let isDelivery = $('input[name="delivery_method"]:checked').val() === 'Delivery';
    
    // Toggle active border cards
    $('#label-pickup').toggleClass('active', !isDelivery);
    $('#label-delivery').toggleClass('active', isDelivery);

    // Toggle address list
    $('#address-section').slideToggle(200, function() {
        $(this).css('display', isDelivery ? 'block' : 'none');
    });

    if (isDelivery) {
        $('#delivery-fee-row').show();
        $('#total-display').text((baseTotal + 7).toFixed(2));
    } else {
        $('#delivery-fee-row').hide();
        $('#total-display').text(baseTotal.toFixed(2));
    }
}

function selectAddressCard(el) {
    $('.pl-address-card').removeClass('selected');
    $(el).addClass('selected');
    $(el).find('input[type="radio"]').prop('checked', true);
}

$('#deliveryForm').on('submit', function(e) {
    let method = $('input[name="delivery_method"]:checked').val();
    if (method === 'Delivery') {
        let addressSelected = $('input[name="address_id"]:checked').length > 0;
        if (!addressSelected) {
            e.preventDefault();
            showToast('Please select a shipping address before proceeding.');
        }
    }
});
</script>

<?php
include '../_foot.php';
?>