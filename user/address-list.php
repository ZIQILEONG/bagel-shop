<?php
include '../_base.php';
auth('Member');

$userId = $_user->id;
$stm = $_db->prepare("SELECT * FROM shipping_address WHERE user_id = ? ORDER BY is_default DESC, id DESC");
$stm->execute([$userId]);
$addresses = $stm->fetchAll();

$_title = "My Shipping Addresses | Pululu Bagel";
include '../_head.php';
?>

<link rel="stylesheet" href="<?= app_url('css/user-address-list.css') ?>">

<div class="pl-addr-wrapper">
    <!-- Breadcrumb -->
    <div class="pl-breadcrumb">
        <a href="/">Home</a>
        <span>&rsaquo;</span>
        <a href="/user/profile.php">My Profile</a>
        <span>&rsaquo;</span>
        <span class="il-4-8a27e5">Shipping Addresses</span>
    </div>

    <!-- Header & Action -->
    <div class="pl-addr-head">
        <div>
            <h1>My Shipping Addresses</h1>
            <p>Manage delivery locations for quick and easy checkout.</p>
        </div>
        <a href="address-create.php" class="pl-btn-add-addr">
            ＋ Add New Address
        </a>
    </div>

    <?php if (!$addresses): ?>
        <!-- EMPTY STATE -->
        <div class="pl-addr-empty">
            <div class="pl-addr-empty-icon">📍</div>
            <h2>No saved addresses found</h2>
            <p>Add your home or office address to receive fresh bagel deliveries.</p>
            <a href="address-create.php" class="pl-btn-add-addr">
                ＋ Add Your First Address
            </a>
        </div>
    <?php else: ?>
        <!-- ADDRESS CARDS LIST -->
        <div class="pl-addr-list">
            <?php foreach ($addresses as $addr): ?>
                <div class="pl-addr-card <?= $addr->is_default ? 'is-default' : '' ?>">
                    <div class="pl-addr-card-top">
                        <div class="pl-recipient-wrap">
                            <span class="pl-recipient-name"><?= htmlspecialchars($addr->recipient_name) ?></span>
                            <?php if (!empty($addr->phone)): ?>
                                <span class="pl-recipient-phone">&bull; <?= htmlspecialchars($addr->phone) ?></span>
                            <?php endif; ?>
                            <?php if ($addr->is_default): ?>
                                <span class="pl-badge-default">Default</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="pl-addr-lines">
                        <?= htmlspecialchars($addr->address_line1) ?><?= !empty($addr->address_line2) ? ', ' . htmlspecialchars($addr->address_line2) : '' ?><br>
                        <?= htmlspecialchars($addr->city) ?>, <?= htmlspecialchars($addr->state) ?> <?= htmlspecialchars($addr->postcode) ?><?= !empty($addr->country) ? ', ' . htmlspecialchars($addr->country) : '' ?>
                    </div>

                    <div class="pl-addr-actions">
                        <a href="address-edit.php?id=<?= $addr->id ?>" class="pl-action-btn pl-btn-edit">
                            ✏️ Edit
                        </a>
                        
                        <!-- SweetAlert2 Delete Trigger Button -->
                        <button type="button" 
                                class="pl-action-btn pl-btn-delete" 
                                onclick="confirmDeleteAddress('<?= $addr->id ?>', '<?= htmlspecialchars(addslashes($addr->recipient_name)) ?>')">
                            🗑️ Delete
                        </button>

                        <?php if (!$addr->is_default): ?>
                            <a href="address-set-default.php?id=<?= $addr->id ?>" class="pl-action-btn pl-btn-set-default">
                                Set as Default
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
function confirmDeleteAddress(addressId, recipientName) {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: 'Delete Address?',
            text: `Are you sure you want to remove the address for "${recipientName}"?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#c0392b',
            cancelButtonColor: '#968377',
            confirmButtonText: 'Yes, Delete',
            cancelButtonText: 'Cancel',
            reverseButtons: true,
            background: '#ffffff',
            customClass: {
                popup: 'pl-swal-popup'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `address-delete.php?id=${addressId}`;
            }
        });
    } else {
        if (confirm('Are you sure you want to delete this address?')) {
            window.location.href = `address-delete.php?id=${addressId}`;
        }
    }
}
</script>

<?php include '../_foot.php'; ?>