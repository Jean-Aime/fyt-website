<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config/config.php';
require_once __DIR__ . '/includes/secure_auth.php';

$page_title = 'Travel Blog & Stories';
$meta_description = 'Discover amazing travel stories, tips, and insights from Forever Young Tours. Read about destinations, cultural experiences, and travel advice.';

// Get filters
$category_filter = $_GET['category'] ?? '';
$tag_filter = $_GET['tag'] ?? '';
$search = $_GET['search'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 9;
$offset = ($page - 1) * $per_page;

// Build query conditions
$where_conditions = ['bp.status = "published"', 'bp.published_at <= NOW()'];
$params = [];

if ($category_filter) {
    $where_conditions[] = 'bc.slug = ?';
    $params[] = $category_filter;
}

if ($tag_filter) {
    $where_conditions[] = 'EXISTS (SELECT 1 FROM blog_post_tags bpt JOIN blog_tags bt ON bpt.tag_id = bt.id WHERE bpt.post_id = bp.id AND bt.slug = ?)';
    $params[] = $tag_filter;
}

if ($search) {
    $where_conditions[] = '(bp.title LIKE ? OR bp.excerpt LIKE ? OR bp.content LIKE ?)';
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$where_clause = implode(' AND ', $where_conditions);

// Get total count
$count_query = "
    SELECT COUNT(DISTINCT bp.id)
    FROM blog_posts bp
    LEFT JOIN blog_categories bc ON bp.category_id = bc.id
    WHERE $where_clause
";
$total_stmt = $db->prepare($count_query);
$total_stmt->execute($params);
$total_posts = $total_stmt->fetchColumn();

// Get blog posts
$posts_query = "
    SELECT bp.*, bc.name as category_name, bc.slug as category_slug,
           u.first_name, u.last_name,
           (SELECT COUNT(*) FROM blog_comments WHERE post_id = bp.id AND status = 'approved') as comment_count
    FROM blog_posts bp
    LEFT JOIN blog_categories bc ON bp.category_id = bc.id
    LEFT JOIN users u ON bp.author_id = u.id
    WHERE $where_clause
    ORDER BY bp.published_at DESC
    LIMIT $per_page OFFSET $offset
";
$posts_stmt = $db->prepare($posts_query);
$posts_stmt->execute($params);
$posts = $posts_stmt->fetchAll();

$total_pages = ceil($total_posts / $per_page);

// Get categories
$categories = $db->query("
    SELECT bc.*, COUNT(bp.id) as post_count
    FROM blog_categories bc
    LEFT JOIN blog_posts bp ON bc.id = bp.category_id AND bp.status = 'published'
    WHERE bc.status = 'active'
    GROUP BY bc.id
    ORDER BY bc.name
")->fetchAll();

// Get popular tags
$popular_tags = $db->query("
    SELECT bt.*, COUNT(bpt.post_id) as post_count
    FROM blog_tags bt
    JOIN blog_post_tags bpt ON bt.id = bpt.tag_id
    JOIN blog_posts bp ON bpt.post_id = bp.id
    WHERE bp.status = 'published'
    GROUP BY bt.id
    ORDER BY post_count DESC
    LIMIT 20
")->fetchAll();

// Get featured posts
$featured_posts = $db->query("
    SELECT bp.*, bc.name as category_name, bc.slug as category_slug
    FROM blog_posts bp
    LEFT JOIN blog_categories bc ON bp.category_id = bc.id
    WHERE bp.status = 'published' AND bp.is_featured = 1
    ORDER BY bp.published_at DESC
    LIMIT 3
")->fetchAll();

// Get recent posts for sidebar
$recent_posts = $db->query("
    SELECT bp.id, bp.title, bp.slug, bp.featured_image, bp.published_at, bc.name as category_name
    FROM blog_posts bp
    LEFT JOIN blog_categories bc ON bp.category_id = bc.id
    WHERE bp.status = 'published'
    ORDER BY bp.published_at DESC
    LIMIT 5
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Forever Young Tours</title>
    <meta name="description" content="<?php echo $meta_description; ?>">
    
    <!-- Open Graph -->
    <meta property="og:title" content="<?php echo $page_title; ?>">
    <meta property="og:description" content="<?php echo $meta_description; ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo getCurrentUrl(); ?>">
    
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .blog-hero {
            background: linear-gradient(135deg, rgba(212, 165, 116, 0.9), rgba(102, 234, 102, 0.9)),
                         url('https://images.unsplash.com/photo-1464037866556-6812c9d1c72e?ixlib=rb-1.2.1&auto=format&fit=crop&w=1200&q=80') center/cover;
            color: white;
            padding: 100px 0;
            text-align: center;
        }
        
        .blog-hero h1 {
            font-size: 3.5em;
            margin-bottom: 20px;
            font-weight: 700;
        }
        
        .blog-hero p {
            font-size: 1.3em;
            max-width: 600px;
            margin: 0 auto 40px;
            opacity: 0.9;
        }
        
        .hero-search {
            max-width: 500px;
            margin: 0 auto;
            position: relative;
        }
        
        .hero-search input {
            width: 100%;
            padding: 15px 60px 15px 20px;
            border: none;
            border-radius: 50px;
            font-size: 1.1em;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .hero-search button {
            position: absolute;
            right: 5px;
            top: 5px;
            bottom: 5px;
            background: var(--primary-color);
            border: none;
            border-radius: 50px;
            padding: 0 20px;
            color: white;
            cursor: pointer;
        }
        
        .featured-section {
            padding: 80px 0;
            background: #f8f9fa;
        }
        
        .featured-posts {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 30px;
            margin-top: 50px;
        }
        
        .featured-post {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        
        .featured-post:hover {
            transform: translateY(-10px);
        }
        
        .featured-image {
            height: 250px;
            background-size: cover;
            background-position: center;
            position: relative;
        }
        
        .featured-badge {
            position: absolute;
            top: 20px;
            left: 20px;
            background: var(--primary-color);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: 600;
        }
        
        .featured-content {
            padding: 30px;
        }
        
        .post-category {
            color: var(--secondary-color);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.9em;
            letter-spacing: 1px;
            margin-bottom: 10px;
        }
        
        .post-title {
            font-size: 1.4em;
            font-weight: 700;
            color: #333;
            margin-bottom: 15px;
            line-height: 1.3;
        }
        
        .post-excerpt {
            color: #666;
            line-height: 1.6;
            margin-bottom: 20px;
        }
        
        .post-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.9em;
            color: #999;
        }
        
        .blog-content {
            padding: 80px 0;
        }
        
        .blog-layout {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 50px;
        }
        
        .blog-filters {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 40px;
        }
        
        .filter-row {
            display: flex;
            gap: 20px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .filter-group label {
            font-weight: 600;
            color: #333;
            font-size: 0.9em;
        }
        
        .filter-group select,
        .filter-group input {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 0.9em;
        }
        
        .posts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-bottom: 50px;
        }
        
        .post-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
        }
        
        .post-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.15);
        }
        
        .post-image {
            height: 200px;
            background-size: cover;
            background-position: center;
            position: relative;
        }
        
        .post-date {
            position: absolute;
            top: 15px;
            right: 15px;
            background: rgba(0,0,0,0.7);
            color: white;
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 0.8em;
            font-weight: 600;
        }
        
        .post-content {
            padding: 25px;
        }
        
        .read-more {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: gap 0.3s ease;
        }
        
        .read-more:hover {
            gap: 10px;
        }
        
        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 30px;
        }
        
        .sidebar-widget {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .widget-title {
            font-size: 1.3em;
            font-weight: 700;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--primary-color);
        }
        
        .category-list {
            list-style: none;
            padding: 0;
        }
        
        .category-list li {
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .category-list li:last-child {
            border-bottom: none;
        }
        
        .category-list a {
            color: #333;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }
        
        .category-list a:hover {
            color: var(--primary-color);
        }
        
        .post-count {
            background: #f8f9fa;
            color: #666;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8em;
        }
        
        .tag-cloud {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .tag {
            background: #f8f9fa;
            color: #666;
            padding: 8px 15px;
            border-radius: 20px;
            text-decoration: none;
            font-size: 0.9em;
            transition: all 0.3s ease;
        }
        
        .tag:hover {
            background: var(--primary-color);
            color: white;
        }
        
        .recent-post {
            display: flex;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .recent-post:last-child {
            border-bottom: none;
        }
        
        .recent-post-image {
            width: 80px;
            height: 60px;
            background-size: cover;
            background-position: center;
            border-radius: 8px;
            flex-shrink: 0;
        }
        
        .recent-post-content {
            flex: 1;
        }
        
        .recent-post-title {
            font-weight: 600;
            color: #333;
            text-decoration: none;
            font-size: 0.9em;
            line-height: 1.3;
            margin-bottom: 5px;
            display: block;
        }
        
        .recent-post-title:hover {
            color: var(--primary-color);
        }
        
        .recent-post-date {
            font-size: 0.8em;
            color: #999;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 50px;
        }
        
        .page-link {
            padding: 12px 18px;
            background: white;
            color: #333;
            text-decoration: none;
            border-radius: 8px;
            border: 1px solid #ddd;
            transition: all 0.3s ease;
        }
        
        .page-link:hover,
        .page-link.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        .newsletter-signup {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            text-align: center;
        }
        
        .newsletter-form {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .newsletter-form input {
            flex: 1;
            padding: 12px 15px;
            border: none;
            border-radius: 8px;
            font-size: 0.9em;
        }
        
        .newsletter-form button {
            background: rgba(255,255,255,0.2);
            color: white;
            border: 1px solid rgba(255,255,255,0.3);
            padding: 12px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .newsletter-form button:hover {
            background: rgba(255,255,255,0.3);
        }
        
        @media (max-width: 768px) {
            .blog-hero h1 {
                font-size: 2.5em;
            }
            
            .blog-layout {
                grid-template-columns: 1fr;
                gap: 30px;
            }
            
            .posts-grid {
                grid-template-columns: 1fr;
            }
            
            .filter-row {
                flex-direction: column;
                align-items: stretch;
            }
            
            .newsletter-form {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <!-- Hero Section -->
    <section class="blog-hero">
        <div class="container">
            <h1>Travel Stories & Insights</h1>
            <p style="color: white;">Discover amazing destinations, cultural experiences, and travel tips from our adventures around the world</p>
            
            <form class="hero-search" method="GET">
                <input type="text" name="search" placeholder="Search for travel stories, destinations, tips..." 
                       value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit">
                    <i class="fas fa-search"></i>
                </button>
            </form>
        </div>
    </section>
    
    <!-- Featured Posts -->
    <?php if (!empty($featured_posts) && empty($search) && empty($category_filter) && empty($tag_filter)): ?>
    <section class="featured-section">
        <div class="container">
            <div class="section-header text-center">
                <h2>Featured Stories</h2>
                <p>Our most popular and inspiring travel stories</p>
            </div>
            
            <div class="featured-posts">
                <?php foreach ($featured_posts as $post): ?>
                <article class="featured-post">
                    <div class="featured-image" style="background-image: url('<?php echo $post['featured_image'] ?: '/placeholder.svg?height=250&width=400'; ?>')">
                        <span class="featured-badge">Featured</span>
                    </div>
                    <div class="featured-content">
                        <div class="post-category"><?php echo htmlspecialchars($post['category_name']); ?></div>
                        <h3 class="post-title">
                            <a href="blog-post.php?slug=<?php echo urlencode($post['slug']); ?>">
                                <?php echo htmlspecialchars($post['title']); ?>
                            </a>
                        </h3>
                        <p class="post-excerpt"><?php echo htmlspecialchars($post['excerpt']); ?></p>
                        <div class="post-meta">
                            <span><?php echo date('M j, Y', strtotime($post['published_at'])); ?></span>
                            <a href="blog-post.php?slug=<?php echo urlencode($post['slug']); ?>" class="read-more">
                                Read More <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>
    
    <!-- Blog Content -->
    <section class="blog-content">
        <div class="container">
            <!-- Filters -->
            <div class="blog-filters">
                <form method="GET" class="filter-form">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label>Category</label>
                            <select name="category" onchange="this.form.submit()">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo htmlspecialchars($category['slug']); ?>" 
                                            <?php echo $category_filter === $category['slug'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['name']); ?> (<?php echo $category['post_count']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label>Search</label>
                            <input type="text" name="search" placeholder="Search posts..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        
                        <div class="filter-group">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-primary">Filter</button>
                        </div>
                        
                        <?php if ($category_filter || $tag_filter || $search): ?>
                        <div class="filter-group">
                            <label>&nbsp;</label>
                            <a href="blog.php" class="btn btn-outline">Clear Filters</a>
                        </div>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            
            <div class="blog-layout">
                <!-- Main Content -->
                <div class="main-content">
                    <?php if (!empty($posts)): ?>
                        <div class="posts-grid">
                            <?php foreach ($posts as $post): ?>
                            <article class="post-card">
                                <div class="post-image" style="background-image: url('<?php echo $post['featured_image'] ?: '/placeholder.svg?height=200&width=300'; ?>')">
                                    <div class="post-date">
                                        <?php echo date('M j', strtotime($post['published_at'])); ?>
                                    </div>
                                </div>
                                <div class="post-content">
                                    <div class="post-category"><?php echo htmlspecialchars($post['category_name']); ?></div>
                                    <h3 class="post-title">
                                        <a href="blog-post.php?slug=<?php echo urlencode($post['slug']); ?>">
                                            <?php echo htmlspecialchars($post['title']); ?>
                                        </a>
                                    </h3>
                                    <p class="post-excerpt"><?php echo htmlspecialchars($post['excerpt']); ?></p>
                                    <div class="post-meta">
                                        <span>By <?php echo htmlspecialchars($post['first_name'] . ' ' . $post['last_name']); ?></span>
                                        <a href="blog-post.php?slug=<?php echo urlencode($post['slug']); ?>" class="read-more">
                                            Read More <i class="fas fa-arrow-right"></i>
                                        </a>
                                    </div>
                                </div>
                            </article>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="page-link">
                                    <i class="fas fa-chevron-left"></i> Previous
                                </a>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                                   class="page-link <?php echo $i === $page ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="page-link">
                                    Next <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="empty-state text-center">
                            <i class="fas fa-search" style="font-size: 4em; color: #ddd; margin-bottom: 20px;"></i>
                            <h3>No posts found</h3>
                            <p>Try adjusting your search criteria or browse our categories</p>
                            <a href="blog.php" class="btn btn-primary">View All Posts</a>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Sidebar -->
                <aside class="sidebar">
                    <!-- Categories -->
                    <div class="sidebar-widget">
                        <h3 class="widget-title">Categories</h3>
                        <ul class="category-list">
                            <?php foreach ($categories as $category): ?>
                            <li>
                                <a href="?category=<?php echo urlencode($category['slug']); ?>">
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </a>
                                <span class="post-count"><?php echo $category['post_count']; ?></span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    
                    <!-- Recent Posts -->
                    <div class="sidebar-widget">
                        <h3 class="widget-title">Recent Posts</h3>
                        <div class="recent-posts">
                            <?php foreach ($recent_posts as $recent): ?>
                            <div class="recent-post">
                                <div class="recent-post-image" style="background-image: url('<?php echo $recent['featured_image'] ?: '/placeholder.svg?height=60&width=80'; ?>')"></div>
                                <div class="recent-post-content">
                                    <a href="blog-post.php?slug=<?php echo urlencode($recent['slug']); ?>" class="recent-post-title">
                                        <?php echo htmlspecialchars($recent['title']); ?>
                                    </a>
                                    <div class="recent-post-date">
                                        <?php echo date('M j, Y', strtotime($recent['published_at'])); ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Tags -->
                    <?php if (!empty($popular_tags)): ?>
                    <div class="sidebar-widget">
                        <h3 class="widget-title">Popular Tags</h3>
                        <div class="tag-cloud">
                            <?php foreach ($popular_tags as $tag): ?>
                                <a href="?tag=<?php echo urlencode($tag['slug']); ?>" class="tag">
                                    <?php echo htmlspecialchars($tag['name']); ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Newsletter Signup -->
                    <div class="sidebar-widget newsletter-signup">
                        <h3 class="widget-title">Stay Updated</h3>
                        <p>Get the latest travel stories and tips delivered to your inbox</p>
                        <form class="newsletter-form" action="api/newsletter.php" method="POST">
                            <input type="email" name="email" placeholder="Your email address" required>
                            <button type="submit">Subscribe</button>
                        </form>
                    </div>
                </aside>
            </div>
        </div>
    </section>
     <?php include 'includes/footer.php'; ?>
    
    <script src="assets/js/main.js"></script>
</body>
</html>
