<?php

// ============================================================================
// PHP Setups
// ============================================================================

date_default_timezone_set('Asia/Kuala_Lumpur');
session_start();

// ============================================================================
// General Page Functions
//Test Change to push --Chai
// ============================================================================

// Is GET request?
function is_get() {
    return $_SERVER['REQUEST_METHOD'] == 'GET';
}

// Is POST request?
function is_post() {
    return $_SERVER['REQUEST_METHOD'] == 'POST';
}

// Obtain GET parameter
function get($key, $value = null) {
    $value = $_GET[$key] ?? $value;
    return is_array($value) ? array_map('trim', $value) : trim($value);
}

// Obtain POST parameter
function post($key, $value = null) {
    $value = $_POST[$key] ?? $value;
    return is_array($value) ? array_map('trim', $value) : trim($value);
}

// Obtain REQUEST (GET and POST) parameter
function req($key, $value = null) {
    $value = $_REQUEST[$key] ?? $value;
    return is_array($value) ? array_map('trim', $value) : trim($value);
}

// Redirect to URL
function redirect($url = null) {
    $url ??= $_SERVER['REQUEST_URI'];
    header("Location: $url");
    exit();
}

// Set or get temporary session variable
function temp($key, $value = null) {
    if ($value !== null) {
        $_SESSION["temp_$key"] = $value;
    }
    else {
        $value = $_SESSION["temp_$key"] ?? null;
        unset($_SESSION["temp_$key"]);
        return $value;
    }
}

// Obtain uploaded file --> cast to object
function get_file($key) {
    $f = $_FILES[$key] ?? null;
    
    if ($f && $f['error'] == 0) {
        return (object)$f;
    }

    return null;
}

// Crop, resize and save photo
function save_photo($f, $folder, $width = 200, $height = 200) {
    $photo = uniqid() . '.jpg';
    
    require_once 'lib/SimpleImage.php';
    $img = new SimpleImage();
    $img->fromFile($f->tmp_name)
        ->thumbnail($width, $height)
        ->toFile("$folder/$photo", 'image/jpeg');

    return $photo;
}

// Save multiple photos for a product
function save_product_photos($files, $product_id, $folder) {
    $saved_photos = [];
    require_once 'lib/SimpleImage.php';

    foreach ($files as $file) {
        if ($file['error'] == 0) {
            $photo = uniqid() . '.jpg';
            $img = new SimpleImage();
            $img->fromFile($file['tmp_name'])
                ->thumbnail(800, 800)
                ->toFile("$folder/$photo", 'image/jpeg');

            // Save to database
            global $_db;
            $stmt = $_db->prepare("INSERT INTO product_photo (product_id, photo) VALUES (?, ?)");
            $stmt->execute([$product_id, $photo]);
            $saved_photos[] = $photo;
        }
    }

    return $saved_photos;
}

// Process image with transformations (flip, rotate, etc.)
function process_image($source, $target, $operations = []) {
    require_once 'lib/SimpleImage.php';
    $img = new SimpleImage();
    $img->fromFile($source);

    foreach ($operations as $op) {
        switch ($op['type']) {
            case 'flip':
                $img->flip($op['direction'] ?? 'x');
                break;
            case 'rotate':
                $img->rotate($op['angle'] ?? 90);
                break;
            case 'resize':
                $img->resize($op['width'] ?? 200, $op['height'] ?? 200);
                break;
            case 'crop':
                $img->crop($op['x1'], $op['y1'], $op['x2'], $op['y2']);
                break;
        }
    }

    $img->toFile($target, 'image/jpeg');
    return true;
}

// Is money?
function is_money($value) {
    return preg_match('/^\-?\d+(\.\d{1,2})?$/', $value);
}

// Is email?
function is_email($value) {
    return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
}

// Return local root path
function root($path = '') {
    return "$_SERVER[DOCUMENT_ROOT]/$path";
}
// Return base url (host + port)
function base($path = '') {
    return "http://$_SERVER[SERVER_NAME]:$_SERVER[SERVER_PORT]/$path";
}

// Return an application-relative URL.
// Works when the project is served from / or from /bagel-shop.
function app_url($path = '') {
    static $app_path = null;

    if ($app_path === null) {
        $document_root = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
        $project_root = realpath(__DIR__) ?: __DIR__;

        $document_root = str_replace('\\', '/', $document_root);
        $project_root = str_replace('\\', '/', $project_root);

        if ($document_root !== '' && strpos($project_root, $document_root) === 0) {
            $app_path = substr($project_root, strlen($document_root));
        }
        else {
            $app_path = '';
        }

        $app_path = '/' . trim($app_path, '/');

        if ($app_path === '/') {
            $app_path = '';
        }
    }

    return $app_path . '/' . ltrim($path, '/');
}
// ============================================================================
// HTML Helpers
// ============================================================================
// Placeholder for TODO
function TODO() {
    echo '<span>TODO</span>';
}
// Encode HTML special characters
function encode($value) {
    return htmlentities($value);
}
// Generate <input type='hidden'>
function html_hidden($key, $attr = '') {
    $value = encode($GLOBALS[$key] ?? '');
    echo "<input type='hidden' id='$key' name='$key' value='$value' $attr>";
}
// Generate <input type='text'>
function html_text($key, $attr = '') {
    $value = encode($GLOBALS[$key] ?? '');
    echo "<input type='text' id='$key' name='$key' value='$value' $attr>";
}
// Generate <input type='password'>
function html_password($key, $attr = '') {
    $value = encode($GLOBALS[$key] ?? '');
    echo "<input type='password' id='$key' name='$key' value='$value' $attr>";
}
// Generate <input type='number'>
function html_number($key, $min = '', $max = '', $step = '', $attr = '') {
    $value = encode($GLOBALS[$key] ?? '');
    echo "<input type='number' id='$key' name='$key' value='$value'
                 min='$min' max='$max' step='$step' $attr>";
}
// Generate <input type='search'>
function html_search($key, $attr = '') {
    $value = encode($GLOBALS[$key] ?? '');
    echo "<input type='search' id='$key' name='$key' value='$value' $attr>";
}
// Generate <textarea>
function html_textarea($key, $attr = '') {
    $value = encode($GLOBALS[$key] ?? '');
    echo "<textarea id='$key' name='$key' $attr>$value</textarea>";
}
// Generate SINGLE <input type='checkbox'>
function html_checkbox($key, $label = '', $attr = '') {
    $value = encode($GLOBALS[$key] ?? '');
    $status = $value == 1 ? 'checked' : '';
    echo "<label><input type='checkbox' id='$key' name='$key' value='1' $status $attr>$label</label>";
}
// Generate <input type='radio'> list
function html_radios($key, $items, $br = false) {
    $value = encode($GLOBALS[$key] ?? '');
    echo '<div>';
    foreach ($items as $id => $text) {
        $state = $id == $value ? 'checked' : '';
        echo "<label><input type='radio' id='{$key}_$id' name='$key' value='$id' $state>$text</label>";
        if ($br) {
            echo '<br>';
        }
    }
    echo '</div>';
}
// Generate <select>
function html_select($key, $items, $default = '- Select One -', $attr = '') {
    $value = encode($GLOBALS[$key] ?? '');
    echo "<select id='$key' name='$key' $attr>";
    if ($default !== null) {
        echo "<option value=''>$default</option>";
    }
    foreach ($items as $id => $text) {
        $state = $id == $value ? 'selected' : '';
        echo "<option value='$id' $state>$text</option>";
    }
    echo '</select>';
}
// Generate <input type='file'>
function html_file($key, $accept = '', $attr = '') {
    echo "<input type='file' id='$key' name='$key' accept='$accept' $attr>";
}

// Generate multiple file input
function html_file_multiple($key, $accept = '', $attr = '') {
    echo "<input type='file' id='$key' name=\"{$key}[]\" accept='$accept' multiple $attr>";
}

// Generate table headers <th>
function table_headers($fields, $sort, $dir, $href = '') {
    foreach ($fields as $k => $v) {
        $d = 'asc'; // Default direction
        $c = '';    // Default class
        if ($k == $sort) {
            $d = $dir == 'asc' ? 'desc' : 'asc';
            $c = $dir;
        }
        echo "<th><a href='?sort=$k&dir=$d&$href' class='$c'>$v</a></th>";
    }
}

// Product Reviews & Ratings
// Add a product review
function add_review($product_id, $user_id, $rating, $comment) {
    global $_db;

    // Check if user already reviewed this product
    $stmt = $_db->prepare("SELECT id FROM product_review WHERE product_id = ? AND user_id = ?");
    $stmt->execute([$product_id, $user_id]);

    if ($stmt->fetch()) {
        return ['success' => false, 'message' => 'You have already reviewed this product.'];
    }

    $stmt = $_db->prepare("INSERT INTO product_review (product_id, user_id, rating, comment, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([$product_id, $user_id, $rating, $comment]);

    // Update average rating
    update_product_rating($product_id);

    return ['success' => true, 'message' => 'Review submitted successfully!'];
}

// Update product average rating
function update_product_rating($product_id) {
    global $_db;

    $stmt = $_db->prepare("SELECT AVG(rating) as avg_rating FROM product_review WHERE product_id = ?");
    $stmt->execute([$product_id]);
    $result = $stmt->fetch();

    $avg_rating = $result->avg_rating ?? 0;

    $stmt = $_db->prepare("UPDATE product SET rating = ? WHERE id = ?");
    $stmt->execute([$avg_rating, $product_id]);
}

// Get product reviews
function get_product_reviews($product_id) {
    global $_db;
    $stmt = $_db->prepare("
        SELECT r.*, u.name as user_name
        FROM product_review r
        JOIN user u ON r.user_id = u.id
        WHERE r.product_id = ?
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$product_id]);
    return $stmt->fetchAll();
}

// Get products by category
function get_products_by_category($category_id = null, $limit = null) {
    global $_db;

    $where = '';
    $params = [];

    if ($category_id !== null && $category_id !== '') {
        $where = 'WHERE p.category_id = ?';
        $params[] = $category_id;
    }

    $query = "SELECT p.* FROM product p $where ORDER BY p.name";

    if ($limit) {
        $query .= " LIMIT $limit";
    }

    $stmt = $_db->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// Get all categories
function get_categories() {
    global $_db;
    $stmt = $_db->query("SELECT * FROM category ORDER BY name");
    return $stmt->fetchAll();
}

// Get product photos
function get_product_photos($product_id) {
    global $_db;
    $stmt = $_db->prepare("SELECT * FROM product_photo WHERE product_id = ? ORDER BY sort_order, id");
    $stmt->execute([$product_id]);
    return $stmt->fetchAll();
}

// ============================================================================
// Error Handlings
// ============================================================================
// Global error array
$_err = [];
// Generate <span class='err'>
function err($key) {
    global $_err;
    if ($_err[$key] ?? false) {
        echo "<span class='err'>$_err[$key]</span>";
    }
    else {
        echo '<span></span>';
    }
}
// ============================================================================
// Security
// ============================================================================
// Global user object
$_user = $_SESSION['user'] ?? null;

// Store a user in the current session without redirecting.
function set_logged_in_user($user): void {
    global $_user;

    session_regenerate_id(true);

    $_SESSION['user'] = $user;
    $_user = $user;

    unset($_SESSION['remember_pending_user_id']);
    unset($_SESSION['pending_auth']);

    load_cart_fr_db($user->id);
}

// Login user
function login($user, $url = '/') {
    set_logged_in_user($user);

    redirect($url);
}
// Logout user
function logout($url = '/') {
    global $_db;

    // Save cart before logout
    if (isset($_SESSION['user'])) {
        $user_id = $_SESSION['user']->id;
        save_cart_to_db($user_id, $_db);

        // Remove Remember Me token from database
        $stmt = $_db->prepare("
            UPDATE user
            SET remember_token = NULL,
                remember_expires = NULL
            WHERE id = ?
        ");

        $stmt->execute([$user_id]);
    }

    // Delete Remember Me cookie
    setcookie(
        'remember_token',
        '',
        time() - 3600,
        '/'
    );

    // Clear session
    unset($_SESSION['cart']);
    unset($_SESSION['user']);
    unset($_SESSION['remember_pending_user_id']);
    unset($_SESSION['pending_auth']);
    unset($_SESSION['captcha_nonces']);

    redirect($url);
}
// Authorization
function auth(...$roles) {
    global $_user;
    if ($_user) {
        if ($roles) {
            if (in_array($_user->role, $roles)) {
                return; // OK
            }
        }
        else {
            return; // OK
        }
    }
    redirect(app_url('login.php'));
}
// ============================================================================
// Shopping Cart
// ============================================================================
// Get shopping cart
function get_cart() {
    return $_SESSION['cart'] ?? [];
}
// Set shopping cart
function set_cart($cart = []) {
    $_SESSION['cart'] = $cart;
}
// Update shopping cart
// and save session cart to DB table --ziqi
function update_cart($id, $unit) {
    global $_user, $_db;

    $cart = get_cart();
    if ($unit >= 1 && $unit <= 10 && is_exists($id, 'product', 'id')) {
        $cart[$id] = $unit;
        ksort($cart);
    }
    else {
        unset($cart[$id]);
    }
    set_cart($cart);

    // If a user is logged in, sync it to the database immediately --ziqi
    if ($_user && $_db) {
        save_cart_to_db($_user->id, $_db);
    }
}

// clear user's rows first, then re-insert current cart --ziqi
function save_cart_to_db($user_id, $_db) {
    $stmt = $_db->prepare("DELETE FROM cart WHERE user_id = ?");
    $stmt->execute([$user_id]);

    $cart = $_SESSION['cart'] ?? [];
    // Insert all current items from session cart into DB
    foreach ($cart as $product_id => $quantity) {
        if (!is_exists($product_id, 'product', 'id')) {
            continue;
        }

        $stmt = $_db->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $product_id, $quantity]);
    }
}

// Fetch the saved database items back into the session cart array (after logout & login) --ziqi
function load_cart_fr_db($user_id) {
    global $_db;
    $stmt = $_db->prepare("SELECT product_id, quantity FROM cart WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $items = $stmt->fetchAll();

    $_SESSION['cart'] = []; // fresh reset for the logged-in user
    foreach ($items as $item) {
        $_SESSION['cart'][$item->product_id] = (int)$item->quantity;
    }
}

// ============================================================================
// Database Setups and Functions
// ============================================================================
// Global PDO object
$_db = new PDO('mysql:dbname=db_bagel', 'root', '', [
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
]);
// Is unique?
function is_unique($value, $table, $field) {
    global $_db;
    $stm = $_db->prepare("SELECT COUNT(*) FROM $table WHERE $field = ?");
    $stm->execute([$value]);
    return $stm->fetchColumn() == 0;
}
// Is exists?
function is_exists($value, $table, $field) {
    global $_db;
    $stm = $_db->prepare("SELECT COUNT(*) FROM $table WHERE $field = ?");
    $stm->execute([$value]);
    return $stm->fetchColumn() > 0;
}

// ============================================================================
// Global Constants and Variables
// ============================================================================

// Range 1-10
$_units = array_combine(range(1, 10), range(1, 10));

<<<<<<< HEAD
// Rating options (1-5 stars)
$_ratings = [
    1 => '★☆☆☆☆ (1)',
    2 => '★★☆☆☆ (2)',
    3 => '★★★☆☆ (3)',
    4 => '★★★★☆ (4)',
    5 => '★★★★★ (5)'
];

//auto login user if remember_token cookie is set and valid
=======
// Verify a Cloudflare Turnstile token on the server.
function verify_turnstile(string $token, string $expected_action = ''): bool {
    if ($token === '' || !function_exists('curl_init')) {
        return false;
    }

    $data = [
        'secret'   => TURNSTILE_SECRET_KEY,
        'response' => $token,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? ''
    ];

    $ch = curl_init(
        'https://challenges.cloudflare.com/turnstile/v0/siteverify'
    );

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($data),
        CURLOPT_TIMEOUT        => 10
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    if ($response === false || $httpCode !== 200) {
        return false;
    }

    $result = json_decode($response, true);

    if (!is_array($result) || empty($result['success'])) {
        return false;
    }

    if ($expected_action !== '' && ($result['action'] ?? '') !== $expected_action) {
        return false;
    }

    return true;
}

// Create a short-lived nonce for one CAPTCHA widget.
function new_captcha_nonce(string $action): string {
    $_SESSION['captcha_nonces'] ??= [];

    foreach ($_SESSION['captcha_nonces'] as $key => $item) {
        if (($item['expires'] ?? 0) < time()) {
            unset($_SESSION['captcha_nonces'][$key]);
        }
    }

    $nonce = bin2hex(random_bytes(24));

    $_SESSION['captcha_nonces'][hash('sha256', $nonce)] = [
        'action' => $action,
        'expires' => time() + 600,
        'attempts' => 0,
    ];

    return $nonce;
}

// Check the widget nonce and limit repeated verification attempts.
function validate_captcha_nonce(string $nonce, string $action): bool {
    $key = hash('sha256', $nonce);
    $item = $_SESSION['captcha_nonces'][$key] ?? null;

    if (!$item ||
        ($item['expires'] ?? 0) < time() ||
        ($item['action'] ?? '') !== $action ||
        ($item['attempts'] ?? 0) >= 5) {

        unset($_SESSION['captcha_nonces'][$key]);
        return false;
    }

    $_SESSION['captcha_nonces'][$key]['attempts']++;
    return true;
}

// Save validated login/register information while the separate puzzle page
// is being completed. Plain-text passwords are never stored here.
function begin_pending_auth(string $action, array $data): void {
    $_SESSION['pending_auth'] = [
        'action' => $action,
        'data' => $data,
        'expires' => time() + 300,
    ];
}

// Return the pending action when it is still valid.
function get_pending_auth(string $expected_action = ''): ?array {
    $pending = $_SESSION['pending_auth'] ?? null;

    if (!$pending || ($pending['expires'] ?? 0) < time()) {
        unset($_SESSION['pending_auth']);
        return null;
    }

    if ($expected_action !== '' &&
        ($pending['action'] ?? '') !== $expected_action) {

        return null;
    }

    return $pending;
}

function clear_pending_auth(): void {
    unset($_SESSION['pending_auth']);
}

// Basic server check of the slider movement data.
// Turnstile remains the authoritative anti-bot protection.
function verify_slider_data($trail, $position, $target): bool {
    if (!is_array($trail) || count($trail) < 2 || count($trail) > 500) {
        return false;
    }

    if (!is_numeric($position) || !is_numeric($target)) {
        return false;
    }

    if (abs((float)$position - (float)$target) > 6) {
        return false;
    }

    $values = [];

    foreach ($trail as $value) {
        if (!is_numeric($value) || abs((float)$value) > 200) {
            return false;
        }

        $values[] = (float)$value;
    }

    $average = array_sum($values) / count($values);
    $variance = 0.0;

    foreach ($values as $value) {
        $variance += ($value - $average) ** 2;
    }

    $variance /= count($values);

    return $variance > 0;
}

// Delete an invalid or expired remember-me cookie.
function clear_remember_cookie(): void {
    setcookie(
        'remember_token',
        '',
        [
            'expires' => time() - 3600,
            'path' => '/',
            'httponly' => true,
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'samesite' => 'Lax',
        ]
    );

    unset($_SESSION['remember_pending_user_id']);
}

// A remembered user must pass visible Turnstile and then the separate puzzle
// once per new browser session before the login is restored.
>>>>>>> 1c7681d7b432e81d51a29e18cd09c54f82af5d77
if (!isset($_SESSION['user']) && isset($_COOKIE['remember_token'])) {
    $token_hash = hash('sha256', $_COOKIE['remember_token']);

    $stmt = $_db->prepare("
        SELECT *
        FROM user
        WHERE remember_token = ?
        AND remember_expires > NOW()
    ");

    $stmt->execute([$token_hash]);
    $remembered_user = $stmt->fetch();

    if ($remembered_user) {
        $_SESSION['remember_pending_user_id'] = $remembered_user->id;

        $current_script = basename($_SERVER['SCRIPT_NAME'] ?? '');
        $allowed_scripts = [
            'remember_verify.php',
            'puzzle.php',
            'slider_verify.php',
        ];

        if (!in_array($current_script, $allowed_scripts, true)) {
            redirect(app_url('user/remember_verify.php'));
        }
    }
    else {
        clear_remember_cookie();
    }
}
