<?php
// Authentication middleware for different user types

function requireMCA() {
    global $db;
    
    if (!isset($_SESSION['user_id'])) {
        header('Location: ../login.php');
        exit;
    }
    
    // Check if user is an MCA
    $stmt = $db->prepare("
        SELECT ma.id, ma.status 
        FROM mca_agents ma 
        JOIN users u ON ma.user_id = u.id 
        WHERE u.id = ? AND ma.status = 'active'
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $mca = $stmt->fetch();
    
    if (!$mca) {
        header('Location: ../login.php?error=access_denied');
        exit;
    }
    
    $_SESSION['mca_id'] = $mca['id'];
    return $mca;
}

function requireAdvisor() {
    global $db;
    
    if (!isset($_SESSION['user_id'])) {
        header('Location: ../login.php');
        exit;
    }
    
    // Check if user is an advisor
    $stmt = $db->prepare("
        SELECT ca.id, ca.status, ca.training_completed 
        FROM certified_advisors ca 
        JOIN users u ON ca.user_id = u.id 
        WHERE u.id = ? AND ca.status IN ('active', 'training')
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $advisor = $stmt->fetch();
    
    if (!$advisor) {
        header('Location: ../login.php?error=access_denied');
        exit;
    }
    
    $_SESSION['advisor_id'] = $advisor['id'];
    $_SESSION['training_completed'] = $advisor['training_completed'];
    return $advisor;
}

function requireAdmin() {
    global $db;
    
    if (!isset($_SESSION['user_id'])) {
        header('Location: ../admin/login.php');
        exit;
    }
    
    // Check if user is admin
    $stmt = $db->prepare("
        SELECT r.name 
        FROM users u 
        JOIN roles r ON u.role_id = r.id 
        WHERE u.id = ? AND r.name = 'admin'
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $role = $stmt->fetchColumn();
    
    if (!$role) {
        header('Location: ../admin/login.php?error=access_denied');
        exit;
    }
}

function getMCADetails($user_id) {
    global $db;
    
    $stmt = $db->prepare("
        SELECT ma.*, u.first_name, u.last_name, u.email, u.phone,
               c.name as country_name, c.code as country_code
        FROM mca_agents ma
        JOIN users u ON ma.user_id = u.id
        LEFT JOIN countries c ON ma.country_id = c.id
        WHERE ma.user_id = ?
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getAdvisorDetails($user_id) {
    global $db;
    
    $stmt = $db->prepare("
        SELECT ca.*, u.first_name, u.last_name, u.email, u.phone,
               ma.agent_code as mca_code, 
               CONCAT(mu.first_name, ' ', mu.last_name) as mca_name
        FROM certified_advisors ca
        JOIN users u ON ca.user_id = u.id
        LEFT JOIN mca_agents ma ON ca.mca_id = ma.id
        LEFT JOIN users mu ON ma.user_id = mu.id
        WHERE ca.user_id = ?
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
