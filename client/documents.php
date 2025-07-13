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
$type_filter = isset($_GET['type']) ? $_GET['type'] : 'all';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'created_at_desc';

// Base SQL query
$sql = "
    SELECT d.*, 
           b.booking_number,
           t.title AS tour_title,
           c.name AS country_name
    FROM documents d
    LEFT JOIN bookings b ON d.booking_id = b.id
    LEFT JOIN tours t ON b.tour_id = t.id
    LEFT JOIN countries c ON t.country_id = c.id
    WHERE b.user_id = ?
";

// Add type filter
if ($type_filter !== 'all') {
    $sql .= " AND d.type = ?";
}

// Add search filter
if (!empty($search_query)) {
    $sql .= " AND (t.title LIKE ? OR c.name LIKE ? OR b.booking_number LIKE ? OR d.title LIKE ?)";
}

// Add sorting
switch ($sort_by) {
    case 'date_asc':
        $sql .= " ORDER BY d.issue_date ASC";
        break;
    case 'date_desc':
        $sql .= " ORDER BY d.issue_date DESC";
        break;
    case 'title_asc':
        $sql .= " ORDER BY d.title ASC";
        break;
    case 'title_desc':
        $sql .= " ORDER BY d.title DESC";
        break;
    case 'created_at_asc':
        $sql .= " ORDER BY d.created_at ASC";
        break;
    default: // created_at_desc
        $sql .= " ORDER BY d.created_at DESC";
        break;
}

// Prepare and execute the query
$stmt = $db->prepare($sql);

if ($type_filter !== 'all' && !empty($search_query)) {
    $search_param = "%$search_query%";
    $stmt->execute([$user_id, $type_filter, $search_param, $search_param, $search_param, $search_param]);
} elseif ($type_filter !== 'all') {
    $stmt->execute([$user_id, $type_filter]);
} elseif (!empty($search_query)) {
    $search_param = "%$search_query%";
    $stmt->execute([$user_id, $search_param, $search_param, $search_param, $search_param]);
} else {
    $stmt->execute([$user_id]);
}

$documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get document statistics for filter badges
$stats = $db->prepare("
    SELECT 
        COUNT(*) AS total,
        COUNT(CASE WHEN type = 'itinerary' THEN 1 END) AS itineraries,
        COUNT(CASE WHEN type = 'ticket' THEN 1 END) AS tickets,
        COUNT(CASE WHEN type = 'voucher' THEN 1 END) AS vouchers,
        COUNT(CASE WHEN type = 'invoice' THEN 1 END) AS invoices,
        COUNT(CASE WHEN type = 'other' THEN 1 END) AS others
    FROM documents d
    JOIN bookings b ON d.booking_id = b.id
    WHERE b.user_id = ?
");
$stats->execute([$user_id]);
$document_stats = $stats->fetch(PDO::FETCH_ASSOC);

// Set page title
$page_title = 'My Documents - Forever Young Tours';
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
                    <h1>My Documents</h1>
                    <div class="page-actions">
                        <a href="bookings.php" class="btn btn-outline">
                            <i class="fas fa-calendar-check"></i> View Bookings
                        </a>
                    </div>
                </div>

                <!-- Document Filters -->
                <div class="document-filters">
                    <div class="filter-group">
                        <div class="filter-label">Filter by type:</div>
                        <div class="type-filters">
                            <a href="?type=all"
                                class="type-badge <?php echo $type_filter === 'all' ? 'active' : ''; ?>">
                                All <span class="badge-count"><?php echo $document_stats['total']; ?></span>
                            </a>
                            <a href="?type=itinerary"
                                class="type-badge <?php echo $type_filter === 'itinerary' ? 'active' : ''; ?>">
                                Itineraries <span
                                    class="badge-count"><?php echo $document_stats['itineraries']; ?></span>
                            </a>
                            <a href="?type=ticket"
                                class="type-badge <?php echo $type_filter === 'ticket' ? 'active' : ''; ?>">
                                Tickets <span class="badge-count"><?php echo $document_stats['tickets']; ?></span>
                            </a>
                            <a href="?type=voucher"
                                class="type-badge <?php echo $type_filter === 'voucher' ? 'active' : ''; ?>">
                                Vouchers <span class="badge-count"><?php echo $document_stats['vouchers']; ?></span>
                            </a>
                            <a href="?type=invoice"
                                class="type-badge <?php echo $type_filter === 'invoice' ? 'active' : ''; ?>">
                                Invoices <span class="badge-count"><?php echo $document_stats['invoices']; ?></span>
                            </a>
                            <a href="?type=other"
                                class="type-badge <?php echo $type_filter === 'other' ? 'active' : ''; ?>">
                                Others <span class="badge-count"><?php echo $document_stats['others']; ?></span>
                            </a>
                        </div>
                    </div>

                    <div class="filter-group">
                        <form method="get" class="search-form">
                            <input type="hidden" name="type" value="<?php echo $type_filter; ?>">
                            <div class="search-input-group">
                                <input type="text" name="search" placeholder="Search documents..."
                                    value="<?php echo htmlspecialchars($search_query); ?>">
                                <button type="submit" class="search-btn">
                                    <i class="fas fa-search"></i>
                                </button>
                                <?php if (!empty($search_query)): ?>
                                    <a href="?type=<?php echo $type_filter; ?>" class="clear-search">
                                        <i class="fas fa-times"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>

                        <div class="sort-dropdown">
                            <select id="sortSelect" onchange="window.location.href=this.value">
                                <option
                                    value="?type=<?php echo $type_filter; ?>&sort=created_at_desc<?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>"
                                    <?php echo $sort_by === 'created_at_desc' ? 'selected' : ''; ?>>
                                    Sort by: Newest First
                                </option>
                                <option
                                    value="?type=<?php echo $type_filter; ?>&sort=created_at_asc<?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>"
                                    <?php echo $sort_by === 'created_at_asc' ? 'selected' : ''; ?>>
                                    Sort by: Oldest First
                                </option>
                                <option
                                    value="?type=<?php echo $type_filter; ?>&sort=date_desc<?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>"
                                    <?php echo $sort_by === 'date_desc' ? 'selected' : ''; ?>>
                                    Sort by: Issue Date (Newest)
                                </option>
                                <option
                                    value="?type=<?php echo $type_filter; ?>&sort=date_asc<?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>"
                                    <?php echo $sort_by === 'date_asc' ? 'selected' : ''; ?>>
                                    Sort by: Issue Date (Oldest)
                                </option>
                                <option
                                    value="?type=<?php echo $type_filter; ?>&sort=title_asc<?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>"
                                    <?php echo $sort_by === 'title_asc' ? 'selected' : ''; ?>>
                                    Sort by: Title (A-Z)
                                </option>
                                <option
                                    value="?type=<?php echo $type_filter; ?>&sort=title_desc<?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>"
                                    <?php echo $sort_by === 'title_desc' ? 'selected' : ''; ?>>
                                    Sort by: Title (Z-A)
                                </option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Documents List -->
                <div class="documents-container">
                    <?php if (empty($documents)): ?>
                        <div class="empty-state">
                            <i class="fas fa-file-alt"></i>
                            <h3>No Documents Found</h3>
                            <p>You don't have any documents matching your criteria.</p>
                            <a href="bookings.php" class="btn btn-primary">View Your Bookings</a>
                        </div>
                    <?php else: ?>
                        <div class="documents-list">
                            <?php foreach ($documents as $document): ?>
                                <div class="document-card">
                                    <div class="document-icon">
                                        <?php
                                        $icon = 'fa-file-alt'; // Default icon
                                        switch ($document['type']) {
                                            case 'itinerary':
                                                $icon = 'fa-route';
                                                break;
                                            case 'ticket':
                                                $icon = 'fa-ticket-alt';
                                                break;
                                            case 'voucher':
                                                $icon = 'fa-receipt';
                                                break;
                                            case 'invoice':
                                                $icon = 'fa-file-invoice-dollar';
                                                break;
                                        }
                                        ?>
                                        <i class="fas <?php echo $icon; ?>"></i>
                                    </div>

                                    <div class="document-content">
                                        <div class="document-header">
                                            <h3><?php echo htmlspecialchars($document['title']); ?></h3>
                                            <span class="document-type <?php echo $document['type']; ?>">
                                                <?php echo ucfirst($document['type']); ?>
                                            </span>
                                        </div>

                                        <div class="document-meta">
                                            <?php if (!empty($document['booking_number'])): ?>
                                                <div class="meta-item">
                                                    <i class="fas fa-calendar-check"></i>
                                                    Booking #<?php echo $document['booking_number']; ?>
                                                </div>
                                            <?php endif; ?>

                                            <?php if (!empty($document['tour_title'])): ?>
                                                <div class="meta-item">
                                                    <i class="fas fa-map-marked-alt"></i>
                                                    <?php echo htmlspecialchars($document['tour_title']); ?>
                                                </div>
                                            <?php endif; ?>

                                            <?php if (!empty($document['issue_date'])): ?>
                                                <div class="meta-item">
                                                    <i class="fas fa-calendar-day"></i>
                                                    Issued: <?php echo date('M j, Y', strtotime($document['issue_date'])); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <div class="document-description">
                                            <?php echo !empty($document['description']) ? nl2br(htmlspecialchars($document['description'])) : 'No description provided.'; ?>
                                        </div>
                                    </div>

                                    <div class="document-actions">
                                        <a href="../<?php echo htmlspecialchars($document['file_path']); ?>"
                                            class="btn btn-sm btn-primary" download>
                                            <i class="fas fa-download"></i> Download
                                        </a>
                                        <?php if ($document['type'] === 'invoice'): ?>
                                            <a href="pay-now.php?booking_id=<?php echo $document['booking_id']; ?>"
                                                class="btn btn-sm btn-outline">
                                                <i class="fas fa-credit-card"></i> Pay Now
                                            </a>
                                        <?php endif; ?>
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
            document.getElementById('pageTitle').textContent = 'My Documents';

            // Highlight active type filter on back/forward navigation
            const typeFilter = '<?php echo $type_filter; ?>';
            if (typeFilter !== 'all') {
                const activeFilter = document.querySelector(`.type-filters a[href*="type=${typeFilter}"]`);
                if (activeFilter) {
                    document.querySelectorAll('.type-filters a').forEach(link => {
                        link.classList.remove('active');
                    });
                    activeFilter.classList.add('active');
                }
            }
        });
    </script>
</body>

</html>