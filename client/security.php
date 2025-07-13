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

// Get user security information
$stmt = $db->prepare("
    SELECT 
        u.*,
        (SELECT COUNT(*) FROM login_history WHERE user_id = u.id) as login_count,
        (SELECT COUNT(*) FROM active_sessions WHERE user_id = u.id) as active_sessions
    FROM users u
    WHERE u.id = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Get login history (last 10 logins)
$stmt = $db->prepare("
    SELECT * FROM login_history 
    WHERE user_id = ? 
    ORDER BY login_time DESC 
    LIMIT 10
");
$stmt->execute([$user_id]);
$login_history = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get active sessions
$stmt = $db->prepare("
    SELECT * FROM active_sessions 
    WHERE user_id = ? 
    ORDER BY last_activity DESC
");
$stmt->execute([$user_id]);
$active_sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle enabling/disabling 2FA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_2fa'])) {
    try {
        $enable_2fa = $_POST['enable_2fa'] === '1';

        $db->beginTransaction();

        // Update 2FA status
        $stmt = $db->prepare("UPDATE users SET two_factor_enabled = ? WHERE id = ?");
        $stmt->execute([$enable_2fa, $user_id]);

        if ($enable_2fa) {
            // Generate new 2FA secret if enabling
            require_once __DIR__ . '/../vendor/authenticator/authenticator.php';
            $secret = Authenticator::generateSecret();

            $stmt = $db->prepare("UPDATE users SET two_factor_secret = ? WHERE id = ?");
            $stmt->execute([$secret, $user_id]);

            $_SESSION['success_message'] = "Two-factor authentication enabled. Please scan the QR code below to set up your authenticator app.";
        } else {
            // Clear secret if disabling
            $stmt = $db->prepare("UPDATE users SET two_factor_secret = NULL WHERE id = ?");
            $stmt->execute([$user_id]);

            $_SESSION['success_message'] = "Two-factor authentication has been disabled.";
        }

        $db->commit();
        header('Location: security.php');
        exit;
    } catch (PDOException $e) {
        $db->rollBack();
        $error = "Error updating 2FA settings: " . $e->getMessage();
    }
}

// Handle session termination
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['terminate_session'])) {
    $session_id = $_POST['session_id'];

    try {
        $db->beginTransaction();

        // Delete the session
        $stmt = $db->prepare("DELETE FROM active_sessions WHERE id = ? AND user_id = ?");
        $stmt->execute([$session_id, $user_id]);

        // If terminating current session, log out
        if ($session_id === session_id()) {
            $db->commit();
            session_destroy();
            header('Location: ../login.php');
            exit;
        }

        $db->commit();
        $_SESSION['success_message'] = "Session terminated successfully.";
        header('Location: security.php');
        exit;
    } catch (PDOException $e) {
        $db->rollBack();
        $error = "Error terminating session: " . $e->getMessage();
    }
}

// Handle terminate all other sessions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['terminate_all_sessions'])) {
    try {
        $db->beginTransaction();

        // Delete all sessions except current one
        $stmt = $db->prepare("DELETE FROM active_sessions WHERE user_id = ? AND id != ?");
        $stmt->execute([$user_id, session_id()]);

        $db->commit();
        $_SESSION['success_message'] = "All other sessions have been terminated.";
        header('Location: security.php');
        exit;
    } catch (PDOException $e) {
        $db->rollBack();
        $error = "Error terminating sessions: " . $e->getMessage();
    }
}

// Set page title
$page_title = 'Account Security - Forever Young Tours';
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
                    <h1>Account Security</h1>
                    <div class="breadcrumb">
                        <a href="dashboard.php">Dashboard</a> &raquo; <span>Security</span>
                    </div>
                </div>

                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success">
                        <?php echo $_SESSION['success_message'];
                        unset($_SESSION['success_message']); ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <div class="security-container">
                    <!-- Two-Factor Authentication Section -->
                    <div class="security-section">
                        <div class="section-header">
                            <h2><i class="fas fa-shield-alt"></i> Two-Factor Authentication</h2>
                            <span
                                class="status-badge <?php echo $user['two_factor_enabled'] ? 'enabled' : 'disabled'; ?>">
                                <?php echo $user['two_factor_enabled'] ? 'Enabled' : 'Disabled'; ?>
                            </span>
                        </div>

                        <div class="section-content">
                            <p>Two-factor authentication adds an extra layer of security to your account by requiring
                                more than just a password to log in.</p>

                            <form method="POST" class="toggle-2fa-form">
                                <input type="hidden" name="enable_2fa"
                                    value="<?php echo $user['two_factor_enabled'] ? '0' : '1'; ?>">
                                <button type="submit" name="toggle_2fa"
                                    class="btn <?php echo $user['two_factor_enabled'] ? 'btn-outline-danger' : 'btn-primary'; ?>">
                                    <?php echo $user['two_factor_enabled'] ? 'Disable 2FA' : 'Enable 2FA'; ?>
                                </button>
                            </form>

                            <?php if ($user['two_factor_enabled'] && isset($_SESSION['success_message']) && strpos($_SESSION['success_message'], 'QR code') !== false): ?>
                                <div class="2fa-setup">
                                    <h4>Set Up Authenticator App</h4>
                                    <p>Scan this QR code with your authenticator app:</p>
                                    <?php
                                    $qrCodeUrl = Authenticator::getQRCodeUrl(
                                        'Forever Young Tours',
                                        $user['email'],
                                        $user['two_factor_secret']
                                    );
                                    ?>
                                    <img src="<?php echo $qrCodeUrl; ?>" alt="2FA QR Code" class="qr-code">
                                    <p>Or enter this code manually:
                                        <code><?php echo chunk_split($user['two_factor_secret'], 4, ' '); ?></code></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Active Sessions Section -->
                    <div class="security-section">
                        <div class="section-header">
                            <h2><i class="fas fa-laptop"></i> Active Sessions</h2>
                            <span class="badge"><?php echo count($active_sessions); ?> active</span>
                        </div>

                        <div class="section-content">
                            <p>These are devices that are currently logged in to your account. If you don't recognize a
                                session, terminate it immediately.</p>

                            <div class="sessions-list">
                                <?php foreach ($active_sessions as $session): ?>
                                    <div
                                        class="session-item <?php echo $session['id'] === session_id() ? 'current-session' : ''; ?>">
                                        <div class="session-info">
                                            <div class="session-icon">
                                                <i class="fas fa-<?php echo getDeviceIcon($session['user_agent']); ?>"></i>
                                            </div>
                                            <div class="session-details">
                                                <strong><?php echo getDeviceName($session['user_agent']); ?></strong>
                                                <div>
                                                    <?php echo $session['ip_address']; ?> &bull;
                                                    <?php echo date('M j, Y g:i A', strtotime($session['last_activity'])); ?>
                                                    <?php if ($session['id'] === session_id()): ?>
                                                        <span class="badge current-badge">Current Session</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="session-actions">
                                            <?php if ($session['id'] !== session_id()): ?>
                                                <form method="POST" class="terminate-form">
                                                    <input type="hidden" name="session_id"
                                                        value="<?php echo $session['id']; ?>">
                                                    <button type="submit" name="terminate_session"
                                                        class="btn btn-sm btn-outline-danger">
                                                        Terminate
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <?php if (count($active_sessions) > 1): ?>
                                <form method="POST" class="terminate-all-form">
                                    <button type="submit" name="terminate_all_sessions" class="btn btn-outline-danger">
                                        <i class="fas fa-sign-out-alt"></i> Terminate All Other Sessions
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Login History Section -->
                    <div class="security-section">
                        <div class="section-header">
                            <h2><i class="fas fa-history"></i> Login History</h2>
                            <span class="badge"><?php echo $user['login_count']; ?> total</span>
                        </div>

                        <div class="section-content">
                            <p>Recent login attempts to your account. Contact support if you see suspicious activity.
                            </p>

                            <div class="history-list">
                                <table class="history-table">
                                    <thead>
                                        <tr>
                                            <th>Date & Time</th>
                                            <th>Device</th>
                                            <th>IP Address</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($login_history as $login): ?>
                                            <tr>
                                                <td><?php echo date('M j, Y g:i A', strtotime($login['login_time'])); ?>
                                                </td>
                                                <td>
                                                    <i
                                                        class="fas fa-<?php echo getDeviceIcon($login['user_agent']); ?>"></i>
                                                    <?php echo getDeviceName($login['user_agent']); ?>
                                                </td>
                                                <td><?php echo $login['ip_address']; ?></td>
                                                <td>
                                                    <span
                                                        class="status-badge <?php echo $login['success'] ? 'success' : 'danger'; ?>">
                                                        <?php echo $login['success'] ? 'Success' : 'Failed'; ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <a href="login_history.php" class="btn btn-outline">
                                <i class="fas fa-list"></i> View Full History
                            </a>
                        </div>
                    </div>

                    <!-- Security Recommendations -->
                    <div class="security-section">
                        <div class="section-header">
                            <h2><i class="fas fa-lightbulb"></i> Security Recommendations</h2>
                        </div>

                        <div class="section-content">
                            <div class="recommendations">
                                <div
                                    class="recommendation <?php echo $user['two_factor_enabled'] ? 'completed' : ''; ?>">
                                    <i
                                        class="fas fa-<?php echo $user['two_factor_enabled'] ? 'check-circle' : 'shield-alt'; ?>"></i>
                                    <div>
                                        <h4>Enable Two-Factor Authentication</h4>
                                        <p>Add an extra layer of security to your account.</p>
                                    </div>
                                </div>

                                <div class="recommendation">
                                    <i class="fas fa-key"></i>
                                    <div>
                                        <h4>Change Password Regularly</h4>
                                        <p>Last changed:
                                            <?php echo $user['password_changed_at'] ? date('M j, Y', strtotime($user['password_changed_at'])) : 'Never'; ?>
                                        </p>
                                    </div>
                                </div>

                                <div
                                    class="recommendation <?php echo count($active_sessions) <= 1 ? 'completed' : ''; ?>">
                                    <i class="fas fa-laptop"></i>
                                    <div>
                                        <h4>Review Active Sessions</h4>
                                        <p><?php echo count($active_sessions); ?> devices currently logged in.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/client-portal.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.getElementById('pageTitle').textContent = 'Account Security';

            // Confirm before terminating sessions
            document.querySelectorAll('.terminate-form, .terminate-all-form').forEach(form => {
                form.addEventListener('submit', function (e) {
                    if (!confirm('Are you sure you want to terminate this session?')) {
                        e.preventDefault();
                    }
                });
            });
        });
    </script>
</body>

</html>

<?php
// Helper functions to get device info from user agent
function getDeviceIcon($user_agent)
{
    if (stripos($user_agent, 'mobile') !== false) {
        return 'mobile-alt';
    } elseif (stripos($user_agent, 'tablet') !== false) {
        return 'tablet-alt';
    } elseif (stripos($user_agent, 'windows') !== false || stripos($user_agent, 'macintosh') !== false) {
        return 'laptop';
    } else {
        return 'desktop';
    }
}

function getDeviceName($user_agent)
{
    $device = 'Unknown Device';

    // Check for mobile devices
    if (preg_match('/iPhone|iPad|iPod/i', $user_agent)) {
        $device = 'Apple Device';
    } elseif (preg_match('/Android/i', $user_agent)) {
        $device = 'Android Device';
    } elseif (preg_match('/Windows Phone/i', $user_agent)) {
        $device = 'Windows Phone';
    }
    // Check for desktop OS
    elseif (preg_match('/Windows NT 10.0/i', $user_agent)) {
        $device = 'Windows 10';
    } elseif (preg_match('/Windows NT 6.3/i', $user_agent)) {
        $device = 'Windows 8.1';
    } elseif (preg_match('/Windows NT 6.2/i', $user_agent)) {
        $device = 'Windows 8';
    } elseif (preg_match('/Macintosh|Mac OS X/i', $user_agent)) {
        $device = 'Mac OS X';
    } elseif (preg_match('/Linux/i', $user_agent)) {
        $device = 'Linux';
    }

    // Check for browsers
    if (preg_match('/Chrome/i', $user_agent)) {
        $device .= ' (Chrome)';
    } elseif (preg_match('/Firefox/i', $user_agent)) {
        $device .= ' (Firefox)';
    } elseif (preg_match('/Safari/i', $user_agent)) {
        $device .= ' (Safari)';
    } elseif (preg_match('/Edge/i', $user_agent)) {
        $device .= ' (Edge)';
    } elseif (preg_match('/Opera|OPR/i', $user_agent)) {
        $device .= ' (Opera)';
    } elseif (preg_match('/MSIE|Trident/i', $user_agent)) {
        $device .= ' (Internet Explorer)';
    }

    return $device;
}
?>