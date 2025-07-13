<?php
// Show any PHP errors clearly (remove on production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session
session_start();

// Ensure user is logged in and is a client
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'client') {
    header('Location: ../login.php');
    exit;
}

// Load config and DB connection
require_once __DIR__ . '/../config/config.php';

// Get user ID
$user_id = $_SESSION['user_id'];

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'created_at_desc';

// Base SQL query
$sql = "
    SELECT b.*, 
           t.title AS tour_title, t.featured_image AS tour_image, t.duration_days,
           c.name AS country_name,
           COALESCE(SUM(p.amount), 0) AS paid_amount
    FROM bookings b
    LEFT JOIN tours t ON b.tour_id = t.id
    LEFT JOIN countries c ON t.country_id = c.id
    LEFT JOIN payments p ON b.id = p.booking_id AND p.status = 'completed'
    WHERE b.user_id = ?
";

// Add status filter
if ($status_filter !== 'all') {
    $sql .= " AND b.status = ?";
}

// Add search filter
if (!empty($search_query)) {
    $sql .= " AND (t.title LIKE ? OR c.name LIKE ? OR b.booking_number LIKE ?)";
}

// Group by and base sort
$sql .= " GROUP BY b.id";

// Add sorting
switch ($sort_by) {
    case 'date_asc':
        $sql .= " ORDER BY b.tour_date ASC";
        break;
    case 'date_desc':
        $sql .= " ORDER BY b.tour_date DESC";
        break;
    case 'price_asc':
        $sql .= " ORDER BY b.total_amount ASC";
        break;
    case 'price_desc':
        $sql .= " ORDER BY b.total_amount DESC";
        break;
    case 'created_at_asc':
        $sql .= " ORDER BY b.created_at ASC";
        break;
    default: // created_at_desc
        $sql .= " ORDER BY b.created_at DESC";
        break;
}

// Prepare and execute the query
$stmt = $db->prepare($sql);

if ($status_filter !== 'all' && !empty($search_query)) {
    $search_param = "%$search_query%";
    $stmt->execute([$user_id, $status_filter, $search_param, $search_param, $search_param]);
} elseif ($status_filter !== 'all') {
    $stmt->execute([$user_id, $status_filter]);
} elseif (!empty($search_query)) {
    $search_param = "%$search_query%";
    $stmt->execute([$user_id, $search_param, $search_param, $search_param]);
} else {
    $stmt->execute([$user_id]);
}

$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get booking statistics for filter badges
$stats = $db->prepare("
    SELECT 
        COUNT(*) AS total,
        COUNT(CASE WHEN status = 'confirmed' THEN 1 END) AS confirmed,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) AS pending,
        COUNT(CASE WHEN status = 'cancelled' THEN 1 END) AS cancelled,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) AS completed
    FROM bookings 
    WHERE user_id = ?
");
$stats->execute([$user_id]);
$booking_stats = $stats->fetch(PDO::FETCH_ASSOC);

// Set page title
$page_title = 'My Bookings - Forever Young Tours';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="../assets/css/client-portal.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>

<body>
    <div class="client-wrapper">
        <?php include 'includes/sidebar.php'; ?>

        <div class="main-content">
            <?php include 'includes/header.php'; ?>

            <div class="content">
                <div class="page-header">
                    <h1>My Bookings</h1>
                    <div class="page-actions">
                        <a href="../book.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> New Booking
                        </a>
                    </div>
                </div>

                <!-- Booking Filters -->
                <div class="booking-filters">
                    <div class="filter-group">
                        <div class="filter-label">Filter by status:</div>
                        <div class="status-filters">
                            <a href="?status=all"
                                class="status-badge <?php echo $status_filter === 'all' ? 'active' : ''; ?>">
                                All <span class="badge-count"><?php echo $booking_stats['total']; ?></span>
                            </a>
                            <a href="?status=confirmed"
                                class="status-badge <?php echo $status_filter === 'confirmed' ? 'active' : ''; ?>">
                                Confirmed <span class="badge-count"><?php echo $booking_stats['confirmed']; ?></span>
                            </a>
                            <a href="?status=pending"
                                class="status-badge <?php echo $status_filter === 'pending' ? 'active' : ''; ?>">
                                Pending <span class="badge-count"><?php echo $booking_stats['pending']; ?></span>
                            </a>
                            <a href="?status=cancelled"
                                class="status-badge <?php echo $status_filter === 'cancelled' ? 'active' : ''; ?>">
                                Cancelled <span class="badge-count"><?php echo $booking_stats['cancelled']; ?></span>
                            </a>
                            <a href="?status=completed"
                                class="status-badge <?php echo $status_filter === 'completed' ? 'active' : ''; ?>">
                                Completed <span class="badge-count"><?php echo $booking_stats['completed']; ?></span>
                            </a>
                        </div>
                    </div>

                    <div class="filter-group">
                        <form method="get" class="search-form">
                            <input type="hidden" name="status" value="<?php echo $status_filter; ?>">
                            <div class="search-input-group">
                                <input type="text" name="search" placeholder="Search bookings..."
                                    value="<?php echo htmlspecialchars($search_query); ?>">
                                <button type="submit" class="search-btn">
                                    <i class="fas fa-search"></i>
                                </button>
                                <?php if (!empty($search_query)): ?>
                                    <a href="?status=<?php echo $status_filter; ?>" class="clear-search">
                                        <i class="fas fa-times"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>

                        <div class="sort-dropdown">
                            <select id="sortSelect" onchange="window.location.href=this.value">
                                <option
                                    value="?status=<?php echo $status_filter; ?>&sort=created_at_desc<?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>"
                                    <?php echo $sort_by === 'created_at_desc' ? 'selected' : ''; ?>>
                                    Sort by: Newest First
                                </option>
                                <option
                                    value="?status=<?php echo $status_filter; ?>&sort=created_at_asc<?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>"
                                    <?php echo $sort_by === 'created_at_asc' ? 'selected' : ''; ?>>
                                    Sort by: Oldest First
                                </option>
                                <option
                                    value="?status=<?php echo $status_filter; ?>&sort=date_asc<?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>"
                                    <?php echo $sort_by === 'date_asc' ? 'selected' : ''; ?>>
                                    Sort by: Tour Date (Asc)
                                </option>
                                <option
                                    value="?status=<?php echo $status_filter; ?>&sort=date_desc<?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>"
                                    <?php echo $sort_by === 'date_desc' ? 'selected' : ''; ?>>
                                    Sort by: Tour Date (Desc)
                                </option>
                                <option
                                    value="?status=<?php echo $status_filter; ?>&sort=price_asc<?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>"
                                    <?php echo $sort_by === 'price_asc' ? 'selected' : ''; ?>>
                                    Sort by: Price (Low to High)
                                </option>
                                <option
                                    value="?status=<?php echo $status_filter; ?>&sort=price_desc<?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>"
                                    <?php echo $sort_by === 'price_desc' ? 'selected' : ''; ?>>
                                    Sort by: Price (High to Low)
                                </option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Bookings List -->
                <div class="bookings-container">
                    <?php if (empty($bookings)): ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-times"></i>
                            <h3>No Bookings Found</h3>
                            <p>You don't have any bookings matching your criteria.</p>
                            <a href="../book.php" class="btn btn-primary">Book a Tour</a>
                        </div>
                    <?php else: ?>
                        <div class="bookings-list">
                            <?php foreach ($bookings as $booking): ?>
                                <div class="booking-card">
                                    <div class="booking-image">
                                        <?php if ($booking['tour_image']): ?>
                                            <img src="../<?php echo htmlspecialchars($booking['tour_image']); ?>"
                                                alt="<?php echo htmlspecialchars($booking['tour_title']); ?>">
                                        <?php else: ?>
                                            <div class="image-placeholder">
                                                <i class="fas fa-image"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="booking-content">
                                        <div class="booking-header">
                                            <h3><?php echo htmlspecialchars($booking['tour_title']); ?></h3>
                                            <div class="booking-meta">
                                                <span class="booking-number">#<?php echo $booking['booking_number']; ?></span>
                                                <span class="booking-status <?php echo $booking['status']; ?>">
                                                    <?php echo ucfirst($booking['status']); ?>
                                                </span>
                                            </div>
                                        </div>

                                        <div class="booking-details">
                                            <div class="detail-item">
                                                <i class="fas fa-map-marker-alt"></i>
                                                <?php echo htmlspecialchars($booking['country_name']); ?>
                                            </div>
                                            <div class="detail-item">
                                                <i class="fas fa-calendar"></i>
                                                <?php echo date('M j, Y', strtotime($booking['tour_date'])); ?>
                                                <?php if ($booking['duration_days'] > 1): ?>
                                                    (<?php echo $booking['duration_days']; ?> days)
                                                <?php endif; ?>
                                            </div>
                                            <div class="detail-item">
                                                <i class="fas fa-users"></i>
                                                <?php echo $booking['adults']; ?> Adults
                                                <?php if ($booking['children'] > 0): ?>
                                                    , <?php echo $booking['children']; ?> Children
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <div class="booking-footer">
                                            <div class="booking-amount">
                                                <span
                                                    class="total">$<?php echo number_format($booking['total_amount'], 2); ?></span>
                                                <span class="paid">Paid:
                                                    $<?php echo number_format($booking['paid_amount'], 2); ?></span>
                                            </div>
                                            <div class="booking-actions">
                                                <a href="booking-details.php?id=<?php echo $booking['id']; ?>"
                                                    class="btn btn-sm btn-outline">
                                                    View Details
                                                </a>
                                                <?php if ($booking['status'] === 'pending'): ?>
                                                    <a href="pay-now.php?booking_id=<?php echo $booking['id']; ?>"
                                                        class="btn btn-sm btn-primary">
                                                        Pay Now
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/client-portal.js"></script>
    <script>
        // Update page title in header
        document.addEventListener('DOMContentLoaded', function () {
            document.getElementById('pageTitle').textContent = 'My Bookings';

            // Highlight active status filter on back/forward navigation
            const statusFilter = '<?php echo $status_filter; ?>';
            if (statusFilter !== 'all') {
                const activeFilter = document.querySelector(`.status-filters a[href*="status=${statusFilter}"]`);
                if (activeFilter) {
                    document.querySelectorAll('.status-filters a').forEach(link => {
                        link.classList.remove('active');
                    });
                    activeFilter.classList.add('active');
                }
            }
        });
    </script>
</body>

</html>