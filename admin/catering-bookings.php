<?php
require_once 'session_check.php';
require_once 'db_connect.php';

// Handle delete action
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = intval($_GET['delete']);
    mysqli_query($conn, "DELETE FROM reservations WHERE id = $id");
    header("Location: catering-bookings.php?deleted=1");
    exit();
}

// Pagination
$per_page = 10;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $per_page;

// Search and filter
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$event_filter = isset($_GET['event_type']) ? mysqli_real_escape_string($conn, $_GET['event_type']) : '';

$conditions = [];
if ($search) {
    $conditions[] = "(cname LIKE '%$search%' OR cemail LIKE '%$search%')";
}
if ($event_filter) {
    $conditions[] = "cevent_type = '$event_filter'";
}

$where_clause = '';
if (count($conditions) > 0) {
    $where_clause = 'WHERE ' . implode(' AND ', $conditions);
}

// Get total count
$total_result = mysqli_query($conn, "SELECT COUNT(*) as count FROM reservations $where_clause");
$total_rows = mysqli_fetch_assoc($total_result)['count'];
$total_pages = ceil($total_rows / $per_page);

// Get bookings
$bookings = mysqli_query($conn, "SELECT * FROM reservations $where_clause ORDER BY created_at DESC LIMIT $offset, $per_page");

// Get event types for filter
$event_types = mysqli_query($conn, "SELECT DISTINCT cevent_type FROM reservations WHERE cevent_type != ''");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catering Bookings - Cafè Erlinda Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Heebo:wght@400;500;600&family=Nunito:wght@600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
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
                <a href="index.php" class="nav-item">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
                <a href="table-bookings.php" class="nav-item">
                    <i class="fas fa-table"></i>
                    <span>Table Bookings</span>
                </a>
                <a href="catering-bookings.php" class="nav-item active">
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
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <?php if (isset($_GET['deleted'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                Booking deleted successfully!
            </div>
            <?php endif; ?>
            
            <!-- Page Header -->
            <div class="page-header">
                <div class="page-title">
                    <h1><i class="fas fa-calendar-alt"></i> Catering Bookings</h1>
                    <p>Manage all event catering reservations</p>
                </div>
                <div class="header-actions">
                    <form method="GET" class="search-box" style="display: flex; gap: 10px;">
                        <div style="position: relative;">
                            <i class="fas fa-search"></i>
                            <input type="text" name="search" placeholder="Search bookings..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <select name="event_type" style="background: var(--dark-light); border: 1px solid rgba(254, 161, 22, 0.2); border-radius: 10px; padding: 12px 15px; color: var(--text); font-size: 14px;" onchange="this.form.submit()">
                            <option value="">All Event Types</option>
                            <?php while ($type = mysqli_fetch_assoc($event_types)): ?>
                            <option value="<?php echo $type['cevent_type']; ?>" <?php echo $event_filter == $type['cevent_type'] ? 'selected' : ''; ?>>
                                <?php echo $type['cevent_type']; ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </form>
                    <a href="index.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>
            
            <!-- Bookings Table -->
            <div class="table-card full-width">
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Event Type</th>
                                <th>People</th>
                                <th>Event Date</th>
                                <th>Special Details</th>
                                <th>Booked On</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($bookings) > 0): ?>
                                <?php while ($row = mysqli_fetch_assoc($bookings)): 
                                    // Get special details based on event type
                                    $special_details = '';
                                    if ($row['cevent_type'] == 'Birthday' && $row['cbirthday_name']) {
                                        $special_details = "For: {$row['cbirthday_name']}, Age: {$row['cage']}";
                                    } elseif ($row['cevent_type'] == 'Anniversary' && $row['ccouple_name']) {
                                        $special_details = "Couple: {$row['ccouple_name']}, Years: {$row['cyears']}";
                                    } elseif ($row['cevent_type'] == 'Reunion' && $row['cgroup_name']) {
                                        $special_details = "Group: {$row['cgroup_name']}, Grad: {$row['cyear_grad']}";
                                    }
                                ?>
                                <tr>
                                    <td>#<?php echo $row['id']; ?></td>
                                    <td><?php echo htmlspecialchars($row['cname']); ?></td>
                                    <td><?php echo htmlspecialchars($row['cemail']); ?></td>
                                    <td><span class="badge event-badge"><?php echo htmlspecialchars($row['cevent_type']); ?></span></td>
                                    <td><?php echo htmlspecialchars($row['cpeople']); ?></td>
                                    <td><?php echo date('M d, Y h:i A', strtotime($row['cevent_date'])); ?></td>
                                    <td><?php echo $special_details ? htmlspecialchars(substr($special_details, 0, 35)) . '...' : '-'; ?></td>
                                    <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                                    <td>
                                        <div style="display: flex; gap: 8px;">
                                            <button class="btn btn-success btn-sm" onclick="viewDetails(<?php echo $row['id']; ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <a href="?delete=<?php echo $row['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this booking?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" style="text-align: center; padding: 40px;">
                                        <i class="fas fa-inbox" style="font-size: 48px; color: var(--text-muted); margin-bottom: 15px;"></i>
                                        <p>No bookings found</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&event_type=<?php echo urlencode($event_filter); ?>"><i class="fas fa-chevron-left"></i></a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <?php if ($i == $page): ?>
                        <span class="active"><?php echo $i; ?></span>
                        <?php else: ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&event_type=<?php echo urlencode($event_filter); ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&event_type=<?php echo urlencode($event_filter); ?>"><i class="fas fa-chevron-right"></i></a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <!-- View Modal -->
    <div class="modal-overlay" id="viewModal">
        <div class="modal">
            <div class="modal-header">
                <h2><i class="fas fa-calendar-alt"></i> Catering Booking Details</h2>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body" id="modalContent">
                <!-- Content loaded via AJAX -->
            </div>
        </div>
    </div>
    
    <script>
        function viewDetails(id) {
            fetch('api/get-catering-booking.php?id=' + id)
                .then(response => response.json())
                .then(data => {
                    let specialSection = '';
                    
                    if (data.cevent_type === 'Birthday' && data.cbirthday_name) {
                        specialSection = `
                            <div class="detail-row">
                                <div class="detail-label">Birthday Person</div>
                                <div class="detail-value">${data.cbirthday_name}</div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Age</div>
                                <div class="detail-value">${data.cage}</div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Theme</div>
                                <div class="detail-value">${data.ctheme || 'Not specified'}</div>
                            </div>
                        `;
                    } else if (data.cevent_type === 'Anniversary' && data.ccouple_name) {
                        specialSection = `
                            <div class="detail-row">
                                <div class="detail-label">Couple Name</div>
                                <div class="detail-value">${data.ccouple_name}</div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Years Together</div>
                                <div class="detail-value">${data.cyears}</div>
                            </div>
                        `;
                    } else if (data.cevent_type === 'Reunion' && data.cgroup_name) {
                        specialSection = `
                            <div class="detail-row">
                                <div class="detail-label">Group Name</div>
                                <div class="detail-value">${data.cgroup_name}</div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Graduation Year</div>
                                <div class="detail-value">${data.cyear_grad}</div>
                            </div>
                        `;
                    }
                    
                    let content = `
                        <div class="detail-row">
                            <div class="detail-label">Booking ID</div>
                            <div class="detail-value">#${data.id}</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Full Name</div>
                            <div class="detail-value">${data.cname}</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Email</div>
                            <div class="detail-value">${data.cemail}</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Event Type</div>
                            <div class="detail-value"><span class="badge event-badge">${data.cevent_type}</span></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Number of People</div>
                            <div class="detail-value">${data.cpeople}</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Event Date & Time</div>
                            <div class="detail-value">${data.cevent_date}</div>
                        </div>
                        ${specialSection}
                        <div class="detail-row">
                            <div class="detail-label">Special Request</div>
                            <div class="detail-value">${data.cspecial_request || 'None'}</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Booked On</div>
                            <div class="detail-value">${data.created_at}</div>
                        </div>
                    `;
                    document.getElementById('modalContent').innerHTML = content;
                    document.getElementById('viewModal').classList.add('active');
                });
        }
        
        function closeModal() {
            document.getElementById('viewModal').classList.remove('active');
        }
        
        document.getElementById('viewModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</body>
</html>
