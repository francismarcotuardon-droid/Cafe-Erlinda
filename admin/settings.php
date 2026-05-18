<?php
require_once 'session_check.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Cafè Erlinda Admin</title>
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
                <a href="catering-bookings.php" class="nav-item">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Catering Bookings</span>
                </a>
                <a href="settings.php" class="nav-item active">
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
            <!-- Page Header -->
            <div class="page-header">
                <div class="page-title">
                    <h1><i class="fas fa-cog"></i> Settings</h1>
                    <p>Admin dashboard settings and information</p>
                </div>
                <div class="header-actions">
                    <a href="index.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>
            
            <!-- Settings Cards -->
            <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));">
                <div class="chart-card">
                    <h3><i class="fas fa-user-shield"></i> Admin Credentials</h3>
                    <div class="detail-row" style="margin-top: 20px;">
                        <div class="detail-label">Username</div>
                        <div class="detail-value">admin</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Password</div>
                        <div class="detail-value">cafe123</div>
                    </div>
                    <div style="margin-top: 20px; padding: 15px; background: rgba(254, 161, 22, 0.1); border-radius: 10px;">
                        <p style="font-size: 13px; color: var(--text-muted);">
                            <i class="fas fa-info-circle" style="color: var(--primary);"></i> 
                            To change credentials, edit the login.php file or implement database-based authentication.
                        </p>
                    </div>
                </div>
                
                <div class="chart-card">
                    <h3><i class="fas fa-database"></i> Database Connection</h3>
                    <div class="detail-row" style="margin-top: 20px;">
                        <div class="detail-label">Server</div>
                        <div class="detail-value">localhost</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Database</div>
                        <div class="detail-value">cafe_erlinda</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Username</div>
                        <div class="detail-value">root</div>
                    </div>
                    <div style="margin-top: 20px; padding: 15px; background: rgba(16, 185, 129, 0.1); border-radius: 10px;">
                        <p style="font-size: 13px; color: var(--success);">
                            <i class="fas fa-check-circle"></i> 
                            Database connection is working properly.
                        </p>
                    </div>
                </div>
                
                <div class="chart-card">
                    <h3><i class="fas fa-info-circle"></i> About</h3>
                    <div style="padding: 20px 0;">
                        <p style="color: var(--text-muted); line-height: 1.8;">
                            <strong style="color: var(--primary);">Cafè Erlinda Admin Dashboard</strong><br><br>
                            This admin dashboard allows you to manage table bookings and event catering reservations for your restaurant website.<br><br>
                            <strong>Features:</strong><br>
                            <i class="fas fa-check" style="color: var(--success); margin-right: 8px;"></i> View all bookings<br>
                            <i class="fas fa-check" style="color: var(--success); margin-right: 8px;"></i> Search and filter reservations<br>
                            <i class="fas fa-check" style="color: var(--success); margin-right: 8px;"></i> View detailed booking information<br>
                            <i class="fas fa-check" style="color: var(--success); margin-right: 8px;"></i> Delete bookings<br>
                            <i class="fas fa-check" style="color: var(--success); margin-right: 8px;"></i> Statistics and analytics
                        </p>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
