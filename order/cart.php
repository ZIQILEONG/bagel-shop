<?php
include '../_base.php';

// ---------------------------------------------------------------------------
// when add to cart, check for login. If not login, popout error prompting login. --Chai
if (is_post()) {
    $btn = req('btn');
    $cart = get_cart();

    if ($btn == 'clear') {
        set_cart();
        redirect('?');
    }

    // delete selected items via checkbox array
    if ($btn == 'delete_selected') {
        $checked_items = $_POST['checked_items'] ?? [];
        foreach ($checked_items as $id) {
            update_cart($id, 0);    // sets quantity to 0 to remove item
        }
        redirect('?');
    }

    // apply voucher
    if ($btn == 'apply_voucher') {
        $code = req('voucher_code');

        $stm = $_db->prepare("SELECT * FROM voucher WHERE code = ?");
        $stm->execute([$code]);
        $v = $stm->fetch(); // v=voucher

        // check exists, active, not expired
        if ($v && $v->active == 1 && $v->expiry >= date('Y-m-d')) {
            // valid
            $_SESSION['voucher'] = [
                'code' => $code,
                'percent' => $v->percent
            ];
            temp('info', 'Voucher applied successfully!');
        }
        else {// invalid, show error
            unset($_SESSION['voucher']);
            temp('info', 'Invalid or expired voucher code.');
        }

        redirect('?');
    }

    // checkout selected
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
        redirect('checkout.php');
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
    .popup {
        width: 100px;
        height: 100px;
        object-fit: cover;
        border-radius: 4px;
    }
    .right {
        text-align: right;
    }
    .cart-bulk-actions {
        margin-bottom: 15px;
        display: flex;
        gap: 15px;
        align-items: center;
    }
    .btn-delete-selected {
        background-color: #dc3545;
        color: white;
        border: none;
        padding: 6px 12px;
        border-radius: 4px;
        cursor: pointer;
    }
    .btn-delete-selected:hover {
        background-color: #c82333;
    }
    .item-checkbox, #select-all {
        width: 16px;
        height: 16px;
        cursor: pointer;
    }
</style>

<?php
// ONE definition of $cart and $voucher, used everywhere below in this file
$cart = get_cart();
$voucher = $_SESSION['voucher'] ?? null;
?>

<p id="selected-summary">
    Selected: <span id="selected-count">0</span> item(s) —
    Subtotal: RM <span id="selected-subtotal">0.00</span>
    <?php if ($voucher): ?>
        | After <?= $voucher['percent'] ?>% off: RM <span id="selected-final">0.00</span>
    <?php endif ?>
</p>

<!-- ONE Master form to handle bulk item selection deletions -->
<form method="post" id="cart-bulk-form">

    <?php if ($cart): ?>
        <div class="cart-bulk-actions">
            <label style="cursor: pointer; font-weight: bold; display: flex; align-items: center; gap: 6px;">
                <input type="checkbox" id="select-all"> Select All
            </label>
            <button type="submit" name="btn" value="delete_selected" class="btn-delete-selected" onclick="return confirm('Delete selected items from cart?')">
                🗑️ Delete Selected
            </button>
        </div>
    <?php endif; ?>

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
            $count = 0;
            $total = 0;

            $stm = $_db->prepare('SELECT * FROM product WHERE id = ?');

            foreach ($cart as $id => $unit):
                $stm->execute([$id]);
                $p = $stm->fetch();

                $subtotal = $p->price * $unit;
                $count += $unit;
                $total += $subtotal;
        ?>
            <tr>
                <td>
                    <input type="checkbox" name="checked_items[]" value="<?= $p->id ?>" class="item-checkbox" data-subtotal="<?= $subtotal ?>">
                </td>
                <td><?= $p->id ?></td>
                <td>
                    <img src="/products/<?= $p->photo ?>" class="popup">
                </td>
                <td><?= $p->name ?></td>
                <td class="right"><?= sprintf('%.2f', $p->price) ?></td>
                <td>
                    <div class="qty-form-container">
                        <input type="hidden" name="id" value="<?= $p->id ?>" class="row-id">
                        <?= html_select('unit', $_units, $unit) ?>
                    </div>
                </td>
                <td class="right">
                    <?= sprintf('%.2f', $subtotal) ?>
                </td>
            </tr>
        <?php endforeach ?>

        <tr>
            <th colspan="5"></th>
            <th class="right"><?= $count ?></th>
            <th class="right"><?= sprintf('%.2f', $total) ?></th>
        </tr>
    </table>
</form>

<!-- show discounted price -->
<?php
$discount = 0;
if ($voucher) {
    $discount = round($total * $voucher['percent'] / 100, 2);
}
$final_total = $total - $discount;
?>

<table class="table">
    <?php if ($voucher): ?>
    <tr>
        <th>Discount (<?= $voucher['percent'] ?>% - <?= $voucher['code'] ?>)</th>
        <td class="right">- RM <?= number_format($discount, 2) ?></td>
    </tr>
    <?php endif ?>
    <tr>
        <th>Total to Pay</th>
        <td class="right"><b>RM <?= number_format($final_total, 2) ?></b></td>
    </tr>
</table>

<!-- checkout button -->
<p>
    <?php if ($cart): ?>
        <?php if ($_user?->role == 'Member'): ?>
            <button type="submit" name="btn" value="checkout_selected" form="cart-bulk-form">Checkout Selected</button>
        <?php else: ?>
            Please <a href="/login.php">login</a> as member to checkout
        <?php endif ?>
    <?php endif ?>
</p>

<!-- voucher code -->
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
    });

    $('.item-checkbox').on('change', function() {
        if ($('.item-checkbox:checked').length === $('.item-checkbox').length) {
            $('#select-all').prop('checked', true);
        } else {
            $('#select-all').prop('checked', false);
        }
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

    let voucherPercent = <?= $voucher ? $voucher['percent'] : 0 ?>;

    function updateSelectedSummary() {
        let count = 0;
        let subtotal = 0;

        $('.item-checkbox:checked').each(function() {
            count++;
            subtotal += parseFloat($(this).data('subtotal'));
        });

        $('#selected-count').text(count);
        $('#selected-subtotal').text(subtotal.toFixed(2));

        if (voucherPercent > 0) {
            let final = subtotal - (subtotal * voucherPercent / 100);
            $('#selected-final').text(final.toFixed(2));
        }
    }

    $('.item-checkbox, #select-all').on('change', updateSelectedSummary);
});
</script>

<?php
include '../_foot.php';