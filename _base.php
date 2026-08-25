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
// Login user
function login($user, $url = '/') {
    global $_user;

    $_SESSION['user'] = $user;
    $_user = $user;

    load_cart_fr_db($user->id);

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
    redirect('/login.php');
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

// Rating options (1-5 stars)
$_ratings = [
    1 => '★☆☆☆☆ (1)',
    2 => '★★☆☆☆ (2)',
    3 => '★★★☆☆ (3)',
    4 => '★★★★☆ (4)',
    5 => '★★★★★ (5)'
];

//auto login user if remember_token cookie is set and valid
if (!isset($_SESSION['user']) && isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];
    $stmt = $_db->prepare("
        SELECT *
        FROM user
        WHERE remember_token = ?
        AND remember_expires > NOW()
    ");

    $stmt->execute([$token]);
    $user = $stmt->fetch();
    if ($user) {

        // Restore login
        login($user);

    }
    else {

        // Invalid/expired token
        setcookie(
            'remember_token',
            '',
            time() - 3600,
            '/'
        );
    }
}