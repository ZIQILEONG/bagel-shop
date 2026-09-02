<?php
include '../_base.php';
include '../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$token = trim($_GET['token'] ?? '');
$success = ($_GET['success'] ?? '') === '1';
$invalidToken = false;
$error = '';
$resetUserId = null;

if (empty($_SESSION['reset_password_csrf'])) {
    $_SESSION['reset_password_csrf'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['reset_password_csrf'];

if (!$success) {
    if ($token === '') {
        $invalidToken = true;
    }
    else {
        $stm = $_db->prepare("
            SELECT user_id
            FROM token
            WHERE token = ?
            AND expire > NOW()
            LIMIT 1
        ");
        $stm->execute([$token]);
        $reset = $stm->fetch();

        if (!$reset) {
            $invalidToken = true;
        }
        else {
            $resetUserId = is_object($reset)
                ? $reset->user_id
                : $reset['user_id'];
        }
    }
}

if (
    !$success &&
    !$invalidToken &&
    $_SERVER['REQUEST_METHOD'] === 'POST'
) {
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $turnstileToken = $_POST['cf-turnstile-response'] ?? '';
    $submittedCsrf = $_POST['csrf_token'] ?? '';

    if (!hash_equals(
        (string) $_SESSION['reset_password_csrf'],
        (string) $submittedCsrf
    )) {
        $error = 'Invalid form request. Please refresh and try again.';
    }
    else if ($password === '') {
        $error = 'Password is required.';
    }
    else if (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    }
    else if (!preg_match('/[A-Z]/', $password)) {
        $error = 'Password needs one uppercase letter.';
    }
    else if (!preg_match('/[a-z]/', $password)) {
        $error = 'Password needs one lowercase letter.';
    }
    else if (!preg_match('/[0-9]/', $password)) {
        $error = 'Password needs one number.';
    }
    else if (!preg_match('/[^A-Za-z0-9\s]/', $password)) {
        $error = 'Password needs one symbol.';
    }
    else if ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    }
    else if (!verify_turnstile($turnstileToken, 'reset')) {
        $error = 'Please complete the Turnstile verification.';
    }
    else {
        try {
            $_db->beginTransaction();

            $hash = password_hash($password, PASSWORD_DEFAULT);

            $stm = $_db->prepare("
                UPDATE user
                SET password = ?
                WHERE id = ?
            ");
            $stm->execute([$hash, $resetUserId]);

            $stm = $_db->prepare("
                DELETE FROM token
                WHERE token = ?
            ");
            $stm->execute([$token]);

            $_db->commit();

            unset($_SESSION['reset_password_csrf']);

            header('Location: reset.php?success=1');
            exit;
        }
        catch (Throwable $e) {
            if ($_db->inTransaction()) {
                $_db->rollBack();
            }

            $error = 'Unable to reset your password. Please try again.';
        }
    }
}

$_title = 'Reset Password | Pululu Bagel';
$_body_class = 'pululu-auth-page';

include '../_head.php';
?>

<div class="register-container">
    <?php if ($success): ?>
        <span class="section-eyebrow">PASSWORD UPDATED</span>
        <h2>Reset successful</h2>
        <p class="register-subtitle">
            Your password has been changed successfully.
            You can now log in using your new password.
        </p>
        <a href="login.php" class="button">Continue to Log In</a>
    <?php elseif ($invalidToken): ?>
        <span class="section-eyebrow">LINK UNAVAILABLE</span>
        <h2>Reset link expired</h2>
        <p class="register-subtitle">
            This password reset link is invalid, expired,
            or has already been used.
        </p>
        <a href="forgot_password.php" class="button">
            Request New Link
        </a>
        <p class="login-text">
            <a href="login.php">Back to Log In</a>
        </p>
    <?php else: ?>
        <span class="section-eyebrow">ACCOUNT RECOVERY</span>
        <h2>Create new password</h2>
        <p class="register-subtitle">
            Create a strong new password for your Pululu account.
        </p>

        <?php if ($error !== ''): ?>
            <div class="err">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif ?>

        <form
            method="post"
            action="reset.php?token=<?= htmlspecialchars(
                rawurlencode($token),
                ENT_QUOTES,
                'UTF-8'
            ) ?>"
            class="register-form"
        >
            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(
                    $csrfToken,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
            >

            <div class="input-group">
                <div class="password-input">
                    <input
                        type="password"
                        id="password"
                        name="password"
                        minlength="8"
                        maxlength="100"
                        required
                        autocomplete="new-password"
                        placeholder="Password"
                    >

                    <label for="password">Password</label>

                    <button
                        type="button"
                        class="toggle-password"
                        onclick="togglePassword('password', this)"
                    >
                        👁
                    </button>
                </div>

                <div
                    class="password-requirements"
                    id="passwordRequirements"
                >
                    <div
                        id="rule-length"
                        class="password-requirement"
                    >
                        <span class="requirement-icon"></span>
                        <span>At least 8 characters</span>
                    </div>

                    <div
                        id="rule-uppercase"
                        class="password-requirement"
                    >
                        <span class="requirement-icon"></span>
                        <span>One uppercase letter</span>
                    </div>

                    <div
                        id="rule-lowercase"
                        class="password-requirement"
                    >
                        <span class="requirement-icon"></span>
                        <span>One lowercase letter</span>
                    </div>

                    <div
                        id="rule-number"
                        class="password-requirement"
                    >
                        <span class="requirement-icon"></span>
                        <span>One number</span>
                    </div>

                    <div
                        id="rule-symbol"
                        class="password-requirement"
                    >
                        <span class="requirement-icon"></span>
                        <span>One symbol</span>
                    </div>
                </div>
            </div>

            <div class="input-group">
                <div class="password-input">
                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        required
                        autocomplete="new-password"
                        placeholder="Confirm new password"
                    >

                    <label for="confirm_password">
                        Confirm Password
                    </label>

                    <button
                        type="button"
                        class="toggle-password"
                        onclick="togglePassword(
                            'confirm_password',
                            this
                        )"
                    >
                        👁
                    </button>
                </div>
            </div>

            <?php
            $captcha_action = 'reset';
            $captcha_web_path = '../captcha';
            include '../captcha/widget.php';
            ?>

            <button type="submit">
                Reset Password
            </button>
        </form>

        <p class="login-text">
            <a href="login.php">Back to Log In</a>
        </p>
    <?php endif ?>
</div>

<script>
function togglePassword(inputId, button) {
    const input = document.getElementById(inputId);

    if (input.type === 'password') {
        input.type = 'text';
        button.textContent = '🙈';
    }
    else {
        input.type = 'password';
        button.textContent = '👁';
    }
}

(function () {
    const passwordInput =
        document.getElementById('password');

    if (!passwordInput) {
        return;
    }

    const passwordRules = {
        'rule-length': password =>
            password.length >= 8,

        'rule-uppercase': password =>
            /[A-Z]/.test(password),

        'rule-lowercase': password =>
            /[a-z]/.test(password),

        'rule-number': password =>
            /[0-9]/.test(password),

        'rule-symbol': password =>
            /[^A-Za-z0-9]/.test(password)
    };

    passwordInput.addEventListener(
        'input',
        function () {
            const password = passwordInput.value;

            for (
                const [ruleId, checkRule]
                of Object.entries(passwordRules)
            ) {
                const rule =
                    document.getElementById(ruleId);

                const valid =
                    checkRule(password);

                rule.classList.toggle(
                    'valid',
                    valid
                );
            }
        }
    );
})();
</script>

<?php include '../_foot.php'; ?>