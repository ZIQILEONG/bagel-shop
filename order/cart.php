<?php
include '../_base.php';

// ---------------------------------------------------------------------------

if (is_post()) {
    $btn = req('btn');
    $cart = get_cart();

    if ($btn == 'clear') {
        set_cart();
        redirect('?');
    }

    if ($btn == 'delete_selected') {
        $checked_items = $_POST['checked_items'] ?? [];
        foreach ($checked_items as $id) {
            update_cart($id, 0);
        }
        temp('info', 'Selected items removed from cart.');
        redirect('?');
    }

    if ($btn == 'apply_voucher') {
        $code = strtoupper(trim(req('voucher_code')));

        if ($code === '') {
            unset($_SESSION['voucher']);
            temp('voucher_error', 'Please enter a promo voucher code.');
            redirect('?');
        }

        $stm = $_db->prepare("SELECT * FROM voucher WHERE UPPER(code) = ?");
        $stm->execute([$code]);
        $v = $stm->fetch();

        $today = date('Y-m-d');

        if (!$v) {
            unset($_SESSION['voucher']);
            temp('voucher_error', "Promo code '{$code}' is invalid.");
        } elseif ((int)$v->active !== 1) {
            unset($_SESSION['voucher']);
            temp('voucher_error', "Promo code '{$code}' is currently inactive.");
        } elseif (!empty($v->expiry) && $v->expiry < $today) {
            unset($_SESSION['voucher']);
            temp('voucher_error', "Promo code '{$code}' expired on " . date('d M Y', strtotime($v->expiry)) . ".");
        } else {
            $_SESSION['voucher'] = [
                'code'    => $v->code,
                'percent' => (float)$v->percent
            ];
            temp('info', "Voucher '{$v->code}' applied! (" . (float)$v->percent . "% OFF)");
        }

        redirect('?');
    }

    if ($btn == 'checkout_selected') {
        $checked_items = $_POST['checked_items'] ?? [];

        if (empty($checked_items)) {
            temp('error', 'Please select at least one item to checkout.');
            redirect('?');
        }

        $checkout_cart = [];
        foreach ($checked_items as $id) {
            if (isset($cart[$id])) {
                $checkout_cart[$id] = $cart[$id];
            }
        }

        $_SESSION['checkout_cart'] = $checkout_cart;
        $_SESSION['use_points'] = isset($_POST['use_points']);

        redirect('checkout-options.php');
    }

    $id   = req('id');
    $unit = (int)req('unit');
    update_cart($id, $unit);
    redirect('?');
}

// ----------------------------------------------------------------------------

$_title = 'Order | Shopping Cart';
include '../_head.php';

$cart = get_cart();
$voucher = $_SESSION['voucher'] ?? null;
$voucher_err = temp('voucher_error');

$cart_count = 0;
foreach ($cart as $id => $u) {
    $cart_count += is_array($u) ? (int)($u['qty'] ?? 0) : (int)$u;
}
?>

<style>
/* =========================================================
   PULULU PREMIUM SHOPPING CART UI/UX
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
    --pl-radius-lg: 20px;
    --pl-radius-md: 14px;
    --pl-radius-sm: 8px;
}

body {
    background-color: #faf5f0;
    color: var(--pl-text);
}

.pl-cart-wrap {
    max-width: 1140px;
    margin: 28px auto 70px;
    padding: 0 20px;
    box-sizing: border-box;
}

/* Breadcrumb */
.pl-cart-breadcrumb {
    font-size: 13px;
    color: var(--pl-muted);
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.pl-cart-breadcrumb a {
    color: var(--pl-muted);
    text-decoration: none;
    transition: color 0.15s ease;
}
.pl-cart-breadcrumb a:hover {
    color: var(--pl-primary);
}

/* Page Header */
.pl-cart-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    margin-bottom: 24px;
}
.pl-cart-header h1 {
    font-size: 28px;
    font-weight: 800;
    color: var(--pl-brown-dark);
    margin: 0 0 6px;
}
.pl-cart-header p {
    font-size: 14px;
    color: var(--pl-muted);
    margin: 0;
}

/* 2-Column Layout */
.pl-cart-layout {
    display: grid;
    grid-template-columns: 1.55fr 1fr;
    gap: 28px;
    align-items: start;
}

/* Left: Items Container */
.pl-cart-main-card {
    background: var(--pl-card-bg);
    border: 1px solid var(--pl-border);
    border-radius: var(--pl-radius-lg);
    padding: 24px;
    box-shadow: 0 4px 20px rgba(62, 38, 25, 0.03);
}

/* Bulk Toolbar */
.pl-cart-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-bottom: 16px;
    border-bottom: 1px solid var(--pl-border);
    margin-bottom: 16px;
}
.pl-select-all-label {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 13.5px;
    font-weight: 700;
    color: var(--pl-brown-dark);
    cursor: pointer;
    user-select: none;
}
.pl-select-all-label input[type="checkbox"],
.pl-item-cb {
    width: 18px;
    height: 18px;
    accent-color: var(--pl-primary);
    cursor: pointer;
}

.pl-btn-delete-bulk {
    background: transparent;
    color: var(--pl-red);
    border: 1px solid #f2dede;
    padding: 6px 14px;
    border-radius: var(--pl-radius-sm);
    font-size: 12.5px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.pl-btn-delete-bulk:hover {
    background: #fdf2f2;
    border-color: var(--pl-red);
}

/* Items List */
.pl-cart-items-list {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.pl-cart-row {
    display: grid;
    grid-template-columns: auto 80px 1fr auto auto;
    gap: 16px;
    align-items: center;
    padding: 14px;
    border: 1px solid var(--pl-border);
    border-radius: var(--pl-radius-md);
    background: #ffffff;
    transition: all 0.2s ease;
}
.pl-cart-row:hover {
    border-color: #dccac0;
    box-shadow: 0 2px 10px rgba(62, 38, 25, 0.04);
}

.pl-cart-thumb {
    width: 80px;
    height: 80px;
    border-radius: 10px;
    object-fit: cover;
    background: #faf6f0;
    border: 1px solid var(--pl-border);
}

.pl-cart-item-info {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.pl-cart-item-id {
    font-size: 11px;
    font-weight: 700;
    color: var(--pl-muted);
    text-transform: uppercase;
}
.pl-cart-item-name {
    font-size: 15px;
    font-weight: 700;
    color: var(--pl-brown-dark);
    text-decoration: none;
    line-height: 1.3;
}
.pl-cart-item-name:hover {
    color: var(--pl-primary);
}
.pl-cart-item-unitprice {
    font-size: 13px;
    color: var(--pl-muted);
}

.pl-cart-qty-select {
    padding: 6px 10px;
    border: 1.5px solid var(--pl-border);
    border-radius: 8px;
    font-size: 13px;
    font-weight: 700;
    color: var(--pl-brown-dark);
    background: #fff;
    outline: none;
    cursor: pointer;
}
.pl-cart-qty-select:focus {
    border-color: var(--pl-primary);
}

.pl-cart-item-subtotal {
    text-align: right;
    min-width: 90px;
}
.pl-cart-subtotal-val {
    font-size: 15.5px;
    font-weight: 800;
    color: var(--pl-primary);
}

/* Cart Capacity Bar */
.pl-cart-capacity-bar {
    margin-top: 18px;
    padding: 12px 16px;
    border-radius: 10px;
    background: var(--pl-accent);
    border: 1px solid var(--pl-border);
    font-size: 12.5px;
    color: var(--pl-text);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

/* Right: Summary Card */
.pl-cart-side {
    display: flex;
    flex-direction: column;
    gap: 20px;
    position: sticky;
    top: 24px;
}

.pl-cart-summary-card,
.pl-cart-voucher-card {
    background: #ffffff;
    border: 1px solid var(--pl-border);
    border-radius: var(--pl-radius-lg);
    padding: 24px;
    box-shadow: 0 4px 20px rgba(62, 38, 25, 0.03);
}

.pl-summary-head {
    font-size: 17px;
    font-weight: 800;
    color: var(--pl-brown-dark);
    margin: 0 0 16px;
    padding-bottom: 12px;
    border-bottom: 1px solid #f5ebe4;
}

.pl-summary-line {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 13.5px;
    color: var(--pl-text);
    margin-bottom: 10px;
}

.pl-summary-line.discount {
    color: var(--pl-green);
    font-weight: 600;
}

.pl-points-box {
    background: #fbf8f5;
    border: 1px solid var(--pl-border);
    border-radius: 12px;
    padding: 12px 14px;
    margin: 14px 0;
}
.pl-points-label {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    font-size: 13px;
    color: var(--pl-text);
    cursor: pointer;
    line-height: 1.4;
}
.pl-points-label input[type="checkbox"] {
    margin-top: 2px;
    accent-color: var(--pl-primary);
}

.pl-summary-divider {
    height: 1px;
    background: #f5ebe4;
    margin: 14px 0;
}

.pl-summary-total-row {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    font-size: 17px;
    font-weight: 800;
    color: var(--pl-brown-dark);
    margin-top: 14px;
}
.pl-total-num {
    font-size: 24px;
    color: var(--pl-primary);
}

.pl-btn-checkout {
    width: 100%;
    margin-top: 18px;
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
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}
.pl-btn-checkout:hover:not(:disabled) {
    background: var(--pl-primary-hover);
    transform: translateY(-1px);
    box-shadow: 0 6px 18px rgba(207, 121, 83, 0.35);
}
.pl-btn-checkout:disabled {
    background: #d8c2b5;
    cursor: not-allowed;
    box-shadow: none;
}

/* Voucher Form */
.pl-voucher-form {
    display: flex;
    gap: 8px;
}
.pl-voucher-input {
    flex: 1;
    padding: 10px 12px;
    border: 1.5px solid var(--pl-border);
    border-radius: 10px;
    font-size: 13px;
    font-weight: 600;
    outline: none;
    text-transform: uppercase;
}
.pl-voucher-input:focus {
    border-color: var(--pl-primary);
}
.pl-btn-apply-voucher {
    background: var(--pl-brown-dark);
    color: #fff;
    border: none;
    padding: 10px 18px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    transition: background 0.15s ease;
}
.pl-btn-apply-voucher:hover {
    background: #2a1910;
}
.pl-voucher-active-tag {
    margin-top: 10px;
    font-size: 12.5px;
    color: var(--pl-green);
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 6px;
}
.pl-voucher-error-tag {
    margin-top: 10px;
    font-size: 12.5px;
    color: var(--pl-red);
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 6px;
    background: #fdf2f2;
    border: 1px solid #f5d6d6;
    padding: 7px 11px;
    border-radius: 8px;
}

/* Empty State Card */
.pl-cart-empty-panel {
    background: #ffffff;
    border: 1px solid var(--pl-border);
    border-radius: var(--pl-radius-lg);
    padding: 80px 24px;
    text-align: center;
    max-width: 600px;
    margin: 40px auto 80px;
    box-shadow: 0 4px 20px rgba(62, 38, 25, 0.03);
}
.pl-cart-empty-icon {
    font-size: 60px;
    margin-bottom: 14px;
}
.pl-cart-empty-panel h2 {
    font-size: 22px;
    font-weight: 800;
    color: var(--pl-brown-dark);
    margin: 0 0 8px;
}
.pl-cart-empty-panel p {
    font-size: 14px;
    color: var(--pl-muted);
    margin: 0 0 24px;
}
.pl-btn-shop-bagels {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: var(--pl-primary);
    color: #ffffff;
    padding: 12px 28px;
    border-radius: 12px;
    font-size: 14px;
    font-weight: 700;
    text-decoration: none;
    transition: all 0.2s ease;
    box-shadow: 0 4px 14px rgba(207, 121, 83, 0.25);
}
.pl-btn-shop-bagels:hover {
    background: var(--pl-primary-hover);
    transform: translateY(-1px);
    box-shadow: 0 6px 18px rgba(207, 121, 83, 0.35);
}

@media (max-width: 880px) {
    .pl-cart-layout {
        grid-template-columns: 1fr;
    }
    .pl-cart-row {
        grid-template-columns: auto 60px 1fr;
        gap: 12px;
    }
    .pl-cart-thumb {
        width: 60px;
        height: 60px;
    }
    .pl-cart-item-subtotal {
        grid-column: 3;
        text-align: left;
    }
}
</style>

<div class="pl-cart-wrap">
    <!-- Breadcrumb -->
    <div class="pl-cart-breadcrumb">
        <a href="/">Home</a>
        <span>&rsaquo;</span>
        <a href="/product/list.php">Shop Bagels</a>
        <span>&rsaquo;</span>
        <span style="color: var(--pl-brown-dark); font-weight: 600;">Shopping Cart</span>
    </div>

    <?php if (!$cart): ?>
        <!-- EMPTY CART STATE -->
        <div class="pl-cart-empty-panel">
            <div class="pl-cart-empty-icon">🥯</div>
            <h2>Your cart is hungry!</h2>
            <p>You haven't added any fresh baked bagels yet. Explore our handcrafted menu.</p>
            <a href="/product/list.php" class="pl-btn-shop-bagels">
                Browse Fresh Bagels &rarr;
            </a>
        </div>
    <?php else: ?>
        <!-- HEADER -->
        <div class="pl-cart-header">
            <div>
                <h1>Your Bagel Bag</h1>
                <p>Review items before selecting delivery options and payment.</p>
            </div>
        </div>

        <div class="pl-cart-layout">
            <!-- LEFT COLUMN: CART ITEMS -->
            <div class="pl-cart-main-card">
                <form method="post" id="cart-bulk-form">
                    <div class="pl-cart-toolbar">
                        <label class="pl-select-all-label">
                            <input type="checkbox" id="select-all" checked> Select All Items
                        </label>
                        <button type="button" class="pl-btn-delete-bulk" onclick="confirmDeleteSelected()">
                            🗑️ Delete Selected
                        </button>
                    </div>

                    <div class="pl-cart-items-list">
                        <?php
                        $stm = $_db->prepare('SELECT * FROM product WHERE id = ?');

                        foreach ($cart as $id => $rawUnit):
                            $unit = is_array($rawUnit) ? (int)($rawUnit['qty'] ?? 1) : (int)$rawUnit;
                            $stm->execute([$id]);
                            $p = $stm->fetch();
                            if (!$p) continue;
                            $subtotal = ($p->price ?? 0) * $unit;
                            $maxStockLimit = min(10, max(1, (int)$p->stock));
                        ?>
                            <div class="pl-cart-row">
                                <!-- Checkbox -->
                                <input type="checkbox" name="checked_items[]" value="<?= htmlspecialchars($p->id) ?>" class="item-checkbox pl-item-cb" data-subtotal="<?= $subtotal ?>" data-qty="<?= $unit ?>" checked>

                                <!-- Thumbnail -->
                                <img src="/products/<?= htmlspecialchars($p->photo ?: 'default.jpg') ?>" class="pl-cart-thumb" alt="<?= htmlspecialchars($p->name) ?>" onerror="this.src='/products/default.jpg'">

                                <!-- Info -->
                                <div class="pl-cart-item-info">
                                    <span class="pl-cart-item-id"><?= htmlspecialchars($p->id) ?></span>
                                    <a href="/product/detail.php?id=<?= htmlspecialchars($p->id) ?>" class="pl-cart-item-name"><?= htmlspecialchars($p->name) ?></a>
                                    <span class="pl-cart-item-unitprice">RM <?= number_format($p->price, 2) ?> each</span>
                                </div>

                                <!-- Stepper Unit -->
                                <div>
                                    <input type="hidden" name="id" value="<?= htmlspecialchars($p->id) ?>" class="row-id">
                                    <select name="unit" class="pl-cart-qty-select">
                                        <?php for ($i = 1; $i <= $maxStockLimit; $i++): ?>
                                            <option value="<?= $i ?>" <?= $unit == $i ? 'selected' : '' ?>><?= $i ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>

                                <!-- Subtotal -->
                                <div class="pl-cart-item-subtotal">
                                    <span class="pl-cart-subtotal-val">RM <?= number_format($subtotal, 2) ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- 100-Item Capacity Indicator -->
                    <div class="pl-cart-capacity-bar">
                        <span>Cart Total: <b><?= $cart_count ?> / 100 items</b></span>
                        <?php if ($cart_count >= 100): ?>
                            <span style="color: var(--pl-red); font-weight: 700;">⚠️ Maximum cart limit reached</span>
                        <?php else: ?>
                            <span style="color: var(--pl-green); font-weight: 600;">✓ In limit</span>
                        <?php endif ?>
                    </div>
                </form>
            </div>

            <!-- RIGHT COLUMN: SUMMARY & ACTIONS -->
            <div class="pl-cart-side">
                <!-- Promo / Voucher Card -->
                <div class="pl-cart-voucher-card">
                    <div class="pl-summary-head">🏷️ Promo Voucher</div>
                    <form method="post" class="pl-voucher-form">
                        <input type="text" name="voucher_code" class="pl-voucher-input" placeholder="Promo code (e.g. BAGEL10)" value="<?= htmlspecialchars($voucher['code'] ?? '') ?>">
                        <button type="submit" name="btn" value="apply_voucher" class="pl-btn-apply-voucher">Apply</button>
                    </form>
                    <?php if ($voucher): ?>
                        <div class="pl-voucher-active-tag">
                            <span>✅ Voucher applied: <b><?= htmlspecialchars($voucher['code']) ?> (<?= htmlspecialchars($voucher['percent']) ?>% OFF)</b></span>
                        </div>
                    <?php endif ?>

                    <?php if (!empty($voucher_err)): ?>
                        <div class="pl-voucher-error-tag">
                            <span>⚠️ <?= htmlspecialchars($voucher_err) ?></span>
                        </div>
                    <?php endif ?>
                </div>

                <!-- Order Calculation Summary Card -->
                <div class="pl-cart-summary-card">
                    <div class="pl-summary-head">Order Summary</div>

                    <div class="pl-summary-line">
                        <span>Selected Items (<b id="selected-count">0</b>)</span>
                        <span>RM <span id="selected-subtotal">0.00</span></span>
                    </div>

                    <div class="pl-summary-line discount" id="voucher-row" style="display:none;">
                        <span>Voucher Discount (<?= $voucher['percent'] ?? 0 ?>%)</span>
                        <span>- RM <span id="voucher-discount">0.00</span></span>
                    </div>

                    <!-- Loyalty Points Block -->
                    <?php 
                    $points_value_available = floor(($_user->points ?? 0) / 100 * 100) / 100;
                    if (($_user->points ?? 0) > 0): 
                    ?>
                        <div class="pl-points-box">
                            <label class="pl-points-label">
                                <input type="checkbox" id="use-points" name="use_points" value="1" form="cart-bulk-form">
                                <span>Redeem <b><?= $_user->points ?> points</b> for <b>RM <?= number_format($points_value_available, 2) ?></b> discount</span>
                            </label>
                        </div>
                    <?php endif ?>

                    <div class="pl-summary-line discount" id="points-row" style="display:none;">
                        <span>Points Redemption</span>
                        <span>- RM <span id="points-discount">0.00</span></span>
                    </div>

                    <div class="pl-summary-divider"></div>

                    <div class="pl-summary-total-row">
                        <span>Total to Pay</span>
                        <span class="pl-total-num">RM <span id="final-total">0.00</span></span>
                    </div>

                    <?php if ($_user?->role == 'Member'): ?>
                        <button type="submit" name="btn" value="checkout_selected" id="checkout-btn" form="cart-bulk-form" class="pl-btn-checkout">
                            Proceed to Delivery Options &rarr;
                        </button>
                    <?php else: ?>
                        <a href="/login.php" class="pl-btn-checkout" style="text-decoration:none;">
                            🔐 Login to Checkout
                        </a>
                    <?php endif ?>
                </div>
            </div>
        </div>

        <script>
        function confirmDeleteSelected() {
            let checkedCount = $('.item-checkbox:checked').length;
            if (checkedCount === 0) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'info',
                        title: 'No items selected',
                        text: 'Please check at least one bagel item to remove.',
                        confirmButtonColor: '#cf7953'
                    });
                } else {
                    alert('Please select at least one item to remove.');
                }
                return;
            }

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Remove Bagels?',
                    text: `Are you sure you want to remove ${checkedCount} selected item(s) from your cart?`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#c0392b',
                    cancelButtonColor: '#968377',
                    confirmButtonText: 'Yes, remove',
                    cancelButtonText: 'Keep items',
                    reverseButtons: true
                }).then((result) => {
                    if (result.isConfirmed) {
                        let form = $('#cart-bulk-form');
                        let hiddenBtn = $('<input>').attr({type: 'hidden', name: 'btn', value: 'delete_selected'});
                        form.append(hiddenBtn).submit();
                    }
                });
            } else {
                if (confirm(`Remove ${checkedCount} selected item(s) from cart?`)) {
                    let form = $('#cart-bulk-form');
                    let hiddenBtn = $('<input>').attr({type: 'hidden', name: 'btn', value: 'delete_selected'});
                    form.append(hiddenBtn).submit();
                }
            }
        }

        $(document).ready(function() {
            // Select all items toggle
            $('#select-all').on('change', function() {
                $('.item-checkbox').prop('checked', this.checked);
                updateSelectedSummary();
            });

            $('.item-checkbox').on('change', function() {
                $('#select-all').prop('checked', $('.item-checkbox:checked').length === $('.item-checkbox').length);
                updateSelectedSummary();
            });

            // Instant auto-update unit stepper change
            $('.pl-cart-qty-select').on('change', function(e) {
                e.preventDefault();
                let row = $(this).closest('.pl-cart-row');
                let productId = row.find('.row-id').val();
                let selectedUnit = $(this).val();

                let hiddenIdInput = $('<input>').attr({type: 'hidden', name: 'id', value: productId});
                let hiddenUnitInput = $('<input>').attr({type: 'hidden', name: 'unit', value: selectedUnit});

                $('#cart-bulk-form').append(hiddenIdInput, hiddenUnitInput).submit();
            });

            $('#use-points').on('change', updateSelectedSummary);

            let voucherPercent = <?= $voucher ? (float)$voucher['percent'] : 0 ?>;
            let pointsAvailableValue = <?= (float)$points_value_available ?>;

            function updateSelectedSummary() {
                let count = 0;
                let subtotal = 0;

                $('.item-checkbox:checked').each(function() {
                    count += parseInt($(this).data('qty')) || 1;
                    subtotal += parseFloat($(this).data('subtotal')) || 0;
                });

                $('#selected-count').text(count);
                $('#selected-subtotal').text(subtotal.toFixed(2));

                if (count === 0) {
                    $('#voucher-row, #points-row').hide();
                    $('#final-total').text('0.00');
                    $('#checkout-btn').prop('disabled', true).text('Select items to checkout');
                    return;
                }

                $('#checkout-btn').prop('disabled', false).html('Proceed to Delivery Options &rarr;');

                let afterVoucher = subtotal;
                if (voucherPercent > 0) {
                    let voucherDiscount = subtotal * voucherPercent / 100;
                    afterVoucher = Math.max(0, subtotal - voucherDiscount);
                    $('#voucher-discount').text(voucherDiscount.toFixed(2));
                    $('#voucher-row').show();
                } else {
                    $('#voucher-row').hide();
                }

                let finalTotal = afterVoucher;
                if ($('#use-points').is(':checked')) {
                    let pointsUsed = Math.min(pointsAvailableValue, afterVoucher);
                    finalTotal = Math.max(0, afterVoucher - pointsUsed);
                    $('#points-discount').text(pointsUsed.toFixed(2));
                    $('#points-row').show();
                } else {
                    $('#points-row').hide();
                }

                $('#final-total').text(finalTotal.toFixed(2));
            }

            updateSelectedSummary();
        });
        </script>
    <?php endif ?>
</div>

<?php
include '../_foot.php';
?>