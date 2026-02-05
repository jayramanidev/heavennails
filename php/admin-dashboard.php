<?php
/**
 * Heaven Nails - Admin Dashboard
 */

require_once 'config.php';
requireLogin();

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $bookingId = intval($_POST['booking_id'] ?? 0);
    
    if ($bookingId > 0) {
        try {
            $db = Database::getInstance()->getConnection();
            
            if ($action === 'update_status') {
                $newStatus = sanitize($_POST['status'] ?? '');
                if (in_array($newStatus, ['pending', 'confirmed', 'cancelled'])) {
                    $stmt = $db->prepare("UPDATE appointments SET status = ? WHERE id = ?");
                    $stmt->execute([$newStatus, $bookingId]);
                }
            } elseif ($action === 'delete') {
                $stmt = $db->prepare("DELETE FROM appointments WHERE id = ?");
                $stmt->execute([$bookingId]);
            }
        } catch (PDOException $e) {
            error_log("Admin action error: " . $e->getMessage());
        }
    }
    header('Location: admin-dashboard.php' . (isset($_GET['filter']) ? '?filter=' . $_GET['filter'] : ''));
    exit;
}

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin-login.php');
    exit;
}

// Get filter
$filter = sanitize($_GET['filter'] ?? 'all');

// Fetch bookings
$bookings = [];
try {
    $db = Database::getInstance()->getConnection();
    
    $sql = "SELECT * FROM appointments";
    if ($filter !== 'all') {
        $sql .= " WHERE status = :status";
    }
    $sql .= " ORDER BY created_at DESC";
    
    $stmt = $db->prepare($sql);
    if ($filter !== 'all') {
        $stmt->bindParam(':status', $filter);
    }
    $stmt->execute();
    $bookings = $stmt->fetchAll();
    
    // Get counts
    $countStmt = $db->query("SELECT status, COUNT(*) as count FROM appointments GROUP BY status");
    $counts = ['all' => 0, 'pending' => 0, 'confirmed' => 0, 'cancelled' => 0];
    while ($row = $countStmt->fetch()) {
        $counts[$row['status']] = $row['count'];
        $counts['all'] += $row['count'];
    }
} catch (PDOException $e) {
    error_log("Fetch error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Heaven Nails</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;600&family=Montserrat:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Montserrat', sans-serif; background: #f5f0ea; min-height: 100vh; }
        
        .header { background: #1a1a1a; color: white; padding: 1rem 1.5rem; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 100; }
        .logo { font-family: 'Cormorant Garamond', serif; font-size: 1.5rem; }
        .logo span { color: #c9a66b; }
        .header-actions { display: flex; gap: 1rem; align-items: center; }
        .header-actions a { color: white; text-decoration: none; font-size: 0.875rem; padding: 0.5rem 1rem; border-radius: 4px; transition: background 0.2s; }
        .header-actions a:hover { background: rgba(255,255,255,0.1); }
        .btn-logout { background: #c9a66b !important; }
        
        .container { max-width: 1200px; margin: 0 auto; padding: 1.5rem; }
        
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
        .stat-card { background: white; padding: 1.25rem; border-radius: 12px; text-align: center; cursor: pointer; border: 2px solid transparent; transition: all 0.2s; text-decoration: none; color: inherit; }
        .stat-card:hover, .stat-card.active { border-color: #c9a66b; }
        .stat-number { font-size: 2rem; font-weight: 700; color: #2d2d2d; }
        .stat-label { font-size: 0.75rem; color: #6b6b6b; text-transform: uppercase; letter-spacing: 0.05em; margin-top: 0.25rem; }
        .stat-card.pending .stat-number { color: #f59e0b; }
        .stat-card.confirmed .stat-number { color: #10b981; }
        .stat-card.cancelled .stat-number { color: #ef4444; }
        
        .bookings-section { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .section-header { padding: 1.25rem 1.5rem; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
        .section-title { font-family: 'Cormorant Garamond', serif; font-size: 1.5rem; color: #2d2d2d; }
        
        .booking-list { }
        .booking-item { padding: 1.25rem 1.5rem; border-bottom: 1px solid #f0f0f0; display: grid; gap: 1rem; }
        .booking-item:last-child { border-bottom: none; }
        
        .booking-header { display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 0.5rem; }
        .booking-client { font-weight: 600; color: #2d2d2d; font-size: 1.125rem; }
        .booking-id { font-size: 0.75rem; color: #999; }
        
        .booking-details { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 0.75rem; }
        .booking-detail { font-size: 0.875rem; }
        .booking-detail strong { color: #6b6b6b; font-weight: 500; display: block; font-size: 0.75rem; margin-bottom: 0.125rem; }
        
        .booking-services { background: #f9f7f4; padding: 0.75rem; border-radius: 6px; font-size: 0.875rem; }
        .booking-notes { font-size: 0.875rem; color: #6b6b6b; font-style: italic; }
        
        .booking-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center; padding-top: 0.5rem; }
        .status-badge { padding: 0.375rem 0.75rem; border-radius: 20px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-confirmed { background: #d1fae5; color: #065f46; }
        .status-cancelled { background: #fee2e2; color: #991b1b; }
        
        .action-btn { padding: 0.5rem 1rem; border: none; border-radius: 6px; font-size: 0.75rem; font-weight: 600; cursor: pointer; transition: all 0.2s; }
        .btn-confirm { background: #10b981; color: white; }
        .btn-confirm:hover { background: #059669; }
        .btn-cancel { background: #6b7280; color: white; }
        .btn-cancel:hover { background: #4b5563; }
        .btn-delete { background: #ef4444; color: white; }
        .btn-delete:hover { background: #dc2626; }
        
        .empty-state { text-align: center; padding: 3rem; color: #6b6b6b; }
        .empty-icon { font-size: 3rem; margin-bottom: 1rem; }
        
        @media (max-width: 640px) {
            .header { flex-direction: column; gap: 1rem; text-align: center; }
            .booking-header { flex-direction: column; }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="logo">Heaven<span>Nails</span> Admin</div>
        <div class="header-actions">
            <a href="../index.html">View Site</a>
            <a href="?logout=1" class="btn-logout">Logout</a>
        </div>
    </header>

    <div class="container">
        <div class="stats">
            <a href="?filter=all" class="stat-card <?= $filter === 'all' ? 'active' : '' ?>">
                <div class="stat-number"><?= $counts['all'] ?? 0 ?></div>
                <div class="stat-label">All Bookings</div>
            </a>
            <a href="?filter=pending" class="stat-card pending <?= $filter === 'pending' ? 'active' : '' ?>">
                <div class="stat-number"><?= $counts['pending'] ?? 0 ?></div>
                <div class="stat-label">Pending</div>
            </a>
            <a href="?filter=confirmed" class="stat-card confirmed <?= $filter === 'confirmed' ? 'active' : '' ?>">
                <div class="stat-number"><?= $counts['confirmed'] ?? 0 ?></div>
                <div class="stat-label">Confirmed</div>
            </a>
            <a href="?filter=cancelled" class="stat-card cancelled <?= $filter === 'cancelled' ? 'active' : '' ?>">
                <div class="stat-number"><?= $counts['cancelled'] ?? 0 ?></div>
                <div class="stat-label">Cancelled</div>
            </a>
        </div>

        <div class="bookings-section">
            <div class="section-header">
                <h2 class="section-title"><?= ucfirst($filter) ?> Bookings</h2>
            </div>
            
            <div class="booking-list">
                <?php if (empty($bookings)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">📅</div>
                        <p>No <?= $filter !== 'all' ? $filter : '' ?> bookings found</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($bookings as $booking): ?>
                        <div class="booking-item">
                            <div class="booking-header">
                                <div>
                                    <div class="booking-client"><?= htmlspecialchars($booking['client_name']) ?></div>
                                    <div class="booking-id">#<?= $booking['id'] ?> · <?= date('M j, Y g:i A', strtotime($booking['created_at'])) ?></div>
                                </div>
                                <span class="status-badge status-<?= $booking['status'] ?>"><?= $booking['status'] ?></span>
                            </div>
                            
                            <div class="booking-details">
                                <div class="booking-detail">
                                    <strong>Email</strong>
                                    <a href="mailto:<?= htmlspecialchars($booking['email']) ?>"><?= htmlspecialchars($booking['email']) ?></a>
                                </div>
                                <div class="booking-detail">
                                    <strong>Phone</strong>
                                    <a href="tel:<?= htmlspecialchars($booking['phone']) ?>"><?= htmlspecialchars($booking['phone']) ?></a>
                                </div>
                                <div class="booking-detail">
                                    <strong>Appointment</strong>
                                    <?= date('M j, Y', strtotime($booking['preferred_date'])) ?> at <?= date('g:i A', strtotime($booking['preferred_time'])) ?>
                                </div>
                            </div>
                            
                            <div class="booking-services">
                                <strong>Services:</strong> 
                                <?php 
                                    $services = json_decode($booking['services'], true);
                                    echo htmlspecialchars(is_array($services) ? implode(', ', $services) : $booking['services']);
                                ?>
                            </div>
                            
                            <?php if (!empty($booking['notes'])): ?>
                                <div class="booking-notes">
                                    <strong>Notes:</strong> <?= htmlspecialchars($booking['notes']) ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="booking-actions">
                                <?php if ($booking['status'] === 'pending'): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                                        <input type="hidden" name="status" value="confirmed">
                                        <button type="submit" class="action-btn btn-confirm">Confirm</button>
                                    </form>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                                        <input type="hidden" name="status" value="cancelled">
                                        <button type="submit" class="action-btn btn-cancel">Cancel</button>
                                    </form>
                                <?php elseif ($booking['status'] === 'confirmed'): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                                        <input type="hidden" name="status" value="cancelled">
                                        <button type="submit" class="action-btn btn-cancel">Cancel</button>
                                    </form>
                                <?php endif; ?>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this booking permanently?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                                    <button type="submit" class="action-btn btn-delete">Delete</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
