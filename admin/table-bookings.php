<?php
require_once 'session_check.php';
require_once 'db_connect.php';

// Handle delete action
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = intval($_GET['delete']);
    mysqli_query($conn, "DELETE FROM table_form WHERE reqid = $id");
    header("Location: table-bookings.php?deleted=1");
    exit();
}

// Pagination
$per_page = 10;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $per_page;

// Search
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$search_condition = '';
if ($search) {
    $search_condition = "WHERE tfull_name LIKE '%$search%' OR temail LIKE '%$search%' OR ttable LIKE '%$search%'";
}

// Get total count
$total_result = mysqli_query($conn, "SELECT COUNT(*) as count FROM table_form $search_condition");
$total_rows = mysqli_fetch_assoc($total_result)['count'];
$total_pages = ceil($total_rows / $per_page);

// Get bookings
$bookings = mysqli_query($conn, "SELECT * FROM table_form $search_condition ORDER BY created DESC LIMIT $offset, $per_page");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Table Bookings - Cafè Erlinda Admin</title>
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
                <a href="table-bookings.php" class="nav-item active">
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
                    <h1><i class="fas fa-table"></i> Table Bookings</h1>
                    <p>Manage all table reservations</p>
                </div>
                <div class="header-actions">
                    <form method="GET" class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" placeholder="Search bookings..." value="<?php echo htmlspecialchars($search); ?>">
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
                                <th>Date & Time</th>
                                <th>No. of People</th>
                                <th>Table</th>
                                <th>Special Request</th>
                                <th>Booked On</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($bookings) > 0): ?>
                                <?php while ($row = mysqli_fetch_assoc($bookings)): ?>
                                <tr>
                                    <td>#<?php echo $row['reqid']; ?></td>
                                    <td><?php echo htmlspecialchars($row['tfull_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['temail']); ?></td>
                                    <td><?php echo date('M d, Y h:i A', strtotime($row['tdate_time'])); ?></td>
                                    <td><?php echo htmlspecialchars($row['tno_people']); ?></td>
                                    <td><span class="badge">Table <?php echo htmlspecialchars($row['ttable']); ?></span></td>
                                    <td><?php echo $row['tmessage'] ? htmlspecialchars(substr($row['tmessage'], 0, 30)) . '...' : '-'; ?></td>
                                    <td><?php echo date('M d, Y', strtotime($row['created'])); ?></td>
                                    <td>
                                        <div style="display: flex; gap: 8px;">
                                            <button class="btn btn-success btn-sm" onclick="viewDetails(<?php echo $row['reqid']; ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <a href="?delete=<?php echo $row['reqid']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this booking?')">
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
                    <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>"><i class="fas fa-chevron-left"></i></a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <?php if ($i == $page): ?>
                        <span class="active"><?php echo $i; ?></span>
                        <?php else: ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>"><i class="fas fa-chevron-right"></i></a>
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
                <h2><i class="fas fa-table"></i> Booking Details</h2>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body" id="modalContent">
                <!-- Content loaded via AJAX -->
            </div>
        </div>
    </div>
    
    <script>
        function viewDetails(id) {
            // Fetch booking details
            fetch('api/get-table-booking.php?id=' + id)
                .then(response => response.json())
                .then(data => {
                    let content = `
                        <div class="detail-row">
                            <div class="detail-label">Booking ID</div>
                            <div class="detail-value">#${data.reqid}</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Full Name</div>
                            <div class="detail-value">${data.tfull_name}</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Email</div>
                            <div class="detail-value">${data.temail}</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Date & Time</div>
                            <div class="detail-value">${data.tdate_time}</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Number of People</div>
                            <div class="detail-value">${data.tno_people}</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Table Number</div>
                            <div class="detail-value">${data.ttable}</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Special Request</div>
                            <div class="detail-value">${data.tmessage || 'None'}</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Booked On</div>
                            <div class="detail-value">${data.created}</div>
                        </div>
                    `;
                    document.getElementById('modalContent').innerHTML = content;
                    document.getElementById('viewModal').classList.add('active');
                });
        }
        
        function closeModal() {
            document.getElementById('viewModal').classList.remove('active');
        }
        
        // Close modal on overlay click
        document.getElementById('viewModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</body>
</html>
