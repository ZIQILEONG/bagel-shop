<?php

include '../_base.php';

$token = trim($_GET['token'] ?? '');

if ($token === '') {
    temp('error', 'Invalid verification link.');
    redirect('../login.php');
}

$token_hash = hash('sha256', $token);

$stmt = $_db->prepare("
    SELECT id
    FROM user
    WHERE verification_token = ?
      AND verification_expires > NOW()
      AND email_verified = 0
      AND is_deleted = 0
");

$stmt->execute([$token_hash]);
$user = $stmt->fetch();

if (!$user) {
    temp('error', 'Verification link is invalid or expired.');
    redirect('../login.php');
}

$stmt = $_db->prepare("
    UPDATE user
    SET email_verified = 1,
        verification_token = NULL,
        verification_expires = NULL
    WHERE id = ?
");

$stmt->execute([$user->id]);

temp('info', 'Email verified successfully. You can now login.');
redirect('../login.php');
