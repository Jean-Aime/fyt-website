<?php
require_once '../config/config.php';
require_once '../includes/secure_auth.php';
require_once '../includes/auth_middleware.php';

$auth = new SecureAuth($db);
requireLogin();
$advisor = requireAdvisor();

$user_id = $_SESSION['user_id'];
$advisor_id = $_SESSION['advisor_id'];

// Get certified advisor details
$advisor_details = getAdvisorDetails($user_id);

if (!$advisor_details) {
    header('Location: ../login.php?error=Advisor profile not found');
    exit;
}

// Get booking and commission statistics
$stmt = $db->prepare("
    SELECT 
        COUNT(b.id) as total_bookings,
        COUNT(CASE WHEN b.status = 'confirmed' THEN 1 END) as confirmed_bookings,
        COUNT(CASE WHEN b.status = 'pending' THEN 1 END) as pending_bookings,
        COUNT(CASE WHEN b.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as bookings_30d,
        COALESCE(SUM(b.total_amount), 0) as total_sales,
        COALESCE(SUM(CASE WHEN b.status = 'confirmed' THEN b.total_amount END), 0) as confirmed_sales
    FROM bookings b
    WHERE b.advisor_id = ?
");
$stmt->execute([$advisor_id]);
$booking_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get commission statistics
$stmt = $db->prepare("
    SELECT 
        COALESCE(SUM(CASE WHEN ac.status = 'pending' THEN ac.commission_amount END), 0) as pending_commission,
        COALESCE(SUM(CASE WHEN ac.status = 'approved' THEN ac.commission_amount END), 0) as approved_commission,
        COALESCE(SUM(CASE WHEN ac.status = 'paid' THEN ac.commission_amount END), 0) as paid_commission,
        COUNT(ac.id) as total_commissions
    FROM advisor_commissions ac
    WHERE ac.advisor_id = ?
");
$stmt->execute([$advisor_id]);
$commission_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get recent bookings
$stmt = $db->prepare("
    SELECT b.*, 
           t.title as tour_title, t.featured_image as tour_image, t.duration_days,
           c.name as country_name,
           u.first_name, u.last_name, u.email as customer_email,
           ac.commission_amount, ac.status as commission_status
    FROM bookings b
    LEFT JOIN tours t ON b.tour_id = t.id
    LEFT JOIN countries c ON t.country_id = c.id
    LEFT JOIN users u ON b.user_id = u.id
    LEFT JOIN advisor_commissions ac ON b.id = ac.booking_id
    WHERE b.advisor_id = ?
    ORDER BY b.created_at DESC
    LIMIT 10
");
$stmt->execute([$advisor_id]);
$recent_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get training progress
$stmt = $db->prepare("
    SELECT 
        COUNT(tm.id) as total_modules,
        COUNT(CASE WHEN tp.status = 'completed' THEN 1 END) as completed_modules,
        AVG(CASE WHEN tp.status = 'completed' THEN tp.score END) as average_score
    FROM advisor_training_modules tm
    LEFT JOIN advisor_training_progress tp ON tm.id = tp.module_id AND tp.advisor_id = ?
    WHERE tm.status = 'active'
");
$stmt->execute([$advisor_id]);
$training = $stmt->fetch(PDO::FETCH_ASSOC);

// Get monthly performance data
$stmt = $db->prepare("
    SELECT 
        DATE_FORMAT(b.created_at, '%Y-%m') as month,
        COUNT(b.id) as booking_count,
        COALESCE(SUM(b.total_amount), 0) as sales,
        COALESCE(SUM(ac.commission_amount), 0) as commissions
    FROM bookings b
    LEFT JOIN advisor_commissions ac ON b.id = ac.booking_id
    WHERE b.advisor_id = ?
    AND b.created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(b.created_at, '%Y-%m')
    ORDER BY month ASC
");
$stmt->execute([$advisor_id]);
$monthly_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Advisor Dashboard - Forever Young Tours';
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --advisor-primary: #228B22;
            --advisor-secondary: #32CD32;
            --advisor-accent: #90EE90;
        }

        .advisor-welcome {
            background: linear-gradient(135deg, var(--advisor-primary) 0%, var(--advisor-secondary) 100%);
            color: white;
            padding: 40px;
            border-radius: 20px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .welcome-content h1 {
            font-size: 2.2em;
            margin-bottom: 10px;
            font-weight: 700;
        }

        .advisor-info {
            display: flex;
            gap: 20px;
            margin-top: 15px;
            flex-wrap: wrap;
        }

        .info-badge {
            background: rgba(255, 255, 255, 0.2);
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: 500;
        }

        .stat-card.sales { border-left: 4px solid var(--advisor-primary); }
        .stat-card.bookings { border-left: 4px solid var(--advisor-secondary); }
        .stat-card.commission { border-left: 4px solid #FFD700; }
        .stat-card.training { border-left: 4px solid #FF6347; }

        .referral-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }

        .referral-link-container {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .referral-input {
            flex: 1;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-family: monospace;
            background: #f8f9fa;
        }

        .copy-btn {
            background: var(--advisor-primary);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .copy-btn:hover {
            background: var(--advisor-secondary);
            transform: translateY(-2px);
        }

        .social-share {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .social-btn {
            padding: 10px 15px;
            border: none;
            border-radius: 8px;
            color: white;
            text-decoration: none;
            font-size: 0.9em;
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .social-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }

        .social-btn.facebook { background: #1877f2; }
        .social-btn.twitter { background: #1da1f2; }
        .social-btn.whatsapp { background: #25d366; }
        .social-btn.linkedin { background: #0077b5; }

        .marketing-materials {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .material-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            text-align: center;
            transition: all 0.3s ease;
        }

        .material-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .material-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--advisor-primary), var(--advisor-secondary));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 1.5em;
        }

        .progress-ring {
            width: 120px;
            height: 120px;
            margin: 0 auto;
        }

        .progress-ring circle {
            fill: none;
            stroke-width: 8;
        }

        .progress-ring .bg {
            stroke: #e0e0e0;
        }

        .progress-ring .progress {
            stroke: var(--advisor-primary);
            stroke-linecap: round;
            transform: rotate(-90deg);
            transform-origin: 50% 50%;
            transition: stroke-dasharray 0.5s ease;
        }

        .progress-text {
            text-align: center;
            margin-top: 15px;
            font-size: 1.1em;
            font-weight: 600;
            color: var(--advisor-primary);
        }
    </style>
</head>

<body>
    <div class="client-wrapper">
        <?php include 'includes/sidebar.php'; ?>

        <div class="main-content">
            <?php include 'includes/header.php'; ?>

            <div class="content">
                <!-- Advisor Welcome Section -->
                <div class="advisor-welcome">
                    <div class="welcome-content">
                        <h1>Welcome, Advisor <?php echo htmlspecialchars($advisor_details['first_name']); ?>!</h1>
                        <p>Grow your network and earn commissions with Forever Young Tours</p>
                        <div class="advisor-info">
                            <span class="info-badge">
                                <i class="fas fa-id-card"></i> <?php echo htmlspecialchars($advisor_details['advisor_code']); ?>
                            </span>
                            <span class="info-badge">
                                <i class="fas fa-flag"></i> <?php echo htmlspecialchars($advisor_details['country']); ?>
                            </span>
                            <span class="info-badge">
                                <i class="fas fa-user-tie"></i> MCA: <?php echo htmlspecialchars($advisor_details['mca_name'] ?? 'Not Assigned'); ?>
                            </span>
                            <span class="info-badge">
                                <i class="fas fa-percentage"></i> <?php echo $advisor_details['commission_rate']; ?>% Commission
                            </span>
                        </div>
                    </div>
                    <div class="quick-actions">
                        <a href="../book.php?advisor=<?php echo $advisor_details['advisor_code']; ?>" class="btn btn-primary">
                            <i class="fas fa-plus"></i> New Booking
                        </a>
                        <a href="clients.php" class="btn btn-outline">
                            <i class="fas fa-users"></i> My Clients
                        </a>
                        <a href="training.php" class="btn btn-secondary">
                            <i class="fas fa-graduation-cap"></i> Training
                        </a>
                    </div>
                </div>

                <!-- Performance Statistics -->
                <div class="stats-grid">
                    <div class="stat-card sales">
                        <div class="stat-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo formatCurrency($booking_stats['confirmed_sales']); ?></h3>
                            <p>Total Sales</p>
                            <span class="stat-change">
                                <?php echo $booking_stats['confirmed_bookings']; ?> confirmed bookings
                            </span>
                        </div>
                    </div>

                    <div class="stat-card bookings">
                        <div class="stat-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $booking_stats['total_bookings']; ?></h3>
                            <p>Total Bookings</p>
                            <span class="stat-change">
                                <?php echo $booking_stats['bookings_30d']; ?> this month
                            </span>
                        </div>
                    </div>

                    <div class="stat-card commission">
                        <div class="stat-icon">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo formatCurrency($commission_stats['paid_commission']); ?></h3>
                            <p>Commissions Earned</p>
                            <span class="stat-change">
                                <?php echo formatCurrency($commission_stats['pending_commission'] + $commission_stats['approved_commission']); ?> pending
                            </span>
                        </div>
                    </div>

                    <div class="stat-card training">
                        <div class="stat-icon">
                            <i class="fas fa-graduation-cap"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo round(($training['completed_modules'] / max($training['total_modules'], 1)) * 100); ?>%</h3>
                            <p>Training Progress</p>
                            <span class="stat-change">
                                <?php echo $training['completed_modules']; ?>/<?php echo $training['total_modules']; ?> modules
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Referral Link Section -->
                <div class="referral-section">
                    <div class="section-header">
                        <h2><i class="fas fa-share-alt"></i> Your Referral Link</h2>
                        <p>Share this link to earn commissions on bookings</p>
                    </div>
                    
                    <div class="referral-link-container">
                        <input type="text" class="referral-input" id="referralLink" 
                               value="<?php echo SITE_URL; ?>/book.php?advisor=<?php echo $advisor_details['advisor_code']; ?>" readonly>
                        <button class="copy-btn" onclick="copyReferralLink()">
                            <i class="fas fa-copy"></i> Copy
                        </button>
                    </div>

                    <div class="social-share">
                        <a href="#" class="social-btn facebook" onclick="shareOnFacebook()">
                            <i class="fab fa-facebook-f"></i> Facebook
                        </a>
                        <a href="#" class="social-btn twitter" onclick="shareOnTwitter()">
                            <i class="fab fa-twitter"></i> Twitter
                        </a>
                        <a href="#" class="social-btn whatsapp" onclick="shareOnWhatsApp()">
                            <i class="fab fa-whatsapp"></i> WhatsApp
                        </a>
                        <a href="#" class="social-btn linkedin" onclick="shareOnLinkedIn()">
                            <i class="fab fa-linkedin-in"></i> LinkedIn
                        </a>
                    </div>
                </div>

                <div class="dashboard-grid">
                    <!-- Performance Chart -->
                    <div class="dashboard-section">
                        <div class="section-header">
                            <h2>Monthly Performance</h2>
                            <div class="chart-controls">
                                <button class="chart-toggle active" data-chart="sales">Sales</button>
                                <button class="chart-toggle" data-chart="commissions">Commissions</button>
                            </div>
                        </div>
                        <div class="chart-container">
                            <canvas id="performanceChart" width="400" height="200"></canvas>
                        </div>
                    </div>

                    <!-- Training Progress -->
                    <div class="dashboard-section">
                        <div class="section-header">
                            <h2>Training Progress</h2>
                            <a href="training.php" class="view-all">Continue Training</a>
                        </div>

                        <div class="training-overview">
                            <div class="progress-ring">
                                <svg width="120" height="120">
                                    <circle class="bg" cx="60" cy="60" r="52"></circle>
                                    <circle class="progress" cx="60" cy="60" r="52" 
                                            stroke-dasharray="<?php echo round(($training['completed_modules'] / max($training['total_modules'], 1)) * 327); ?> 327"></circle>
                                </svg>
                            </div>
                            <div class="progress-text">
                                <?php echo round(($training['completed_modules'] / max($training['total_modules'], 1)) * 100); ?>% Complete
                            </div>
                            <div class="training-details">
                                <p><?php echo $training['completed_modules']; ?> of <?php echo $training['total_modules']; ?> modules completed</p>
                                <?php if ($training['average_score']): ?>
                                    <p>Average Score: <strong><?php echo round($training['average_score'], 1); ?>%</strong></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Bookings -->
                <div class="dashboard-section">
                    <div class="section-header">
                        <h2>Recent Bookings</h2>
                        <a href="bookings.php" class="view-all">View All</a>
                    </div>

                    <?php if (empty($recent_bookings)): ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-times"></i>
                            <h3>No Bookings Yet</h3>
                            <p>Start sharing your referral link to earn your first commission!</p>
                            <button onclick="copyReferralLink()" class="btn btn-primary">Copy Referral Link</button>
                        </div>
                    <?php else: ?>
                        <div class="bookings-list">
                            <?php foreach (array_slice($recent_bookings, 0, 5) as $booking): ?>
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
                                            <span class="booking-status <?php echo $booking['status']; ?>">
                                                <?php echo ucfirst($booking['status']); ?>
                                            </span>
                                        </div>

                                        <div class="booking-details">
                                            <div class="detail-item">
                                                <i class="fas fa-user"></i>
                                                <?php echo htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']); ?>
                                            </div>
                                            <div class="detail-item">
                                                <i class="fas fa-calendar"></i>
                                                <?php echo date('M j, Y', strtotime($booking['tour_date'])); ?>
                                            </div>
                                            <div class="detail-item">
                                                <i class="fas fa-map-marker-alt"></i>
                                                <?php echo htmlspecialchars($booking['country_name']); ?>
                                            </div>
                                        </div>

                                        <div class="booking-footer">
                                            <div class="booking-amount">
                                                <span class="total"><?php echo formatCurrency($booking['total_amount']); ?></span>
                                                <?php if ($booking['commission_amount']): ?>
                                                    <span class="commission">Commission: <?php echo formatCurrency($booking['commission_amount']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <a href="booking-details.php?id=<?php echo $booking['id']; ?>" class="btn btn-sm btn-outline">
                                                View Details
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Marketing Materials -->
                <div class="dashboard-section">
                    <div class="section-header">
                        <h2>Marketing Materials</h2>
                        <a href="marketing.php" class="view-all">View All</a>
                    </div>

                    <div class="marketing-materials">
                        <div class="material-card">
                            <div class="material-icon">
                                <i class="fas fa-images"></i>
                            </div>
                            <h4>Tour Brochures</h4>
                            <p>Download high-quality brochures for all tour packages</p>
                            <a href="marketing.php#brochures" class="btn btn-sm btn-primary">Download</a>
                        </div>

                        <div class="material-card">
                            <div class="material-icon">
                                <i class="fas fa-share-alt"></i>
                            </div>
                            <h4>Social Media Kit</h4>
                            <p>Ready-to-share posts and graphics for social media</p>
                            <a href="marketing.php#social" class="btn btn-sm btn-primary">Access</a>
                        </div>

                        <div class="material-card">
                            <div class="material-icon">
                                <i class="fas fa-video"></i>
                            </div>
                            <h4>Video Content</h4>
                            <p>Promotional videos and virtual tour previews</p>
                            <a href="marketing.php#videos" class="btn btn-sm btn-primary">Watch</a>
                        </div>

                        <div class="material-card">
                            <div class="material-icon">
                                <i class="fas fa-file-alt"></i>
                            </div>
                            <h4>Email Templates</h4>
                            <p>Professional email templates for client outreach</p>
                            <a href="marketing.php#emails" class="btn btn-sm btn-primary">Use</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/client-portal.js"></script>
    <script>
        // Performance Chart
        const ctx = document.getElementById('performanceChart').getContext('2d');
        const monthlyData = <?php echo json_encode($monthly_data); ?>;
        
        const chartData = {
            labels: monthlyData.map(item => {
                const date = new Date(item.month + '-01');
                return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
            }),
            datasets: [{
                label: 'Sales ($)',
                data: monthlyData.map(item => parseFloat(item.sales)),
                borderColor: '#228B22',
                backgroundColor: 'rgba(34, 139, 34, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4
            }]
        };

        const performanceChart = new Chart(ctx, {
            type: 'line',
            data: chartData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value.toLocaleString();
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Sales: $' + context.parsed.y.toLocaleString();
                            }
                        }
                    }
                }
            }
        });

        // Chart toggle functionality
        document.querySelectorAll('.chart-toggle').forEach(button => {
            button.addEventListener('click', function() {
                document.querySelectorAll('.chart-toggle').forEach(btn => btn.classList.remove('active'));
                this.classList.add('active');
                
                const chartType = this.dataset.chart;
                if (chartType === 'commissions') {
                    performanceChart.data.datasets[0].label = 'Commissions ($)';
                    performanceChart.data.datasets[0].data = monthlyData.map(item => parseFloat(item.commissions));
                    performanceChart.data.datasets[0].borderColor = '#FFD700';
                    performanceChart.data.datasets[0].backgroundColor = 'rgba(255, 215, 0, 0.1)';
                } else {
                    performanceChart.data.datasets[0].label = 'Sales ($)';
                    performanceChart.data.datasets[0].data = monthlyData.map(item => parseFloat(item.sales));
                    performanceChart.data.datasets[0].borderColor = '#228B22';
                    performanceChart.data.datasets[0].backgroundColor = 'rgba(34, 139, 34, 0.1)';
                }
                performanceChart.update();
            });
        });

        // Referral link functions
        function copyReferralLink() {
            const referralInput = document.getElementById('referralLink');
            referralInput.select();
            document.execCommand('copy');
            
            // Show success message
            const copyBtn = document.querySelector('.copy-btn');
            const originalText = copyBtn.innerHTML;
            copyBtn.innerHTML = '<i class="fas fa-check"></i> Copied!';
            copyBtn.style.background = '#27ae60';
            
            setTimeout(() => {
                copyBtn.innerHTML = originalText;
                copyBtn.style.background = '';
            }, 2000);
        }

        function shareOnFacebook() {
            const url = document.getElementById('referralLink').value;
            const shareUrl = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(url)}`;
            window.open(shareUrl, '_blank', 'width=600,height=400');
        }

        function shareOnTwitter() {
            const url = document.getElementById('referralLink').value;
            const text = 'Discover amazing tours with Forever Young Tours! Book through my link and let\'s explore the world together.';
            const shareUrl = `https://twitter.com/intent/tweet?text=${encodeURIComponent(text)}&url=${encodeURIComponent(url)}`;
            window.open(shareUrl, '_blank', 'width=600,height=400');
        }

        function shareOnWhatsApp() {
            const url = document.getElementById('referralLink').value;
            const text = `Discover amazing tours with Forever Young Tours! Book through my link: ${url}`;
            const shareUrl = `https://wa.me/?text=${encodeURIComponent(text)}`;
            window.open(shareUrl, '_blank');
        }

        function shareOnLinkedIn() {
            const url = document.getElementById('referralLink').value;
            const shareUrl = `https://www.linkedin.com/sharing/share-offsite/?url=${encodeURIComponent(url)}`;
            window.open(shareUrl, '_blank', 'width=600,height=400');
        }
    </script>
</body>
</html>
