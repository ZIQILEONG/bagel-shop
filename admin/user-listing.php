<?php
include '../_base.php';
require '../lib/SimplePager.php';
auth('Admin');

// Handle bulk deactivation
if (is_post() && req('btn') == 'delete_selected') {
    $ids = post('ids', []);
    $before = count($ids);
    $ids = array_diff($ids, [(string) $_user->id]); // never delete yourself
    if (count($ids) > 0) {
        $in  = implode(',', array_fill(0, count($ids), '?'));
        $stm = $_db->prepare("UPDATE user SET is_deleted = 1 WHERE id IN ($in)");
        $stm->execute(array_values($ids));
        temp('info', count($ids) . ' member(s) deactivated.');
    }
    else if ($before > 0) {
        temp('info', 'No members deactivated — you cannot deactivate your own account.');
    }
    redirect('user-listing.php');
}

// Sanitize inputs
$search = trim(get('search', ''));
$page   = get('page', '1');

// Whitelist sorting parameters safely
$sorts = ['id', 'name', 'email', 'role', 'phone_no'];
$sort  = in_array(get('sort'), $sorts, true) ? get('sort') : 'id';
$dir   = (strtolower(get('dir')) === 'desc') ? 'DESC' : 'ASC';

// Build WHERE conditions
$where  = 'WHERE is_deleted = 0';
$params = [];
if ($search !== '') {
    $where .= ' AND (name LIKE ? OR email LIKE ? OR phone_no LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// SQL Query with explicit space separation
$query = "SELECT * FROM user {$where} ORDER BY {$sort} {$dir}";
$pager = new SimplePager($query, $params, 10, $page);
$arr   = $pager->result;

// Render async partial for AJAX requests
if (get('ajax') == '1') {
    include 'user-listing-results.php';
    exit();
}

$_title = 'Member | Listing (Admin)';
include '../_head.php';
?>

<style>
/* =========================================================
   PULULU ADMIN USER LISTING STYLES
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
    max-width: 1140px;
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
}
.pl-admin-breadcrumb a:hover {
    color: var(--pl-primary);
}

/* Header */
.pl-admin-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 16px;
}
.pl-admin-header h1 {
    font-size: 28px;
    font-weight: 800;
    color: var(--pl-brown-dark);
    margin: 0 0 6px;
}
.pl-admin-header p {
    font-size: 14px;
    color: var(--pl-muted);
    margin: 0;
}

.pl-btn-add-member {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: var(--pl-primary);
    color: #ffffff;
    padding: 11px 22px;
    border-radius: 12px;
    font-size: 14px;
    font-weight: 700;
    text-decoration: none;
    transition: all 0.2s ease;
    box-shadow: 0 4px 14px rgba(207, 121, 83, 0.25);
}
.pl-btn-add-member:hover {
    background: var(--pl-primary-hover);
    transform: translateY(-1px);
}

/* Search Card Container */
.pl-search-card {
    background: #ffffff;
    border: 1px solid var(--pl-border);
    border-radius: 16px;
    padding: 18px 22px;
    margin-bottom: 24px;
    box-shadow: 0 4px 16px rgba(62, 38, 25, 0.03);
}

.pl-search-form {
    display: flex;
    gap: 12px;
}

.pl-search-input {
    flex: 1;
    padding: 11px 16px;
    border: 1.5px solid var(--pl-border);
    border-radius: 10px;
    font-size: 14px;
    outline: none;
    background: #faf6f2;
    color: var(--pl-text);
}
.pl-search-input:focus {
    border-color: var(--pl-primary);
    background: #ffffff;
}

.pl-btn-search {
    background: var(--pl-brown-dark);
    color: #fff;
    border: none;
    padding: 11px 24px;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    transition: background 0.15s ease;
}
.pl-btn-search:hover {
    background: #23130a;
}

/* Results Wrapper */
#resultsWrap {
    margin-top: 0;
}
</style>

<div class="pl-admin-wrap">
    <!-- Breadcrumb -->
    <div class="pl-admin-breadcrumb">
        <a href="/">Home</a>
        <span>&rsaquo;</span>
        <span style="color: var(--pl-brown-dark); font-weight: 600;">User Management</span>
    </div>

    <!-- Header -->
    <div class="pl-admin-header">
        <div>
            <h1>Registered Members</h1>
            <p>View, manage, edit roles, and deactivate platform user accounts.</p>
        </div>
        <a href="user-detail.php" class="pl-btn-add-member">＋ Add New Member</a>
    </div>

    <!-- Search Card -->
    <div class="pl-search-card">
        <form method="get" class="pl-search-form" id="searchForm">
            <input type="text" 
                   name="search" 
                   id="search" 
                   class="pl-search-input" 
                   placeholder="Search by name, email, or contact number..." 
                   value="<?= htmlspecialchars($search) ?>" 
                   autocomplete="off">
            <input type="hidden" name="sort" id="sort" value="<?= htmlspecialchars(strtolower($sort)) ?>">
            <input type="hidden" name="dir" id="dir" value="<?= htmlspecialchars(strtolower($dir)) ?>">
            <button type="submit" class="pl-btn-search">Search</button>
        </form>
    </div>

    <!-- Live Async Results Target -->
    <div id="resultsWrap">
        <?php include 'user-listing-results.php'; ?>
    </div>
</div>

<script>
let timer = null;

function loadResults(page) {
    $.get('user-listing.php', {
        ajax: 1,
        search: $('#search').val(),
        sort: $('#sort').val(),
        dir: $('#dir').val(),
        page: page || 1
    }, function(html) {
        $('#resultsWrap').html(html);
        bindSelectAll();
    });
}

function setSort(col) {
    let currentSort = $('#sort').val();
    let currentDir = $('#dir').val();
    
    if (currentSort === col) {
        $('#dir').val(currentDir.toLowerCase() === 'asc' ? 'desc' : 'asc');
    } else {
        $('#sort').val(col);
        $('#dir').val('asc');
    }
    loadResults(1);
}

function bindSelectAll() {
    $('#selectAllUsers').off('change').on('change', function() {
        $('.user-cb').prop('checked', this.checked);
    });
}

function confirmBulkDelete() {
    let count = $('.user-cb:checked').length;
    if (count === 0) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'info',
                title: 'No Members Selected',
                text: 'Please select at least one member to deactivate.',
                confirmButtonColor: '#cf7953'
            });
        } else {
            alert('Please select at least one member to deactivate.');
        }
        return;
    }

    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: `Deactivate ${count} Member(s)?`,
            text: 'These accounts will be disabled and prevented from logging in.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#c0392b',
            cancelButtonColor: '#968377',
            confirmButtonText: 'Yes, Deactivate',
            cancelButtonText: 'Cancel',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                $('#bulkDeleteForm').submit();
            }
        });
    } else {
        if (confirm(`Deactivate ${count} member(s)?`)) {
            $('#bulkDeleteForm').submit();
        }
    }
}

$(document).ready(function () {
    bindSelectAll();

    $('#search').on('input', function () {
        clearTimeout(timer);
        timer = setTimeout(() => loadResults(1), 350);
    });

    $('#resultsWrap').on('click', '.pager a', function (e) {
        e.preventDefault();
        let page = new URL(this.href).searchParams.get('page');
        loadResults(page);
    });

    $('#searchForm').on('submit', function (e) { 
        e.preventDefault(); 
        loadResults(1); 
    });
});
</script>

<?php
include '../_foot.php';
?>