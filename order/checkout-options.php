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

<link rel="stylesheet" href="<?= app_url('css/order-checkout-options.css') ?>">

<div class="pl-checkout-wrap">
    <!-- Breadcrumb -->
    <div class="pl-breadcrumb">
        <a href="/">Home</a>
        <span>&rsaquo;</span>
        <a href="cart.php">Shopping Cart</a>
        <span>&rsaquo;</span>
        <span class="il-4-8a27e5">Delivery Options</span>
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
                    <div class="il-35-cb4589" id="address-section">
                        <h2>📍 Delivery Address</h2>
                        
                        <?php if (!$addresses): ?>
                            <div class="il-76-5b58fc">
                                You have no saved shipping addresses. 
                                <a class="il-77-d1c965" href="/user/address-create.php">+ Add an address now</a>
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

                    <div class="pl-summary-row il-35-cb4589" id="delivery-fee-row">
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