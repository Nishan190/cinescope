<?php
// ==============================================
// HELPER FUNCTIONS
// ==============================================

/**
 * Log user activity
 */
function logActivity($pdo, $userId, $action, $details) {
    try {
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $action, $details]);
        return true;
    } catch(Exception $e) {
        return false;
    }
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Get current user ID
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current username
 */
function getCurrentUsername() {
    return $_SESSION['username'] ?? null;
}

/**
 * Escape output for HTML
 */
function escape($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Generate JSON response
 */
function jsonResponse($success, $data = [], $message = '') {
    header('Content-Type: application/json');
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit;
}

/**
 * Sanitize input
 */
function sanitize($input) {
    return trim(htmlspecialchars(strip_tags($input)));
}
?>