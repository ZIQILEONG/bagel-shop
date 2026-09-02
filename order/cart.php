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
        $stm = $_db->prepare("SELECT stock, name FROM product WHERE id = ?");

        foreach ($checked_items as $id) {
            if (isset($cart[$id])) {
                $rawUnit = $cart[$id];
                $requested_qty = is_array($rawUnit) ? (int)($rawUnit['qty'] ?? 1) : (int)$rawUnit;

                // Validate item stock in database
                $stm->execute([$id]);
                $p = $stm->fetch();

                if (!$p || (int)$p->stock < 1) {
                    temp('error', "Sorry, item '{$p->name}' is currently out of stock.");
                    redirect('?');
                } elseif ((int)$p->stock < $requested_qty) {
                    temp('error', "Sorry, '{$p->name}' only has {$p->stock} item(s) available in stock.");
                    redirect('?');
                }

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

<link rel="stylesheet" href="<?= app_url('css/order-cart.css') ?>">

<div class="pl-cart-wrap">
    <!-- Breadcrumb -->
    <div class="pl-cart-breadcrumb">
        <a href="/">Home</a>
        <span>&rsaquo;</span>
        <a href="/product/list.php">Shop Bagels</a>
        <span>&rsaquo;</span>
        <span class="il-4-8a27e5">Shopping Cart</span>
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
                            $stock = (int)$p->stock;
                            $isOutOfStock = ($stock <= 0);
                            $maxStockLimit = min(10, max(0, $stock));
                        ?>
                            <div class="pl-cart-row">
                                
                            <!-- Checkbox (Disabled if Out of Stock) -->
                            <input type="checkbox" name="checked_items[]" value="<?= htmlspecialchars($p->id) ?>" class="item-checkbox pl-item-cb" data-subtotal="<?= $subtotal ?>" data-qty="<?= $unit ?>" <?= $isOutOfStock ? 'disabled' : 'checked' ?>>

                            <!-- Thumbnail -->
                            <img src="/products/<?= htmlspecialchars($p->photo ?: 'default.jpg') ?>" class="pl-cart-thumb" alt="<?= htmlspecialchars($p->name) ?>" onerror="this.src='/products/default.jpg'">

                            <!-- Info -->
                            <div class="pl-cart-item-info">
                                <span class="pl-cart-item-id"><?= htmlspecialchars($p->id) ?></span>
                                <a href="/product/detail.php?id=<?= htmlspecialchars($p->id) ?>" class="pl-cart-item-name"><?= htmlspecialchars($p->name) ?></a>
                                <span class="pl-cart-item-unitprice">RM <?= number_format($p->price, 2) ?> each</span>
                                <?php if ($isOutOfStock): ?>
                                    <span class="il-71-f0138a">⚠️ Out of Stock</span>
                                <?php endif; ?>
                            </div>

                            <!-- Stepper Unit -->
                            <div>
                                <input type="hidden" name="id" value="<?= htmlspecialchars($p->id) ?>" class="row-id">
                                <?php if ($isOutOfStock): ?>
                                    <span class="il-72-7e1a2f">0</span>
                                <?php else: ?>
                                    <select name="unit" class="pl-cart-qty-select">
                                        <?php for ($i = 1; $i <= $maxStockLimit; $i++): ?>
                                            <option value="<?= $i ?>" <?= $unit == $i ? 'selected' : '' ?>><?= $i ?></option>
                                        <?php endfor; ?>
                                    </select>
                                <?php endif; ?>
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
                            <span class="il-73-bb738e">⚠️ Maximum cart limit reached</span>
                        <?php else: ?>
                            <span class="il-74-4054d5">✓ In limit</span>
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

                    <div class="pl-summary-line discount il-35-cb4589" id="voucher-row">
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

                    <div class="pl-summary-line discount il-35-cb4589" id="points-row">
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
                        <a href="/login.php" class="pl-btn-checkout il-75-116e33">
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
                $('.item-checkbox:not(:disabled)').prop('checked', this.checked);
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