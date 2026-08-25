<?php

if (!isset($captcha_action)) {
    throw new RuntimeException('Set $captcha_action before including captcha/widget.php.');
}

$captcha_web_path ??= 'captcha';
$captcha_site_key = encode(TURNSTILE_SITE_KEY);
$captcha_action_html = encode($captcha_action);
$captcha_path_html = encode(rtrim($captcha_web_path, '/'));
?>

<link rel="stylesheet" href="<?= $captcha_path_html ?>/captcha-flow.css">

<div id="turnstile-only" class="turnstile-only">
    <div class="cf-turnstile"
         data-sitekey="<?= $captcha_site_key ?>"
         data-action="<?= $captcha_action_html ?>"
         data-appearance="always"
         data-size="normal"
         data-callback="formTurnstileSuccess"
         data-expired-callback="formTurnstileExpired"
         data-error-callback="formTurnstileError">
    </div>

    <?= err('captcha') ?>
</div>

<script>
(function () {
    const box = document.getElementById('turnstile-only');
    const form = box ? box.closest('form') : null;
    let verified = false;

    function updateButton() {
        const button = form ? form.querySelector('button[type="submit"]') : null;

        if (button) {
            button.disabled = !verified;
        }
    }

    window.formTurnstileSuccess = function () {
        verified = true;
        updateButton();
    };

    window.formTurnstileExpired = function () {
        verified = false;
        updateButton();
    };

    window.formTurnstileError = function () {
        verified = false;
        updateButton();

        return true;
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', updateButton);
    }
    else {
        updateButton();
    }
})();
</script>

<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
