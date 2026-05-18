<?php
require_once 'session_check.php';
require_once 'db_connect.php';

// Get statistics
$table_bookings_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM table_form"))['count'];
$catering_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM reservations"))['count'];

// Get today's bookings
$today = date('Y-m-d');
$today_table = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM table_form WHERE DATE(tdate_time) = '$today'"))['count'];
$today_catering = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM reservations WHERE DATE(cevent_date) = '$today'"))['count'];

// Get recent bookings
$recent_tables = mysqli_query($conn, "SELECT * FROM table_form ORDER BY created DESC LIMIT 5");
$recent_catering = mysqli_query($conn, "SELECT * FROM reservations ORDER BY created_at DESC LIMIT 5");

// Get event type distribution for chart
$event_types = mysqli_query($conn, "SELECT cevent_type, COUNT(*) as count FROM reservations GROUP BY cevent_type");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Cafè Erlinda Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Heebo:wght@400;500;600&family=Nunito:wght@600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="admin-style.css">
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
                <a href="index.php" class="nav-item active">
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
            <!-- Header -->
            <header class="content-header">
                <div class="header-left">
                    <h1>Dashboard Overview</h1>
                    <p class="breadcrumb">Welcome back, <?php echo htmlspecialchars($_SESSION['admin_username']); ?>!</p>
                </div>
                <div class="header-right">
                    <div class="date-display">
                        <i class="fas fa-calendar"></i>
                        <span><?php echo date('F d, Y'); ?></span>
                    </div>
                </div>
            </header>
            
            <!-- Stats Cards -->
            <section class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon table-icon">
                        <i class="fas fa-table"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $table_bookings_count; ?></h3>
                        <p>Total Table Bookings</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon catering-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $catering_count; ?></h3>
                        <p>Total Catering Events</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon today-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $today_table + $today_catering; ?></h3>
                        <p>Today's Bookings</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon revenue-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $table_bookings_count + $catering_count; ?></h3>
                        <p>Total Reservations</p>
                    </div>
                </div>
            </section>
            
            <!-- Charts Section -->
            <section class="charts-section">
                <div class="chart-card">
                    <h3><i class="fas fa-chart-pie"></i> Event Types Distribution</h3>
                    <canvas id="eventChart"></canvas>
                </div>
                
                <div class="chart-card">
                    <h3><i class="fas fa-chart-bar"></i> Recent Activity</h3>
                    <div class="activity-list">
                        <?php 
                        $all_recent = [];
                        while ($row = mysqli_fetch_assoc($recent_tables)) {
                            $all_recent[] = ['type' => 'table', 'data' => $row, 'time' => $row['created']];
                        }
                        while ($row = mysqli_fetch_assoc($recent_catering)) {
                            $all_recent[] = ['type' => 'catering', 'data' => $row, 'time' => $row['created_at']];
                        }
                        usort($all_recent, function($a, $b) {
                            return strtotime($b['time']) - strtotime($a['time']);
                        });
                        $all_recent = array_slice($all_recent, 0, 5);
                        
                        foreach ($all_recent as $item): 
                        ?>
                        <div class="activity-item">
                            <div class="activity-icon <?php echo $item['type']; ?>">
                                <i class="fas fa-<?php echo $item['type'] === 'table' ? 'table' : 'calendar'; ?>"></i>
                            </div>
                            <div class="activity-details">
                                <p class="activity-title">
                                    <?php 
                                    if ($item['type'] === 'table') {
                                        echo htmlspecialchars($item['data']['tfull_name']) . ' - Table Booking';
                                    } else {
                                        echo htmlspecialchars($item['data']['cname']) . ' - ' . htmlspecialchars($item['data']['cevent_type']);
                                    }
                                    ?>
                                </p>
                                <p class="activity-time">
                                    <i class="fas fa-clock"></i> 
                                    <?php echo date('M d, Y h:i A', strtotime($item['time'])); ?>
                                </p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
            
            <!-- Recent Bookings Tables -->
            <section class="tables-section">
                <div class="table-card">
                    <div class="table-header">
                        <h3><i class="fas fa-table"></i> Recent Table Bookings</h3>
                        <a href="table-bookings.php" class="view-all">View All <i class="fas fa-arrow-right"></i></a>
                    </div>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Date & Time</th>
                                    <th>People</th>
                                    <th>Table</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                mysqli_data_seek($recent_tables, 0);
                                while ($row = mysqli_fetch_assoc($recent_tables)): 
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['tfull_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['temail']); ?></td>
                                    <td><?php echo date('M d, Y h:i A', strtotime($row['tdate_time'])); ?></td>
                                    <td><?php echo htmlspecialchars($row['tno_people']); ?></td>
                                    <td><span class="badge"><?php echo htmlspecialchars($row['ttable']); ?></span></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="table-card">
                    <div class="table-header">
                        <h3><i class="fas fa-calendar-alt"></i> Recent Catering Bookings</h3>
                        <a href="catering-bookings.php" class="view-all">View All <i class="fas fa-arrow-right"></i></a>
                    </div>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Event Type</th>
                                    <th>People</th>
                                    <th>Event Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                mysqli_data_seek($recent_catering, 0);
                                while ($row = mysqli_fetch_assoc($recent_catering)): 
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['cname']); ?></td>
                                    <td><span class="badge event-badge"><?php echo htmlspecialchars($row['cevent_type']); ?></span></td>
                                    <td><?php echo htmlspecialchars($row['cpeople']); ?></td>
                                    <td><?php echo date('M d, Y h:i A', strtotime($row['cevent_date'])); ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </main>
    </div>
    
    <script>
        // Event Types Chart
        const eventCtx = document.getElementById('eventChart').getContext('2d');
        new Chart(eventCtx, {
            type: 'doughnut',
            data: {
                labels: [<?php 
                    $labels = [];
                    $data = [];
                    mysqli_data_seek($event_types, 0);
                    while ($row = mysqli_fetch_assoc($event_types)) {
                        $labels[] = "'" . $row['cevent_type'] . "'";
                        $data[] = $row['count'];
                    }
                    echo implode(',', $labels);
                ?>],
                datasets: [{
                    data: [<?php echo implode(',', $data); ?>],
                    backgroundColor: ['#FEA116', '#0f172a', '#3b82f6', '#10b981', '#f59e0b'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: '#94a3b8',
                            padding: 15,
                            font: { size: 12 }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>
