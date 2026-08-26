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
        redirect('?');
    }

    if ($btn == 'apply_voucher') {
        $code = req('voucher_code');

        $stm = $_db->prepare("SELECT * FROM voucher WHERE code = ?");
        $stm->execute([$code]);
        $v = $stm->fetch();

        if ($v && $v->active == 1 && $v->expiry >= date('Y-m-d')) {
            $_SESSION['voucher'] = [
                'code' => $code,
                'percent' => $v->percent
            ];
            temp('info', 'Voucher applied successfully!');
        }
        else {
            unset($_SESSION['voucher']);
            temp('info', 'Invalid or expired voucher code.');
        }

        redirect('?');
    }

    if ($btn == 'checkout_selected') {
        $checked_items = $_POST['checked_items'] ?? [];

        if (empty($checked_items)) {
            temp('info', 'Please select at least one item to checkout.');
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
    $unit = req('unit');
    update_cart($id, $unit);
    redirect();
}

// ----------------------------------------------------------------------------

$_title = 'Order | Shopping Cart';
include '../_head.php';
?>

<style>
    .popup { width: 100px; height: 100px; object-fit: cover; border-radius: 4px; }
    .right { text-align: right; }
    .cart-bulk-actions { margin-bottom: 15px; display: flex; gap: 15px; align-items: center; }
    .btn-delete-selected { background-color: #dc3545; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; }
    .btn-delete-selected:hover { background-color: #c82333; }
    .item-checkbox, #select-all { width: 16px; height: 16px; cursor: pointer; }
    #summary-box { border: 1px solid #ccc; padding: 12px; margin: 15px 0; max-width: 400px; }
    #summary-box div { display: flex; justify-content: space-between; margin: 4px 0; }
</style>

<?php
$cart = get_cart();
$voucher = $_SESSION['voucher'] ?? null;
$cart_count = array_sum($cart);
?>

<?php if (!$cart): ?>

    <p>🛒 Your cart is empty. <a href="/product/list.php">Browse bagels</a> to get started!</p>

<?php else: ?>

    <form method="post" id="cart-bulk-form">

        <div class="cart-bulk-actions">
            <label style="cursor: pointer; font-weight: bold; display: flex; align-items: center; gap: 6px;">
                <input type="checkbox" id="select-all" checked> Select All
            </label>
            <button type="submit" name="btn" value="delete_selected" class="btn-delete-selected" onclick="return confirm('Delete selected items from cart?')">
                🗑️ Delete Selected
            </button>
        </div>

        <table class="table">
            <tr>
                <th width="5%"></th>
                <th>Id</th>
                <th>Image</th>
                <th>Name</th>
                <th>Price (RM)</th>
                <th>Unit</th>
                <th>Subtotal (RM)</th>
            </tr>

            <?php
                $stm = $_db->prepare('SELECT * FROM product WHERE id = ?');

                foreach ($cart as $id => $unit):
                    $stm->execute([$id]);
                    $p = $stm->fetch();
                    $subtotal = $p->price * $unit;
            ?>
                <tr>
                    <td>
                        <input type="checkbox" name="checked_items[]" value="<?= $p->id ?>" class="item-checkbox" data-subtotal="<?= $subtotal ?>" checked>
                    </td>
                    <td><?= $p->id ?></td>
                    <td><img src="/products/<?= $p->photo ?>" class="popup"></td>
                    <td><?= $p->name ?></td>
                    <td class="right"><?= sprintf('%.2f', $p->price) ?></td>
                    <td>
                        <input type="hidden" name="id" value="<?= $p->id ?>" class="row-id">
                        <?= html_select('unit', $_units, $unit) ?>
                    </td>
                    <td class="right"><?= sprintf('%.2f', $subtotal) ?></td>
                </tr>
            <?php endforeach ?>
        </table>

        <p style="color:#666; font-size:13px;">
            Cart total: <?= $cart_count ?> / 100 items
            <?php if ($cart_count >= 100): ?>
                <b style="color:red">— Cart limit reached, cannot add more.</b>
            <?php endif ?>
        </p>

        <?php $points_value_available = floor(($_user->points ?? 0) / 100 * 100) / 100; ?>
        <?php if (($_user->points ?? 0) > 0): ?>
        <p>
            <label>
                <input type="checkbox" id="use-points" name="use_points" value="1">
                Use my <?= $_user->points ?> points (worth RM <?= number_format($points_value_available, 2) ?>) for discount
            </label>
        </p>
        <?php endif ?>

        <div id="summary-box">
            <div><span>Selected items:</span> <b id="selected-count">0</b></div>
            <div><span>Subtotal:</span> <b>RM <span id="selected-subtotal">0.00</span></b></div>
            <div id="voucher-row" style="display:none;"><span>Voucher (<?= $voucher['percent'] ?? 0 ?>%):</span> <b>- RM <span id="voucher-discount">0.00</span></b></div>
            <div id="points-row" style="display:none;"><span>Points used:</span> <b>- RM <span id="points-discount">0.00</span></b></div>
            <div><span><b>Total to Pay:</b></span> <b>RM <span id="final-total">0.00</span></b></div>
        </div>

        <p>
            <?php if ($_user?->role == 'Member'): ?>
                <button type="submit" name="btn" value="checkout_selected" id="checkout-btn">Checkout Selected</button>
            <?php else: ?>
                Please <a href="/login.php">login</a> as member to checkout
            <?php endif ?>
        </p>
    </form>

    <form method="post" class="form">
        <label>Voucher Code</label>
        <input type="text" name="voucher_code" value="<?= $voucher['code'] ?? '' ?>">
        <button name="btn" value="apply_voucher">Apply</button>
        <?php if ($voucher): ?>
            <p>✅ Voucher applied: <?= $voucher['percent'] ?>% off</p>
        <?php endif ?>
    </form>

    <script>
    $(document).ready(function() {
        $('#select-all').on('change', function() {
            $('.item-checkbox').prop('checked', this.checked);
            updateSelectedSummary();
        });

        $('.item-checkbox').on('change', function() {
            $('#select-all').prop('checked', $('.item-checkbox:checked').length === $('.item-checkbox').length);
            updateSelectedSummary();
        });

        $('.table select').on('change', function(e) {
            e.preventDefault();
            let row = $(this).closest('tr');
            let productId = row.find('.row-id').val();
            let selectedUnit = $(this).val();
            let hiddenIdInput = $('<input>').attr({type: 'hidden', name: 'id', value: productId});
            let hiddenUnitInput = $('<input>').attr({type: 'hidden', name: 'unit', value: selectedUnit});
            $('#cart-bulk-form').append(hiddenIdInput, hiddenUnitInput).submit();
        });

        $('#use-points').on('change', updateSelectedSummary);

        let voucherPercent = <?= $voucher ? $voucher['percent'] : 0 ?>;
        let pointsAvailableValue = <?= $points_value_available ?>;

        function updateSelectedSummary() {
            let count = 0;
            let subtotal = 0;

            $('.item-checkbox:checked').each(function() {
                count++;
                subtotal += parseFloat($(this).data('subtotal'));
            });

            $('#selected-count').text(count);
            $('#selected-subtotal').text(subtotal.toFixed(2));

            if (count === 0) {
                $('#voucher-row, #points-row').hide();
                $('#final-total').text('0.00');
                $('#checkout-btn').prop('disabled', true).text('Select items to checkout');
                return;
            }

            $('#checkout-btn').prop('disabled', false).text('Checkout Selected');

            let afterVoucher = subtotal;
            if (voucherPercent > 0) {
                let voucherDiscount = subtotal * voucherPercent / 100;
                afterVoucher = subtotal - voucherDiscount;
                $('#voucher-discount').text(voucherDiscount.toFixed(2));
                $('#voucher-row').show();
            } else {
                $('#voucher-row').hide();
            }

            let finalTotal = afterVoucher;
            if ($('#use-points').is(':checked')) {
                let pointsUsed = Math.min(pointsAvailableValue, afterVoucher);
                finalTotal = afterVoucher - pointsUsed;
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

<?php
include '../_foot.php';