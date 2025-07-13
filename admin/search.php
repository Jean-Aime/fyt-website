<?php
require_once '../config/config.php';
require_once 'includes/secure_auth.php';

$auth = new SecureAuth($db);
requireLogin();

$page_title = 'Search Results';
$query = trim($_GET['q'] ?? '');
$type = $_GET['type'] ?? 'all';
$results = [];
$total_results = 0;

if ($query) {
    $search_term = "%$query%";
    
    // Search tours
    if ($type === 'all' || $type === 'tours') {
        if (hasPermission('tours.view')) {
            $stmt = $db->prepare("
                SELECT 'tour' as type, id, title as name, short_description as description, 
                       featured_image as image, status, created_at
                FROM tours 
                WHERE title LIKE ? OR short_description LIKE ? OR full_description LIKE ?
                ORDER BY title
                LIMIT 20
            ");
            $stmt->execute([$search_term, $search_term, $search_term]);
            $tour_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $results = array_merge($results, $tour_results);
        }
    }
    
    // Search bookings
    if ($type === 'all' || $type === 'bookings') {
        if (hasPermission('bookings.view')) {
            $stmt = $db->prepare("
                SELECT 'booking' as type, b.id, 
                       CONCAT('Booking #', b.booking_reference) as name,
                       CONCAT(u.first_name, ' ', u.last_name, ' - ', t.title) as description,
                       t.featured_image as image, b.status, b.created_at
                FROM bookings b
                JOIN users u ON b.user_id = u.id
                JOIN tours t ON b.tour_id = t.id
                WHERE b.booking_reference LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? 
                   OR u.email LIKE ? OR t.title LIKE ?
                ORDER BY b.created_at DESC
                LIMIT 20
            ");
            $stmt->execute([$search_term, $search_term, $search_term, $search_term, $search_term]);
            $booking_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $results = array_merge($results, $booking_results);
        }
    }
    
    // Search users
    if ($type === 'all' || $type === 'users') {
        if (hasPermission('users.view')) {
            $stmt = $db->prepare("
                SELECT 'user' as type, u.id, 
                       CONCAT(u.first_name, ' ', u.last_name) as name,
                       CONCAT(u.email, ' - ', r.display_name) as description,
                       NULL as image, u.status, u.created_at
                FROM users u
                JOIN roles r ON u.role_id = r.id
                WHERE u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.username LIKE ?
                ORDER BY u.first_name, u.last_name
                LIMIT 20
            ");
            $stmt->execute([$search_term, $search_term, $search_term, $search_term]);
            $user_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $results = array_merge($results, $user_results);
        }
    }
    
    // Search countries
    if ($type === 'all' || $type === 'destinations') {
        if (hasPermission('destinations.view')) {
            $stmt = $db->prepare("
                SELECT 'country' as type, id, name, 
                       CONCAT(continent, ' - ', currency) as description,
                       flag_image as image, status, created_at
                FROM countries 
                WHERE name LIKE ? OR code LIKE ? OR continent LIKE ?
                ORDER BY name
                LIMIT 20
            ");
            $stmt->execute([$search_term, $search_term, $search_term]);
            $country_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $results = array_merge($results, $country_results);
        }
    }
    
    $total_results = count($results);
    
    // Log search activity
    $auth->logActivity($_SESSION['user_id'], 'search', "Searched for: $query", [
        'query' => $query,
        'type' => $type,
        'results_count' => $total_results
    ]);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Forever Young Tours Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .search-header {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }
        
        .search-form {
            display: flex;
            gap: 15px;
            align-items: end;
            margin-bottom: 20px;
        }
        
        .search-input-group {
            flex: 1;
            max-width: 500px;
        }
        
        .search-input {
            width: 100%;
            padding: 15px 20px;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            font-size: 1.1em;
            transition: all 0.3s ease;
        }
        
        .search-input:focus {
            outline: none;
            border-color: var(--admin-primary);
            box-shadow: 0 0 0 3px rgba(212, 165, 116, 0.1);
        }
        
        .search-filters {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .filter-btn {
            padding: 8px 15px;
            border: 1px solid #ddd;
            background: white;
            color: #666;
            border-radius: 20px;
            text-decoration: none;
            font-size: 0.9em;
            transition: all 0.3s ease;
        }
        
        .filter-btn.active,
        .filter-btn:hover {
            background: var(--admin-primary);
            color: white;
            border-color: var(--admin-primary);
        }
        
        .search-stats {
            color: #666;
            font-size: 0.9em;
        }
        
        .results-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        
        .result-item {
            display: flex;
            gap: 20px;
            padding: 25px;
            border-bottom: 1px solid #f0f0f0;
            transition: all 0.3s ease;
        }
        
        .result-item:hover {
            background: #f8f9fa;
        }
        
        .result-item:last-child {
            border-bottom: none;
        }
        
        .result-image {
            width: 80px;
            height: 60px;
            border-radius: 8px;
            object-fit: cover;
            flex-shrink: 0;
        }
        
        .result-placeholder {
            width: 80px;
            height: 60px;
            border-radius: 8px;
            background: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
            flex-shrink: 0;
        }
        
        .result-content {
            flex: 1;
        }
        
        .result-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }
        
        .result-title {
            font-size: 1.2em;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        
        .result-title a {
            color: #333;
            text-decoration: none;
        }
        
        .result-title a:hover {
            color: var(--admin-primary);
        }
        
        .result-type {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.7em;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .result-type.tour { background: #e3f2fd; color: #1976d2; }
        .result-type.booking { background: #f3e5f5; color: #7b1fa2; }
        .result-type.user { background: #e8f5e8; color: #388e3c; }
        .result-type.country { background: #fff3e0; color: #f57c00; }
        
        .result-description {
            color: #666;
            font-size: 0.9em;
            line-height: 1.4;
            margin-bottom: 10px;
        }
        
        .result-meta {
            display: flex;
            gap: 20px;
            font-size: 0.8em;
            color: #999;
        }
        
        .result-meta-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .result-status {
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 0.7em;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .result-status.active { background: #d4edda; color: #155724; }
        .result-status.inactive { background: #f8d7da; color: #721c24; }
        .result-status.pending { background: #fff3cd; color: #856404; }
        .result-status.confirmed { background: #d1ecf1; color: #0c5460; }
        
        .no-results {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        
        .no-results i {
            font-size: 4em;
            margin-bottom: 20px;
            color: #ddd;
        }
        
        .no-results h3 {
            font-size: 1.5em;
            margin-bottom: 10px;
            color: #333;
        }
        
        .search-suggestions {
            margin-top: 20px;
        }
        
        .search-suggestions h4 {
            margin-bottom: 10px;
            color: #333;
        }
        
        .suggestion-list {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .suggestion-item {
            padding: 5px 10px;
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 15px;
            color: #666;
            text-decoration: none;
            font-size: 0.8em;
            transition: all 0.3s ease;
        }
        
        .suggestion-item:hover {
            background: var(--admin-primary);
            color: white;
            border-color: var(--admin-primary);
        }
        
        @media (max-width: 768px) {
            .search-form {
                flex-direction: column;
                align-items: stretch;
            }
            
            .result-item {
                flex-direction: column;
                gap: 15px;
            }
            
            .result-header {
                flex-direction: column;
                gap: 10px;
            }
            
            .result-meta {
                flex-direction: column;
                gap: 5px;
            }
        }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include 'includes/header.php'; ?>
            
            <div class="content">
                <!-- Search Header -->
                <div class="search-header">
                    <form method="GET" class="search-form">
                        <div class="search-input-group">
                            <label for="search-input">Search</label>
                            <input type="text" id="search-input" name="q" class="search-input" 
                                   value="<?php echo htmlspecialchars($query); ?>" 
                                   placeholder="Search tours, bookings, customers, destinations..."
                                   autofocus>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Search
                        </button>
                    </form>
                    
                    <div class="search-filters">
                        <a href="?q=<?php echo urlencode($query); ?>&type=all" 
                           class="filter-btn <?php echo $type === 'all' ? 'active' : ''; ?>">
                            All Results
                        </a>
                        <a href="?q=<?php echo urlencode($query); ?>&type=tours" 
                           class="filter-btn <?php echo $type === 'tours' ? 'active' : ''; ?>">
                            Tours
                        </a>
                        <a href="?q=<?php echo urlencode($query); ?>&type=bookings" 
                           class="filter-btn <?php echo $type === 'bookings' ? 'active' : ''; ?>">
                            Bookings
                        </a>
                        <a href="?q=<?php echo urlencode($query); ?>&type=users" 
                           class="filter-btn <?php echo $type === 'users' ? 'active' : ''; ?>">
                            Users
                        </a>
                        <a href="?q=<?php echo urlencode($query); ?>&type=destinations" 
                           class="filter-btn <?php echo $type === 'destinations' ? 'active' : ''; ?>">
                            Destinations
                        </a>
                    </div>
                    
                    <?php if ($query): ?>
                        <div class="search-stats">
                            Found <?php echo number_format($total_results); ?> result(s) for "<?php echo htmlspecialchars($query); ?>"
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Search Results -->
                <?php if (!$query): ?>
                    <div class="no-results">
                        <i class="fas fa-search"></i>
                        <h3>Start Your Search</h3>
                        <p>Enter a search term to find tours, bookings, customers, and destinations.</p>
                        
                        <div class="search-suggestions">
                            <h4>Popular Searches:</h4>
                            <div class="suggestion-list">
                                <a href="?q=rwanda" class="suggestion-item">Rwanda</a>
                                <a href="?q=gorilla" class="suggestion-item">Gorilla Tours</a>
                                <a href="?q=safari" class="suggestion-item">Safari</a>
                                <a href="?q=pending" class="suggestion-item">Pending Bookings</a>
                                <a href="?q=active" class="suggestion-item">Active Tours</a>
                            </div>
                        </div>
                    </div>
                <?php elseif (empty($results)): ?>
                    <div class="no-results">
                        <i class="fas fa-search-minus"></i>
                        <h3>No Results Found</h3>
                        <p>We couldn't find anything matching "<?php echo htmlspecialchars($query); ?>"</p>
                        
                        <div class="search-suggestions">
                            <h4>Try searching for:</h4>
                            <div class="suggestion-list">
                                <a href="?q=tour" class="suggestion-item">Tours</a>
                                <a href="?q=booking" class="suggestion-item">Bookings</a>
                                <a href="?q=customer" class="suggestion-item">Customers</a>
                                <a href="?q=rwanda" class="suggestion-item">Rwanda</a>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="results-container">
                        <?php foreach ($results as $result): ?>
                            <div class="result-item">
                                <div>
                                    <?php if ($result['image']): ?>
                                        <img src="../<?php echo htmlspecialchars($result['image']); ?>" 
                                             alt="<?php echo htmlspecialchars($result['name']); ?>" class="result-image">
                                    <?php else: ?>
                                        <div class="result-placeholder">
                                            <i class="fas fa-<?php 
                                                echo $result['type'] === 'tour' ? 'map-marked-alt' : 
                                                    ($result['type'] === 'booking' ? 'calendar-check' : 
                                                    ($result['type'] === 'user' ? 'user' : 'globe')); 
                                            ?>"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="result-content">
                                    <div class="result-header">
                                        <div>
                                            <div class="result-title">
                                                <a href="<?php 
                                                    echo $result['type'] === 'tour' ? 'tours/view.php?id=' . $result['id'] : 
                                                        ($result['type'] === 'booking' ? 'bookings/view.php?id=' . $result['id'] : 
                                                        ($result['type'] === 'user' ? 'users/view.php?id=' . $result['id'] : 
                                                        'destinations/countries.php?id=' . $result['id'])); 
                                                ?>">
                                                    <?php echo htmlspecialchars($result['name']); ?>
                                                </a>
                                            </div>
                                        </div>
                                        <span class="result-type <?php echo $result['type']; ?>">
                                            <?php echo ucfirst($result['type']); ?>
                                        </span>
                                    </div>
                                    
                                    <?php if ($result['description']): ?>
                                        <div class="result-description">
                                            <?php echo htmlspecialchars($result['description']); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="result-meta">
                                        <div class="result-meta-item">
                                            <i class="fas fa-calendar"></i>
                                            <?php echo date('M j, Y', strtotime($result['created_at'])); ?>
                                        </div>
                                        <div class="result-meta-item">
                                            <i class="fas fa-info-circle"></i>
                                            <span class="result-status <?php echo $result['status']; ?>">
                                                <?php echo ucfirst($result['status']); ?>
                                            </span>
                                        </div>
                                        <div class="result-meta-item">
                                            <i class="fas fa-external-link-alt"></i>
                                            <a href="<?php 
                                                echo $result['type'] === 'tour' ? 'tours/view.php?id=' . $result['id'] : 
                                                    ($result['type'] === 'booking' ? 'bookings/view.php?id=' . $result['id'] : 
                                                    ($result['type'] === 'user' ? 'users/view.php?id=' . $result['id'] : 
                                                    'destinations/countries.php?id=' . $result['id'])); 
                                            ?>">View Details</a>
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
    
    <script>
        // Highlight search terms in results
        function highlightSearchTerms() {
            const query = '<?php echo addslashes($query); ?>';
            if (!query) return;
            
            const terms = query.split(' ').filter(term => term.length > 2);
            const resultItems = document.querySelectorAll('.result-title, .result-description');
            
            resultItems.forEach(item => {
                let html = item.innerHTML;
                terms.forEach(term => {
                    const regex = new RegExp(`(${term})`, 'gi');
                    html = html.replace(regex, '<mark>$1</mark>');
                });
                item.innerHTML = html;
            });
        }
        
        // Auto-focus search input
        document.getElementById('search-input').focus();
        
        // Highlight search terms when page loads
        document.addEventListener('DOMContentLoaded', highlightSearchTerms);
        
        // Add search suggestions functionality
        document.getElementById('search-input').addEventListener('input', function() {
            // This could be enhanced with AJAX suggestions
            // For now, we'll just show basic functionality
        });
    </script>
    
    <style>
        mark {
            background: #fff3cd;
            color: #856404;
            padding: 1px 2px;
            border-radius: 2px;
        }
    </style>
    
    <script src="../assets/js/admin.js"></script>
</body>
</html>
