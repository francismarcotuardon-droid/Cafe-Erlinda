<?php
require_once 'session_check.php';
require_once 'db_connect.php';

// Fetch all orders with staff info
$all_orders = [];
$orders_query = "SELECT o.*, s.full_name as staff_name 
               FROM orders o 
               LEFT JOIN staff s ON o.staff_id = s.staff_id 
               ORDER BY o.created_at DESC 
               LIMIT 100";
$orders_result = mysqli_query($conn, $orders_query);

if ($orders_result) {
    while ($row = mysqli_fetch_assoc($orders_result)) {
        $all_orders[] = $row;
    }
}

// Fetch void logs
$void_logs = [];
$logs_query = "SELECT v.*, o.customer_name, o.total_amount as order_amount, o.items 
               FROM void_logs v 
               LEFT JOIN orders o ON v.order_id = o.order_id 
               ORDER BY v.voided_at DESC 
               LIMIT 50";
$logs_result = mysqli_query($conn, $logs_query);

if ($logs_result) {
    while ($row = mysqli_fetch_assoc($logs_result)) {
        $void_logs[] = $row;
    }
}

// Calculate stats
$total_orders = count($all_orders);
$total_revenue = 0;
$voided_count = 0;
$voided_amount = 0;

foreach ($all_orders as $order) {
    if ($order['status'] === 'voided') {
        $voided_count++;
        $voided_amount += (float)$order['total_amount'];
    } else {
        $total_revenue += (float)$order['total_amount'];
    }
}

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders & Voids - Cafè Erlinda Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Heebo:wght@400;500;600&family=Nunito:wght@600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="admin-style.css">
    <style>
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

        .voided-row { background: rgba(239, 68, 68, 0.05) !important; }
        .voided-row td { color: #94a3b8; }
        .voided-amount { color: #f87171; font-weight: 700; text-decoration: line-through; }
        .amount { color: #34d399; font-weight: 700; }

        .order-id { font-weight: 700; color: #FEA116; }
        .void-reason { font-size: 12px; color: #f87171; font-style: italic; }
        .staff-name { font-size: 13px; color: #94a3b8; }
        .timestamp { font-size: 12px; color: #64748b; }
        .order-items { max-width: 200px; font-size: 13px; color: #94a3b8; }

        .tabs { display: flex; gap: 5px; border-bottom: 2px solid rgba(254, 161, 22, 0.1); margin-bottom: 25px; }
        .tab-btn {
            padding: 14px 24px; background: none; border: none; color: #94a3b8;
            font-size: 14px; font-weight: 600; cursor: pointer;
            border-bottom: 2px solid transparent; margin-bottom: -2px;
            transition: all 0.3s; display: flex; align-items: center; gap: 8px;
        }
        .tab-btn:hover { color: #FEA116; }
        .tab-btn.active { color: #FEA116; border-bottom-color: #FEA116; }
        .tab-count { background: #334155; padding: 2px 8px; border-radius: 10px; font-size: 11px; }

        .sub-tab-content { display: none; }
        .sub-tab-content.active { display: block; }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <!-- Sidebar -->
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
                <a href="admin-orders.php" class="nav-item active">
                    <i class="fas fa-receipt"></i>
                    <span>Orders & Voids</span>
                </a>
                <a href="inventory.php" class="nav-item">
                    <i class="fas fa-boxes"></i>
                    <span>Inventory</span>
                </a>
                <a href="settings.php" class="nav-item">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
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
            <!-- Page Header -->
            <div class="page-header">
                <div class="page-title">
                    <h1><i class="fas fa-receipt text-warning me-2"></i> Orders & Void Management</h1>
                    <p>View all orders and voided transactions across staff</p>
                </div>
                <div class="header-actions">
                    <a href="staff-dashboard.php" target="_blank" class="btn btn-outline" style="margin-right: 10px;">
                        <i class="fas fa-user-tie"></i> Staff Portal
                    </a>
                    <a href="index.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>

            <!-- Stats Grid -->
            <section class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
                <div class="stat-card">
                    <div class="stat-icon table-icon">
                        <i class="fas fa-receipt"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $total_orders; ?></h3>
                        <p>Total Orders</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon today-icon">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-info">
                        <h3 style="color: #10b981;">$<?php echo number_format($total_revenue, 2); ?></h3>
                        <p>Total Revenue</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(239, 68, 68, 0.15); color: #ef4444;">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3 style="color: #ef4444;"><?php echo $voided_count; ?></h3>
                        <p>Voided Orders</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(239, 68, 68, 0.15); color: #ef4444;">
                        <i class="fas fa-ban"></i>
                    </div>
                    <div class="stat-info">
                        <h3 style="color: #ef4444;">$<?php echo number_format($voided_amount, 2); ?></h3>
                        <p>Voided Amount</p>
                    </div>
                </div>
            </section>

            <!-- Tabs -->
            <div class="tabs">
                <button class="tab-btn active" onclick="switchTab('all')">
                    <i class="fas fa-list"></i> All Orders
                    <span class="tab-count"><?php echo count($all_orders); ?></span>
                </button>
                <button class="tab-btn" onclick="switchTab('voided')">
                    <i class="fas fa-times-circle"></i> Voided
                    <span class="tab-count"><?php echo count($voided_logs); ?></span>
                </button>
            </div>

            <!-- All Orders Tab -->
            <div id="all-tab" class="sub-tab-content active">
                <div class="table-card full-width">
                    <div class="table-header" style="margin-bottom: 15px;">
                        <h3><i class="fas fa-list-ul text-warning me-2"></i>All Orders</h3>
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" placeholder="Search orders..." oninput="searchTable('allOrdersTable', this.value)">
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table id="allOrdersTable">
                            <thead>
                                <tr>
                                    <th>Order #</th>
                                    <th>Customer</th>
                                    <th>Type</th>
                                    <th>Items</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Staff</th>
                                    <th>Created</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($all_orders)): ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; padding: 40px;">
                                        <i class="fas fa-receipt" style="font-size: 48px; color: var(--text-muted); margin-bottom: 15px;"></i>
                                        <p>No orders found</p>
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($all_orders as $order): ?>
                                <tr class="<?php echo $order['status'] === 'voided' ? 'voided-row' : ''; ?>">
                                    <td><span class="order-id">#<?php echo $order['order_id']; ?></span></td>
                                    <td><div style="font-weight: 600;"><?php echo htmlspecialchars($order['customer_name']); ?></div></td>
                                    <td><span class="text-capitalize"><?php echo str_replace('_', ' ', $order['order_type']); ?></span></td>
                                    <td><div class="order-items"><?php echo htmlspecialchars($order['items']); ?></div></td>
                                    <td>
                                        <?php if ($order['status'] === 'voided'): ?>
                                        <span class="voided-amount">$<?php echo number_format($order['total_amount'], 2); ?></span>
                                        <?php else: ?>
                                        <span class="amount">$<?php echo number_format($order['total_amount'], 2); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo getStatusBadge($order['status']); ?></td>
                                    <td><span class="staff-name"><i class="fas fa-user me-1"></i><?php echo htmlspecialchars($order['staff_name'] ?? 'Unknown'); ?></span></td>
                                    <td><span class="timestamp"><?php echo date('M d, h:i A', strtotime($order['created_at'])); ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Voided Tab -->
            <div id="voided-tab" class="sub-tab-content">
                <div class="table-card full-width">
                    <div class="table-header" style="margin-bottom: 15px;">
                        <h3><i class="fas fa-history text-danger me-2"></i>Void Audit Log</h3>
                        <span class="badge" style="background: rgba(239, 68, 68, 0.15); color: #f87171;"><?php echo count($void_logs); ?> Records</span>
                    </div>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Log #</th>
                                    <th>Order #</th>
                                    <th>Customer</th>
                                    <th>Original Amount</th>
                                    <th>Voided By</th>
                                    <th>Void Reason</th>
                                    <th>Voided At</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($void_logs)): ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 40px;">
                                        <i class="fas fa-check-circle" style="font-size: 48px; color: var(--text-muted); margin-bottom: 15px;"></i>
                                        <p>No voided orders recorded</p>
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($void_logs as $log): ?>
                                <tr class="voided-row">
                                    <td><span class="order-id">#<?php echo $log['log_id']; ?></span></td>
                                    <td><strong>#<?php echo $log['order_id']; ?></strong></td>
                                    <td><?php echo htmlspecialchars($log['customer_name'] ?? 'Unknown'); ?></td>
                                    <td><span class="voided-amount">$<?php echo number_format($log['order_amount'] ?? 0, 2); ?></span></td>
                                    <td><span class="staff-name"><i class="fas fa-user me-1"></i><?php echo htmlspecialchars($log['voided_by_name']); ?></span></td>
                                    <td><div class="void-reason"><i class="fas fa-quote-left me-1"></i><?php echo htmlspecialchars($log['void_reason']); ?></div></td>
                                    <td><span class="timestamp"><?php echo date('M d, h:i A', strtotime($log['voided_at'])); ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        function switchTab(tabName) {
            document.querySelectorAll('.sub-tab-content').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.getElementById(tabName + '-tab').classList.add('active');
            event.currentTarget.classList.add('active');
        }

        function searchTable(tableId, searchTerm) {
            const rows = document.querySelectorAll('#' + tableId + ' tbody tr');
            searchTerm = searchTerm.toLowerCase();
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        }
    </script>
</body>
</html>
