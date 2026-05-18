<?php
require_once 'session_check.php';
require_once 'db_connect.php';

// DELETE ITEM
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = intval($_GET['delete']);
    mysqli_query($conn, "DELETE FROM inventory WHERE id = $id");
    header("Location: inventory.php?deleted=1");
    exit();
}

// PAGINATION
$per_page = 10;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $per_page;

// SEARCH
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$search_condition = '';

if ($search) {
    $search_condition = "WHERE item_name LIKE '%$search%' OR status LIKE '%$search%'";
}

// TOTAL COUNT
$total_result = mysqli_query($conn, "SELECT COUNT(*) as count FROM inventory $search_condition");
$total_rows = mysqli_fetch_assoc($total_result)['count'];
$total_pages = ceil($total_rows / $per_page);

// FETCH INVENTORY
$inventory = mysqli_query($conn, "SELECT * FROM inventory $search_condition ORDER BY id DESC LIMIT $offset, $per_page");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Inventory - Admin</title>
    <link rel="stylesheet" href="admin-style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>
<div class="admin-wrapper">

    <!-- SIDEBAR -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <i class="fas fa-utensils"></i>
            <span>Cafè Erlinda</span>
        </div>

        <nav class="sidebar-nav">
            <a href="index.php" class="nav-item">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>

            <a href="table-bookings.php" class="nav-item">
                <i class="fas fa-table"></i>
                <span>Table Bookings</span>
            </a>

            <a href="catering-bookings.php" class="nav-item">
                <i class="fas fa-calendar-alt"></i>
                <span>Catering Bookings</span>
            </a>

            <a href="inventory.php" class="nav-item active">
                <i class="fas fa-boxes"></i>
                <span>Inventory</span>
            </a>

            <a href="settings.php" class="nav-item">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
        </nav>

        <div class="sidebar-footer">
            <a href="logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="main-content">

        <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            Item deleted successfully!
        </div>
        <?php endif; ?>

        <!-- HEADER -->
        <div class="page-header">
            <div class="page-title">
                <h1><i class="fas fa-boxes"></i> Inventory</h1>
                <p>Manage your inventory items</p>
            </div>

            <div class="header-actions">
                <form method="GET" class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" placeholder="Search items..." value="<?php echo htmlspecialchars($search); ?>">
                </form>

                <a href="add-inventory.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add Item
                </a>
            </div>
        </div>

        <!-- TABLE -->
        <div class="table-card full-width">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Item Name</th>
                            <th>Quantity</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php if (mysqli_num_rows($inventory) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($inventory)): ?>
                        <tr>
                            <td>#<?php echo $row['id']; ?></td>
                            <td><?php echo htmlspecialchars($row['item_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['quantity']); ?></td>
                            <td>
                                <span class="badge">
                                    <?php echo htmlspecialchars($row['status']); ?>
                                </span>
                            </td>
                            <td>
                                <div style="display:flex; gap:8px;">
                                    <a href="edit-inventory.php?id=<?php echo $row['id']; ?>" class="btn btn-success btn-sm">
                                        <i class="fas fa-edit"></i>
                                    </a>

                                    <a href="?delete=<?php echo $row['id']; ?>" 
                                       class="btn btn-danger btn-sm"
                                       onclick="return confirm('Delete this item?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="text-align:center; padding:40px;">
                                <i class="fas fa-box-open" style="font-size:48px;"></i>
                                <p>No inventory items found</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- PAGINATION -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php endif; ?>

                <?php for ($i=1; $i <= $total_pages; $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="active"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        </div>
    </main>
</div>
</body>
</html>