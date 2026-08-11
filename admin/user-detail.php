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
if ($u && is_post() && req('btn') == 'delete') {
    $stm = $_db->prepare("DELETE FROM user WHERE id = ?");
    $ok  = $stm->execute([$u->id]);
    if ($ok) {
        temp('info', 'Member deleted.');
        redirect('user-listing.php');
    }
    temp('info', 'Cannot delete this member: existing orders or tokens reference this account.');
    redirect('user-detail.php?id=' . $u->id);
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
if (is_post() && req('btn') != 'delete' && req('btn') != 'batch') {
    $name     = req('name');
    $email    = req('email');
    $password = req('password');
    $role     = req('role');
    $phone_no = req('phone_no');
    $photo    = $u->photo ?? 'default.jpg';
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
    if (!$u && $password == '') {
        $_err['password'] = 'Required';
    }
    if ($role == '' || !array_key_exists($role, $roles)) {
        $_err['role'] = 'Required';
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
                $stm->execute([$name, $email, $hash, $role, $phone_no, $photo, $u->id]);
            }
            else {
                $stm = $_db->prepare("
                    UPDATE user SET name = ?, email = ?, role = ?, phone_no = ?, photo = ?
                    WHERE id = ?
                ");
                $stm->execute([$name, $email, $role, $phone_no, $photo, $u->id]);
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
            $stm->execute([$name, $email, $hash, $role, $phone_no, $photo]);
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
<form method="post" enctype="multipart/form-data" class="form">
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
    <label for="password">Password <?= $u ? '(leave blank to keep unchanged)' : '' ?></label>
    <?= html_password('password', 'maxlength="100"') ?>
    <?= err('password') ?>
    <label for="role">Role</label>
    <?= html_select('role', $roles, null) ?>
    <?= err('role') ?>
    <label for="phone_no">Phone No</label>
    <?= html_text('phone_no', 'maxlength="15"') ?>
    <?= err('phone_no') ?>
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
    <button data-post="user-detail.php?id=<?= $u->id ?>&btn=delete" data-confirm>Delete Member</button>
</p>
<?php endif ?>
<p>
    <button data-get="user-listing.php">Back to Listing</button>
</p>
<?php
include '../_foot.php';