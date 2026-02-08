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
    <link rel="icon" type="image/jpeg" href="../assets/images/logo/logo.png.jpg?v=2">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;600&family=Montserrat:wght@400;500;600&display=swap" rel="stylesheet">
    <!-- FullCalendar CSS -->
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Montserrat', sans-serif; background: #f5f0ea; min-height: 100vh; }
        
        .header { background: #1a1a1a; color: white; padding: 1rem 1.5rem; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 100; }
        .logo { display: flex; align-items: center; }
        .logo-img { max-height: 40px; mix-blend-mode: screen; /* Lightens on dark header */ }
        
        .header-actions { display: flex; gap: 1rem; align-items: center; }
/* ... (existing styles) ... */
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
