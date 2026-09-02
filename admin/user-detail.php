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
    $confirm_password = req('confirm_password'); // NEW
    $role             = req('role');
    $phone_no         = req('phone_no');
    $photo            = $u->photo ?? 'default.jpg';
    // Name
    if ($name == '') {
        $_err['name'] = 'Required';
    }
    // Email
    if ($email == '') {
        $_err['email'] = 'Required';
    }
    else if (!is_email($email)) {
        $_err['email'] = 'Invalid email';
    }
    else if ((!$u || $email != $u->email) && is_exists($email, 'user', 'email')) {
        $_err['email'] = 'Already exists';
    }
    // Phone (format check, matches register.php's Malaysian format;
    // optional field so only validated when filled in)
    $phone_normalized = preg_replace('/[\s().-]/', '', $phone_no);
    if ($phone_no !== '' && !preg_match('/^(01\d{8,9}|\+601\d{8,9})$/', $phone_normalized)) {
        $_err['phone_no'] = 'Invalid phone number format';
    }
    // Password rules
    if (!$u && $password == '') {
        // Required when creating a new member
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
    // Role
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
$_title = $u ? 'Member | Detail (Admin)' : 'Member | Create (Admin)';
include '../_head.php';
?>
<form method="post" enctype="multipart/form-data" class="form" id="memberForm">
    <?php if ($u): ?>
    <label>Id</label>
    <b><?= $u->id ?></b>
    <br>
    <?php endif ?>
    <label for="name">Name</label>
    <?= html_text('name', 'maxlength="100"') ?>
    <?= err('name') ?>
    <label for="email">Email</label>
    <?= html_text('email', 'maxlength="100"') ?>
    <?= err('email') ?>
    <label for="phone_no">Phone No</label>
    <?= html_text('phone_no', 'maxlength="15" placeholder="e.g. 0123456789"') ?>
    <?= err('phone_no') ?>
    <label for="password">Password <?= $u ? '(leave blank to keep unchanged)' : '' ?></label>
    <?= html_password('password', 'id="password" maxlength="100" autocomplete="new-password"') ?>
    <?= err('password') ?>

    <label for="confirm_password">Retype Password</label>
    <?= html_password('confirm_password', 'id="confirm_password" maxlength="100" autocomplete="new-password"') ?>
    <?= err('confirm_password') ?>
    <?php if (!$u || true): ?>
    <div class="password-requirements" id="passwordRequirements">
        <div id="rule-length" class="password-requirement"><span class="requirement-icon"></span><span>At least 8 characters</span></div>
        <div id="rule-uppercase" class="password-requirement"><span class="requirement-icon"></span><span>One uppercase letter</span></div>
        <div id="rule-lowercase" class="password-requirement"><span class="requirement-icon"></span><span>One lowercase letter</span></div>
        <div id="rule-number" class="password-requirement"><span class="requirement-icon"></span><span>One number</span></div>
        <div id="rule-symbol" class="password-requirement"><span class="requirement-icon"></span><span>One symbol</span></div>
    </div>
    <?php endif ?>
    <label for="role">Role</label>
    <?php if ($u && $u->id == $_user->id): ?>
        <b><?= $roles[$role] ?? $role ?></b> <span class="err">(you cannot change your own role)</span>
        <input type="hidden" name="role" value="<?= $role ?>">
    <?php else: ?>
        <?= html_select('role', $roles, null) ?>
        <?= err('role') ?>
    <?php endif ?>
    <label class="upload" for="photo">
        <img src="/photos/<?= $u->photo ?? 'default.jpg' ?>">
        <?= html_file('photo', 'image/*') ?>
    </label>
    <?= err('photo') ?>
    <section>
        <button>Save</button>
    </section>
</form>
<?php if (!$u): ?>
<form method="post" class="form">
    <label for="batch_data">Batch Add (one per line: name,email,password,role)</label>
    <?= html_textarea('batch_data', 'rows="6" placeholder="Alice Tan,alice@example.com,pass123,Member&#10;Bob Lee,bob@example.com,pass456,Admin"') ?>
    <section>
        <button name="btn" value="batch">Import</button>
    </section>
</form>
<?php endif ?>
<?php if ($u): ?>
<p>
    <?php if ($u->id == $_user->id): ?>
        
        <button type="button" disabled title="You cannot delete your own account">Delete Member</button>
    <?php else: ?>
        <button data-post="user-detail.php?id=<?= $u->id ?>&btn=delete" data-confirm="Delete member '<?= encode($u->name) ?>'? This cannot be undone.">Delete Member</button>
    <?php endif ?>
</p>
<?php endif ?>
<p>
    <button data-get="user-listing.php">Back to Listing</button>
</p>
<script>

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
            alert('Password and Retype Password do not match.');
            confirmInput.focus();
        }
    });
})();
</script>
<?php
include '../_foot.php';