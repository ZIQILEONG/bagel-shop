<?php
include '../_base.php';
auth('Admin');

$roles = ['Admin' => 'Admin', 'Member' => 'Member'];
$id = req('id');
$u = null;

if ($id) {
    $stm = $_db->prepare("SELECT * FROM user WHERE id = ?");
    $stm->execute([$id]);
    $u = $stm->fetch();
    if (!$u) {
        temp('error', 'User not found.');
        redirect('user-listing.php');
    }
}

// ----------------------------------------------------------------------------
// Delete member
// ----------------------------------------------------------------------------
if ($u && is_post() && req('btn') == 'delete') {
    if ($u->id == $_user->id) {
        temp('info', 'You cannot delete your own account while logged in.');
        redirect('user-detail.php?id=' . $u->id);
    }
    $stm = $_db->prepare("UPDATE user SET is_deleted = 1 WHERE id = ?");
    $stm->execute([$u->id]);
    temp('info', 'Member deactivated.');
    redirect('user-listing.php');
}

// ----------------------------------------------------------------------------
// Batch Add Members
// ----------------------------------------------------------------------------
if (!$u && is_post() && req('btn') == 'batch') {
    $lines  = explode("\n", req('batch_data'));
    $count  = 0;
    $errors = [];
    foreach ($lines as $n => $line) {
        $line = trim($line);
        if ($line == '') {
            continue;
        }
        $cols = array_map('trim', explode(',', $line));
        if (count($cols) < 4) {
            $errors[] = 'Line ' . ($n + 1) . ': expected name,email,password,role';
            continue;
        }
        [$bName, $bEmail, $bPassword, $bRole] = $cols;
        if ($bName == '' || !is_email($bEmail) || !is_unique($bEmail, 'user', 'email') ||
            $bPassword == '' || !array_key_exists($bRole, $roles)) {
            $errors[] = 'Line ' . ($n + 1) . ': invalid or duplicate data';
            continue;
        }
        $hash = password_hash($bPassword, PASSWORD_DEFAULT);
        $stm = $_db->prepare("
            INSERT INTO user (name, email, password, role, phone_no, photo)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stm->execute([$bName, $bEmail, $hash, $bRole, '', 'default.jpg']);
        $count++;
    }
    temp('info', "$count member(s) imported." . ($errors ? ' Skipped - ' . implode('; ', $errors) : ''));
    redirect('user-listing.php');
}

// ----------------------------------------------------------------------------
// Create / update member
// ----------------------------------------------------------------------------
if (is_post() && req('btn') != 'delete' && req('btn') != 'batch') {
    $name             = req('name');
    $email            = req('email');
    $password         = req('password');
    $confirm_password = req('confirm_password');
    $role             = req('role');
    $phone_no         = req('phone_no');
    $photo            = $u->photo ?? 'default.jpg';

    if ($name == '') {
        $_err['name'] = 'Required';
    }

    if ($email == '') {
        $_err['email'] = 'Required';
    }
    else if (!is_email($email)) {
        $_err['email'] = 'Invalid email';
    }
    else if ((!$u || $email != $u->email) && is_exists($email, 'user', 'email')) {
        $_err['email'] = 'Already exists';
    }

    $phone_normalized = preg_replace('/[\s().-]/', '', $phone_no);
    if ($phone_no !== '' && !preg_match('/^(01\d{8,9}|\+601\d{8,9})$/', $phone_normalized)) {
        $_err['phone_no'] = 'Invalid phone number format';
    }

    if (!$u && $password == '') {
        $_err['password'] = 'Required';
    }

    if ($password != '') {
        if (strlen($password) < 8) {
            $_err['password'] = 'Password must be at least 8 characters';
        }
        else if (!preg_match('/[A-Z]/', $password)) {
            $_err['password'] = 'Password needs one uppercase letter';
        }
        else if (!preg_match('/[a-z]/', $password)) {
            $_err['password'] = 'Password needs one lowercase letter';
        }
        else if (!preg_match('/[0-9]/', $password)) {
            $_err['password'] = 'Password needs one number';
        }
        else if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $_err['password'] = 'Password needs one symbol';
        }

        if ($confirm_password == '') {
            $_err['confirm_password'] = 'Please retype the password';
        }
        else if ($password !== $confirm_password) {
            $_err['confirm_password'] = 'Passwords do not match';
        }
    }

    if ($role == '' || !array_key_exists($role, $roles)) {
        $_err['role'] = 'Required';
    }
    else if ($u && $u->id == $_user->id && $u->role == 'Admin' && $role != 'Admin') {
        $_err['role'] = 'You cannot remove Admin from your own account';
    }

    $f = get_file('photo');
    if ($f && !getimagesize($f->tmp_name)) {
        $_err['photo'] = 'Invalid image';
    }

    if (!$_err) {
        if ($f) {
            $dir = root('photos');
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $photo = save_photo($f, $dir);
        }

        if ($u) {
            if ($password != '') {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stm = $_db->prepare("
                    UPDATE user SET name = ?, email = ?, password = ?, role = ?, phone_no = ?, photo = ?
                    WHERE id = ?
                ");
                $stm->execute([$name, $email, $hash, $role, $phone_normalized, $photo, $u->id]);
            }
            else {
                $stm = $_db->prepare("
                    UPDATE user SET name = ?, email = ?, role = ?, phone_no = ?, photo = ?
                    WHERE id = ?
                ");
                $stm->execute([$name, $email, $role, $phone_normalized, $photo, $u->id]);
            }
            temp('info', 'Member updated.');
            redirect('user-detail.php?id=' . $u->id);
        }
        else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stm = $_db->prepare("
                INSERT INTO user (name, email, password, role, phone_no, photo)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stm->execute([$name, $email, $hash, $role, $phone_normalized, $photo]);
            $newId = $_db->lastInsertId();
            temp('info', 'Member created.');
            redirect('user-detail.php?id=' . $newId);
        }
    }
}

$name     = $name     ?? $u->name     ?? '';
$email    = $email    ?? $u->email    ?? '';
$role     = $role     ?? $u->role     ?? '';
$phone_no = $phone_no ?? $u->phone_no ?? '';

// ----------------------------------------------------------------------------
$_title = $u ? "Admin | Manage {$u->name}" : 'Admin | Add New Member';
include '../_head.php';
?>

<style>
/* =========================================================
   PULULU ADMIN MEMBER MANAGEMENT MODERN UI
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

.pl-admin-wrap {
    max-width: 1040px;
    margin: 28px auto 80px;
    padding: 0 20px;
    box-sizing: border-box;
}

/* Breadcrumb */
.pl-admin-breadcrumb {
    font-size: 13px;
    color: var(--pl-muted);
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.pl-admin-breadcrumb a {
    color: var(--pl-muted);
    text-decoration: none;
    transition: color 0.15s ease;
}
.pl-admin-breadcrumb a:hover {
    color: var(--pl-primary);
}

/* Page Header */
.pl-admin-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 16px;
}
.pl-admin-header h1 {
    font-size: 26px;
    font-weight: 800;
    color: var(--pl-brown-dark);
    margin: 0 0 6px;
}
.pl-admin-header p {
    font-size: 14px;
    color: var(--pl-muted);
    margin: 0;
}

/* Main Grid */
.pl-user-grid {
    display: grid;
    grid-template-columns: 300px 1fr;
    gap: 28px;
    align-items: start;
}

/* Card Panel */
.pl-panel-card {
    background: var(--pl-card-bg);
    border: 1px solid var(--pl-border);
    border-radius: 20px;
    padding: 26px;
    box-shadow: 0 4px 18px rgba(62, 38, 25, 0.03);
    margin-bottom: 24px;
}
.pl-panel-card h2 {
    font-size: 17px;
    font-weight: 800;
    color: var(--pl-brown-dark);
    margin: 0 0 18px;
    padding-bottom: 12px;
    border-bottom: 1px solid #f5ebe4;
    display: flex;
    align-items: center;
    gap: 8px;
}

/* Profile Photo Box (Left Column) */
.pl-avatar-section {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
}
.pl-avatar-wrapper {
    width: 140px;
    height: 140px;
    border-radius: 50%;
    border: 3px solid var(--pl-primary);
    padding: 4px;
    background: #fff;
    margin-bottom: 16px;
    position: relative;
}
.pl-avatar-preview {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
    background: #faf5f0;
}
.pl-avatar-upload-btn {
    display: inline-block;
    font-size: 12.5px;
    font-weight: 700;
    color: var(--pl-primary);
    background: var(--pl-accent);
    border: 1.5px solid var(--pl-border);
    padding: 8px 16px;
    border-radius: 999px;
    cursor: pointer;
    transition: all 0.2s ease;
}
.pl-avatar-upload-btn:hover {
    background: #f5ebe4;
    border-color: var(--pl-primary);
}
.pl-avatar-input {
    display: none;
}

/* Role & Status Pill */
.pl-user-meta-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    font-weight: 800;
    padding: 4px 12px;
    border-radius: 999px;
    margin-top: 10px;
    text-transform: uppercase;
}
.pl-user-meta-pill.admin { background: #eaf3ff; color: #1d68cd; }
.pl-user-meta-pill.member { background: #eaf6ed; color: #217d47; }
.pl-user-meta-pill.disabled { background: #fdf2f2; color: #c0392b; }

/* Form Fields */
.pl-form-row {
    margin-bottom: 18px;
}
.pl-form-row label {
    display: block;
    font-size: 13.5px;
    font-weight: 700;
    color: var(--pl-brown-dark);
    margin-bottom: 6px;
}
.pl-input-control {
    width: 100%;
    padding: 10px 14px;
    border: 1.5px solid var(--pl-border);
    border-radius: 10px;
    font-size: 14px;
    color: var(--pl-text);
    outline: none;
    box-sizing: border-box;
    transition: border-color 0.2s ease;
    background: #ffffff;
}
.pl-input-control:focus {
    border-color: var(--pl-primary);
}
.pl-select-control {
    width: 100%;
    padding: 10px 14px;
    border: 1.5px solid var(--pl-border);
    border-radius: 10px;
    font-size: 14px;
    font-weight: 600;
    color: var(--pl-brown-dark);
    background: #ffffff;
    outline: none;
    cursor: pointer;
    box-sizing: border-box;
}

/* Password Requirements Box */
.password-requirements {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
    background: var(--pl-accent);
    border: 1px solid var(--pl-border);
    padding: 12px 16px;
    border-radius: 12px;
    margin: 10px 0 16px;
}
.password-requirement {
    font-size: 12px;
    font-weight: 600;
    color: var(--pl-muted);
    display: flex;
    align-items: center;
    gap: 6px;
    transition: color 0.2s ease;
}
.password-requirement::before {
    content: "○";
    font-size: 14px;
    color: var(--pl-muted);
}
.password-requirement.valid {
    color: var(--pl-green);
}
.password-requirement.valid::before {
    content: "✓";
    color: var(--pl-green);
    font-weight: 800;
}

/* Button Group */
.pl-form-actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-top: 24px;
    padding-top: 18px;
    border-top: 1px solid #f5ebe4;
    flex-wrap: wrap;
}
.pl-btn-save {
    background: var(--pl-primary);
    color: #ffffff;
    border: none;
    padding: 12px 28px;
    border-radius: 12px;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s ease;
    box-shadow: 0 4px 14px rgba(207, 121, 83, 0.25);
}
.pl-btn-save:hover {
    background: var(--pl-primary-hover);
    transform: translateY(-1px);
}
.pl-btn-delete {
    background: transparent;
    color: var(--pl-red);
    border: 1.5px solid #f8cfcf;
    padding: 10px 18px;
    border-radius: 12px;
    font-size: 13.5px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s ease;
}
.pl-btn-delete:hover:not(:disabled) {
    background: #fdf2f2;
    border-color: var(--pl-red);
}
.pl-btn-delete:disabled {
    color: #c4b5ac;
    border-color: var(--pl-border);
    cursor: not-allowed;
}

.pl-btn-back-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: var(--pl-brown-dark);
    text-decoration: none;
    font-size: 13.5px;
    font-weight: 700;
}
.pl-btn-back-btn:hover {
    color: var(--pl-primary);
}

.pl-err-box {
    color: var(--pl-red);
    font-size: 12.5px;
    font-weight: 700;
    margin-top: 4px;
    display: block;
}

@media (max-width: 820px) {
    .pl-user-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="pl-admin-wrap">
    <!-- Breadcrumb -->
    <div class="pl-admin-breadcrumb">
        <a href="/">Home</a>
        <span>&rsaquo;</span>
        <a href="user-listing.php">User Management</a>
        <span>&rsaquo;</span>
        <span style="color: var(--pl-brown-dark); font-weight: 600;">
            <?= $u ? htmlspecialchars($u->name) : 'Add New Member' ?>
        </span>
    </div>

    <!-- Header -->
    <div class="pl-admin-header">
        <div>
            <h1><?= $u ? 'Edit User Profile' : 'Add New Member' ?></h1>
            <p><?= $u ? "Managing account details for User #{$u->id}" : 'Create an individual member or batch import multiple accounts.' ?></p>
        </div>

        <a href="user-listing.php" class="pl-btn-back-btn">&larr; Back to Member List</a>
    </div>

    <form method="post" enctype="multipart/form-data" id="memberForm">
        <div class="pl-user-grid">
            <!-- Left: Avatar & Meta Card -->
            <div class="pl-panel-card pl-avatar-section">
                <h2>👤 Photo &amp; Role</h2>
                
                <div class="pl-avatar-wrapper">
                    <img src="/photos/<?= htmlspecialchars($u->photo ?? 'default.jpg') ?>" 
                         class="pl-avatar-preview" 
                         id="avatarPreview"
                         onerror="this.src='/photos/default.jpg'">
                </div>

                <label class="pl-avatar-upload-btn" for="photo">
                    📷 Change Photo
                </label>
                <input type="file" name="photo" id="photo" class="pl-avatar-input" accept="image/*" onchange="previewImage(this)">
                <?= err('photo') ?>

                <?php if ($u): ?>
                    <div style="margin-top: 14px;">
                        <span class="pl-user-meta-pill <?= strtolower($u->role) ?>">
                            <?= htmlspecialchars($u->role) ?>
                        </span>
                        <?php if (!empty($u->is_deleted)): ?>
                            <span class="pl-user-meta-pill disabled">Deactivated</span>
                        <?php endif ?>
                    </div>
                <?php endif ?>
            </div>

            <!-- Right: Details Form -->
            <div class="pl-panel-card">
                <h2>📝 Account Information</h2>

                <?php if ($u): ?>
                    <div class="pl-form-row">
                        <label>User Reference ID</label>
                        <b style="font-size: 15px; color: var(--pl-primary);">#<?= $u->id ?></b>
                    </div>
                <?php endif ?>

                <div class="pl-form-row">
                    <label for="name">Full Name</label>
                    <input type="text" name="name" id="name" class="pl-input-control" value="<?= htmlspecialchars($name) ?>" maxlength="100" placeholder="e.g. Rachel Green">
                    <?= err('name') ?>
                </div>

                <div class="pl-form-row">
                    <label for="email">Email Address</label>
                    <input type="email" name="email" id="email" class="pl-input-control" value="<?= htmlspecialchars($email) ?>" maxlength="100" placeholder="e.g. rachel@example.com">
                    <?= err('email') ?>
                </div>

                <div class="pl-form-row">
                    <label for="phone_no">Contact Number</label>
                    <input type="text" name="phone_no" id="phone_no" class="pl-input-control" value="<?= htmlspecialchars($phone_no) ?>" maxlength="15" placeholder="e.g. 0123456789">
                    <?= err('phone_no') ?>
                </div>

                <div class="pl-form-row">
                    <label for="role">Assigned Role</label>
                    <?php if ($u && $u->id == $_user->id): ?>
                        <div style="font-weight: 700; color: var(--pl-brown-dark); padding: 8px 0;">
                            <?= $roles[$role] ?? $role ?> <span style="font-size: 12px; color: var(--pl-muted); font-weight: normal;">(You cannot remove Admin role from yourself)</span>
                        </div>
                        <input type="hidden" name="role" value="<?= htmlspecialchars($role) ?>">
                    <?php else: ?>
                        <select name="role" id="role" class="pl-select-control">
                            <?php foreach ($roles as $r_val => $r_lbl): ?>
                                <option value="<?= htmlspecialchars($r_val) ?>" <?= $role === $r_val ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($r_lbl) ?>
                                </option>
                            <?php endforeach ?>
                        </select>
                        <?= err('role') ?>
                    <?php endif ?>
                </div>

                <hr style="border: none; border-top: 1px solid #f5ebe4; margin: 24px 0 20px;">

                <!-- Password Setup -->
                <div class="pl-form-row">
                    <label for="password">Password <?= $u ? '<span style="font-weight: normal; color: var(--pl-muted);">(Leave blank to keep unchanged)</span>' : '' ?></label>
                    <input type="password" name="password" id="password" class="pl-input-control" maxlength="100" autocomplete="new-password" placeholder="••••••••">
                    <?= err('password') ?>
                </div>

                <div class="pl-form-row">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" name="confirm_password" id="confirm_password" class="pl-input-control" maxlength="100" autocomplete="new-password" placeholder="••••••••">
                    <?= err('confirm_password') ?>
                </div>

                <div class="password-requirements" id="passwordRequirements">
                    <div id="rule-length" class="password-requirement">At least 8 characters</div>
                    <div id="rule-uppercase" class="password-requirement">One uppercase letter</div>
                    <div id="rule-lowercase" class="password-requirement">One lowercase letter</div>
                    <div id="rule-number" class="password-requirement">One number</div>
                    <div id="rule-symbol" class="password-requirement" style="grid-column: span 2;">One symbol (!@#$%^&*)</div>
                </div>

                <div class="pl-form-actions">
                    <button type="submit" class="pl-btn-save">
                        <?= $u ? '💾 Save Changes' : '✨ Create Member' ?>
                    </button>

                    <?php if ($u): ?>
                        <?php if ($u->id == $_user->id): ?>
                            <button type="button" class="pl-btn-delete" disabled title="You cannot delete your own account">
                                🔒 Self Account
                            </button>
                        <?php else: ?>
                            <button type="button" class="pl-btn-delete" onclick="confirmDeleteUser('<?= $u->id ?>', '<?= addslashes(htmlspecialchars($u->name)) ?>')">
                                🗑️ Deactivate Member
                            </button>
                        <?php endif ?>
                    <?php endif ?>
                </div>
            </div>
        </div>
    </form>

    <!-- Batch Add Option for New Members -->
    <?php if (!$u): ?>
        <div class="pl-panel-card" style="margin-top: 10px;">
            <h2>⚡ Quick Batch Import</h2>
            <p style="font-size: 13.5px; color: var(--pl-muted); margin: 0 0 14px;">
                Enter one member per line formatted as: <code>Name, Email, Password, Role</code>
            </p>
            <form method="post">
                <textarea name="batch_data" class="pl-input-control" rows="5" placeholder="Alice Tan, alice@example.com, Pass@1234, Member&#10;Bob Lee, bob@example.com, Admin#9999, Admin"></textarea>
                <div style="margin-top: 14px;">
                    <button type="submit" name="btn" value="batch" class="pl-btn-save" style="background: var(--pl-brown-dark);">
                        📥 Import Members
                    </button>
                </div>
            </form>
        </div>
    <?php endif ?>
</div>

<script>
// Image live preview handler
function previewImage(input) {
    if (input.files && input.files[0]) {
        let reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('avatarPreview').src = e.target.result;
        }
        reader.readAsDataURL(input.files[0]);
    }
}

// Live password strength validator
(function () {
    const passwordInput = document.getElementById('password');
    const confirmInput  = document.getElementById('confirm_password');
    if (!passwordInput) return;

    const rules = {
        'rule-length':    p => p.length >= 8,
        'rule-uppercase': p => /[A-Z]/.test(p),
        'rule-lowercase': p => /[a-z]/.test(p),
        'rule-number':    p => /[0-9]/.test(p),
        'rule-symbol':    p => /[^A-Za-z0-9]/.test(p)
    };

    passwordInput.addEventListener('input', function () {
        const value = passwordInput.value;
        for (const [id, check] of Object.entries(rules)) {
            document.getElementById(id)?.classList.toggle('valid', check(value));
        }
    });

    document.getElementById('memberForm')?.addEventListener('submit', function (e) {
        if (passwordInput.value !== '' && passwordInput.value !== confirmInput.value) {
            e.preventDefault();
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Password Mismatch',
                    text: 'Password and Retype Password do not match.',
                    confirmButtonColor: '#cf7953'
                });
            } else {
                alert('Password and Retype Password do not match.');
            }
            confirmInput.focus();
        }
    });
})();

// Deactivation confirmation modal
function confirmDeleteUser(userId, userName) {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: `Deactivate ${userName}?`,
            text: "This user will no longer be able to log in or make purchases.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#c0392b',
            cancelButtonColor: '#968377',
            confirmButtonText: 'Yes, Deactivate',
            cancelButtonText: 'Cancel',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                let form = document.createElement('form');
                form.method = 'POST';
                form.action = `user-detail.php?id=${userId}`;
                
                let hiddenBtn = document.createElement('input');
                hiddenBtn.type = 'hidden';
                hiddenBtn.name = 'btn';
                hiddenBtn.value = 'delete';
                
                form.appendChild(hiddenBtn);
                document.body.appendChild(form);
                form.submit();
            }
        });
    } else {
        if (confirm(`Deactivate member '${userName}'? This cannot be undone.`)) {
            let form = document.createElement('form');
            form.method = 'POST';
            form.action = `user-detail.php?id=${userId}`;
            
            let hiddenBtn = document.createElement('input');
            hiddenBtn.type = 'hidden';
            hiddenBtn.name = 'btn';
            hiddenBtn.value = 'delete';
            
            form.appendChild(hiddenBtn);
            document.body.appendChild(form);
            form.submit();
        }
    }
}
</script>

<?php
include '../_foot.php';
?>