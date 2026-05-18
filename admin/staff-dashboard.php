<?php
session_start();

// Check if staff is logged in
if (!isset($_SESSION['staff_id'])) {
    header("Location: unified-login.php");
    exit();
}

require_once 'db_connect.php';

$staff_id = $_SESSION['staff_id'];
$staff_name = $_SESSION['staff_name'];
$staff_role = $_SESSION['staff_role'];

// Handle new order submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_order') {
    $customer_name = mysqli_real_escape_string($conn, $_POST['customer_name'] ?? '');
    $table_number = mysqli_real_escape_string($conn, $_POST['table_number'] ?? '');
    $order_type = mysqli_real_escape_string($conn, $_POST['order_type'] ?? 'dine_in');
    $items = mysqli_real_escape_string($conn, $_POST['items'] ?? '');
    $total_amount = (float)($_POST['total_amount'] ?? 0);
    $notes = mysqli_real_escape_string($conn, $_POST['notes'] ?? '');

    $insert_query = "INSERT INTO orders (customer_name, table_number, order_type, items, total_amount, notes, staff_id, status) 
                     VALUES ('$customer_name', '$table_number', '$order_type', '$items', $total_amount, '$notes', $staff_id, 'pending')";

    if (mysqli_query($conn, $insert_query)) {
        header("Location: staff-dashboard.php?success=order_created");
        exit();
    } else {
        $error = "Error creating order: " . mysqli_error($conn);
    }
}

// Handle void order
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'void_order') {
    $order_id = (int)$_POST['order_id'];
    $void_reason = mysqli_real_escape_string($conn, $_POST['void_reason'] ?? '');

    $order_query = "SELECT * FROM orders WHERE order_id = $order_id";
    $order_result = mysqli_query($conn, $order_query);
    $order = mysqli_fetch_assoc($order_result);

    if ($order) {
        $update_query = "UPDATE orders SET status = 'voided', void_reason = '$void_reason', voided_by = $staff_id, voided_at = NOW() WHERE order_id = $order_id";

        if (mysqli_query($conn, $update_query)) {
            $log_query = "INSERT INTO void_logs (order_id, voided_by, voided_by_name, void_reason, original_amount) 
                         VALUES ($order_id, $staff_id, '$staff_name', '$void_reason', {$order['total_amount']})";
            mysqli_query($conn, $log_query);

            header("Location: staff-dashboard.php?success=order_voided");
            exit();
        } else {
            $error = "Error voiding order: " . mysqli_error($conn);
        }
    }
}

// Handle status update
if (isset($_GET['action']) && $_GET['action'] === 'update_status' && isset($_GET['id']) && isset($_GET['status'])) {
    $order_id = (int)$_GET['id'];
    $status = mysqli_real_escape_string($conn, $_GET['status']);

    $update_query = "UPDATE orders SET status = '$status' WHERE order_id = $order_id";
    mysqli_query($conn, $update_query);
    header("Location: staff-dashboard.php");
    exit();
}

// Fetch orders
$today_orders = [];
$today_query = "SELECT o.*, s.full_name as staff_name 
                FROM orders o 
                LEFT JOIN staff s ON o.staff_id = s.staff_id 
                WHERE DATE(o.created_at) = CURDATE() 
                ORDER BY o.created_at DESC";
$today_result = mysqli_query($conn, $today_query);
if ($today_result) {
    while ($row = mysqli_fetch_assoc($today_result)) {
        $today_orders[] = $row;
    }
}

$active_orders = [];
$active_query = "SELECT o.*, s.full_name as staff_name 
                FROM orders o 
                LEFT JOIN staff s ON o.staff_id = s.staff_id 
                WHERE o.status != 'voided' 
                ORDER BY o.created_at DESC LIMIT 50";
$active_result = mysqli_query($conn, $active_query);
if ($active_result) {
    while ($row = mysqli_fetch_assoc($active_result)) {
        $active_orders[] = $row;
    }
}

$voided_orders = [];
$voided_query = "SELECT o.*, s.full_name as staff_name, v.voided_by_name, v.void_reason, v.voided_at as void_time
                FROM orders o 
                LEFT JOIN staff s ON o.staff_id = s.staff_id 
                LEFT JOIN void_logs v ON o.order_id = v.order_id
                WHERE o.status = 'voided' 
                ORDER BY o.voided_at DESC LIMIT 50";
$voided_result = mysqli_query($conn, $voided_query);
if ($voided_result) {
    while ($row = mysqli_fetch_assoc($voided_result)) {
        $voided_orders[] = $row;
    }
}


// Fetch inventory items
$inventory_items = [];
$inventory_query = "SELECT * FROM inventory ORDER BY category, item_name ASC";
$inventory_result = mysqli_query($conn, $inventory_query);
if ($inventory_result) {
    while ($row = mysqli_fetch_assoc($inventory_result)) {
        $inventory_items[] = $row;
    }
}

// Fetch inventory categories for filter
$inventory_categories = [];
$cat_query = "SELECT DISTINCT category FROM inventory ORDER BY category ASC";
$cat_result = mysqli_query($conn, $cat_query);
if ($cat_result) {
    while ($row = mysqli_fetch_assoc($cat_result)) {
        $inventory_categories[] = $row['category'];
    }
}

// Calculate inventory stats
$inventory_stats = [
    'total_items' => count($inventory_items),
    'low_stock' => count(array_filter($inventory_items, fn($i) => (float)$i['quantity'] <= (float)$i['min_stock'])),
    'out_of_stock' => count(array_filter($inventory_items, fn($i) => (float)$i['quantity'] == 0)),
    'total_value' => array_sum(array_map(fn($i) => (float)$i['quantity'] * (float)$i['unit_cost'], $inventory_items))
];

// Handle inventory actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_inventory') {
        $item_name = mysqli_real_escape_string($conn, $_POST['item_name'] ?? '');
        $category = mysqli_real_escape_string($conn, $_POST['category'] ?? '');
        $quantity = (float)($_POST['quantity'] ?? 0);
        $unit = mysqli_real_escape_string($conn, $_POST['unit'] ?? 'pcs');
        $min_stock = (float)($_POST['min_stock'] ?? 0);
        $unit_cost = (float)($_POST['unit_cost'] ?? 0);
        $supplier = mysqli_real_escape_string($conn, $_POST['supplier'] ?? '');
        $notes = mysqli_real_escape_string($conn, $_POST['notes'] ?? '');

        $inv_insert = "INSERT INTO inventory (item_name, category, quantity, unit, min_stock, unit_cost, supplier, notes, last_updated) 
                       VALUES ('$item_name', '$category', $quantity, '$unit', $min_stock, $unit_cost, '$supplier', '$notes', NOW())";

        if (mysqli_query($conn, $inv_insert)) {
            header("Location: staff-dashboard.php?tab=inventory&success=inv_added");
            exit();
        } else {
            $error = "Error adding inventory: " . mysqli_error($conn);
        }
    }

    if ($_POST['action'] === 'update_inventory') {
        $inv_id = (int)$_POST['inv_id'];
        $quantity = (float)($_POST['quantity'] ?? 0);
        $unit_cost = (float)($_POST['unit_cost'] ?? 0);
        $min_stock = (float)($_POST['min_stock'] ?? 0);
        $notes = mysqli_real_escape_string($conn, $_POST['notes'] ?? '');

        $inv_update = "UPDATE inventory SET quantity = $quantity, unit_cost = $unit_cost, min_stock = $min_stock, notes = '$notes', last_updated = NOW() WHERE id = $inv_id";

        if (mysqli_query($conn, $inv_update)) {
            header("Location: staff-dashboard.php?tab=inventory&success=inv_updated");
            exit();
        } else {
            $error = "Error updating inventory: " . mysqli_error($conn);
        }
    }

    if ($_POST['action'] === 'adjust_stock') {
        $inv_id = (int)$_POST['inv_id'];
        $adjustment = (float)($_POST['adjustment'] ?? 0);
        $adjust_reason = mysqli_real_escape_string($conn, $_POST['adjust_reason'] ?? '');

        $item_query = "SELECT * FROM inventory WHERE id = $inv_id";
        $item_result = mysqli_query($conn, $item_query);
        $item = mysqli_fetch_assoc($item_result);

        if ($item) {
            $new_qty = (float)$item['quantity'] + $adjustment;
            if ($new_qty < 0) $new_qty = 0;

            $adj_update = "UPDATE inventory SET quantity = $new_qty, last_updated = NOW() WHERE id = $inv_id";

            if (mysqli_query($conn, $adj_update)) {
                $log_adj = "INSERT INTO inventory_adjustments (inventory_id, item_name, previous_qty, adjustment, new_qty, reason, adjusted_by, adjusted_by_name, adjusted_at) 
                           VALUES ($inv_id, '{$item['item_name']}', {$item['quantity']}, $adjustment, $new_qty, '$adjust_reason', $staff_id, '$staff_name', NOW())";
                mysqli_query($conn, $log_adj);

                header("Location: staff-dashboard.php?tab=inventory&success=inv_adjusted");
                exit();
            } else {
                $error = "Error adjusting stock: " . mysqli_error($conn);
            }
        }
    }

    if ($_POST['action'] === 'delete_inventory') {
        $inv_id = (int)$_POST['inv_id'];
        $del_query = "DELETE FROM inventory WHERE id = $inv_id";

        if (mysqli_query($conn, $del_query)) {
            header("Location: staff-dashboard.php?tab=inventory&success=inv_deleted");
            exit();
        } else {
            $error = "Error deleting item: " . mysqli_error($conn);
        }
    }
}

// Fetch adjustment history
$adjustments = [];
$adj_query = "SELECT * FROM inventory_adjustments ORDER BY adjusted_at DESC LIMIT 50";
$adj_result = mysqli_query($conn, $adj_query);
if ($adj_result) {
    while ($row = mysqli_fetch_assoc($adj_result)) {
        $adjustments[] = $row;
    }
}

function getStockStatus($item) {
    $qty = (float)$item['quantity'];
    $min = (float)$item['min_stock'];

    if ($qty == 0) {
        return '<span class="status-badge voided"><i class="fas fa-times-circle me-1"></i>Out of Stock</span>';
    } elseif ($qty <= $min) {
        return '<span class="status-badge pending"><i class="fas fa-exclamation-triangle me-1"></i>Low Stock</span>';
    } else {
        return '<span class="status-badge served"><i class="fas fa-check-circle me-1"></i>In Stock</span>';
    }
}

function getStockBar($item) {
    $qty = (float)$item['quantity'];
    $min = (float)$item['min_stock'];
    $max = max($qty, $min * 3, 10);
    $pct = min(100, ($qty / $max) * 100);

    $color = $qty == 0 ? 'var(--danger)' : ($qty <= $min ? 'var(--warning)' : 'var(--success)');

    return '<div class="stock-bar"><div class="stock-fill" style="width: ' . $pct . '%; background: ' . $color . ';"></div></div>';
}

// Calculate stats
$today_revenue = 0;
foreach ($today_orders as $order) {
    if ($order['status'] !== 'voided') {
        $today_revenue += (float)$order['total_amount'];
    }
}

$stats = [
    'today_total' => count($today_orders),
    'today_revenue' => $today_revenue,
    'pending' => count(array_filter($active_orders, fn($o) => $o['status'] === 'pending')),
    'preparing' => count(array_filter($active_orders, fn($o) => $o['status'] === 'preparing')),
    'ready' => count(array_filter($active_orders, fn($o) => $o['status'] === 'ready')),
    'voided_today' => count(array_filter($today_orders, fn($o) => $o['status'] === 'voided'))
];

function getStatusBadge($status) {
    $badges = [
        'pending' => '<span class="status-badge pending"><i class="fas fa-clock me-1"></i>Pending</span>',
        'preparing' => '<span class="status-badge preparing"><i class="fas fa-fire me-1"></i>Preparing</span>',
        'ready' => '<span class="status-badge ready"><i class="fas fa-bell me-1"></i>Ready</span>',
        'served' => '<span class="status-badge served"><i class="fas fa-check-circle me-1"></i>Served</span>',
        'voided' => '<span class="status-badge voided"><i class="fas fa-times-circle me-1"></i>Voided</span>',
    ];
    return $badges[$status] ?? '<span class="status-badge">' . ucfirst($status) . '</span>';
}

function getOrderTypeIcon($type) {
    $icons = [
        'dine_in' => '<i class="fas fa-utensils text-info"></i>',
        'takeout' => '<i class="fas fa-shopping-bag text-warning"></i>',
        'delivery' => '<i class="fas fa-motorcycle text-purple"></i>',
    ];
    return $icons[$type] ?? '<i class="fas fa-utensils"></i>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - Cafè Erlinda</title>
    <link href="https://fonts.googleapis.com/css2?family=Heebo:wght@400;500;600&family=Nunito:wght@600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #FEA116;
            --primary-dark: #e59015;
            --dark: #0f172a;
            --dark-light: #1e293b;
            --dark-lighter: #334155;
            --text: #f1f5f9;
            --text-muted: #94a3b8;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
            --purple: #8b5cf6;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Heebo', sans-serif;
            background: var(--dark);
            color: var(--text);
            min-height: 100vh;
        }

        .staff-wrapper {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 280px;
            background: var(--dark-light);
            border-right: 1px solid rgba(254, 161, 22, 0.1);
            display: flex;
            flex-direction: column;
            position: fixed;
            height: 100vh;
            z-index: 100;
        }

        .sidebar-header {
            padding: 25px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid rgba(254, 161, 22, 0.1);
        }

        .sidebar-header i {
            font-size: 28px;
            color: var(--primary);
        }

        .sidebar-header span {
            font-family: 'Pacifico', cursive;
            font-size: 22px;
            color: var(--primary);
        }

        .sidebar-nav {
            flex: 1;
            padding: 20px 15px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 18px;
            border-radius: 12px;
            color: var(--text-muted);
            text-decoration: none;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            background: none;
            width: 100%;
            font-size: 15px;
        }

        .nav-item:hover {
            background: rgba(254, 161, 22, 0.1);
            color: var(--primary);
        }

        .nav-item.active {
            background: linear-gradient(135deg, rgba(254, 161, 22, 0.2) 0%, rgba(254, 161, 22, 0.05) 100%);
            color: var(--primary);
            border: 1px solid rgba(254, 161, 22, 0.3);
        }

        .nav-item i {
            font-size: 18px;
            width: 24px;
            text-align: center;
        }

        .sidebar-footer {
            padding: 20px 15px;
            border-top: 1px solid rgba(254, 161, 22, 0.1);
        }

        .logout-btn {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 18px;
            border-radius: 12px;
            color: var(--danger);
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            background: rgba(239, 68, 68, 0.1);
        }

        .staff-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            margin-left: auto;
        }

        .badge-manager { background: rgba(59, 130, 246, 0.15); color: #60a5fa; }
        .badge-staff { background: rgba(16, 185, 129, 0.15); color: #34d399; }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 30px;
            background: var(--dark);
            min-height: 100vh;
        }

        /* Header */
        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(254, 161, 22, 0.1);
        }

        .header-left h1 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .header-left p {
            color: var(--text-muted);
            font-size: 14px;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .staff-info {
            display: flex;
            align-items: center;
            gap: 12px;
            background: var(--dark-light);
            padding: 10px 16px;
            border-radius: 12px;
            border: 1px solid rgba(254, 161, 22, 0.1);
        }

        .staff-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 14px;
        }

        .staff-details p {
            margin: 0;
            font-weight: 600;
            font-size: 14px;
        }

        .staff-details small {
            color: var(--text-muted);
            font-size: 12px;
        }

        .date-display {
            display: flex;
            align-items: center;
            gap: 10px;
            background: var(--dark-light);
            padding: 12px 20px;
            border-radius: 10px;
            color: var(--primary);
            border: 1px solid rgba(254, 161, 22, 0.1);
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--dark-light);
            border-radius: 16px;
            padding: 25px;
            display: flex;
            align-items: center;
            gap: 18px;
            border: 1px solid rgba(254, 161, 22, 0.1);
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            border-color: rgba(254, 161, 22, 0.3);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .stat-icon {
            width: 55px;
            height: 55px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
        }

        .stat-icon.blue { background: rgba(59, 130, 246, 0.15); color: var(--info); }
        .stat-icon.green { background: rgba(16, 185, 129, 0.15); color: var(--success); }
        .stat-icon.amber { background: rgba(245, 158, 11, 0.15); color: var(--warning); }
        .stat-icon.red { background: rgba(239, 68, 68, 0.15); color: var(--danger); }
        .stat-icon.purple { background: rgba(139, 92, 246, 0.15); color: var(--purple); }

        .stat-info h3 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .stat-info p {
            color: var(--text-muted);
            font-size: 13px;
        }

        /* Action Bar */
        .action-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .search-box {
            position: relative;
        }

        .search-box input {
            background: var(--dark-light);
            border: 1px solid rgba(254, 161, 22, 0.2);
            border-radius: 12px;
            padding: 12px 15px 12px 45px;
            color: var(--text);
            width: 300px;
            font-size: 14px;
        }

        .search-box input:focus {
            outline: none;
            border-color: var(--primary);
        }

        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 22px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.3s;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: #fff;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(254, 161, 22, 0.3);
        }

        .btn-outline {
            background: transparent;
            border: 1px solid rgba(254, 161, 22, 0.3);
            color: var(--primary);
        }

        .btn-outline:hover {
            background: rgba(254, 161, 22, 0.1);
        }

        /* Tabs */
        .tabs-container {
            margin-bottom: 25px;
        }

        .tabs {
            display: flex;
            gap: 5px;
            border-bottom: 2px solid rgba(254, 161, 22, 0.1);
            padding-bottom: 0;
        }

        .tab-btn {
            padding: 14px 24px;
            background: none;
            border: none;
            color: var(--text-muted);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .tab-btn:hover {
            color: var(--primary);
        }

        .tab-btn.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }

        .tab-count {
            background: var(--dark-lighter);
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 11px;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* Table Card */
        .table-card {
            background: var(--dark-light);
            border-radius: 16px;
            padding: 25px;
            border: 1px solid rgba(254, 161, 22, 0.1);
        }

        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 14px 12px;
            text-align: left;
            font-size: 14px;
        }

        th {
            color: var(--text-muted);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.5px;
            border-bottom: 2px solid rgba(254, 161, 22, 0.2);
        }

        td {
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            color: var(--text);
        }

        tr:hover td {
            background: rgba(254, 161, 22, 0.03);
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-badge.pending { background: rgba(245, 158, 11, 0.15); color: #fbbf24; }
        .status-badge.preparing { background: rgba(59, 130, 246, 0.15); color: #60a5fa; }
        .status-badge.ready { background: rgba(139, 92, 246, 0.15); color: #a78bfa; }
        .status-badge.served { background: rgba(16, 185, 129, 0.15); color: #34d399; }
        .status-badge.voided { background: rgba(239, 68, 68, 0.15); color: #f87171; }

        .order-id {
            font-weight: 700;
            color: var(--primary);
        }

        .customer-name {
            font-weight: 600;
        }

        .order-items {
            max-width: 200px;
            font-size: 13px;
            color: var(--text-muted);
            line-height: 1.4;
        }

        .amount {
            font-weight: 700;
            color: var(--success);
            font-size: 15px;
        }

        .voided-amount {
            font-weight: 700;
            color: var(--danger);
            font-size: 15px;
            text-decoration: line-through;
        }

        .table-num {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            font-weight: 600;
            font-size: 13px;
        }

        .timestamp {
            font-size: 12px;
            color: var(--text-muted);
        }

        .staff-name {
            font-size: 13px;
            color: var(--text-muted);
        }

        .void-reason {
            font-size: 12px;
            color: var(--danger);
            font-style: italic;
            max-width: 200px;
        }

        .voided-row {
            background: rgba(239, 68, 68, 0.05) !important;
        }

        .voided-row td {
            color: var(--text-muted);
        }

        /* Action Buttons */
        .action-btns {
            display: flex;
            gap: 6px;
        }

        .btn-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 13px;
        }

        .btn-view { background: rgba(59, 130, 246, 0.15); color: #60a5fa; }
        .btn-view:hover { background: rgba(59, 130, 246, 0.25); }

        .btn-void { background: rgba(239, 68, 68, 0.15); color: #f87171; }
        .btn-void:hover { background: rgba(239, 68, 68, 0.25); }

        .btn-status {
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-status.preparing { background: rgba(59, 130, 246, 0.15); color: #60a5fa; }
        .btn-status.ready { background: rgba(139, 92, 246, 0.15); color: #a78bfa; }
        .btn-status.served { background: rgba(16, 185, 129, 0.15); color: #34d399; }

        .btn-status:hover {
            transform: translateY(-1px);
            filter: brightness(1.2);
        }

        /* Alert */
        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #6ee7b7;
        }

        .alert-warning {
            background: rgba(245, 158, 11, 0.15);
            border: 1px solid rgba(245, 158, 11, 0.3);
            color: #fcd34d;
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
        }

        /* Modal */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s;
        }

        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .modal {
            background: var(--dark-light);
            border-radius: 20px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            transform: scale(0.9);
            transition: all 0.3s;
            border: 1px solid rgba(254, 161, 22, 0.2);
        }

        .modal-overlay.active .modal {
            transform: scale(1);
        }

        .modal-header {
            padding: 25px;
            border-bottom: 1px solid rgba(254, 161, 22, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            font-size: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-header h2 i {
            color: var(--primary);
        }

        .modal-close {
            background: none;
            border: none;
            color: var(--text-muted);
            font-size: 24px;
            cursor: pointer;
            transition: color 0.3s;
        }

        .modal-close:hover {
            color: var(--danger);
        }

        .modal-body {
            padding: 25px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            color: var(--text);
            font-size: 14px;
            margin-bottom: 8px;
            font-weight: 500;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid rgba(254, 161, 22, 0.2);
            border-radius: 10px;
            background: var(--dark);
            color: var(--text);
            font-size: 14px;
            transition: all 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .form-text {
            color: var(--text-muted);
            font-size: 12px;
            margin-top: 6px;
        }

        .modal-footer {
            padding: 20px 25px;
            border-top: 1px solid rgba(254, 161, 22, 0.1);
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .btn-secondary {
            background: var(--dark-lighter);
            color: var(--text);
        }

        .btn-secondary:hover {
            background: var(--dark);
        }

        .btn-danger {
            background: rgba(239, 68, 68, 0.2);
            color: var(--danger);
        }

        .btn-danger:hover {
            background: rgba(239, 68, 68, 0.3);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-state i {
            font-size: 64px;
            color: var(--dark-lighter);
            margin-bottom: 16px;
        }

        .empty-state h4 {
            color: var(--text-muted);
            margin-bottom: 8px;
        }

        .empty-state p {
            color: var(--text-muted);
            font-size: 14px;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .sidebar {
                width: 80px;
            }
            .sidebar-header span,
            .nav-item span,
            .logout-btn span,
            .staff-badge {
                display: none;
            }
            .main-content {
                margin-left: 80px;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                width: 280px;
            }
            .sidebar.active {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
            }
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .action-bar {
                flex-direction: column;
                align-items: stretch;
            }
            .search-box input {
                width: 100%;
            }
        }
    
        /* Inventory Styles */
        .inventory-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .inv-card {
            background: var(--dark-light);
            border-radius: 16px;
            padding: 25px;
            display: flex;
            align-items: center;
            gap: 18px;
            border: 1px solid rgba(254, 161, 22, 0.1);
            transition: all 0.3s ease;
        }

        .inv-card:hover {
            transform: translateY(-3px);
            border-color: rgba(254, 161, 22, 0.3);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .inv-icon {
            width: 55px;
            height: 55px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
        }

        .inv-icon.teal { background: rgba(20, 184, 166, 0.15); color: #2dd4bf; }
        .inv-icon.orange { background: rgba(249, 115, 22, 0.15); color: #fb923c; }
        .inv-icon.rose { background: rgba(244, 63, 94, 0.15); color: #fb7185; }
        .inv-icon.cyan { background: rgba(6, 182, 212, 0.15); color: #22d3ee; }

        .inv-info h3 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .inv-info p {
            color: var(--text-muted);
            font-size: 13px;
        }

        .stock-bar {
            width: 100%;
            height: 6px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 3px;
            overflow: hidden;
            margin-top: 8px;
        }

        .stock-fill {
            height: 100%;
            border-radius: 3px;
            transition: width 0.5s ease;
        }

        .qty-display {
            font-weight: 700;
            font-size: 15px;
        }

        .qty-low { color: var(--warning); }
        .qty-out { color: var(--danger); }
        .qty-ok { color: var(--success); }

        .category-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            background: rgba(254, 161, 22, 0.1);
            color: var(--primary);
        }

        .unit-text {
            color: var(--text-muted);
            font-size: 12px;
        }

        .cost-text {
            color: var(--success);
            font-weight: 600;
            font-size: 14px;
        }

        .filter-bar {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 8px 16px;
            border-radius: 10px;
            background: var(--dark-light);
            border: 1px solid rgba(254, 161, 22, 0.2);
            color: var(--text-muted);
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
        }

        .filter-btn:hover, .filter-btn.active {
            background: rgba(254, 161, 22, 0.15);
            color: var(--primary);
            border-color: var(--primary);
        }

        .adjust-input {
            width: 80px;
            padding: 8px 10px;
            border: 2px solid rgba(254, 161, 22, 0.2);
            border-radius: 8px;
            background: var(--dark);
            color: var(--text);
            font-size: 14px;
            text-align: center;
        }

        .adjust-input:focus {
            outline: none;
            border-color: var(--primary);
        }

        .history-row {
            background: rgba(254, 161, 22, 0.03);
        }

        .adjust-positive { color: var(--success); font-weight: 600; }
        .adjust-negative { color: var(--danger); font-weight: 600; }

        .supplier-text {
            font-size: 12px;
            color: var(--text-muted);
        }

        .last-updated {
            font-size: 11px;
            color: var(--text-muted);
        }

        @media (max-width: 768px) {
            .inventory-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

</style>
</head>
<body>
    <div class="staff-wrapper">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <i class="fas fa-utensils"></i>
                <span>Cafè Erlinda</span>
            </div>

            <nav class="sidebar-nav">
                <button class="nav-item active" onclick="showTab('orders')">
                    <i class="fas fa-receipt"></i>
                    <span>Orders</span>
                </button>
                <button class="nav-item" onclick="showTab('create')">
                    <i class="fas fa-plus-circle"></i>
                    <span>New Order</span>
                </button>
                <button class="nav-item" onclick="showTab('voided')">
                    <i class="fas fa-times-circle"></i>
                    <span>Voided</span>
                </button>
                <button class="nav-item" onclick="showTab('inventory')">
                    <i class="fas fa-boxes"></i>
                    <span>Inventory</span>
                </button>

            </nav>

            <div class="sidebar-footer">
                <a href="unified-logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <header class="content-header">
                <div class="header-left">
                    <h1><i class="fas fa-concierge-bell text-warning me-2"></i>Staff Dashboard</h1>
                    <p>Welcome back, <?php echo htmlspecialchars($staff_name); ?>!</p>
                </div>
                <div class="header-right">
                    <div class="date-display">
                        <i class="fas fa-calendar"></i>
                        <span><?php echo date('F d, Y'); ?></span>
                    </div>
                    <div class="staff-info">
                        <div class="staff-avatar"><?php echo strtoupper(substr($staff_name, 0, 2)); ?></div>
                        <div class="staff-details">
                            <p><?php echo htmlspecialchars($staff_name); ?></p>
                            <small><?php echo ucfirst($staff_role); ?></small>
                        </div>
                        <span class="staff-badge badge-<?php echo $staff_role; ?>"><?php echo ucfirst($staff_role); ?></span>
                    </div>
                </div>
            </header>

            <!-- Alerts -->
            <?php if (isset($_GET['success']) && $_GET['success'] === 'order_created'): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                Order created successfully!
            </div>
            <?php endif; ?>

            <?php if (isset($_GET['success']) && $_GET['success'] === 'order_voided'): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i>
                Order has been voided successfully.
            </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error; ?>
            </div>
            <?php endif; ?>

            <!-- Stats Grid -->
            <section class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon blue">
                        <i class="fas fa-receipt"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['today_total']; ?></h3>
                        <p>Today's Orders</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon green">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-info">
                        <h3 style="color: var(--success);">$<?php echo number_format($stats['today_revenue'], 2); ?></h3>
                        <p>Today's Revenue</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon amber">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <h3 style="color: var(--warning);"><?php echo $stats['pending']; ?></h3>
                        <p>Pending</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon purple">
                        <i class="fas fa-fire"></i>
                    </div>
                    <div class="stat-info">
                        <h3 style="color: var(--purple);"><?php echo $stats['preparing']; ?></h3>
                        <p>Preparing</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon red">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3 style="color: var(--danger);"><?php echo $stats['voided_today']; ?></h3>
                        <p>Voided Today</p>
                    </div>
                </div>
            </section>

            <!-- Orders Tab Content -->
            <div id="orders-tab" class="tab-content active">
                <div class="action-bar">
                    <div class="tabs">
                        <button class="tab-btn active" onclick="switchSubTab('active')">
                            <i class="fas fa-fire"></i> Active
                            <span class="tab-count"><?php echo count($active_orders); ?></span>
                        </button>
                        <button class="tab-btn" onclick="switchSubTab('today')">
                            <i class="fas fa-calendar-day"></i> Today
                            <span class="tab-count"><?php echo count($today_orders); ?></span>
                        </button>
                    </div>
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="searchOrders" placeholder="Search orders..." oninput="searchTable('ordersTable', this.value)">
                    </div>
                </div>

                <!-- Active Orders -->
                <div id="active-subtab" class="sub-tab-content active">
                    <div class="table-card">
                        <div class="table-responsive">
                            <table id="ordersTable">
                                <thead>
                                    <tr>
                                        <th>Order #</th>
                                        <th>Customer</th>
                                        <th>Type</th>
                                        <th>Items</th>
                                        <th>Table</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($active_orders)): ?>
                                    <tr>
                                        <td colspan="8">
                                            <div class="empty-state">
                                                <i class="fas fa-clipboard-list"></i>
                                                <h4>No active orders</h4>
                                                <p>Create a new order to get started</p>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach ($active_orders as $order): ?>
                                    <tr>
                                        <td><span class="order-id">#<?php echo $order['order_id']; ?></span></td>
                                        <td>
                                            <div class="customer-name"><?php echo htmlspecialchars($order['customer_name']); ?></div>
                                            <div class="timestamp"><?php echo date('h:i A', strtotime($order['created_at'])); ?></div>
                                        </td>
                                        <td><?php echo getOrderTypeIcon($order['order_type']); ?> <span class="text-capitalize"><?php echo str_replace('_', ' ', $order['order_type']); ?></span></td>
                                        <td><div class="order-items"><?php echo htmlspecialchars($order['items']); ?></div></td>
                                        <td>
                                            <?php if ($order['table_number']): ?>
                                            <span class="table-num"><i class="fas fa-chair"></i> <?php echo $order['table_number']; ?></span>
                                            <?php else: ?><span class="timestamp">-</span><?php endif; ?>
                                        </td>
                                        <td><span class="amount">$<?php echo number_format($order['total_amount'], 2); ?></span></td>
                                        <td><?php echo getStatusBadge($order['status']); ?></td>
                                        <td>
                                            <div class="action-btns">
                                                <?php if ($order['status'] === 'pending'): ?>
                                                <a href="?action=update_status&id=<?php echo $order['order_id']; ?>&status=preparing" class="btn-status preparing"><i class="fas fa-fire"></i> Prep</a>
                                                <?php elseif ($order['status'] === 'preparing'): ?>
                                                <a href="?action=update_status&id=<?php echo $order['order_id']; ?>&status=ready" class="btn-status ready"><i class="fas fa-bell"></i> Ready</a>
                                                <?php elseif ($order['status'] === 'ready'): ?>
                                                <a href="?action=update_status&id=<?php echo $order['order_id']; ?>&status=served" class="btn-status served"><i class="fas fa-check"></i> Served</a>
                                                <?php endif; ?>
                                                <button class="btn-icon btn-void" onclick="openVoidModal(<?php echo $order['order_id']; ?>, '<?php echo htmlspecialchars($order['customer_name']); ?>', <?php echo $order['total_amount']; ?>)"><i class="fas fa-times"></i></button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Today's Orders -->
                <div id="today-subtab" class="sub-tab-content" style="display: none;">
                    <div class="table-card">
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Order #</th>
                                        <th>Customer</th>
                                        <th>Type</th>
                                        <th>Items</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($today_orders)): ?>
                                    <tr>
                                        <td colspan="7">
                                            <div class="empty-state">
                                                <i class="fas fa-calendar-day"></i>
                                                <h4>No orders today</h4>
                                                <p>Orders placed today will appear here</p>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach ($today_orders as $order): ?>
                                    <tr class="<?php echo $order['status'] === 'voided' ? 'voided-row' : ''; ?>">
                                        <td><span class="order-id">#<?php echo $order['order_id']; ?></span></td>
                                        <td><div class="customer-name"><?php echo htmlspecialchars($order['customer_name']); ?></div></td>
                                        <td><?php echo getOrderTypeIcon($order['order_type']); ?> <span class="text-capitalize"><?php echo str_replace('_', ' ', $order['order_type']); ?></span></td>
                                        <td><div class="order-items"><?php echo htmlspecialchars($order['items']); ?></div></td>
                                        <td>
                                            <?php if ($order['status'] === 'voided'): ?>
                                            <span class="voided-amount">$<?php echo number_format($order['total_amount'], 2); ?></span>
                                            <?php else: ?>
                                            <span class="amount">$<?php echo number_format($order['total_amount'], 2); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo getStatusBadge($order['status']); ?></td>
                                        <td><span class="timestamp"><?php echo date('h:i A', strtotime($order['created_at'])); ?></span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Create Order Tab -->
            <div id="create-tab" class="tab-content">
                <div class="table-card">
                    <h3 style="margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-plus-circle text-success"></i> Create New Order
                    </h3>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="create_order">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Customer Name *</label>
                                <input type="text" name="customer_name" placeholder="Enter customer name" required>
                            </div>
                            <div class="form-group">
                                <label>Order Type *</label>
                                <select name="order_type" required>
                                    <option value="dine_in">Dine In</option>
                                    <option value="takeout">Takeout</option>
                                    <option value="delivery">Delivery</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Table Number</label>
                                <input type="text" name="table_number" placeholder="e.g., T-05">
                                <div class="form-text">Leave empty for takeout/delivery</div>
                            </div>
                            <div class="form-group">
                                <label>Total Amount ($) *</label>
                                <input type="number" name="total_amount" step="0.01" min="0" placeholder="0.00" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Order Items *</label>
                            <textarea name="items" rows="3" placeholder="e.g., Classic Chicken Burger x2, Iced Caramel Latte x2" required></textarea>
                        </div>
                        <div class="form-group">
                            <label>Notes</label>
                            <textarea name="notes" rows="2" placeholder="Any special instructions..."></textarea>
                        </div>
                        <div style="display: flex; gap: 10px; justify-content: flex-end;">
                            <button type="button" class="btn btn-secondary" onclick="showTab('orders')">Cancel</button>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Create Order</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Voided Tab -->
            <div id="voided-tab" class="tab-content">
                <div class="action-bar">
                    <h3 style="display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-times-circle text-danger"></i> Voided Orders
                    </h3>
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" placeholder="Search voided orders..." oninput="searchTable('voidedTable', this.value)">
                    </div>
                </div>
                <div class="table-card">
                    <div class="table-responsive">
                        <table id="voidedTable">
                            <thead>
                                <tr>
                                    <th>Order #</th>
                                    <th>Customer</th>
                                    <th>Items</th>
                                    <th>Amount</th>
                                    <th>Voided By</th>
                                    <th>Reason</th>
                                    <th>Voided At</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($voided_orders)): ?>
                                <tr>
                                    <td colspan="7">
                                        <div class="empty-state">
                                            <i class="fas fa-check-circle"></i>
                                            <h4>No voided orders</h4>
                                            <p>Voided orders will appear here with audit details</p>
                                        </div>
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($voided_orders as $order): ?>
                                <tr class="voided-row">
                                    <td><span class="order-id">#<?php echo $order['order_id']; ?></span></td>
                                    <td><div class="customer-name"><?php echo htmlspecialchars($order['customer_name']); ?></div></td>
                                    <td><div class="order-items"><?php echo htmlspecialchars($order['items']); ?></div></td>
                                    <td><span class="voided-amount">$<?php echo number_format($order['total_amount'], 2); ?></span></td>
                                    <td><span class="staff-name"><i class="fas fa-user me-1"></i><?php echo htmlspecialchars($order['voided_by_name'] ?? 'Unknown'); ?></span></td>
                                    <td><div class="void-reason"><i class="fas fa-quote-left me-1"></i><?php echo htmlspecialchars($order['void_reason'] ?? 'No reason'); ?></div></td>
                                    <td><span class="timestamp"><?php echo $order['void_time'] ? date('M d, h:i A', strtotime($order['void_time'])) : 'N/A'; ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Inventory Tab -->
            <div id="inventory-tab" class="tab-content">
                <!-- Inventory Stats -->
                <section class="inventory-grid">
                    <div class="inv-card">
                        <div class="inv-icon teal">
                            <i class="fas fa-boxes"></i>
                        </div>
                        <div class="inv-info">
                            <h3 style="color: #2dd4bf;"><?php echo $inventory_stats['total_items']; ?></h3>
                            <p>Total Items</p>
                        </div>
                    </div>
                    <div class="inv-card">
                        <div class="inv-icon orange">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="inv-info">
                            <h3 style="color: #fb923c;"><?php echo $inventory_stats['low_stock']; ?></h3>
                            <p>Low Stock</p>
                        </div>
                    </div>
                    <div class="inv-card">
                        <div class="inv-icon rose">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <div class="inv-info">
                            <h3 style="color: #fb7185;"><?php echo $inventory_stats['out_of_stock']; ?></h3>
                            <p>Out of Stock</p>
                        </div>
                    </div>
                    <div class="inv-card">
                        <div class="inv-icon cyan">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                        <div class="inv-info">
                            <h3 style="color: #22d3ee;">$<?php echo number_format($inventory_stats['total_value'], 2); ?></h3>
                            <p>Total Value</p>
                        </div>
                    </div>
                </section>

                <!-- Inventory Alerts -->
                <?php if (isset($_GET['success']) && $_GET['success'] === 'inv_added'): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    Inventory item added successfully!
                </div>
                <?php endif; ?>
                <?php if (isset($_GET['success']) && $_GET['success'] === 'inv_updated'): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    Inventory item updated successfully!
                </div>
                <?php endif; ?>
                <?php if (isset($_GET['success']) && $_GET['success'] === 'inv_adjusted'): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    Stock adjusted successfully!
                </div>
                <?php endif; ?>
                <?php if (isset($_GET['success']) && $_GET['success'] === 'inv_deleted'): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-trash"></i>
                    Inventory item deleted.
                </div>
                <?php endif; ?>

                <!-- Action Bar -->
                <div class="action-bar">
                    <div class="tabs">
                        <button class="tab-btn active" onclick="switchInvTab('items')">
                            <i class="fas fa-boxes"></i> Items
                            <span class="tab-count"><?php echo count($inventory_items); ?></span>
                        </button>
                        <button class="tab-btn" onclick="switchInvTab('history')">
                            <i class="fas fa-history"></i> History
                            <span class="tab-count"><?php echo count($adjustments); ?></span>
                        </button>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" id="searchInventory" placeholder="Search items..." oninput="searchTable('inventoryTable', this.value)">
                        </div>
                        <button class="btn btn-primary" onclick="openAddInventoryModal()">
                            <i class="fas fa-plus"></i> Add Item
                        </button>
                    </div>
                </div>

                <!-- Items Sub-tab -->
                <div id="items-invsub" class="inv-sub-tab-content active">
                    <!-- Category Filters -->
                    <div class="filter-bar">
                        <button class="filter-btn active" onclick="filterCategory('all', this)">All</button>
                        <?php foreach ($inventory_categories as $cat): ?>
                        <button class="filter-btn" onclick="filterCategory('<?php echo htmlspecialchars($cat); ?>', this)"><?php echo ucfirst(htmlspecialchars($cat)); ?></button>
                        <?php endforeach; ?>
                    </div>

                    <div class="table-card">
                        <div class="table-responsive">
                            <table id="inventoryTable">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th>Category</th>
                                        <th>Stock Level</th>
                                        <th>Min. Stock</th>
                                        <th>Unit Cost</th>
                                        <th>Supplier</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($inventory_items)): ?>
                                    <tr>
                                        <td colspan="8">
                                            <div class="empty-state">
                                                <i class="fas fa-box-open"></i>
                                                <h4>No inventory items</h4>
                                                <p>Add items to start tracking your inventory</p>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach ($inventory_items as $item): ?>
                                    <tr data-category="<?php echo htmlspecialchars($item['category']); ?>">
                                        <td>
                                            <div class="customer-name"><?php echo htmlspecialchars($item['item_name']); ?></div>
                                            <div class="unit-text"><?php echo htmlspecialchars($item['unit']); ?></div>
                                        </td>
                                        <td><span class="category-badge"><i class="fas fa-tag"></i> <?php echo ucfirst(htmlspecialchars($item['category'])); ?></span></td>
                                        <td>
                                            <span class="qty-display <?php echo (float)$item['quantity'] == 0 ? 'qty-out' : ((float)$item['quantity'] <= (float)$item['min_stock'] ? 'qty-low' : 'qty-ok'); ?>">
                                                <?php echo number_format($item['quantity'], 2); ?> <?php echo htmlspecialchars($item['unit']); ?>
                                            </span>
                                            <?php echo getStockBar($item); ?>
                                        </td>
                                        <td><span class="timestamp"><?php echo number_format($item['min_stock'], 2); ?> <?php echo htmlspecialchars($item['unit']); ?></span></td>
                                        <td><span class="cost-text">$<?php echo number_format($item['unit_cost'], 2); ?></span></td>
                                        <td><span class="supplier-text"><i class="fas fa-truck me-1"></i><?php echo htmlspecialchars($item['supplier'] ?: 'N/A'); ?></span></td>
                                        <td><?php echo getStockStatus($item); ?></td>
                                        <td>
                                            <div class="action-btns">
                                                <button class="btn-icon btn-view" onclick="openEditInventoryModal(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['item_name']); ?>', <?php echo $item['quantity']; ?>, <?php echo $item['unit_cost']; ?>, <?php echo $item['min_stock']; ?>, '<?php echo htmlspecialchars($item['notes'] ?? ''); ?>')" title="Edit"><i class="fas fa-edit"></i></button>
                                                <button class="btn-icon btn-void" onclick="openAdjustModal(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['item_name']); ?>', <?php echo $item['quantity']; ?>)" title="Adjust Stock"><i class="fas fa-balance-scale"></i></button>
                                                <form method="POST" action="" style="display: inline;" onsubmit="return confirm('Delete this item?');">
                                                    <input type="hidden" name="action" value="delete_inventory">
                                                    <input type="hidden" name="inv_id" value="<?php echo $item['id']; ?>">
                                                    <button type="submit" class="btn-icon btn-void" title="Delete"><i class="fas fa-trash"></i></button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- History Sub-tab -->
                <div id="history-invsub" class="inv-sub-tab-content" style="display: none;">
                    <div class="table-card">
                        <div class="table-responsive">
                            <table id="adjustmentsTable">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th>Previous</th>
                                        <th>Adjustment</th>
                                        <th>New Qty</th>
                                        <th>Reason</th>
                                        <th>Adjusted By</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($adjustments)): ?>
                                    <tr>
                                        <td colspan="7">
                                            <div class="empty-state">
                                                <i class="fas fa-history"></i>
                                                <h4>No adjustments yet</h4>
                                                <p>Stock adjustments will be logged here</p>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach ($adjustments as $adj): ?>
                                    <tr class="history-row">
                                        <td><div class="customer-name"><?php echo htmlspecialchars($adj['item_name']); ?></div></td>
                                        <td><span class="timestamp"><?php echo number_format($adj['previous_qty'], 2); ?></span></td>
                                        <td>
                                            <span class="<?php echo $adj['adjustment'] >= 0 ? 'adjust-positive' : 'adjust-negative'; ?>">
                                                <i class="fas fa-<?php echo $adj['adjustment'] >= 0 ? 'arrow-up' : 'arrow-down'; ?> me-1"></i>
                                                <?php echo ($adj['adjustment'] >= 0 ? '+' : '') . number_format($adj['adjustment'], 2); ?>
                                            </span>
                                        </td>
                                        <td><span class="qty-display qty-ok"><?php echo number_format($adj['new_qty'], 2); ?></span></td>
                                        <td><div class="void-reason"><i class="fas fa-quote-left me-1"></i><?php echo htmlspecialchars($adj['reason'] ?: 'No reason'); ?></div></td>
                                        <td><span class="staff-name"><i class="fas fa-user me-1"></i><?php echo htmlspecialchars($adj['adjusted_by_name']); ?></span></td>
                                        <td><span class="timestamp"><?php echo date('M d, h:i A', strtotime($adj['adjusted_at'])); ?></span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </main>
    </div>

    <!-- Void Modal -->
    <div class="modal-overlay" id="voidModal">
        <div class="modal">
            <div class="modal-header">
                <h2><i class="fas fa-exclamation-triangle text-danger"></i> Void Order</h2>
                <button class="modal-close" onclick="closeVoidModal()">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="void_order">
                <input type="hidden" name="order_id" id="voidOrderId">
                <div class="modal-body">
                    <div class="alert alert-warning" style="margin-bottom: 20px;">
                        <i class="fas fa-exclamation-triangle"></i>
                        This action cannot be undone. The order will be marked as voided.
                    </div>
                    <div class="form-group">
                        <label>Void Reason *</label>
                        <textarea name="void_reason" rows="3" placeholder="Enter reason for voiding this order..." required></textarea>
                        <div class="form-text">This reason will be visible to admin and other staff</div>
                    </div>
                    <div style="background: var(--dark); padding: 15px; border-radius: 10px; margin-top: 15px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                            <span class="timestamp">Customer:</span>
                            <span class="customer-name" id="voidCustomerName"></span>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span class="timestamp">Amount:</span>
                            <span style="color: var(--danger); font-weight: 700;" id="voidAmount"></span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeVoidModal()">Cancel</button>
                    <button type="submit" class="btn btn-danger"><i class="fas fa-times-circle"></i> Void Order</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Tab switching
        function showTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
            document.getElementById(tabName + '-tab').classList.add('active');
            event.currentTarget.classList.add('active');
        }

        // Sub-tab switching
        function switchSubTab(tabName) {
            document.querySelectorAll('.sub-tab-content').forEach(t => t.style.display = 'none');
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.getElementById(tabName + '-subtab').style.display = 'block';
            event.currentTarget.classList.add('active');
        }

        // Search table
        function searchTable(tableId, searchTerm) {
            const rows = document.querySelectorAll('#' + tableId + ' tbody tr');
            searchTerm = searchTerm.toLowerCase();
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        }

        // Void modal
        function openVoidModal(orderId, customerName, amount) {
            document.getElementById('voidOrderId').value = orderId;
            document.getElementById('voidCustomerName').textContent = customerName;
            document.getElementById('voidAmount').textContent = '$' + parseFloat(amount).toFixed(2);
            document.getElementById('voidModal').classList.add('active');
        }

        function closeVoidModal() {
            document.getElementById('voidModal').classList.remove('active');
        }

        document.getElementById('voidModal').addEventListener('click', function(e) {
            if (e.target === this) closeVoidModal();
        });

        // Auto-refresh every 30 seconds
        setTimeout(function() {
            location.reload();
        }, 30000);

        // Inventory tab switching
        function switchInvTab(tabName) {
            document.querySelectorAll('.inv-sub-tab-content').forEach(t => t.style.display = 'none');
            document.querySelectorAll('#inventory-tab .tab-btn').forEach(b => b.classList.remove('active'));
            document.getElementById(tabName + '-invsub').style.display = 'block';
            event.currentTarget.classList.add('active');
        }

        // Category filter
        function filterCategory(category, btn) {
            const rows = document.querySelectorAll('#inventoryTable tbody tr');
            rows.forEach(row => {
                if (category === 'all') {
                    row.style.display = '';
                } else {
                    const cat = row.getAttribute('data-category');
                    row.style.display = (cat === category) ? '' : 'none';
                }
            });
            document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
        }

        // Add Inventory Modal
        function openAddInventoryModal() {
            document.getElementById('addInventoryModal').classList.add('active');
        }
        function closeAddInventoryModal() {
            document.getElementById('addInventoryModal').classList.remove('active');
        }
        document.getElementById('addInventoryModal').addEventListener('click', function(e) {
            if (e.target === this) closeAddInventoryModal();
        });

        // Edit Inventory Modal
        function openEditInventoryModal(id, name, qty, cost, min, notes) {
            document.getElementById('editInvId').value = id;
            document.getElementById('editInvQty').value = qty;
            document.getElementById('editInvCost').value = cost;
            document.getElementById('editInvMin').value = min;
            document.getElementById('editInvNotes').value = notes;
            document.getElementById('editInventoryModal').classList.add('active');
        }
        function closeEditInventoryModal() {
            document.getElementById('editInventoryModal').classList.remove('active');
        }
        document.getElementById('editInventoryModal').addEventListener('click', function(e) {
            if (e.target === this) closeEditInventoryModal();
        });

        // Adjust Stock Modal
        function openAdjustModal(id, name, qty) {
            document.getElementById('adjustInvId').value = id;
            document.getElementById('adjustItemName').textContent = name;
            document.getElementById('adjustCurrentQty').textContent = parseFloat(qty).toFixed(2);
            document.getElementById('adjustStockModal').classList.add('active');
        }
        function closeAdjustModal() {
            document.getElementById('adjustStockModal').classList.remove('active');
        }
        document.getElementById('adjustStockModal').addEventListener('click', function(e) {
            if (e.target === this) closeAdjustModal();
        });

        // URL tab parameter handling
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('tab') === 'inventory') {
            document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
            document.getElementById('inventory-tab').classList.add('active');
            document.querySelector('.nav-item[onclick*="inventory"]').classList.add('active');
        }

    </script>

    <!-- Add Inventory Modal -->
    <div class="modal-overlay" id="addInventoryModal">
        <div class="modal">
            <div class="modal-header">
                <h2><i class="fas fa-plus-circle text-success"></i> Add Inventory Item</h2>
                <button class="modal-close" onclick="closeAddInventoryModal()">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="add_inventory">
                <div class="modal-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Item Name *</label>
                            <input type="text" name="item_name" placeholder="e.g., Coffee Beans" required>
                        </div>
                        <div class="form-group">
                            <label>Category *</label>
                            <input type="text" name="category" placeholder="e.g., Beverages, Food, Supplies" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Quantity *</label>
                            <input type="number" name="quantity" step="0.01" min="0" placeholder="0.00" required>
                        </div>
                        <div class="form-group">
                            <label>Unit *</label>
                            <input type="text" name="unit" placeholder="e.g., kg, pcs, liters" value="pcs" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Min. Stock Level *</label>
                            <input type="number" name="min_stock" step="0.01" min="0" placeholder="Alert when below this" required>
                            <div class="form-text">You'll be alerted when stock falls below this level</div>
                        </div>
                        <div class="form-group">
                            <label>Unit Cost ($)</label>
                            <input type="number" name="unit_cost" step="0.01" min="0" placeholder="0.00">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Supplier</label>
                        <input type="text" name="supplier" placeholder="e.g., ABC Suppliers">
                    </div>
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea name="notes" rows="2" placeholder="Any additional notes..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeAddInventoryModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Add Item</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Inventory Modal -->
    <div class="modal-overlay" id="editInventoryModal">
        <div class="modal">
            <div class="modal-header">
                <h2><i class="fas fa-edit text-info"></i> Edit Inventory Item</h2>
                <button class="modal-close" onclick="closeEditInventoryModal()">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="update_inventory">
                <input type="hidden" name="inv_id" id="editInvId">
                <div class="modal-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Quantity *</label>
                            <input type="number" name="quantity" id="editInvQty" step="0.01" min="0" required>
                        </div>
                        <div class="form-group">
                            <label>Min. Stock Level *</label>
                            <input type="number" name="min_stock" id="editInvMin" step="0.01" min="0" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Unit Cost ($)</label>
                        <input type="number" name="unit_cost" id="editInvCost" step="0.01" min="0">
                    </div>
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea name="notes" id="editInvNotes" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeEditInventoryModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Item</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Adjust Stock Modal -->
    <div class="modal-overlay" id="adjustStockModal">
        <div class="modal">
            <div class="modal-header">
                <h2><i class="fas fa-balance-scale text-warning"></i> Adjust Stock</h2>
                <button class="modal-close" onclick="closeAdjustModal()">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="adjust_stock">
                <input type="hidden" name="inv_id" id="adjustInvId">
                <div class="modal-body">
                    <div class="alert alert-warning" style="margin-bottom: 20px;">
                        <i class="fas fa-info-circle"></i>
                        Use positive numbers to add stock, negative to remove.
                    </div>
                    <div style="background: var(--dark); padding: 15px; border-radius: 10px; margin-bottom: 20px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                            <span class="timestamp">Item:</span>
                            <span class="customer-name" id="adjustItemName"></span>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span class="timestamp">Current Stock:</span>
                            <span style="color: var(--primary); font-weight: 700;" id="adjustCurrentQty"></span>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Adjustment *</label>
                            <input type="number" name="adjustment" step="0.01" placeholder="e.g., +10 or -5" required>
                            <div class="form-text">Positive = restock, Negative = usage/waste</div>
                        </div>
                        <div class="form-group">
                            <label>Reason *</label>
                            <input type="text" name="adjust_reason" placeholder="e.g., Restock, Spoilage, Usage" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeAdjustModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Apply Adjustment</button>
                </div>
            </form>
        </div>
    </div>

</body>
</html> 