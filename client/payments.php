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
    SELECT p.*, 
           b.booking_number,
           t.title AS tour_title,
           c.name AS country_name
    FROM payments p
    LEFT JOIN bookings b ON p.booking_id = b.id
    LEFT JOIN tours t ON b.tour_id = t.id
    LEFT JOIN countries c ON t.country_id = c.id
    WHERE b.user_id = ?
";

// Add status filter
if ($status_filter !== 'all') {
    $sql .= " AND p.status = ?";
}

// Add search filter
if (!empty($search_query)) {
    $sql .= " AND (t.title LIKE ? OR c.name LIKE ? OR b.booking_number LIKE ? OR p.payment_number LIKE ?)";
}

// Add sorting
switch ($sort_by) {
    case 'date_asc':
        $sql .= " ORDER BY p.payment_date ASC";
        break;
    case 'date_desc':
        $sql .= " ORDER BY p.payment_date DESC";
        break;
    case 'amount_asc':
        $sql .= " ORDER BY p.amount ASC";
        break;
    case 'amount_desc':
        $sql .= " ORDER BY p.amount DESC";
        break;
    case 'created_at_asc':
        $sql .= " ORDER BY p.created_at ASC";
        break;
    default: // created_at_desc
        $sql .= " ORDER BY p.created_at DESC";
        break;
}

// Prepare and execute the query
$stmt = $db->prepare($sql);

if ($status_filter !== 'all' && !empty($search_query)) {
    $search_param = "%$search_query%";
    $stmt->execute([$user_id, $status_filter, $search_param, $search_param, $search_param, $search_param]);
} elseif ($status_filter !== 'all') {
    $stmt->execute([$user_id, $status_filter]);
} elseif (!empty($search_query)) {
    $search_param = "%$search_query%";
    $stmt->execute([$user_id, $search_param, $search_param, $search_param, $search_param]);
} else {
    $stmt->execute([$user_id]);
}

$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get payment statistics for filter badges
$stats = $db->prepare("
    SELECT 
        COUNT(*) AS total,
        COUNT(CASE WHEN p.status = 'completed' THEN 1 END) AS completed,
        COUNT(CASE WHEN p.status = 'pending' THEN 1 END) AS pending,
        COUNT(CASE WHEN p.status = 'failed' THEN 1 END) AS failed,
        COUNT(CASE WHEN p.status = 'refunded' THEN 1 END) AS refunded,
        COALESCE(SUM(p.amount), 0) AS total_amount
    FROM payments p
    JOIN bookings b ON p.booking_id = b.id
    WHERE b.user_id = ?
");
$stats->execute([$user_id]);
$payment_stats = $stats->fetch(PDO::FETCH_ASSOC);

// Set page title
$page_title = 'Payment History - Forever Young Tours';
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
                    <h1>Payment History</h1>
                    <div class="page-actions">
                        <a href="pay-now.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Make Payment
                        </a>
                    </div>
                </div>

                <!-- Payment Filters -->
                <div class="booking-filters">
                    <div class="filter-group">
                        <div class="filter-label">Filter by status:</div>
                        <div class="status-filters">
                            <a href="?status=all"
                                class="status-badge <?php echo $status_filter === 'all' ? 'active' : ''; ?>">
                                All <span class="badge-count"><?php echo $payment_stats['total']; ?></span>
                            </a>
                            <a href="?status=completed"
                                class="status-badge <?php echo $status_filter === 'completed' ? 'active' : ''; ?>">
                                Completed <span class="badge-count"><?php echo $payment_stats['completed']; ?></span>
                            </a>
                            <a href="?status=pending"
                                class="status-badge <?php echo $status_filter === 'pending' ? 'active' : ''; ?>">
                                Pending <span class="badge-count"><?php echo $payment_stats['pending']; ?></span>
                            </a>
                            <a href="?status=failed"
                                class="status-badge <?php echo $status_filter === 'failed' ? 'active' : ''; ?>">
                                Failed <span class="badge-count"><?php echo $payment_stats['failed']; ?></span>
                            </a>
                            <a href="?status=refunded"
                                class="status-badge <?php echo $status_filter === 'refunded' ? 'active' : ''; ?>">
                                Refunded <span class="badge-count"><?php echo $payment_stats['refunded']; ?></span>
                            </a>
                        </div>
                    </div>

                    <div class="filter-group">
                        <form method="get" class="search-form">
                            <input type="hidden" name="status" value="<?php echo $status_filter; ?>">
                            <div class="search-input-group">
                                <input type="text" name="search" placeholder="Search payments..."
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
                                    Sort by: Payment Date (Asc)
                                </option>
                                <option
                                    value="?status=<?php echo $status_filter; ?>&sort=date_desc<?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>"
                                    <?php echo $sort_by === 'date_desc' ? 'selected' : ''; ?>>
                                    Sort by: Payment Date (Desc)
                                </option>
                                <option
                                    value="?status=<?php echo $status_filter; ?>&sort=amount_asc<?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>"
                                    <?php echo $sort_by === 'amount_asc' ? 'selected' : ''; ?>>
                                    Sort by: Amount (Low to High)
                                </option>
                                <option
                                    value="?status=<?php echo $status_filter; ?>&sort=amount_desc<?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>"
                                    <?php echo $sort_by === 'amount_desc' ? 'selected' : ''; ?>>
                                    Sort by: Amount (High to Low)
                                </option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Payments Summary -->
                <div class="summary-card">
                    <div class="summary-item">
                        <div class="summary-label">Total Payments</div>
                        <div class="summary-value"><?php echo $payment_stats['total']; ?></div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Total Amount</div>
                        <div class="summary-value">$<?php echo number_format($payment_stats['total_amount'], 2); ?>
                        </div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Completed</div>
                        <div class="summary-value"><?php echo $payment_stats['completed']; ?></div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Pending</div>
                        <div class="summary-value"><?php echo $payment_stats['pending']; ?></div>
                    </div>
                </div>

                <!-- Payments List -->
                <div class="payments-container">
                    <?php if (empty($payments)): ?>
                        <div class="empty-state">
                            <i class="fas fa-receipt"></i>
                            <h3>No Payments Found</h3>
                            <p>You don't have any payments matching your criteria.</p>
                        </div>
                    <?php else: ?>
                        <div class="payments-list">
                            <?php foreach ($payments as $payment): ?>
                                <div class="payment-card">
                                    <div class="payment-header">
                                        <div class="payment-number">
                                            <i class="fas fa-receipt"></i>
                                            #<?php echo $payment['payment_number']; ?>
                                        </div>
                                        <div class="payment-status <?php echo $payment['status']; ?>">
                                            <?php echo ucfirst($payment['status']); ?>
                                        </div>
                                    </div>

                                    <div class="payment-body">
                                        <div class="payment-info">
                                            <div class="info-row">
                                                <span class="info-label">Booking:</span>
                                                <span class="info-value">
                                                    #<?php echo $payment['booking_number']; ?>
                                                    <?php if (!empty($payment['tour_title'])): ?>
                                                        - <?php echo htmlspecialchars($payment['tour_title']); ?>
                                                    <?php endif; ?>
                                                </span>
                                            </div>
                                            <div class="info-row">
                                                <span class="info-label">Date:</span>
                                                <span class="info-value">
                                                    <?php echo date('M j, Y', strtotime($payment['payment_date'])); ?>
                                                </span>
                                            </div>
                                            <div class="info-row">
                                                <span class="info-label">Method:</span>
                                                <span class="info-value">
                                                    <?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?>
                                                </span>
                                            </div>
                                        </div>

                                        <div class="payment-amount">
                                            <div class="amount">$<?php echo number_format($payment['amount'], 2); ?></div>
                                            <?php if ($payment['status'] === 'completed'): ?>
                                                <div class="payment-success">
                                                    <i class="fas fa-check-circle"></i> Paid
                                                </div>
                                            <?php elseif ($payment['status'] === 'pending'): ?>
                                                <a href="pay-now.php?payment_id=<?php echo $payment['id']; ?>"
                                                    class="btn btn-sm btn-primary">
                                                    Complete Payment
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="payment-footer">
                                        <div class="payment-actions">
                                            <a href="payment-details.php?id=<?php echo $payment['id']; ?>"
                                                class="btn btn-sm btn-outline">
                                                <i class="fas fa-eye"></i> View Details
                                            </a>
                                            <?php if ($payment['status'] === 'completed' && !empty($payment['receipt_url'])): ?>
                                                <a href="<?php echo $payment['receipt_url']; ?>" class="btn btn-sm btn-outline"
                                                    target="_blank">
                                                    <i class="fas fa-file-download"></i> Download Receipt
                                                </a>
                                            <?php endif; ?>
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
            document.getElementById('pageTitle').textContent = 'Payment History';

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