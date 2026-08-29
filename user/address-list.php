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

<style>
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
    --pl-green-bg: #eaf5ee;
    --pl-red: #c0392b;
}

body {
    background-color: #faf5f0;
    color: var(--pl-text);
}

.pl-addr-wrapper {
    max-width: 820px;
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

/* Header & Add Button */
.pl-addr-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 26px;
    flex-wrap: wrap;
    gap: 16px;
}
.pl-addr-head h1 {
    font-size: 26px;
    font-weight: 800;
    color: var(--pl-brown-dark);
    margin: 0 0 4px;
}
.pl-addr-head p {
    font-size: 14px;
    color: var(--pl-muted);
    margin: 0;
}

.pl-btn-add-addr {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: var(--pl-primary);
    color: #ffffff;
    padding: 10px 20px;
    border-radius: 12px;
    font-size: 13.5px;
    font-weight: 700;
    text-decoration: none;
    box-shadow: 0 4px 14px rgba(207, 121, 83, 0.25);
    transition: all 0.2s ease;
}
.pl-btn-add-addr:hover {
    background: var(--pl-primary-hover);
    transform: translateY(-1px);
    box-shadow: 0 6px 18px rgba(207, 121, 83, 0.35);
}

/* Address Cards Grid */
.pl-addr-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.pl-addr-card {
    background: var(--pl-card-bg);
    border: 1.5px solid var(--pl-border);
    border-radius: 18px;
    padding: 24px 28px;
    box-shadow: 0 4px 18px rgba(62, 38, 25, 0.03);
    position: relative;
    transition: all 0.2s ease;
}
.pl-addr-card.is-default {
    border-color: var(--pl-primary);
    background: #fffdfb;
}

.pl-addr-card-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 12px;
}

.pl-recipient-wrap {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}
.pl-recipient-name {
    font-size: 16px;
    font-weight: 800;
    color: var(--pl-brown-dark);
}
.pl-recipient-phone {
    font-size: 13.5px;
    color: var(--pl-muted);
    font-weight: 600;
}

.pl-badge-default {
    background: var(--pl-green-bg);
    color: var(--pl-green);
    border: 1px solid #c9e8d4;
    font-size: 11px;
    font-weight: 700;
    padding: 3px 10px;
    border-radius: 999px;
    letter-spacing: 0.03em;
    text-transform: uppercase;
}

.pl-addr-lines {
    font-size: 14px;
    line-height: 1.6;
    color: var(--pl-text);
    margin-bottom: 20px;
    max-width: 620px;
}

/* Action Buttons Footer */
.pl-addr-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    padding-top: 14px;
    border-top: 1px solid #f5ebe4;
    flex-wrap: wrap;
}

.pl-action-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    border-radius: 8px;
    font-size: 12.5px;
    font-weight: 700;
    text-decoration: none;
    transition: all 0.15s ease;
    border: none;
    cursor: pointer;
    background: transparent;
}

.pl-btn-edit {
    background: var(--pl-accent);
    color: var(--pl-brown-dark);
    border: 1px solid var(--pl-border);
}
.pl-btn-edit:hover {
    background: #f1e4dc;
    border-color: #d8c2b5;
}

.pl-btn-delete {
    background: #fff;
    color: var(--pl-red);
    border: 1px solid #f5d6d6;
}
.pl-btn-delete:hover {
    background: #fdf2f2;
    border-color: var(--pl-red);
}

.pl-btn-set-default {
    background: #fff;
    color: var(--pl-primary);
    border: 1px solid var(--pl-border);
    margin-left: auto;
}
.pl-btn-set-default:hover {
    background: #fff8f5;
    border-color: var(--pl-primary);
}

/* Empty State */
.pl-addr-empty {
    background: #ffffff;
    border: 1px solid var(--pl-border);
    border-radius: 20px;
    padding: 70px 24px;
    text-align: center;
    box-shadow: 0 4px 20px rgba(62, 38, 25, 0.03);
}
.pl-addr-empty-icon {
    font-size: 54px;
    margin-bottom: 12px;
}
.pl-addr-empty h2 {
    font-size: 20px;
    font-weight: 800;
    color: var(--pl-brown-dark);
    margin: 0 0 6px;
}
.pl-addr-empty p {
    font-size: 14px;
    color: var(--pl-muted);
    margin: 0 0 22px;
}

@media (max-width: 600px) {
    .pl-addr-card {
        padding: 18px 20px;
    }
    .pl-btn-set-default {
        margin-left: 0;
        width: 100%;
        justify-content: center;
    }
}
</style>

<div class="pl-addr-wrapper">
    <!-- Breadcrumb -->
    <div class="pl-breadcrumb">
        <a href="/">Home</a>
        <span>&rsaquo;</span>
        <a href="/user/profile.php">My Profile</a>
        <span>&rsaquo;</span>
        <span style="color: var(--pl-brown-dark); font-weight: 600;">Shipping Addresses</span>
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