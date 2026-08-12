<?php
include '../_base.php';

// ---------------------------------------------------------------------------
// when add to cart, check for login. If not login, popout error prompting login. --Chai
if (is_post()) {
    $btn = req('btn');
    if ($btn == 'clear') {
        set_cart();
        redirect('?');
    }

    // handles delete selected items via checkbox array --ziqi
    if ($btn == 'delete selected') {
        $checked_items = $_POST['check_items'] ?? [];
        foreach ($checked_items as $id) {
            update_cart($id, 0);    // sets quantity to 0 to remove item
        }
        redirect('?');
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
    /* Layout styling for the new select layout elements */
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
$cart = get_cart();
?>

<!-- ONE Master form to handle bulk item selection deletions -->
<form method="post" id="cart-bulk-form">

    <?php if ($cart): ?>
        <!-- Top selection tools layout bar -->
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
            <th width="5%"></th> <!-- Header column space for the checklist checkboxes -->
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
                <!-- Individual item checkbox matching item ID value -->
                <td>
                    <input type="checkbox" name="checked_items[]" value="<?= $p->id ?>" class="item-checkbox">
                </td>
                <td><?= $p->id ?></td>
                <td>
                    <img src="/products/<?= $p->photo ?>" class="popup">
                </td>
                <td><?= $p->name ?></td>
                <td class="right"><?= sprintf('%.2f', $p->price) ?></td>
                <td>
                    <!-- Separate isolated internal form tracking for inline qty changes -->
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

<p>
    <?php if ($cart): ?>
        <?php if ($_user?->role == 'Member'): ?>
            <button data-get="checkout.php">Checkout</button>
        <?php else: ?>
            Please <a href="/login.php">login</a> as member to checkout
        <?php endif ?>
    <?php endif ?>
</p>

<!-- Include jQuery script selectors to drive standard selection states -->
<script>
$(document).ready(function() {
    // 1. Select All Checkbox behavior layout rule
    $('#select-all').on('change', function() {
        $('.item-checkbox').prop('checked', this.checked);
    });

    // Uncheck master box if an individual item gets unchecked manually
    $('.item-checkbox').on('change', function() {
        if ($('.item-checkbox:checked').length === $('.item-checkbox').length) {
            $('#select-all').prop('checked', true);
        } else {
            $('#select-all').prop('checked', false);
        }
    });

    // 2. Safely hijack the dropdown triggers inside the bulk layout form wrapper
    $('.table select').on('change', function(e) {
        e.preventDefault();
        
        // Find specific target product contextual components relative to the clicked row elements
        let row = $(this).closest('tr');
        let productId = row.find('.row-id').val();
        let selectedUnit = $(this).val();

        // Dynamically append target single row parameters data and submit directly via form reference
        let hiddenIdInput = $('<input>').attr({type: 'hidden', name: 'id', value: productId});
        let hiddenUnitInput = $('<input>').attr({type: 'hidden', name: 'unit', value: selectedUnit});
        
        // Temporarily append values and push processing execution safely
        $('#cart-bulk-form').append(hiddenIdInput, hiddenUnitInput).submit();
    });
});
</script>

<?php
include '../_foot.php';