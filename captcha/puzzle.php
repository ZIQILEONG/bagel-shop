<?php

include '../_base.php';
include '../config.php';

$pending = get_pending_auth();

if (!$pending) {
    redirect(app_url('login.php'));
}

$action = $pending['action'];
$nonce = new_captcha_nonce($action);
$back_url = app_url('login.php');

if ($action === 'register') {
    $back_url = app_url('user/register.php');
}
else if ($action === 'remember') {
    $back_url = app_url('user/remember_verify.php');
}

$_title = 'Security Verification';
include '../_head.php';
?>

<link rel="stylesheet" href="captcha-flow.css">

<div class="puzzle-overlay">
    <div class="puzzle-dialog">
        <a class="puzzle-close"
           href="<?= encode($back_url) ?>"
           aria-label="Close">&times;</a>

        <h2>Security Verification</h2>

        <p>Drag the slider to fit the puzzle piece.</p>

        <div id="puzzle-check"
             data-action="<?= encode($action) ?>"
             data-nonce="<?= encode($nonce) ?>"
             data-endpoint="slider_verify.php"
             data-asset-base=".">

            <div id="slider-captcha"></div>

            <p id="puzzle-message" class="puzzle-message">
                Slide to complete the puzzle.
            </p>
        </div>
    </div>
</div>

<script src="vendor/longbow.slidercaptcha.min.js"></script>
<script src="captcha-flow.js"></script>

<?php include '../_foot.php'; ?>
