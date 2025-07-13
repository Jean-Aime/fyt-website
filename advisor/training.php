<?php
require_once '../config/config.php';
require_once '../includes/secure_auth.php';

$auth = new SecureAuth($db);
requireLogin();

// Ensure user is a certified advisor
if ($_SESSION['role_name'] !== 'certified_advisor') {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Get certified advisor details
$stmt = $db->prepare("SELECT * FROM certified_advisors WHERE user_id = ?");
$stmt->execute([$user_id]);
$advisor = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle module completion
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'complete_module') {
    if (verifyCSRFToken($_POST['csrf_token'])) {
        try {
            $module_id = (int) $_POST['module_id'];
            $score = (int) $_POST['score'];

            // Check if module exists and is active
            $stmt = $db->prepare("SELECT * FROM advisor_training_modules WHERE id = ? AND status = 'active'");
            $stmt->execute([$module_id]);
            $module = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$module) {
                throw new Exception('Invalid module');
            }

            // Check if already completed
            $stmt = $db->prepare("SELECT id FROM advisor_training_progress WHERE advisor_id = ? AND module_id = ? AND status = 'completed'");
            $stmt->execute([$advisor['id'], $module_id]);
            if ($stmt->fetchColumn()) {
                throw new Exception('Module already completed');
            }

            // Record completion
            $stmt = $db->prepare("
                INSERT INTO advisor_training_progress (advisor_id, module_id, status, score, completed_at, created_at) 
                VALUES (?, ?, 'completed', ?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE status = 'completed', score = ?, completed_at = NOW()
            ");
            $stmt->execute([$advisor['id'], $module_id, $score, $score]);

            // Check if this completes certification requirements
            $stmt = $db->prepare("
                SELECT COUNT(*) as completed_count
                FROM advisor_training_progress atp
                JOIN advisor_training_modules atm ON atp.module_id = atm.id
                WHERE atp.advisor_id = ? AND atp.status = 'completed' AND atm.required = 1
            ");
            $stmt->execute([$advisor['id']]);
            $completed_required = $stmt->fetchColumn();

            $stmt = $db->prepare("SELECT COUNT(*) as total_required FROM advisor_training_modules WHERE required = 1 AND status = 'active'");
            $stmt->execute();
            $total_required = $stmt->fetchColumn();

            if ($completed_required >= $total_required) {
                // Update advisor status to certified
                $stmt = $db->prepare("UPDATE certified_advisors SET certification_status = 'certified', certified_at = NOW() WHERE id = ?");
                $stmt->execute([$advisor['id']]);
                $certification_achieved = true;
            }

            $success = 'Module completed successfully!';
            if (isset($certification_achieved)) {
                $success .= ' Congratulations! You are now fully certified!';
            }

        } catch (Exception $e) {
            $error = 'Error completing module: ' . $e->getMessage();
        }
    }
}

// Get training modules with progress
$stmt = $db->prepare("
    SELECT atm.*, 
           atp.status as progress_status,
           atp.score,
           atp.completed_at,
           CASE 
               WHEN atp.status = 'completed' THEN 'completed'
               WHEN atm.prerequisites IS NULL OR atm.prerequisites = '' THEN 'available'
               ELSE 'locked'
           END as availability_status
    FROM advisor_training_modules atm
    LEFT JOIN advisor_training_progress atp ON atm.id = atp.module_id AND atp.advisor_id = ?
    WHERE atm.status = 'active'
    ORDER BY atm.order_sequence ASC, atm.created_at ASC
");
$stmt->execute([$advisor['id']]);
$modules = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate overall progress
$total_modules = count($modules);
$completed_modules = count(array_filter($modules, function($m) { return $m['progress_status'] === 'completed'; }));
$progress_percentage = $total_modules > 0 ? round(($completed_modules / $total_modules) * 100) : 0;

// Get certification status
$stmt = $db->prepare("
    SELECT 
        COUNT(CASE WHEN atm.required = 1 THEN 1 END) as required_modules,
        COUNT(CASE WHEN atm.required = 1 AND atp.status = 'completed' THEN 1 END) as completed_required
    FROM advisor_training_modules atm
    LEFT JOIN advisor_training_progress atp ON atm.id = atp.module_id AND atp.advisor_id = ?
    WHERE atm.status = 'active'
");
$stmt->execute([$advisor['id']]);
$cert_progress = $stmt->fetch(PDO::FETCH_ASSOC);

$page_title = 'Training Center - Advisor Portal';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="../assets/css/client-portal.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .training-header {
            background: linear-gradient(135deg, #228B22, #32CD32);
            color: white;
            padding: 40px;
            border-radius: 20px;
            margin-bottom: 30px;
            text-align: center;
        }

        .training-header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
        }

        .training-header p {
            font-size: 1.1em;
            opacity: 0.9;
        }

        .progress-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .progress-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            text-align: center;
        }

        .progress-circle {
            width: 100px;
            height: 100px;
            margin: 0 auto 20px;
            position: relative;
        }

        .progress-circle svg {
            width: 100%;
            height: 100%;
            transform: rotate(-90deg);
        }

        .progress-circle .bg {
            fill: none;
            stroke: #e0e0e0;
            stroke-width: 8;
        }

        .progress-circle .progress {
            fill: none;
            stroke: #228B22;
            stroke-width: 8;
            stroke-linecap: round;
            transition: stroke-dasharray 0.5s ease;
        }

        .progress-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 1.2em;
            font-weight: bold;
            color: #228B22;
        }

        .certification-status {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }

        .cert-badge {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 10px 20px;
            border-radius: 25px;
            font-weight: 600;
            font-size: 0.9em;
        }

        .cert-badge.certified {
            background: #d4edda;
            color: #155724;
        }

        .cert-badge.in-progress {
            background: #fff3cd;
            color: #856404;
        }

        .cert-badge.not-started {
            background: #f8d7da;
            color: #721c24;
        }

        .modules-grid {
            display: grid;
            gap: 20px;
        }

        .module-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .module-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }

        .module-header {
            padding: 25px;
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .module-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5em;
            color: white;
        }

        .module-icon.available {
            background: linear-gradient(135deg, #228B22, #32CD32);
        }

        .module-icon.completed {
            background: linear-gradient(135deg, #27ae60, #2ecc71);
        }

        .module-icon.locked {
            background: #95a5a6;
        }

        .module-info h3 {
            margin-bottom: 5px;
            color: #2c3e50;
            font-size: 1.3em;
        }

        .module-meta {
            display: flex;
            gap: 15px;
            font-size: 0.9em;
            color: #7f8c8d;
        }

        .module-content {
            padding: 0 25px 25px;
        }

        .module-description {
            color: #555;
            margin-bottom: 20px;
            line-height: 1.6;
        }

        .module-topics {
            margin-bottom: 20px;
        }

        .module-topics h4 {
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 1em;
        }

        .topics-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .topic-tag {
            background: #f8f9fa;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.85em;
            color: #555;
            border: 1px solid #e0e0e0;
        }

        .module-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .module-status {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
        }

        .status-completed {
            color: #27ae60;
        }

        .status-available {
            color: #228B22;
        }

        .status-locked {
            color: #95a5a6;
        }

        .quiz-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        .quiz-content {
            background: white;
            border-radius: 15px;
            padding: 40px;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }

        .quiz-question {
            margin-bottom: 20px;
        }

        .quiz-options {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .quiz-option {
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .quiz-option:hover {
            border-color: #228B22;
            background: #f0f8f0;
        }

        .quiz-option.selected {
            border-color: #228B22;
            background: #e8f5e8;
        }

        @media (max-width: 768px) {
            .training-header {
                padding: 20px;
            }

            .training-header h1 {
                font-size: 2em;
            }

            .progress-overview {
                grid-template-columns: 1fr;
            }

            .module-header {
                flex-direction: column;
                text-align: center;
            }

            .module-actions {
                flex-direction: column;
                gap: 15px;
                align-items: stretch;
            }
        }
    </style>
</head>

<body>
    <div class="client-wrapper">
        <?php include 'includes/sidebar.php'; ?>

        <div class="main-content">
            <?php include 'includes/header.php'; ?>

            <div class="content">
                <div class="training-header">
                    <h1><i class="fas fa-graduation-cap"></i> Training Center</h1>
                    <p>Complete your training modules to become a certified Forever Young Tours advisor</p>
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

                <!-- Progress Overview -->
                <div class="progress-overview">
                    <div class="progress-card">
                        <div class="progress-circle">
                            <svg viewBox="0 0 100 100">
                                <circle class="bg" cx="50" cy="50" r="40"></circle>
                                <circle class="progress" cx="50" cy="50" r="40" 
                                        stroke-dasharray="<?php echo $progress_percentage * 2.51; ?> 251"></circle>
                            </svg>
                            <div class="progress-text"><?php echo $progress_percentage; ?>%</div>
                        </div>
                        <h3>Overall Progress</h3>
                        <p><?php echo $completed_modules; ?> of <?php echo $total_modules; ?> modules completed</p>
                    </div>

                    <div class="progress-card">
                        <div class="progress-circle">
                            <svg viewBox="0 0 100 100">
                                <circle class="bg" cx="50" cy="50" r="40"></circle>
                                <circle class="progress" cx="50" cy="50" r="40" 
                                        stroke-dasharray="<?php echo round(($cert_progress['completed_required'] / max($cert_progress['required_modules'], 1)) * 251); ?> 251"></circle>
                            </svg>
                            <div class="progress-text"><?php echo round(($cert_progress['completed_required'] / max($cert_progress['required_modules'], 1)) * 100); ?>%</div>
                        </div>
                        <h3>Certification Progress</h3>
                        <p><?php echo $cert_progress['completed_required']; ?> of <?php echo $cert_progress['required_modules']; ?> required modules</p>
                    </div>
                </div>

                <!-- Certification Status -->
                <div class="certification-status">
                    <h2><i class="fas fa-certificate"></i> Certification Status</h2>
                    <?php if ($advisor['certification_status'] === 'certified'): ?>
                        <span class="cert-badge certified">
                            <i class="fas fa-check-circle"></i> Certified Advisor
                        </span>
                        <p>Congratulations! You are a certified Forever Young Tours advisor. You can now earn full commissions on all bookings.</p>
                    <?php elseif ($cert_progress['completed_required'] > 0): ?>
                        <span class="cert-badge in-progress">
                            <i class="fas fa-clock"></i> Certification In Progress
                        </span>
                        <p>You're making great progress! Complete all required modules to achieve full certification.</p>
                    <?php else: ?>
                        <span class="cert-badge not-started">
                            <i class="fas fa-exclamation-circle"></i> Not Started
                        </span>
                        <p>Start your training journey to become a certified advisor and unlock full commission potential.</p>
                    <?php endif; ?>
                </div>

                <!-- Training Modules -->
                <div class="modules-section">
                    <div class="section-header">
                        <h2>Training Modules</h2>
                        <p>Complete these modules to enhance your knowledge and skills</p>
                    </div>

                    <div class="modules-grid">
                        <?php foreach ($modules as $module): ?>
                            <div class="module-card">
                                <div class="module-header">
                                    <div class="module-icon <?php echo $module['availability_status']; ?>">
                                        <?php if ($module['progress_status'] === 'completed'): ?>
                                            <i class="fas fa-check"></i>
                                        <?php elseif ($module['availability_status'] === 'locked'): ?>
                                            <i class="fas fa-lock"></i>
                                        <?php else: ?>
                                            <i class="fas fa-play"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="module-info">
                                        <h3><?php echo htmlspecialchars($module['title']); ?>
                                            <?php if ($module['required']): ?>
                                                <span style="color: #e74c3c; font-size: 0.8em;">(Required)</span>
                                            <?php endif; ?>
                                        </h3>
                                        <div class="module-meta">
                                            <span><i class="fas fa-clock"></i> <?php echo $module['duration_minutes']; ?> min</span>
                                            <span><i class="fas fa-signal"></i> <?php echo ucfirst($module['difficulty_level']); ?></span>
                                            <?php if ($module['progress_status'] === 'completed'): ?>
                                                <span><i class="fas fa-star"></i> Score: <?php echo $module['score']; ?>%</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="module-content">
                                    <div class="module-description">
                                        <?php echo htmlspecialchars($module['description']); ?>
                                    </div>

                                    <?php if ($module['topics']): ?>
                                        <div class="module-topics">
                                            <h4>Topics Covered:</h4>
                                            <div class="topics-list">
                                                <?php foreach (explode(',', $module['topics']) as $topic): ?>
                                                    <span class="topic-tag"><?php echo trim(htmlspecialchars($topic)); ?></span>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <div class="module-actions">
                                        <div class="module-status">
                                            <?php if ($module['progress_status'] === 'completed'): ?>
                                                <span class="status-completed">
                                                    <i class="fas fa-check-circle"></i> Completed
                                                </span>
                                            <?php elseif ($module['availability_status'] === 'locked'): ?>
                                                <span class="status-locked">
                                                    <i class="fas fa-lock"></i> Locked
                                                </span>
                                            <?php else: ?>
                                                <span class="status-available">
                                                    <i class="fas fa-play-circle"></i> Available
                                                </span>
                                            <?php endif; ?>
                                        </div>

                                        <div class="module-buttons">
                                            <?php if ($module['availability_status'] === 'available' && $module['progress_status'] !== 'completed'): ?>
                                                <button onclick="startModule(<?php echo $module['id']; ?>)" class="btn btn-primary">
                                                    <i class="fas fa-play"></i> Start Module
                                                </button>
                                            <?php elseif ($module['progress_status'] === 'completed'): ?>
                                                <button onclick="reviewModule(<?php echo $module['id']; ?>)" class="btn btn-outline">
                                                    <i class="fas fa-eye"></i> Review
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quiz Modal -->
    <div class="quiz-modal" id="quizModal" style="display: none;">
        <div class="quiz-content">
            <div class="quiz-header">
                <h2 id="quizTitle">Module Quiz</h2>
                <button onclick="closeQuiz()" class="btn btn-outline btn-sm" style="float: right;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="quizContent">
                <!-- Quiz content loaded dynamically -->
            </div>
        </div>
    </div>

    <script src="../assets/js/client-portal.js"></script>
    <script>
        function startModule(moduleId) {
            // In a real application, this would load the module content
            // For now, we'll simulate with a simple quiz
            showQuiz(moduleId);
        }

        function reviewModule(moduleId) {
            // Show module content for review
            alert('Module review functionality would be implemented here.');
        }

        function showQuiz(moduleId) {
            const modal = document.getElementById('quizModal');
            const content = document.getElementById('quizContent');
            
            // Sample quiz questions (in a real app, these would come from the database)
            const sampleQuestions = [
                {
                    question: "What is the primary goal of Forever Young Tours?",
                    options: [
                        "To provide luxury travel experiences",
                        "To promote cultural exchange and agro-tourism",
                        "To compete with other tour operators",
                        "To maximize profits only"
                    ],
                    correct: 1
                },
                {
                    question: "What commission rate do certified advisors earn?",
                    options: ["10%", "15%", "20%", "25%"],
                    correct: 1
                },
                {
                    question: "Which of the following is NOT a core tour category?",
                    options: [
                        "Agro-Tourism",
                        "Cultural Tours", 
                        "Adventure Travel",
                        "Space Tourism"
                    ],
                    correct: 3
                }
            ];

            let currentQuestion = 0;
            let answers = [];

            function renderQuestion() {
                const q = sampleQuestions[currentQuestion];
                content.innerHTML = `
                    <div class="quiz-progress">
                        <p>Question ${currentQuestion + 1} of ${sampleQuestions.length}</p>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: ${((currentQuestion + 1) / sampleQuestions.length) * 100}%"></div>
                        </div>
                    </div>
                    <div class="quiz-question">
                        <h3>${q.question}</h3>
                        <div class="quiz-options">
                            ${q.options.map((option, index) => `
                                <div class="quiz-option" onclick="selectOption(${index})">
                                    ${option}
                                </div>
                            `).join('')}
                        </div>
                    </div>
                    <div class="quiz-actions" style="margin-top: 20px;">
                        <button onclick="nextQuestion()" class="btn btn-primary" id="nextBtn" disabled>
                            ${currentQuestion === sampleQuestions.length - 1 ? 'Finish Quiz' : 'Next Question'}
                        </button>
                    </div>
                `;
            }

            window.selectOption = function(index) {
                document.querySelectorAll('.quiz-option').forEach(opt => opt.classList.remove('selected'));
                document.querySelectorAll('.quiz-option')[index].classList.add('selected');
                answers[currentQuestion] = index;
                document.getElementById('nextBtn').disabled = false;
            };

            window.nextQuestion = function() {
                if (currentQuestion < sampleQuestions.length - 1) {
                    currentQuestion++;
                    renderQuestion();
                } else {
                    // Calculate score
                    let correct = 0;
                    answers.forEach((answer, index) => {
                        if (answer === sampleQuestions[index].correct) correct++;
                    });
                    const score = Math.round((correct / sampleQuestions.length) * 100);
                    
                    // Submit completion
                    completeModule(moduleId, score);
                }
            };

            renderQuestion();
            modal.style.display = 'flex';
        }

        function completeModule(moduleId, score) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="complete_module">
                <input type="hidden" name="module_id" value="${moduleId}">
                <input type="hidden" name="score" value="${score}">
            `;
            document.body.appendChild(form);
            form.submit();
        }

        function closeQuiz() {
            document.getElementById('quizModal').style.display = 'none';
        }
    </script>
</body>
</html>
