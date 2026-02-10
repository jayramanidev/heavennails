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
    // Validate CSRF token for all POST actions
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        http_response_code(403);
        die('Invalid CSRF token. Please refresh and try again.');
    }
    
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
            // Fetch booking details first to get email
            $stmt = $db->prepare("SELECT * FROM appointments WHERE id = ?");
            $stmt->execute([$bookingId]);
            $booking = $stmt->fetch();

            if ($booking) {
                // Update status
                $updateStmt = $db->prepare("UPDATE appointments SET status = ? WHERE id = ?");
                $updateStmt->execute([$newStatus, $bookingId]);

                // Send email notification based on status
                $clientName = $booking['client_name'];
                $email = $booking['email'];
                $date = date('F j, Y', strtotime($booking['preferred_date']));
                $time = date('g:i A', strtotime($booking['preferred_time']));

                if ($newStatus === 'confirmed') {
                    $servicesArr = json_decode($booking['services'], true);
                    $serviceList = is_array($servicesArr) ? implode(', ', $servicesArr) : $booking['services'];
                    $dayOfWeek = date('l', strtotime($booking['preferred_date']));
                    $googleMapsLink = "https://maps.app.goo.gl/sL6c3vtcnqBhm7os8"; // From index.html

                    $subject = "You’re Booked! ✨ Appointment Confirmed at The Heaven Nails";
                    $message = "
<html>
<body style='font-family: sans-serif; color: #333;'>
<p>Dear $clientName,</p>

<p>We are delighted to confirm your appointment! ✅ Your slot has been officially secured, and our team is looking forward to pampering you.</p>

<p><strong>Your Appointment Details:</strong></p>

<p>
Service: $serviceList<br>
📅 Date: $dayOfWeek, $date<br>
🕒 Time: $time<br>
📍 Location: The Heaven Nails, Shree Ram Society 5-A, Rajkot. (<a href='$googleMapsLink'>View on Map</a>)
</p>

<p><strong>✨ Important Reminders for a Perfect Experience:</strong></p>

<ul>
<li><strong>Arrive on Time:</strong> Please arrive 10 minutes early to select your colors and relax. If you are more than 15 minutes late, we may need to shorten your service or reschedule to respect the next client’s time.</li>
<li><strong>Gel Removal:</strong> If you currently have gel polish or extensions on your nails that need removal, please let us know in advance so we can adjust the timing.</li>
<li><strong>Cancellations:</strong> We understand plans change! If you need to reschedule, please notify us at least 24 hours in advance.</li>
</ul>

<p>Need help finding us? Call or WhatsApp us at " . SALON_PHONE . ".</p>

<p>See you on $dayOfWeek!</p>

<p>Warmly,<br>
The Heaven Nails Team<br>
<em>Where Beauty Meets Perfection</em></p>
</body>
</html>
";
                } elseif ($newStatus === 'cancelled') {
                    $reason = "Due to an unforeseen scheduling conflict on our end. We sincerely apologize for the inconvenience."; // Default reason
                    
                    $subject = "Update regarding your appointment ❌";
                    $message = "
<html>
<body style='font-family: sans-serif; color: #333;'>
<p>Dear $clientName,</p>

<p>We are writing to inform you that your appointment scheduled for $date at $time has been cancelled.</p>

<p><strong>Reason:</strong> $reason</p>

<p><strong>Ready to re-book?</strong> We would love to see you another time! You can reschedule your appointment by replying to this email or calling us at " . SALON_PHONE . ".</p>

<p>Thank you for understanding.</p>

<p>Best regards,<br>
The Heaven Nails Team Rajkot</p>
</body>
</html>
";
                }

                if ($message) {
                    sendMail($email, $subject, $message);
                }
            }
        }
        header('Location: admin' . (isset($_GET['view']) ? '?view=' . $_GET['view'] : ''));
        exit;
    }
    
    if ($action === 'delete') {
        $bookingId = intval($_POST['booking_id'] ?? 0);
        if ($bookingId > 0) {
            $stmt = $db->prepare("DELETE FROM appointments WHERE id = ?");
            $stmt->execute([$bookingId]);
        }
        header('Location: admin' . (isset($_GET['view']) ? '?view=' . $_GET['view'] : ''));
        exit;
    }

    // Service Management Actions
    if ($action === 'add_service') {
        $name = sanitize($_POST['name'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        $duration = intval($_POST['duration'] ?? 60);
        $description = sanitize($_POST['description'] ?? '');
        $icon = sanitize($_POST['icon'] ?? 'fa-solid fa-sparkles');
        
        if ($name && $price > 0) {
            $stmt = $db->prepare("INSERT INTO services (name, price, duration_minutes, description, icon_class) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $price, $duration, $description, $icon]);
        }
        header('Location: ../admin?view=services');
        exit;
    }

    if ($action === 'edit_service') {
        $id = intval($_POST['service_id'] ?? 0);
        $name = sanitize($_POST['name'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        $duration = intval($_POST['duration'] ?? 60);
        $description = sanitize($_POST['description'] ?? '');
        $icon = sanitize($_POST['icon'] ?? '');
        
        if ($id > 0 && $name && $price > 0) {
            $stmt = $db->prepare("UPDATE services SET name=?, price=?, duration_minutes=?, description=?, icon_class=? WHERE id=?");
            $stmt->execute([$name, $price, $duration, $description, $icon, $id]);
        }
        header('Location: ../admin?view=services');
        exit;
    }

    if ($action === 'toggle_service') {
        $id = intval($_POST['service_id'] ?? 0);
        $active = intval($_POST['is_active'] ?? 0);
        if ($id > 0) {
            $stmt = $db->prepare("UPDATE services SET is_active = ? WHERE id = ?");
            $stmt->execute([$active, $id]);
        }
        header('Location: ../admin?view=services');
        exit;
    }
}

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login');
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

    // Get services if view is services
    $servicesList = [];
    if ($view === 'services') {
        $svcStmt = $db->query("SELECT * FROM services ORDER BY name");
        $servicesList = $svcStmt->fetchAll();
    }
} catch (PDOException $e) {
    error_log("Fetch error: " . $e->getMessage());
    $staffList = [];
    $servicesList = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Heaven Nails</title>
    <link rel="icon" type="image/jpeg" href="../assets/images/logo/logo.png.jpg?v=2">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;600&family=Montserrat:wght@400;500;600&display=swap" rel="stylesheet">
    <!-- FullCalendar CSS -->
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Montserrat', sans-serif; background: #f5f0ea; min-height: 100vh; }
        
        .header { background: #1a1a1a; color: white; padding: 1rem 1.5rem; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 100; }
        .logo { display: flex; align-items: center; }
        .logo-img { max-height: 40px; mix-blend-mode: screen; }
        
        .header-actions { display: flex; gap: 1rem; align-items: center; }
        .header-actions a { color: white; text-decoration: none; font-size: 0.9rem; padding: 0.5rem 1rem; border-radius: 4px; transition: background 0.2s; }
        .header-actions a:hover { background: rgba(255,255,255,0.1); }
        .btn-logout { background: #ef4444 !important; }
        .btn-logout:hover { background: #dc2626 !important; }
        
        .container { max-width: 1200px; margin: 0 auto; padding: 2rem 1.5rem; }
        
        /* View Toggle */
        .view-toggle { display: flex; gap: 0.5rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
        .view-btn { padding: 0.75rem 1.25rem; background: white; border: 2px solid #e0e0e0; border-radius: 8px; color: #666; text-decoration: none; font-weight: 500; transition: all 0.2s; }
        .view-btn:hover { border-color: #c9a66b; color: #c9a66b; }
        .view-btn.active { background: #c9a66b; color: white; border-color: #c9a66b; }
        
        /* Stats Cards */
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
        .stat-card { background: white; padding: 1.5rem; border-radius: 12px; text-align: center; text-decoration: none; color: inherit; box-shadow: 0 2px 8px rgba(0,0,0,0.05); border: 2px solid transparent; transition: all 0.2s; }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .stat-card.active { border-color: #c9a66b; }
        .stat-number { font-size: 2.5rem; font-weight: 700; color: #2d2d2d; }
        .stat-label { font-size: 0.875rem; color: #666; margin-top: 0.25rem; text-transform: uppercase; letter-spacing: 0.05em; }
        .stat-card.pending .stat-number { color: #f59e0b; }
        .stat-card.confirmed .stat-number { color: #10b981; }
        .stat-card.cancelled .stat-number { color: #ef4444; }
        
        /* Section Headers */
        .section-header { margin-bottom: 1.5rem; }
        .section-title { font-family: 'Cormorant Garamond', serif; font-size: 2rem; font-weight: 600; color: #2d2d2d; }
        
        /* Bookings List */
        .bookings-section { background: white; padding: 1.5rem; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .booking-list { display: flex; flex-direction: column; gap: 1rem; }
        .booking-item { background: #faf8f5; padding: 1.25rem; border-radius: 8px; border: 1px solid #e0e0e0; }
        .booking-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.5rem; }
        .booking-client { font-size: 1.125rem; font-weight: 600; color: #2d2d2d; }
        .booking-id { font-size: 0.8rem; color: #999; margin-top: 0.25rem; }
        .booking-details { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 1rem; }
        .booking-detail { font-size: 0.9rem; }
        .booking-detail strong { display: block; font-size: 0.75rem; text-transform: uppercase; color: #999; margin-bottom: 0.25rem; }
        .booking-detail a { color: #c9a66b; text-decoration: none; }
        .booking-detail a:hover { text-decoration: underline; }
        .booking-services { background: white; padding: 0.75rem; border-radius: 6px; font-size: 0.9rem; margin-bottom: 0.75rem; }
        .booking-notes { font-size: 0.875rem; color: #666; font-style: italic; margin-bottom: 0.75rem; }
        .booking-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        
        /* Status Badges */
        .status-badge { padding: 0.4rem 0.75rem; border-radius: 20px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-confirmed { background: #d1fae5; color: #065f46; }
        .status-cancelled { background: #fee2e2; color: #991b1b; }
        
        /* Action Buttons */
        .action-btn { padding: 0.5rem 1rem; border: none; border-radius: 6px; font-size: 0.8rem; font-weight: 500; cursor: pointer; transition: all 0.2s; }
        .btn-confirm { background: #10b981; color: white; }
        .btn-confirm:hover { background: #059669; }
        .btn-cancel { background: #f59e0b; color: white; }
        .btn-cancel:hover { background: #d97706; }
        .btn-delete { background: #ef4444; color: white; }
        .btn-delete:hover { background: #dc2626; }
        
        /* Empty State */
        .empty-state { text-align: center; padding: 3rem 1rem; color: #999; }
        .empty-icon { font-size: 3rem; margin-bottom: 1rem; }
        
        /* Calendar Section */
        .calendar-section { background: white; padding: 1.5rem; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .calendar-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem; }
        .calendar-title { font-family: 'Cormorant Garamond', serif; font-size: 1.75rem; font-weight: 600; color: #2d2d2d; }
        
        /* Block Date Form */
        .block-form { background: #faf8f5; padding: 1.25rem; border-radius: 8px; margin-bottom: 1.5rem; border: 1px solid #e0e0e0; }
        .block-form h4 { margin-bottom: 1rem; color: #2d2d2d; }
        .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-bottom: 1rem; }
        .form-group { display: flex; flex-direction: column; gap: 0.35rem; }
        .form-group label { font-size: 0.8rem; font-weight: 500; color: #666; }
        .form-group input, .form-group select { padding: 0.6rem; border: 1px solid #ddd; border-radius: 6px; font-size: 0.9rem; }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: #c9a66b; }
        .btn-block { background: #374151; color: white; padding: 0.75rem 1.5rem; border: none; border-radius: 6px; font-weight: 500; cursor: pointer; width: 100%; transition: background 0.2s; }
        .btn-block:hover { background: #1f2937; }
        
        /* FullCalendar Customizations */
        #calendar { margin-top: 1rem; }
        .fc { font-family: 'Montserrat', sans-serif; }
        .fc-toolbar-title { font-family: 'Cormorant Garamond', serif !important; }
        .fc-button-primary { background-color: #c9a66b !important; border-color: #c9a66b !important; }
        .fc-button-primary:hover { background-color: #b8956a !important; border-color: #b8956a !important; }
        .fc-button-primary:not(:disabled).fc-button-active { background-color: #8b7355 !important; border-color: #8b7355 !important; }
        
        /* Services Section */
        .services-section { background: white; padding: 1.5rem; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        
        /* Modals */
        .modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
        .modal-content { background: white; padding: 2rem; border-radius: 12px; width: 500px; max-width: 90%; max-height: 90vh; overflow-y: auto; }
        .modal-content h3 { font-family: 'Cormorant Garamond', serif; font-size: 1.5rem; margin-bottom: 1.5rem; color: #2d2d2d; }
        
        /* Responsive */
        @media (max-width: 768px) {
            .header { padding: 0.75rem 1rem; }
            .container { padding: 1rem; }
            .stats { grid-template-columns: repeat(2, 1fr); }
            .stat-number { font-size: 1.75rem; }
            .booking-details { grid-template-columns: 1fr; }
            .form-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="logo">
            <img src="../assets/images/logo/logo.png.jpg" alt="Heaven Nails Admin" class="logo-img">
            <span style="font-family: 'Cormorant Garamond', serif; font-size: 1.25rem; margin-left: 10px;">The Heaven <span>Nails</span></span>
        </div>
        <div class="header-actions">
            <a href="../">View Site</a>
            <a href="?logout=1" class="btn-logout">Logout</a>
        </div>
    </header>

    <div class="container">
        <!-- View Toggle -->
        <div class="view-toggle">
            <a href="?view=list" class="view-btn <?= $view === 'list' ? 'active' : '' ?>">📋 List View</a>
            <a href="?view=calendar" class="view-btn <?= $view === 'calendar' ? 'active' : '' ?>">📅 Calendar View</a>
            <a href="?view=services" class="view-btn <?= $view === 'services' ? 'active' : '' ?>">💅 Services</a>
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
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRFToken()) ?>">
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
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRFToken()) ?>">
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
        
        <?php elseif ($view === 'services'): ?>
        <!-- Services Management -->
        <div class="services-section">
            <div class="section-header" style="display: flex; justify-content: space-between; align-items: center;">
                <h2 class="section-title">Manage Services</h2>
                <button onclick="document.getElementById('addServiceModal').style.display='block'" class="btn-block" style="width: auto;">+ Add New Service</button>
            </div>

            <div class="service-list" style="background: white; padding: 1rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 2px solid #f0f0f0; text-align: left;">
                            <th style="padding: 1rem;">Service Name</th>
                            <th style="padding: 1rem;">Price</th>
                            <th style="padding: 1rem;">Duration</th>
                            <th style="padding: 1rem;">Status</th>
                            <th style="padding: 1rem;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($servicesList as $svc): ?>
                        <tr style="border-bottom: 1px solid #f0f0f0;">
                            <td style="padding: 1rem;">
                                <strong><?= htmlspecialchars($svc['name']) ?></strong><br>
                                <small style="color: #666;"><?= htmlspecialchars(substr($svc['description'], 0, 50)) ?>...</small>
                            </td>
                            <td style="padding: 1rem;">₹<?= number_format($svc['price'], 2) ?></td>
                            <td style="padding: 1rem;"><?= $svc['duration_minutes'] ?> mins</td>
                            <td style="padding: 1rem;">
                                <span class="status-badge status-<?= $svc['is_active'] ? 'confirmed' : 'cancelled' ?>">
                                    <?= $svc['is_active'] ? 'Active' : 'Inactive' ?>
                                </span>
                            </td>
                            <td style="padding: 1rem;">
                                <button onclick="editService(<?= htmlspecialchars(json_encode($svc)) ?>)" class="action-btn" style="background: #3b82f6; color: white;">Edit</button>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="toggle_service">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRFToken()) ?>">
                                    <input type="hidden" name="service_id" value="<?= $svc['id'] ?>">
                                    <input type="hidden" name="is_active" value="<?= $svc['is_active'] ? 0 : 1 ?>">
                                    <button type="submit" class="action-btn" style="background: <?= $svc['is_active'] ? '#ef4444' : '#10b981' ?>; color: white;">
                                        <?= $svc['is_active'] ? 'Deactivate' : 'Activate' ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Add Service Modal -->
        <div id="addServiceModal" class="modal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
            <div class="modal-content" style="background: white; padding: 2rem; border-radius: 8px; width: 500px; max-width: 90%;">
                <h3>Add New Service</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="add_service">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRFToken()) ?>">
                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" name="name" required style="width: 100%; padding: 0.5rem; border: 1px solid #ccc;">
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" rows="3" style="width: 100%; padding: 0.5rem; border: 1px solid #ccc;"></textarea>
                    </div>
                    <div class="form-row" style="display: flex; gap: 1rem;">
                        <div class="form-group" style="flex: 1;">
                            <label>Price (₹)</label>
                            <input type="number" name="price" required step="0.01" style="width: 100%; padding: 0.5rem; border: 1px solid #ccc;">
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <label>Duration (mins)</label>
                            <input type="number" name="duration" required style="width: 100%; padding: 0.5rem; border: 1px solid #ccc;">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Icon Class (FontAwesome)</label>
                        <input type="text" name="icon" placeholder="fa-solid fa-sparkles" style="width: 100%; padding: 0.5rem; border: 1px solid #ccc;">
                    </div>
                    <div style="display: flex; justify-content: flex-end; gap: 1rem; margin-top: 1rem;">
                        <button type="button" onclick="document.getElementById('addServiceModal').style.display='none'" class="btn-secondary">Cancel</button>
                        <button type="submit" class="btn-primary">Add Service</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Edit Service Modal -->
        <div id="editServiceModal" class="modal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
            <div class="modal-content" style="background: white; padding: 2rem; border-radius: 8px; width: 500px; max-width: 90%;">
                <h3>Edit Service</h3>
                <form method="POST" id="editServiceForm">
                    <input type="hidden" name="action" value="edit_service">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRFToken()) ?>">
                    <input type="hidden" name="service_id" id="edit_id">
                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" name="name" id="edit_name" required style="width: 100%; padding: 0.5rem; border: 1px solid #ccc;">
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" id="edit_description" rows="3" style="width: 100%; padding: 0.5rem; border: 1px solid #ccc;"></textarea>
                    </div>
                    <div class="form-row" style="display: flex; gap: 1rem;">
                        <div class="form-group" style="flex: 1;">
                            <label>Price (₹)</label>
                            <input type="number" name="price" id="edit_price" required step="0.01" style="width: 100%; padding: 0.5rem; border: 1px solid #ccc;">
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <label>Duration (mins)</label>
                            <input type="number" name="duration" id="edit_duration" required style="width: 100%; padding: 0.5rem; border: 1px solid #ccc;">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Icon Class (FontAwesome)</label>
                        <input type="text" name="icon" id="edit_icon" style="width: 100%; padding: 0.5rem; border: 1px solid #ccc;">
                    </div>
                    <div style="display: flex; justify-content: flex-end; gap: 1rem; margin-top: 1rem;">
                        <button type="button" onclick="document.getElementById('editServiceModal').style.display='none'" class="btn-secondary">Cancel</button>
                        <button type="submit" class="btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>

        <script>
            function editService(svc) {
                document.getElementById('edit_id').value = svc.id;
                document.getElementById('edit_name').value = svc.name;
                document.getElementById('edit_description').value = svc.description;
                document.getElementById('edit_price').value = svc.price;
                document.getElementById('edit_duration').value = svc.duration_minutes;
                document.getElementById('edit_icon').value = svc.icon_class;
                
                const modal = document.getElementById('editServiceModal');
                modal.style.display = 'flex';
            }
            
            // Close modals when clicking outside
            window.onclick = function(event) {
                if (event.target.classList.contains('modal')) {
                    event.target.style.display = "none";
                }
            }
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
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRFToken()) ?>">
                                        <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                                        <input type="hidden" name="status" value="confirmed">
                                        <button type="submit" class="action-btn btn-confirm">Confirm</button>
                                    </form>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRFToken()) ?>">
                                        <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                                        <input type="hidden" name="status" value="cancelled">
                                        <button type="submit" class="action-btn btn-cancel">Cancel</button>
                                    </form>
                                <?php elseif ($booking['status'] === 'confirmed'): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRFToken()) ?>">
                                        <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                                        <input type="hidden" name="status" value="cancelled">
                                        <button type="submit" class="action-btn btn-cancel">Cancel</button>
                                    </form>
                                <?php endif; ?>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this booking permanently?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRFToken()) ?>">
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
