<?php

// Forever Young Tours - Secure Authentication System
// Handles user authentication, authorization, and security

class SecureAuth
{
    private $db;
    private $sessionLifetime;
    private $maxLoginAttempts;
    private $lockoutDuration;

    public function __construct($database)
    {
        $this->db = $database;
        $this->sessionLifetime = SESSION_LIFETIME;
        $this->maxLoginAttempts = MAX_LOGIN_ATTEMPTS;
        $this->lockoutDuration = LOCKOUT_DURATION;

        // Configure session security
        $this->configureSession();
    }

    private function configureSession()
    {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.cookie_httponly', 1);
            ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
            ini_set('session.use_strict_mode', 1);
            ini_set('session.cookie_samesite', 'Strict');

            ini_set('session.gc_maxlifetime', $this->sessionLifetime);
            session_set_cookie_params([
                'lifetime' => $this->sessionLifetime,
                'path' => '/',
                'secure' => isset($_SERVER['HTTPS']),
                'httponly' => true,
                'samesite' => 'Strict'
            ]);

            session_start();
        }
    }

    public function login($username, $password, $rememberMe = false)
    {
        try {
            if ($this->isAccountLocked($username)) {
                return [
                    'success' => false,
                    'message' => 'Account is temporarily locked due to too many failed login attempts.'
                ];
            }

            $stmt = $this->db->prepare("
                SELECT u.*, r.name as role_name, r.display_name as role_display 
                FROM users u 
                JOIN roles r ON u.role_id = r.id 
                WHERE (u.username = ? OR u.email = ?) AND u.status = 'active'
            ");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($password, $user['password_hash'])) {
                $this->recordFailedLogin($username);
                return ['success' => false, 'message' => 'Invalid username or password.'];
            }

            $this->resetLoginAttempts($user['id']);
            $this->createSession($user, $rememberMe);
            $this->updateLastLogin($user['id']);
            $this->logActivity($user['id'], 'login', 'User logged in successfully');

            return ['success' => true, 'message' => 'Login successful', 'user' => $user];

        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Login error.'];
        }
    }

    public function logout()
    {
        try {
            if (isset($_SESSION['user_id'])) {
                $this->logActivity($_SESSION['user_id'], 'logout', 'User logged out');

                if (isset($_SESSION['session_id'])) {
                    $stmt = $this->db->prepare("DELETE FROM user_sessions WHERE id = ?");
                    $stmt->execute([$_SESSION['session_id']]);
                }
            }

            $_SESSION = [];
            if (isset($_COOKIE[session_name()])) {
                setcookie(session_name(), '', time() - 3600, '/');
            }

            session_destroy();
            return true;

        } catch (Exception $e) {
            error_log("Logout error: " . $e->getMessage());
            return false;
        }
    }

    public function validateSession()
    {
        try {
            if (!isset($_SESSION['user_id'], $_SESSION['session_id'])) {
                return false;
            }

            $stmt = $this->db->prepare("
                SELECT s.*, u.status 
                FROM user_sessions s 
                JOIN users u ON s.user_id = u.id 
                WHERE s.id = ? AND s.user_id = ? AND s.expires_at > NOW()
            ");
            $stmt->execute([$_SESSION['session_id'], $_SESSION['user_id']]);
            $session = $stmt->fetch();

            if (!$session || $session['status'] !== 'active') {
                $this->logout();
                return false;
            }

            $stmt = $this->db->prepare("
                UPDATE user_sessions 
                SET last_activity = NOW(), expires_at = DATE_ADD(NOW(), INTERVAL ? SECOND)
                WHERE id = ?
            ");
            $stmt->execute([$this->sessionLifetime, $_SESSION['session_id']]);

            return true;

        } catch (Exception $e) {
            error_log("Session validation error: " . $e->getMessage());
            return false;
        }
    }

    public function hasPermission($permission)
    {
        if (!isset($_SESSION['permissions'])) {
            return false;
        }
        return in_array($permission, $_SESSION['permissions']);
    }

    public function getUserPermissions($userId)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT p.name, p.display_name, p.category 
                FROM permissions p 
                JOIN role_permissions rp ON p.id = rp.permission_id 
                JOIN users u ON rp.role_id = u.role_id 
                WHERE u.id = ?
            ");
            $stmt->execute([$userId]);

            return $stmt->fetchAll();

        } catch (Exception $e) {
            error_log("Get user permissions error: " . $e->getMessage());
            return [];
        }
    }

    public function logActivity($userId, $action, $description = '', $metadata = null)
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO user_activity_logs (user_id, action, description, ip_address, user_agent, metadata) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
            $meta = $metadata ? json_encode($metadata) : null;

            $stmt->execute([$userId, $action, $description, $ip, $ua, $meta]);
            return true;

        } catch (Exception $e) {
            error_log("Log activity error: " . $e->getMessage());
            return false;
        }
    }

    private function createSession($user, $rememberMe = false)
    {
        $sessionId = bin2hex(random_bytes(64));
        $expiry = $rememberMe ? time() + (30 * 86400) : time() + $this->sessionLifetime;

        $stmt = $this->db->prepare("
            INSERT INTO user_sessions (id, user_id, ip_address, user_agent, expires_at) 
            VALUES (?, ?, ?, ?, FROM_UNIXTIME(?))
        ");
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $stmt->execute([$sessionId, $user['id'], $ip, $ua, $expiry]);

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['session_id'] = $sessionId;
        $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role_id'] = $user['role_id'];
        $_SESSION['role_name'] = $user['role_name'];
        $_SESSION['role_display'] = $user['role_display'];
        $_SESSION['permissions'] = array_column($this->getUserPermissions($user['id']), 'name');


        if ($rememberMe) {
            setcookie('remember_token', $sessionId, $expiry, '/', '', isset($_SERVER['HTTPS']), true);
        }
    }

    private function isAccountLocked($username)
    {
        try {
            $stmt = $this->db->prepare("SELECT locked_until FROM users WHERE (username = ? OR email = ?) AND locked_until > NOW()");
            $stmt->execute([$username, $username]);
            return $stmt->fetchColumn() !== false;
        } catch (Exception $e) {
            error_log("Account lock check error: " . $e->getMessage());
            return false;
        }
    }

    private function recordFailedLogin($username)
    {
        try {
            $stmt = $this->db->prepare("SELECT id, login_attempts FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch();

            if ($user) {
                $attempts = $user['login_attempts'] + 1;
                $lockUntil = ($attempts >= $this->maxLoginAttempts)
                    ? date('Y-m-d H:i:s', time() + $this->lockoutDuration)
                    : null;

                $stmt = $this->db->prepare("UPDATE users SET login_attempts = ?, locked_until = ? WHERE id = ?");
                $stmt->execute([$attempts, $lockUntil, $user['id']]);

                $this->logActivity($user['id'], 'failed_login', 'Failed login attempt');
            }

        } catch (Exception $e) {
            error_log("Record failed login error: " . $e->getMessage());
        }
    }

    private function resetLoginAttempts($userId)
    {
        try {
            $stmt = $this->db->prepare("UPDATE users SET login_attempts = 0, locked_until = NULL WHERE id = ?");
            $stmt->execute([$userId]);
        } catch (Exception $e) {
            error_log("Reset login attempts error: " . $e->getMessage());
        }
    }

    private function updateLastLogin($userId)
    {
        try {
            $stmt = $this->db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$userId]);
        } catch (Exception $e) {
            error_log("Update last login error: " . $e->getMessage());
        }
    }

    public function cleanupExpiredSessions()
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM user_sessions WHERE expires_at < NOW()");
            $stmt->execute();
            return $stmt->rowCount();
        } catch (Exception $e) {
            error_log("Cleanup error: " . $e->getMessage());
            return 0;
        }
    }
}

// === Helper Functions ===

function requireLogin()
{
    global $auth;
    if (!$auth->validateSession()) {
        header('Location: login.php');
        exit;
    }
}

function requirePermission($permission)
{
    global $auth;
    if (!$auth->hasPermission($permission)) {
        http_response_code(403);
        die('Access denied.');
    }
}

function hasPermission($permission)
{
    global $auth;
    return $auth->hasPermission($permission);
}

function isLoggedIn()
{
    global $auth;
    return $auth->validateSession();
}

function getCurrentUser()
{
    global $db;
    if (!isset($_SESSION['user_id']))
        return null;

    $stmt = $db->prepare("SELECT id, first_name, last_name, email, role_id FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $roleStmt = $db->prepare("SELECT display_name FROM roles WHERE id = ?");
        $roleStmt->execute([$user['role_id']]);
        $user['role_display'] = $roleStmt->fetchColumn() ?: 'User';
    }

    return $user;
}

// ✅ Initialize global auth instance
$auth = new SecureAuth($db);
if (rand(1, 100) === 1) {
    $auth->cleanupExpiredSessions();
}
function hasNavPermission($permission)
{
    return in_array($permission, $_SESSION['permissions'] ?? []);
}



?>
