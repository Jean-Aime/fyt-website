<?php
require_once 'config/config.php';
require_once 'includes/functions.php';

$page_title = 'Travel Resources & Guides';
$meta_description = 'Essential travel resources, guides, and information for your journey. Find visa requirements, travel tips, safety guides, and downloadable resources.';

// Get resource categories
$categories = $db->query("
    SELECT rc.*, COUNT(r.id) as resource_count
    FROM resource_categories rc
    LEFT JOIN resources r ON rc.id = r.category_id AND r.status = 'active'
    WHERE rc.status = 'active'
    GROUP BY rc.id
    ORDER BY rc.sort_order, rc.name
")->fetchAll();

// Get featured resources
$featured_resources = $db->query("
    SELECT r.*, rc.name as category_name, rc.icon as category_icon
    FROM resources r
    LEFT JOIN resource_categories rc ON r.category_id = rc.id
    WHERE r.status = 'active' AND r.is_featured = 1
    ORDER BY r.created_at DESC
    LIMIT 6
")->fetchAll();

// Get popular downloads
$popular_downloads = $db->query("
    SELECT r.*, rc.name as category_name
    FROM resources r
    LEFT JOIN resource_categories rc ON r.category_id = rc.id
    WHERE r.status = 'active' AND r.resource_type = 'download'
    ORDER BY r.download_count DESC
    LIMIT 8
")->fetchAll();

// Get travel tips
$travel_tips = $db->query("
    SELECT * FROM travel_tips 
    WHERE status = 'active' 
    ORDER BY is_featured DESC, created_at DESC 
    LIMIT 6
")->fetchAll();

// Get country information for visa guide
$countries_with_visa_info = $db->query("
    SELECT c.*, vi.visa_required, vi.visa_on_arrival, vi.visa_free_days
    FROM countries c
    LEFT JOIN visa_information vi ON c.id = vi.country_id
    WHERE c.status = 'active' AND vi.id IS NOT NULL
    ORDER BY c.name
    LIMIT 12
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
        .resources-hero {
            background: linear-gradient(135deg, rgba(212, 165, 116, 0.9), rgba(102, 126, 234, 0.9)),
                url('/placeholder.svg?height=400&width=1200') center/cover;
            color: white;
            padding: 100px 0;
            text-align: center;
        }

        .resources-hero h1 {
            font-size: 3.5em;
            margin-bottom: 20px;
            font-weight: 700;
        }

        .resources-hero p {
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
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
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

        .categories-section {
            padding: 80px 0;
            background: #f8f9fa;
        }

        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
            margin-top: 50px;
        }

        .category-card {
            background: white;
            padding: 40px 30px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .category-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }

        .category-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 2em;
            color: white;
        }

        .category-title {
            font-size: 1.3em;
            font-weight: 700;
            color: #333;
            margin-bottom: 10px;
        }

        .category-description {
            color: #666;
            line-height: 1.6;
            margin-bottom: 15px;
        }

        .category-count {
            color: var(--primary-color);
            font-weight: 600;
            font-size: 0.9em;
        }

        .featured-section {
            padding: 80px 0;
        }

        .featured-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 30px;
            margin-top: 50px;
        }

        .resource-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .resource-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
        }

        .resource-image {
            height: 200px;
            background-size: cover;
            background-position: center;
            position: relative;
        }

        .resource-type {
            position: absolute;
            top: 15px;
            left: 15px;
            background: var(--primary-color);
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: 600;
            text-transform: uppercase;
        }

        .resource-content {
            padding: 25px;
        }

        .resource-category {
            color: var(--secondary-color);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.8em;
            letter-spacing: 1px;
            margin-bottom: 10px;
        }

        .resource-title {
            font-size: 1.3em;
            font-weight: 700;
            color: #333;
            margin-bottom: 10px;
            line-height: 1.3;
        }

        .resource-description {
            color: #666;
            line-height: 1.6;
            margin-bottom: 20px;
        }

        .resource-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.9em;
            color: #999;
            margin-bottom: 20px;
        }

        .resource-actions {
            display: flex;
            gap: 10px;
        }

        .downloads-section {
            padding: 80px 0;
            background: #f8f9fa;
        }

        .downloads-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 50px;
        }

        .download-item {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            display: flex;
            align-items: center;
            gap: 20px;
            transition: all 0.3s ease;
        }

        .download-item:hover {
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }

        .download-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5em;
            flex-shrink: 0;
        }

        .download-info {
            flex: 1;
        }

        .download-title {
            font-weight: 700;
            color: #333;
            margin-bottom: 5px;
        }

        .download-meta {
            font-size: 0.9em;
            color: #666;
            margin-bottom: 10px;
        }

        .download-stats {
            font-size: 0.8em;
            color: #999;
        }

        .tips-section {
            padding: 80px 0;
        }

        .tips-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-top: 50px;
        }

        .tip-card {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            position: relative;
            transition: all 0.3s ease;
        }

        .tip-card:hover {
            transform: translateY(-5px);
        }

        .tip-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: 15px 15px 0 0;
        }

        .tip-icon {
            width: 50px;
            height: 50px;
            background: rgba(212, 165, 116, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-color);
            font-size: 1.2em;
            margin-bottom: 20px;
        }

        .tip-title {
            font-size: 1.2em;
            font-weight: 700;
            color: #333;
            margin-bottom: 15px;
        }

        .tip-content {
            color: #666;
            line-height: 1.6;
        }

        .visa-section {
            padding: 80px 0;
            background: #f8f9fa;
        }

        .visa-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 50px;
        }

        .visa-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
        }

        .visa-card:hover {
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }

        .visa-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }

        .country-flag {
            width: 40px;
            height: 30px;
            background: #f0f0f0;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2em;
        }

        .country-name {
            font-weight: 700;
            color: #333;
        }

        .visa-status {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: 600;
            text-transform: uppercase;
        }

        .visa-required {
            background: #f8d7da;
            color: #721c24;
        }

        .visa-on-arrival {
            background: #fff3cd;
            color: #856404;
        }

        .visa-free {
            background: #d4edda;
            color: #155724;
        }

        .visa-details {
            font-size: 0.9em;
            color: #666;
            margin-top: 10px;
        }

        .cta-section {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 80px 0;
            text-align: center;
        }

        .cta-section h2 {
            font-size: 2.5em;
            margin-bottom: 20px;
        }

        .cta-section p {
            font-size: 1.2em;
            margin-bottom: 40px;
            opacity: 0.9;
        }

        @media (max-width: 768px) {
            .resources-hero h1 {
                font-size: 2.5em;
            }

            .categories-grid,
            .featured-grid,
            .downloads-grid,
            .tips-grid,
            .visa-grid {
                grid-template-columns: 1fr;
            }

            .download-item {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>
</head>

<body>
    <?php include 'includes/header.php'; ?>

    <!-- Hero Section -->
    <section class="resources-hero">
        <div class="container">
            <h1>Travel Resources & Guides</h1>
            <p>Everything you need to plan your perfect journey - from visa information to travel tips and downloadable
                guides</p>

            <form class="hero-search" action="search-resources.php" method="GET">
                <input type="text" name="q" placeholder="Search for travel guides, visa info, tips...">
                <button type="submit">
                    <i class="fas fa-search"></i>
                </button>
            </form>
        </div>
    </section>

    <!-- Resource Categories -->
    <section class="categories-section">
        <div class="container">
            <div class="section-header text-center">
                <h2>Resource Categories</h2>
                <p>Find the information you need organized by category</p>
            </div>

            <div class="categories-grid">
                <?php foreach ($categories as $category): ?>
                    <div class="category-card"
                        onclick="location.href='resources-category.php?slug=<?php echo urlencode($category['slug']); ?>'">
                        <div class="category-icon">
                            <i class="<?php echo $category['icon'] ?: 'fas fa-folder'; ?>"></i>
                        </div>
                        <h3 class="category-title"><?php echo htmlspecialchars($category['name']); ?></h3>
                        <p class="category-description"><?php echo htmlspecialchars($category['description']); ?></p>
                        <div class="category-count"><?php echo $category['resource_count']; ?> resources</div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Featured Resources -->
    <section class="featured-section">
        <div class="container">
            <div class="section-header text-center">
                <h2>Featured Resources</h2>
                <p>Our most popular and essential travel resources</p>
            </div>

            <div class="featured-grid">
                <?php foreach ($featured_resources as $resource): ?>
                    <div class="resource-card">
                        <div class="resource-image"
                            style="background-image: url('<?php echo $resource['featured_image'] ?: '/placeholder.svg?height=200&width=350'; ?>')">
                            <span class="resource-type"><?php echo ucfirst($resource['resource_type']); ?></span>
                        </div>
                        <div class="resource-content">
                            <div class="resource-category"><?php echo htmlspecialchars($resource['category_name']); ?></div>
                            <h3 class="resource-title"><?php echo htmlspecialchars($resource['title']); ?></h3>
                            <p class="resource-description"><?php echo htmlspecialchars($resource['description']); ?></p>
                            <div class="resource-meta">
                                <span><?php echo date('M j, Y', strtotime($resource['created_at'])); ?></span>
                                <?php if ($resource['resource_type'] === 'download'): ?>
                                    <span><i class="fas fa-download"></i>
                                        <?php echo number_format($resource['download_count']); ?> downloads</span>
                                <?php endif; ?>
                            </div>
                            <div class="resource-actions">
                                <?php if ($resource['resource_type'] === 'download'): ?>
                                    <a href="download-resource.php?id=<?php echo $resource['id']; ?>" class="btn btn-primary">
                                        <i class="fas fa-download"></i> Download
                                    </a>
                                <?php else: ?>
                                    <a href="resource-detail.php?slug=<?php echo urlencode($resource['slug']); ?>"
                                        class="btn btn-primary">
                                        Read More
                                    </a>
                                <?php endif; ?>
                                <a href="resource-detail.php?slug=<?php echo urlencode($resource['slug']); ?>"
                                    class="btn btn-outline">
                                    View Details
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Popular Downloads -->
    <section class="downloads-section">
        <div class="container">
            <div class="section-header text-center">
                <h2>Popular Downloads</h2>
                <p>Essential guides and resources you can download for offline use</p>
            </div>

            <div class="downloads-grid">
                <?php foreach ($popular_downloads as $download): ?>
                    <div class="download-item">
                        <div class="download-icon">
                            <?php
                            $file_ext = pathinfo($download['file_path'], PATHINFO_EXTENSION);
                            $icon = 'fas fa-file';
                            switch (strtolower($file_ext)) {
                                case 'pdf':
                                    $icon = 'fas fa-file-pdf';
                                    break;
                                case 'doc':
                                case 'docx':
                                    $icon = 'fas fa-file-word';
                                    break;
                                case 'xls':
                                case 'xlsx':
                                    $icon = 'fas fa-file-excel';
                                    break;
                                case 'jpg':
                                case 'jpeg':
                                case 'png':
                                    $icon = 'fas fa-file-image';
                                    break;
                            }
                            ?>
                            <i class="<?php echo $icon; ?>"></i>
                        </div>
                        <div class="download-info">
                            <h4 class="download-title"><?php echo htmlspecialchars($download['title']); ?></h4>
                            <div class="download-meta">
                                <?php echo htmlspecialchars($download['category_name']); ?> •
                                <?php echo strtoupper($file_ext); ?> •
                                <?php echo formatFileSize($download['file_size']); ?>
                            </div>
                            <div class="download-stats">
                                <i class="fas fa-download"></i> <?php echo number_format($download['download_count']); ?>
                                downloads
                            </div>
                        </div>
                        <a href="download-resource.php?id=<?php echo $download['id']; ?>" class="btn btn-primary">
                            <i class="fas fa-download"></i>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Travel Tips -->
    <section class="tips-section">
        <div class="container">
            <div class="section-header text-center">
                <h2>Essential Travel Tips</h2>
                <p>Expert advice to make your travels smoother and more enjoyable</p>
            </div>

            <div class="tips-grid">
                <?php foreach ($travel_tips as $tip): ?>
                    <div class="tip-card">
                        <div class="tip-icon">
                            <i class="<?php echo $tip['icon'] ?: 'fas fa-lightbulb'; ?>"></i>
                        </div>
                        <h3 class="tip-title"><?php echo htmlspecialchars($tip['title']); ?></h3>
                        <div class="tip-content"><?php echo htmlspecialchars($tip['content']); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Visa Information -->
    <section class="visa-section">
        <div class="container">
            <div class="section-header text-center">
                <h2>Visa Requirements</h2>
                <p>Quick reference for visa requirements to popular destinations</p>
            </div>

            <div class="visa-grid">
                <?php foreach ($countries_with_visa_info as $country): ?>
                    <div class="visa-card">
                        <div class="visa-header">
                            <div class="country-flag">
                                <?php echo strtoupper(substr($country['name'], 0, 2)); ?>
                            </div>
                            <div class="country-name"><?php echo htmlspecialchars($country['name']); ?></div>
                        </div>

                        <div class="visa-status <?php
                        if ($country['visa_required'])
                            echo 'visa-required';
                        elseif ($country['visa_on_arrival'])
                            echo 'visa-on-arrival';
                        else
                            echo 'visa-free';
                        ?>">
                            <?php
                            if ($country['visa_required'])
                                echo 'Visa Required';
                            elseif ($country['visa_on_arrival'])
                                echo 'Visa on Arrival';
                            else
                                echo 'Visa Free';
                            ?>
                        </div>

                        <?php if ($country['visa_free_days']): ?>
                            <div class="visa-details">
                                Stay up to <?php echo $country['visa_free_days']; ?> days
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="text-center" style="margin-top: 40px;">
                <a href="visa-guide.php" class="btn btn-primary btn-lg">
                    View Complete Visa Guide
                </a>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section">
        <div class="container">
            <h2>Need Personalized Travel Advice?</h2>
            <p>Our travel experts are here to help you plan the perfect journey</p>
            <div class="cta-buttons">
                <a href="contact.php" class="btn btn-white btn-lg">Contact Our Experts</a>
                <a href="travel.php" class="btn btn-outline-white btn-lg">Browse Tours</a>
            </div>
        </div>
    </section>

    <?php include 'includes/footer.php'; ?>

    <script src="assets/js/main.js"></script>
</body>

</html>