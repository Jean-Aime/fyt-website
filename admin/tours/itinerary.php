<?php
require_once '../../config/config.php';
require_once('../../includes/secure_auth.php');

$auth = new SecureAuth($db);
requireLogin();
requirePermission('tours.edit');

$tour_id = isset($_GET['tour_id']) ? (int) $_GET['tour_id'] : 0;

if (!$tour_id) {
    header('Location: index.php?error=Tour not found');
    exit;
}

// Get tour details
$stmt = $db->prepare("SELECT id, title, duration_days FROM tours WHERE id = ?");
$stmt->execute([$tour_id]);
$tour = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tour) {
    header('Location: index.php?error=Tour not found');
    exit;
}

// Handle form submission
if ($_POST) {
    if (verifyCSRFToken($_POST['csrf_token'])) {
        try {
            if (isset($_POST['action'])) {
                switch ($_POST['action']) {
                    case 'save_itinerary':
                        // Delete existing itinerary
                        $stmt = $db->prepare("DELETE FROM itineraries WHERE tour_id = ?");
                        $stmt->execute([$tour_id]);

                        // Insert new itinerary
                        if (isset($_POST['days']) && is_array($_POST['days'])) {
                            $stmt = $db->prepare("
                                INSERT INTO itineraries (tour_id, day_number, title, description, activities, meals_included, accommodation, transportation)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                            ");

                            foreach ($_POST['days'] as $day_number => $day_data) {
                                if (!empty($day_data['title'])) {
                                    $stmt->execute([
                                        $tour_id,
                                        $day_number,
                                        trim($day_data['title']),
                                        trim($day_data['description']),
                                        trim($day_data['activities']),
                                        trim($day_data['meals_included']),
                                        trim($day_data['accommodation']),
                                        trim($day_data['transportation'])
                                    ]);
                                }
                            }
                        }

                        $success = 'Itinerary saved successfully!';
                        break;
                }
            }
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    } else {
        $error = 'Invalid security token. Please try again.';
    }
}

// Get existing itinerary
$stmt = $db->prepare("SELECT * FROM itineraries WHERE tour_id = ? ORDER BY day_number");
$stmt->execute([$tour_id]);
$existing_itinerary = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Convert to associative array by day number
$itinerary = [];
foreach ($existing_itinerary as $day) {
    $itinerary[$day['day_number']] = $day;
}

$page_title = 'Manage Itinerary: ' . $tour['title'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Forever Young Tours Admin</title>
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        <?php
        // Update the itinerary builder styling
        $itinerary_style = "
            .itinerary-container {
                max-width: 1000px;
                margin: 0 auto;
            }
            
            .day-card {
                background: white;
                border-radius: 15px;
                padding: 30px;
                margin-bottom: 30px;
                box-shadow: 0 5px 20px rgba(0,0,0,0.05);
                border-left: 5px solid #3498db;
                transition: transform 0.3s ease, box-shadow 0.3s ease;
            }
            
            .day-card:hover {
                transform: translateY(-5px);
                box-shadow: 0 15px 30px rgba(0,0,0,0.1);
            }
            
            .day-header {
                display: flex;
                align-items: center;
                gap: 20px;
                margin-bottom: 25px;
            }
            
            .day-number {
                width: 60px;
                height: 60px;
                border-radius: 50%;
                background: #3498db;
                color: white;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 1.5em;
                font-weight: bold;
                box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
            }
            
            .day-title-input {
                flex: 1;
                font-size: 1.3em;
                font-weight: 600;
                padding: 12px 20px;
                border: 2px solid #ecf0f1;
                border-radius: 10px;
                transition: border-color 0.3s ease, box-shadow 0.3s ease;
            }
            
            .day-title-input:focus {
                border-color: #3498db;
                box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.25);
                outline: none;
            }
            
            .day-content {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 25px;
            }
            
            .day-section {
                display: flex;
                flex-direction: column;
            }
            
            .day-section.full-width {
                grid-column: 1 / -1;
            }
            
            .section-label {
                font-weight: 600;
                color: #2c3e50;
                margin-bottom: 10px;
                display: flex;
                align-items: center;
                gap: 10px;
            }
            
            .section-icon {
                width: 25px;
                height: 25px;
                border-radius: 6px;
                background: #3498db;
                color: white;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 0.9em;
            }
            
            .day-textarea {
                resize: vertical;
                min-height: 100px;
                padding: 15px;
                border: 2px solid #ecf0f1;
                border-radius: 10px;
                font-family: inherit;
                transition: border-color 0.3s ease, box-shadow 0.3s ease;
            }
            
            .day-textarea:focus {
                border-color: #3498db;
                box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.25);
                outline: none;
            }
            
            .day-input {
                padding: 12px 15px;
                border: 2px solid #ecf0f1;
                border-radius: 10px;
                transition: border-color 0.3s ease, box-shadow 0.3s ease;
            }
            
            .day-input:focus {
                border-color: #3498db;
                box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.25);
                outline: none;
            }
            
            .add-day-btn {
                background: linear-gradient(135deg, #3498db, #2980b9);
                color: white;
                border: none;
                padding: 15px 30px;
                border-radius: 12px;
                font-size: 1.1em;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s ease;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 12px;
                margin: 30px auto;
                width: fit-content;
                box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
            }
            
            .add-day-btn:hover {
                transform: translateY(-3px);
                box-shadow: 0 10px 25px rgba(52, 152, 219, 0.4);
            }
            
            .remove-day-btn {
                background: #e74c3c;
                color: white;
                border: none;
                padding: 8px 15px;
                border-radius: 8px;
                cursor: pointer;
                font-size: 0.9em;
                transition: all 0.3s ease;
                display: flex;
                align-items: center;
                gap: 5px;
            }
            
            .remove-day-btn:hover {
                background: #c0392b;
                transform: translateY(-2px);
            }
            
            .save-itinerary {
                position: sticky;
                bottom: 30px;
                background: white;
                padding: 25px;
                border-radius: 15px;
                box-shadow: 0 -5px 20px rgba(0,0,0,0.1);
                text-align: center;
                margin-top: 40px;
                border: 1px solid #ecf0f1;
                z-index: 100;
            }
            
            .progress-indicator {
                background: #f8f9fa;
                padding: 20px 25px;
                border-radius: 12px;
                margin-bottom: 30px;
                display: flex;
                align-items: center;
                justify-content: space-between;
                box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            }
            
            .progress-text {
                font-weight: 600;
                color: #2c3e50;
                font-size: 1.1em;
            }
            
            .progress-bar {
                flex: 1;
                height: 10px;
                background: #ecf0f1;
                border-radius: 5px;
                margin: 0 20px;
                overflow: hidden;
            }
            
            .progress-fill {
                height: 100%;
                background: linear-gradient(90deg, #3498db, #2980b9);
                border-radius: 5px;
                transition: width 0.5s ease;
            }
        ";

        // Add this style to the head section
        echo "<style>$itinerary_style</style>";
        ?>
    </style>
</head>

<body>
    <div class="admin-wrapper">
        <?php include '../includes/sidebar.php'; ?>

        <div class="main-content">
            <?php include '../includes/header.php'; ?>

            <div class="content">
                <div class="content-header">
                    <div class="content-title">
                        <h2>Manage Itinerary</h2>
                        <p>Tour: <?php echo htmlspecialchars($tour['title']); ?> (<?php echo $tour['duration_days']; ?>
                            days)</p>
                    </div>
                    <div class="content-actions">
                        <a href="view.php?id=<?php echo $tour_id; ?>" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Tour
                        </a>
                        <button onclick="autoGenerateItinerary()" class="btn btn-info">
                            <i class="fas fa-magic"></i> Auto Generate
                        </button>
                    </div>
                </div>

                <?php if (isset($success)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($error)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <div class="itinerary-container">
                    <!-- Progress Indicator -->
                    <div class="progress-indicator">
                        <span class="progress-text">Itinerary Progress</span>
                        <div class="progress-bar">
                            <div class="progress-fill" id="progressFill" style="width: 0%"></div>
                        </div>
                        <span id="progressText">0 of <?php echo $tour['duration_days']; ?> days</span>
                    </div>

                    <form method="POST" id="itineraryForm">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="save_itinerary">

                        <div id="itineraryDays">
                            <?php for ($day = 1; $day <= $tour['duration_days']; $day++): ?>
                                <div class="day-card" data-day="<?php echo $day; ?>">
                                    <div class="day-header">
                                        <div class="day-number"><?php echo $day; ?></div>
                                        <input type="text" name="days[<?php echo $day; ?>][title]" class="day-title-input"
                                            placeholder="Day <?php echo $day; ?> title..."
                                            value="<?php echo htmlspecialchars($itinerary[$day]['title'] ?? ''); ?>"
                                            onchange="updateProgress()">
                                        <?php if ($day > $tour['duration_days']): ?>
                                            <button type="button" class="remove-day-btn"
                                                onclick="removeDay(<?php echo $day; ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>

                                    <div class="day-content">
                                        <div class="day-section full-width">
                                            <label class="section-label">
                                                <div class="section-icon">
                                                    <i class="fas fa-align-left"></i>
                                                </div>
                                                Description
                                            </label>
                                            <textarea name="days[<?php echo $day; ?>][description]" class="day-textarea"
                                                placeholder="Describe the day's overview and highlights..."><?php echo htmlspecialchars($itinerary[$day]['description'] ?? ''); ?></textarea>
                                        </div>

                                        <div class="day-section full-width">
                                            <label class="section-label">
                                                <div class="section-icon">
                                                    <i class="fas fa-hiking"></i>
                                                </div>
                                                Activities
                                            </label>
                                            <textarea name="days[<?php echo $day; ?>][activities]" class="day-textarea"
                                                placeholder="List the activities and experiences for this day..."><?php echo htmlspecialchars($itinerary[$day]['activities'] ?? ''); ?></textarea>
                                        </div>

                                        <div class="day-section">
                                            <label class="section-label">
                                                <div class="section-icon">
                                                    <i class="fas fa-utensils"></i>
                                                </div>
                                                Meals Included
                                            </label>
                                            <input type="text" name="days[<?php echo $day; ?>][meals_included]"
                                                class="day-input" placeholder="e.g., Breakfast, Lunch, Dinner"
                                                value="<?php echo htmlspecialchars($itinerary[$day]['meals_included'] ?? ''); ?>">
                                        </div>

                                        <div class="day-section">
                                            <label class="section-label">
                                                <div class="section-icon">
                                                    <i class="fas fa-bed"></i>
                                                </div>
                                                Accommodation
                                            </label>
                                            <input type="text" name="days[<?php echo $day; ?>][accommodation]"
                                                class="day-input" placeholder="Hotel, lodge, or camping details"
                                                value="<?php echo htmlspecialchars($itinerary[$day]['accommodation'] ?? ''); ?>">
                                        </div>

                                        <div class="day-section">
                                            <label class="section-label">
                                                <div class="section-icon">
                                                    <i class="fas fa-car"></i>
                                                </div>
                                                Transportation
                                            </label>
                                            <input type="text" name="days[<?php echo $day; ?>][transportation]"
                                                class="day-input" placeholder="Vehicle type or travel method"
                                                value="<?php echo htmlspecialchars($itinerary[$day]['transportation'] ?? ''); ?>">
                                        </div>
                                    </div>
                                </div>
                            <?php endfor; ?>
                        </div>

                        <div class="save-itinerary">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-save"></i> Save Itinerary
                            </button>
                            <p style="margin-top: 10px; color: var(--admin-text-muted);">
                                All changes will be saved when you click the save button
                            </p>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        let dayCounter = <?php echo $tour['duration_days']; ?>;

        function updateProgress() {
            const totalDays = <?php echo $tour['duration_days']; ?>;
            const dayCards = document.querySelectorAll('.day-card');
            let completedDays = 0;

            dayCards.forEach(card => {
                const titleInput = card.querySelector('.day-title-input');
                if (titleInput && titleInput.value.trim()) {
                    completedDays++;
                }
            });

            const percentage = (completedDays / totalDays) * 100;
            document.getElementById('progressFill').style.width = percentage + '%';
            document.getElementById('progressText').textContent = `${completedDays} of ${totalDays} days`;
        }

        function addDay() {
            dayCounter++;
            const container = document.getElementById('itineraryDays');

            const dayCard = document.createElement('div');
            dayCard.className = 'day-card';
            dayCard.setAttribute('data-day', dayCounter);

            dayCard.innerHTML = `
                <div class="day-header">
                    <div class="day-number">${dayCounter}</div>
                    <input type="text" 
                           name="days[${dayCounter}][title]" 
                           class="day-title-input" 
                           placeholder="Day ${dayCounter} title..."
                           onchange="updateProgress()">
                    <button type="button" class="remove-day-btn" onclick="removeDay(${dayCounter})">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
                
                <div class="day-content">
                    <div class="day-section full-width">
                        <label class="section-label">
                            <div class="section-icon">
                                <i class="fas fa-align-left"></i>
                            </div>
                            Description
                        </label>
                        <textarea name="days[${dayCounter}][description]" 
                                  class="day-textarea" 
                                  placeholder="Describe the day's overview and highlights..."></textarea>
                    </div>
                    
                    <div class="day-section full-width">
                        <label class="section-label">
                            <div class="section-icon">
                                <i class="fas fa-hiking"></i>
                            </div>
                            Activities
                        </label>
                        <textarea name="days[${dayCounter}][activities]" 
                                  class="day-textarea" 
                                  placeholder="List the activities and experiences for this day..."></textarea>
                    </div>
                    
                    <div class="day-section">
                        <label class="section-label">
                            <div class="section-icon">
                                <i class="fas fa-utensils"></i>
                            </div>
                            Meals Included
                        </label>
                        <input type="text" 
                               name="days[${dayCounter}][meals_included]" 
                               class="day-input" 
                               placeholder="e.g., Breakfast, Lunch, Dinner">
                    </div>
                    
                    <div class="day-section">
                        <label class="section-label">
                            <div class="section-icon">
                                <i class="fas fa-bed"></i>
                            </div>
                            Accommodation
                        </label>
                        <input type="text" 
                               name="days[${dayCounter}][accommodation]" 
                               class="day-input" 
                               placeholder="Hotel, lodge, or camping details">
                    </div>
                    
                    <div class="day-section">
                        <label class="section-label">
                            <div class="section-icon">
                                <i class="fas fa-car"></i>
                            </div>
                            Transportation
                        </label>
                        <input type="text" 
                               name="days[${dayCounter}][transportation]" 
                               class="day-input" 
                               placeholder="Vehicle type or travel method">
                    </div>
                </div>
            `;

            container.appendChild(dayCard);

            // Scroll to new day
            dayCard.scrollIntoView({ behavior: 'smooth' });

            // Focus on title input
            dayCard.querySelector('.day-title-input').focus();
        }

        function removeDay(dayNumber) {
            if (confirm('Are you sure you want to remove this day from the itinerary?')) {
                const dayCard = document.querySelector(`[data-day="${dayNumber}"]`);
                if (dayCard) {
                    dayCard.remove();
                    updateProgress();
                }
            }
        }

        function autoGenerateItinerary() {
            if (confirm('This will generate a basic itinerary template. Any existing content will be overwritten. Continue?')) {
                const tourTitle = '<?php echo addslashes($tour['title']); ?>';
                const totalDays = <?php echo $tour['duration_days']; ?>;

                // Basic templates based on tour duration
                const templates = {
                    1: [
                        { title: 'Arrival and Tour Highlights', description: 'Arrive and explore the main attractions', activities: 'City tour, welcome dinner' }
                    ],
                    2: [
                        { title: 'Arrival and Orientation', description: 'Arrive and get oriented with the destination', activities: 'Airport transfer, city orientation tour' },
                        { title: 'Main Activities and Departure', description: 'Experience the highlights and prepare for departure', activities: 'Main tour activities, departure preparations' }
                    ],
                    3: [
                        { title: 'Arrival and Welcome', description: 'Arrive and settle in', activities: 'Airport transfer, welcome briefing, local dinner' },
                        { title: 'Main Tour Activities', description: 'Full day of tour highlights', activities: 'Guided tours, cultural experiences, adventure activities' },
                        { title: 'Final Experiences and Departure', description: 'Last activities and departure', activities: 'Final sightseeing, souvenir shopping, departure' }
                    ]
                };

                // Use appropriate template or generate generic one
                let template = templates[totalDays] || [];

                if (template.length === 0) {
                    // Generate generic template for longer tours
                    for (let i = 1; i <= totalDays; i++) {
                        if (i === 1) {
                            template.push({
                                title: 'Arrival and Welcome',
                                description: 'Arrive at destination and begin your adventure',
                                activities: 'Airport transfer, welcome briefing, orientation tour'
                            });
                        } else if (i === totalDays) {
                            template.push({
                                title: 'Final Day and Departure',
                                description: 'Last experiences and departure preparations',
                                activities: 'Final sightseeing, souvenir shopping, departure transfer'
                            });
                        } else {
                            template.push({
                                title: `Day ${i} Activities`,
                                description: `Explore and experience the highlights of day ${i}`,
                                activities: 'Guided tours, cultural experiences, adventure activities'
                            });
                        }
                    }
                }

                // Fill in the form
                template.forEach((day, index) => {
                    const dayNumber = index + 1;
                    const titleInput = document.querySelector(`input[name="days[${dayNumber}][title]"]`);
                    const descInput = document.querySelector(`textarea[name="days[${dayNumber}][description]"]`);
                    const activitiesInput = document.querySelector(`textarea[name="days[${dayNumber}][activities]"]`);
                    const mealsInput = document.querySelector(`input[name="days[${dayNumber}][meals_included]"]`);
                    const accommodationInput = document.querySelector(`input[name="days[${dayNumber}][accommodation]"]`);
                    const transportInput = document.querySelector(`input[name="days[${dayNumber}][transportation]"]`);

                    if (titleInput) titleInput.value = day.title;
                    if (descInput) descInput.value = day.description;
                    if (activitiesInput) activitiesInput.value = day.activities;
                    if (mealsInput) mealsInput.value = 'Breakfast, Lunch, Dinner';
                    if (accommodationInput) accommodationInput.value = 'Tourist class accommodation';
                    if (transportInput) transportInput.value = 'Private vehicle';
                });

                updateProgress();

                // Show success message
                const alert = document.createElement('div');
                alert.className = 'alert alert-success';
                alert.innerHTML = '<i class="fas fa-check-circle"></i> Basic itinerary template generated! Please review and customize as needed.';
                document.querySelector('.content').insertBefore(alert, document.querySelector('.itinerary-container'));

                // Remove alert after 5 seconds
                setTimeout(() => {
                    alert.remove();
                }, 5000);
            }
        }

        // Initialize progress on page load
        document.addEventListener('DOMContentLoaded', function () {
            updateProgress();

            // Add change listeners to all inputs
            document.querySelectorAll('.day-title-input').forEach(input => {
                input.addEventListener('change', updateProgress);
            });
        });

        // Auto-save functionality
        let autoSaveTimeout;
        function autoSave() {
            clearTimeout(autoSaveTimeout);
            autoSaveTimeout = setTimeout(() => {
                // Could implement auto-save to localStorage here
                console.log('Auto-saving itinerary...');
            }, 2000);
        }

        // Add auto-save listeners
        document.addEventListener('input', autoSave);
    </script>

    <script src="../../assets/js/admin.js"></script>
</body>

</html>
