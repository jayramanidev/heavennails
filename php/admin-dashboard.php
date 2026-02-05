<?php
/**
 * Heaven Nails - Admin Dashboard with Calendar View
 */

require_once 'config.php';
requireLogin();

$db = Database::getInstance()->getConnection();

// Handle AJAX requests
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    
    // Get calendar events
    if ($_GET['ajax'] === 'events') {
        $start = sanitize($_GET['start'] ?? '');
        $end = sanitize($_GET['end'] ?? '');
        
        $events = [];
        
        // Get appointments
        $stmt = $db->prepare("
            SELECT a.*, s.name as staff_name 
            FROM appointments a 
            LEFT JOIN staff s ON a.staff_id = s.id
            WHERE a.preferred_date BETWEEN :start AND :end
        ");
        $stmt->execute([':start' => $start, ':end' => $end]);
        
        while ($row = $stmt->fetch()) {
            $statusColors = [
                'pending' => '#f59e0b',
                'confirmed' => '#10b981', 
                'cancelled' => '#ef4444'
            ];
            
            $services = json_decode($row['services'], true);
            $servicesList = is_array($services) ? implode(', ', $services) : $row['services'];
            
            $events[] = [
                'id' => 'booking-' . $row['id'],
                'title' => $row['client_name'],
                'start' => $row['preferred_date'] . 'T' . $row['preferred_time'],
                'end' => date('Y-m-d\TH:i:s', strtotime($row['preferred_date'] . ' ' . $row['preferred_time']) + ($row['duration_minutes'] ?? 60) * 60),
                'backgroundColor' => $statusColors[$row['status']] ?? '#6b7280',
                'borderColor' => $statusColors[$row['status']] ?? '#6b7280',
                'extendedProps' => [
                    'type' => 'booking',
                    'bookingId' => $row['id'],
                    'email' => $row['email'],
                    'phone' => $row['phone'],
                    'services' => $servicesList,
                    'status' => $row['status'],
                    'staff' => $row['staff_name'] ?? 'Any'
                ]
            ];
        }
        
        // Get blocked dates
        $blockStmt = $db->prepare("
            SELECT bd.*, s.name as staff_name 
            FROM blocked_dates bd 
            LEFT JOIN staff s ON bd.staff_id = s.id
            WHERE bd.block_date BETWEEN :start AND :end
        ");
        $blockStmt->execute([':start' => $start, ':end' => $end]);
        
        while ($block = $blockStmt->fetch()) {
            $events[] = [
                'id' => 'block-' . $block['id'],
                'title' => '🚫 ' . ($block['reason'] ?: 'Blocked'),
                'start' => $block['block_date'] . ($block['block_time'] ? 'T' . $block['block_time'] : ''),
                'end' => $block['block_date'] . ($block['end_time'] ? 'T' . $block['end_time'] : ''),
                'allDay' => !$block['block_time'],
                'backgroundColor' => '#374151',
                'borderColor' => '#374151',
                'extendedProps' => [
                    'type' => 'blocked',
                    'blockId' => $block['id'],
                    'reason' => $block['reason'],
                    'staff' => $block['staff_name'] ?? 'All Staff'
                ]
            ];
        }
        
        echo json_encode($events);
        exit;
    }
    
    exit;
}

// Handle block date form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'block_date') {
        $blockDate = sanitize($_POST['block_date'] ?? '');
        $blockTime = sanitize($_POST['block_time'] ?? '') ?: null;
        $endTime = sanitize($_POST['end_time'] ?? '') ?: null;
        $reason = sanitize($_POST['reason'] ?? '');
        $staffId = isset($_POST['staff_id']) && $_POST['staff_id'] !== '' ? intval($_POST['staff_id']) : null;
        
        if ($blockDate) {
            $stmt = $db->prepare("
                INSERT INTO blocked_dates (block_date, block_time, end_time, reason, staff_id)
                VALUES (:date, :time, :end_time, :reason, :staff_id)
            ");
            $stmt->execute([
                ':date' => $blockDate,
                ':time' => $blockTime,
                ':end_time' => $endTime,
                ':reason' => $reason,
                ':staff_id' => $staffId
            ]);
        }
        header('Location: admin-dashboard.php?view=calendar');
        exit;
    }
    
    if ($action === 'unblock') {
        $blockId = intval($_POST['block_id'] ?? 0);
        if ($blockId > 0) {
            $stmt = $db->prepare("DELETE FROM blocked_dates WHERE id = ?");
            $stmt->execute([$blockId]);
        }
        header('Location: admin-dashboard.php?view=calendar');
        exit;
    }
    
    if ($action === 'update_status') {
        $bookingId = intval($_POST['booking_id'] ?? 0);
        $newStatus = sanitize($_POST['status'] ?? '');
        if ($bookingId > 0 && in_array($newStatus, ['pending', 'confirmed', 'cancelled'])) {
            $stmt = $db->prepare("UPDATE appointments SET status = ? WHERE id = ?");
            $stmt->execute([$newStatus, $bookingId]);
        }
        header('Location: admin-dashboard.php' . (isset($_GET['view']) ? '?view=' . $_GET['view'] : ''));
        exit;
    }
    
    if ($action === 'delete') {
        $bookingId = intval($_POST['booking_id'] ?? 0);
        if ($bookingId > 0) {
            $stmt = $db->prepare("DELETE FROM appointments WHERE id = ?");
            $stmt->execute([$bookingId]);
        }
        header('Location: admin-dashboard.php' . (isset($_GET['view']) ? '?view=' . $_GET['view'] : ''));
        exit;
    }
}

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin-login.php');
    exit;
}

// Get view mode
$view = sanitize($_GET['view'] ?? 'list');
$filter = sanitize($_GET['filter'] ?? 'all');

// Fetch data for list view
$bookings = [];
$counts = ['all' => 0, 'pending' => 0, 'confirmed' => 0, 'cancelled' => 0];

try {
    $sql = "SELECT a.*, s.name as staff_name FROM appointments a LEFT JOIN staff s ON a.staff_id = s.id";
    if ($filter !== 'all') {
        $sql .= " WHERE a.status = :status";
    }
    $sql .= " ORDER BY a.created_at DESC";
    
    $stmt = $db->prepare($sql);
    if ($filter !== 'all') {
        $stmt->bindParam(':status', $filter);
    }
    $stmt->execute();
    $bookings = $stmt->fetchAll();
    
    $countStmt = $db->query("SELECT status, COUNT(*) as count FROM appointments GROUP BY status");
    while ($row = $countStmt->fetch()) {
        $counts[$row['status']] = $row['count'];
        $counts['all'] += $row['count'];
    }
    
    // Get staff for block date form
    $staffStmt = $db->query("SELECT id, name FROM staff WHERE is_active = TRUE ORDER BY name");
    $staffList = $staffStmt->fetchAll();
} catch (PDOException $e) {
    error_log("Fetch error: " . $e->getMessage());
    $staffList = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Heaven Nails</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;600&family=Montserrat:wght@400;500;600&display=swap" rel="stylesheet">
    <!-- FullCalendar CSS -->
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css" rel="stylesheet">
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
        
        .container { max-width: 1400px; margin: 0 auto; padding: 1.5rem; }
        
        /* View Toggle */
        .view-toggle { display: flex; gap: 0.5rem; margin-bottom: 1.5rem; }
        .view-btn { padding: 0.75rem 1.5rem; border: 2px solid #c9a66b; background: white; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.2s; text-decoration: none; color: #2d2d2d; }
        .view-btn:hover, .view-btn.active { background: #c9a66b; color: white; }
        
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
        .stat-card { background: white; padding: 1.25rem; border-radius: 12px; text-align: center; cursor: pointer; border: 2px solid transparent; transition: all 0.2s; text-decoration: none; color: inherit; }
        .stat-card:hover, .stat-card.active { border-color: #c9a66b; }
        .stat-number { font-size: 2rem; font-weight: 700; color: #2d2d2d; }
        .stat-label { font-size: 0.75rem; color: #6b6b6b; text-transform: uppercase; letter-spacing: 0.05em; margin-top: 0.25rem; }
        .stat-card.pending .stat-number { color: #f59e0b; }
        .stat-card.confirmed .stat-number { color: #10b981; }
        .stat-card.cancelled .stat-number { color: #ef4444; }
        
        /* Calendar Section */
        .calendar-section { background: white; border-radius: 12px; padding: 1.5rem; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .calendar-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 1rem; }
        .calendar-title { font-family: 'Cormorant Garamond', serif; font-size: 1.5rem; }
        
        #calendar { margin-top: 1rem; }
        .fc { font-family: 'Montserrat', sans-serif; }
        .fc-toolbar-title { font-family: 'Cormorant Garamond', serif !important; }
        .fc-button-primary { background: #c9a66b !important; border-color: #c9a66b !important; }
        .fc-button-primary:hover { background: #b8956a !important; }
        .fc-button-primary:disabled { background: #ddd !important; border-color: #ddd !important; }
        .fc-day-today { background: #fef9f3 !important; }
        
        /* Block Date Form */
        .block-form { background: #f9f7f4; padding: 1.25rem; border-radius: 8px; margin-bottom: 1rem; }
        .block-form h4 { margin-bottom: 1rem; font-size: 1rem; }
        .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-bottom: 1rem; }
        .form-group { display: flex; flex-direction: column; gap: 0.25rem; }
        .form-group label { font-size: 0.75rem; font-weight: 600; color: #6b6b6b; }
        .form-group input, .form-group select { padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px; font-size: 0.875rem; }
        .btn-block { background: #374151; color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 6px; font-weight: 600; cursor: pointer; }
        .btn-block:hover { background: #1f2937; }
        
        /* Bookings List */
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

        /* Event popup */
        .fc-popover { max-width: 300px; }
        
        @media (max-width: 640px) {
            .header { flex-direction: column; gap: 1rem; text-align: center; }
            .booking-header { flex-direction: column; }
            .view-toggle { flex-wrap: wrap; }
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
        <!-- View Toggle -->
        <div class="view-toggle">
            <a href="?view=list" class="view-btn <?= $view === 'list' ? 'active' : '' ?>">📋 List View</a>
            <a href="?view=calendar" class="view-btn <?= $view === 'calendar' ? 'active' : '' ?>">📅 Calendar View</a>
        </div>
        
        <!-- Stats -->
        <div class="stats">
            <a href="?view=<?= $view ?>&filter=all" class="stat-card <?= $filter === 'all' ? 'active' : '' ?>">
                <div class="stat-number"><?= $counts['all'] ?? 0 ?></div>
                <div class="stat-label">All Bookings</div>
            </a>
            <a href="?view=<?= $view ?>&filter=pending" class="stat-card pending <?= $filter === 'pending' ? 'active' : '' ?>">
                <div class="stat-number"><?= $counts['pending'] ?? 0 ?></div>
                <div class="stat-label">Pending</div>
            </a>
            <a href="?view=<?= $view ?>&filter=confirmed" class="stat-card confirmed <?= $filter === 'confirmed' ? 'active' : '' ?>">
                <div class="stat-number"><?= $counts['confirmed'] ?? 0 ?></div>
                <div class="stat-label">Confirmed</div>
            </a>
            <a href="?view=<?= $view ?>&filter=cancelled" class="stat-card cancelled <?= $filter === 'cancelled' ? 'active' : '' ?>">
                <div class="stat-number"><?= $counts['cancelled'] ?? 0 ?></div>
                <div class="stat-label">Cancelled</div>
            </a>
        </div>

        <?php if ($view === 'calendar'): ?>
        <!-- Calendar View -->
        <div class="calendar-section">
            <div class="calendar-header">
                <h2 class="calendar-title">Appointment Calendar</h2>
            </div>
            
            <!-- Block Date Form -->
            <div class="block-form">
                <h4>🚫 Block Date/Time</h4>
                <form method="POST" action="?view=calendar">
                    <input type="hidden" name="action" value="block_date">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Date *</label>
                            <input type="date" name="block_date" required>
                        </div>
                        <div class="form-group">
                            <label>Start Time (optional)</label>
                            <input type="time" name="block_time">
                        </div>
                        <div class="form-group">
                            <label>End Time (optional)</label>
                            <input type="time" name="end_time">
                        </div>
                        <div class="form-group">
                            <label>Staff (optional)</label>
                            <select name="staff_id">
                                <option value="">All Staff</option>
                                <?php foreach ($staffList as $staff): ?>
                                <option value="<?= $staff['id'] ?>"><?= htmlspecialchars($staff['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Reason</label>
                            <input type="text" name="reason" placeholder="e.g., Holiday, Sick leave">
                        </div>
                    </div>
                    <button type="submit" class="btn-block">Block Date</button>
                </form>
            </div>
            
            <div id="calendar"></div>
        </div>
        
        <!-- FullCalendar JS -->
        <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const calendarEl = document.getElementById('calendar');
                const calendar = new FullCalendar.Calendar(calendarEl, {
                    initialView: 'dayGridMonth',
                    headerToolbar: {
                        left: 'prev,next today',
                        center: 'title',
                        right: 'dayGridMonth,timeGridWeek,timeGridDay'
                    },
                    events: 'admin-dashboard.php?ajax=events',
                    eventClick: function(info) {
                        const props = info.event.extendedProps;
                        if (props.type === 'booking') {
                            alert(
                                `Booking #${props.bookingId}\n` +
                                `Client: ${info.event.title}\n` +
                                `Email: ${props.email}\n` +
                                `Phone: ${props.phone}\n` +
                                `Services: ${props.services}\n` +
                                `Status: ${props.status.toUpperCase()}\n` +
                                `Artist: ${props.staff}`
                            );
                        } else if (props.type === 'blocked') {
                            if (confirm(`Remove block: ${props.reason || 'Blocked'}?\nStaff: ${props.staff}`)) {
                                const form = document.createElement('form');
                                form.method = 'POST';
                                form.innerHTML = `
                                    <input type="hidden" name="action" value="unblock">
                                    <input type="hidden" name="block_id" value="${props.blockId}">
                                `;
                                document.body.appendChild(form);
                                form.submit();
                            }
                        }
                    },
                    height: 'auto',
                    slotMinTime: '09:00:00',
                    slotMaxTime: '20:00:00',
                    allDaySlot: true,
                    nowIndicator: true,
                    businessHours: {
                        daysOfWeek: [1, 2, 3, 4, 5, 6],
                        startTime: '10:00',
                        endTime: '18:00'
                    }
                });
                calendar.render();
            });
        </script>
        
        <?php else: ?>
        <!-- List View -->
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
                                <div class="booking-detail">
                                    <strong>Artist</strong>
                                    <?= htmlspecialchars($booking['staff_name'] ?? 'Any Available') ?>
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
        <?php endif; ?>
    </div>
</body>
</html>
