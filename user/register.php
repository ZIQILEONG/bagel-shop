<?php

include '../_base.php';
include '../config.php';

$name = '';
$email = '';
$phone_no = '';
$phone_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone_no = trim($_POST['phone_no'] ?? '');

    $password_input = $_POST['password'] ?? '';
    $reenter_password_input =
        $_POST['reenter_password'] ?? '';

    $turnstile_token =
        $_POST['cf-turnstile-response'] ?? '';


    // Name validation
    if ($name === '') {
        $_err['name'] = 'Name is required';
    }


    // Email validation
    if ($email === '') {
        $_err['email'] = 'Email is required';
    }
    else if (!is_email($email)) {
        $_err['email'] = 'Invalid email';
    }


    // Phone validation
    $phone_normalized = preg_replace(
        '/[\s().-]/',
        '',
        $phone_no
    );

    if ($phone_no === '') {
        $phone_error = 'Phone number is required';
    }
    else if (
        !preg_match(
            '/^(01\d{8,9}|\+601\d{8,9})$/',
            $phone_normalized
        )
    ) {
        $phone_error =
            'Please enter a valid Malaysian phone number';
    }


    // Password validation
    if ($password_input === '') {
        $_err['password'] =
            'Password is required';
    }
    else if (strlen($password_input) < 8) {
        $_err['password'] =
            'Password must be at least 8 characters';
    }
    else if (!preg_match('/[A-Z]/', $password_input)) {
        $_err['password'] =
            'Password needs one uppercase letter';
    }
    else if (!preg_match('/[a-z]/', $password_input)) {
        $_err['password'] =
            'Password needs one lowercase letter';
    }
    else if (!preg_match('/[0-9]/', $password_input)) {
        $_err['password'] =
            'Password needs one number';
    }
    else if (
        !preg_match(
            '/[^A-Za-z0-9]/',
            $password_input
        )
    ) {
        $_err['password'] =
            'Password needs one symbol';
    }


    // Confirm password
    if ($reenter_password_input === '') {
        $_err['reenter_password'] =
            'Please re-enter your password';
    }
    else if (
        $password_input !== $reenter_password_input
    ) {
        $_err['reenter_password'] =
            'Passwords do not match';
    }


    // Duplicate email
    if (
        $phone_error === '' &&
        !$_err &&
        is_exists($email, 'user', 'email')
    ) {
        $_err['email'] =
            'Email already exists';
    }


    // Turnstile
    if (
        $phone_error === '' &&
        !$_err &&
        !verify_turnstile(
            $turnstile_token,
            'register'
        )
    ) {
        $_err['captcha'] =
            'Please complete the Turnstile verification.';
    }


    // Continue to puzzle verification
    if (
        $phone_error === '' &&
        !$_err
    ) {
        begin_pending_auth('register', [

            'name' => $name,

            'email' => $email,

            'phone_no' => $phone_normalized,

            'password_hash' => password_hash(
                $password_input,
                PASSWORD_DEFAULT
            ),

        ]);

        redirect('../captcha/puzzle.php');
    }
}

$_title = 'Create Account | Pululu Bagel';
$_body_class = 'pululu-auth-page';

include '../_head.php';

?>

<?php if ($phone_error !== ''): ?>

<script>
alert(
    <?= json_encode($phone_error) ?>
);
</script>

<?php endif; ?>


<div class="register-container">

    <span class="section-eyebrow">
        Join Pululu
    </span>

    <h2>
        Create account
    </h2>

    <p class="register-subtitle">
        Save your details, earn reward points and make
        future orders faster.
    </p>


    <form method="post" class="register-form">

        <div class="input-group">

            <?= html_text(
                'name',
                'maxlength="50"
                 autocomplete="name"
                 required
                 placeholder="Name"'
            ) ?>

            <label for="name">
                Name
            </label>

            <?= err('name') ?>

        </div>


        <div class="input-group">

            <input
                type="email"
                id="email"
                name="email"
                value="<?= htmlspecialchars(
                    $email,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
                maxlength="100"
                autocomplete="email"
                required
                placeholder="E-mail"
            >

            <label for="email">
                E-mail
            </label>

            <?= err('email') ?>

        </div>


        <div class="input-group">

            <input
                type="tel"
                id="phone_no"
                name="phone_no"
                value="<?= htmlspecialchars(
                    $phone_no,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
                maxlength="16"
                inputmode="tel"
                autocomplete="tel"
                required
                placeholder="Phone number"
            >

            <label for="phone_no">
                Phone number
            </label>

        </div>


        <!-- Password field -->

        <div class="input-group">

            <div class="password-input">

                <?= html_password(
                    'password',
                    'minlength="8"
                     maxlength="100"
                     required
                     autocomplete="new-password"
                     placeholder="Password"'
                ) ?>

                <label for="password">
                    Password
                </label>

                <button
                    type="button"
                    class="toggle-password"
                    onclick="togglePassword(
                        'password',
                        this
                    )"
                >
                    👁
                </button>

            </div>

            <?= err('password') ?>


            <div
                class="password-requirements"
                id="passwordRequirements"
                aria-live="polite"
            >

                <div
                    id="rule-length"
                    class="password-requirement"
                >
                    <span class="requirement-icon"></span>
                    <span>
                        At least 8 characters
                    </span>
                </div>

                <div
                    id="rule-uppercase"
                    class="password-requirement"
                >
                    <span class="requirement-icon"></span>
                    <span>
                        One uppercase letter
                    </span>
                </div>

                <div
                    id="rule-lowercase"
                    class="password-requirement"
                >
                    <span class="requirement-icon"></span>
                    <span>
                        One lowercase letter
                    </span>
                </div>

                <div
                    id="rule-number"
                    class="password-requirement"
                >
                    <span class="requirement-icon"></span>
                    <span>
                        One number
                    </span>
                </div>

                <div
                    id="rule-symbol"
                    class="password-requirement"
                >
                    <span class="requirement-icon"></span>
                    <span>
                        One symbol
                    </span>
                </div>

            </div>

        </div>


        <!-- Confirm password field -->

        <div class="input-group">

            <div class="password-input">

                <?= html_password(
                    'reenter_password',
                    'minlength="8"
                     maxlength="100"
                     required
                     autocomplete="new-password"
                     placeholder="Re-enter Password"'
                ) ?>

                <label for="reenter_password">
                    Re-enter Password
                </label>

                <button
                    type="button"
                    class="toggle-password"
                    onclick="togglePassword(
                        'reenter_password',
                        this
                    )"
                >
                    👁
                </button>

            </div>

            <?= err('reenter_password') ?>

        </div>


        <?php

        $captcha_action = 'register';
        $captcha_web_path = '../captcha';

        include '../captcha/widget.php';

        ?>


        <button type="submit">
            Create account
        </button>

    </form>


    <p class="login-text">

        Already have an account?

        <a href="../login.php">
            Log in
        </a>

    </p>

</div>


<script>

function togglePassword(inputId, button) {

    const input =
        document.getElementById(inputId);

    if (input.type === 'password') {

        input.type = 'text';

        button.textContent = '🙈';
    }
    else {

        input.type = 'password';

        button.textContent = '👁';
    }
}


const passwordInput =
    document.getElementById('password');

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


if (passwordInput) {

    passwordInput.addEventListener(
        'input',
        function () {

            const password =
                passwordInput.value;

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
}

</script>

<?php include '../_foot.php'; ?>