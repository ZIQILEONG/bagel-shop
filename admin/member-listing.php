<?php
require_once '../db.php';
include '../_base.php';

// Get search query parameter
$search = trim($_GET['search'] ?? '');

// Base SQL query targeting users with 'Member' role
$sql = "SELECT id, name, email, phone_no, photo, role FROM user WHERE role = 'Member'";[cite] 1]
$params = [];

// Apply search filter if entered
if (!empty($search)) {
    $sql .= " AND (name LIKE :search OR email LIKE :search OR phone_no LIKE :search)";[cite: 1]
    $params[':search'] = '%' . $search . '%';
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$members = $stmt->fetchAll();

// Include site header
include 'header.php';
?>

<div class="admin-container">
    <h2>Member Management</h2>

    <!-- Basic Search Form -->
    <form method="GET" action="admin_members.php" class="search-form">
        <input 
            type="text" 
            name="search" 
            value="<?= htmlspecialchars($search) ?>" 
            placeholder="Search by name, email, or phone..."
        >
        <button type="submit">Search</button>
        <?php if (!empty($search)): ?>
            <a href="admin_members.php">Clear Search</a>
        <?php endif; ?>
    </form>

    <br>

    <!-- Member Table -->
    <table border="1" cellpadding="10" cellspacing="0" width="100%">
        <thead>
            <tr>
                <th>Photo</th>
                <th>ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Phone No</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($members)): ?>
                <tr>
                    <td colspan="5" style="text-align: center;">No members found.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($members as $member): ?>
                    <tr>
                        <td align="center">
                            <img src="uploads/<?= htmlspecialchars($member['photo']) ?>" alt="Profile" width="50" style="border-radius: 50%;">[cite: 1]
                        </td>
                        <td><?= htmlspecialchars($member['id']) ?></td>[cite: 1]
                        <td><?= htmlspecialchars($member['name']) ?></td>[cite: 1]
                        <td><?= htmlspecialchars($member['email']) ?></td>[cite: 1]
                        <td><?= htmlspecialchars($member['phone_no'] ?? 'N/A') ?></td>[cite: 1]
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php
// Include site footer
include 'footer.php';
?>