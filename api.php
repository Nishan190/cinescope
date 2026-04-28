<?php
// ==============================================
// BACKEND API ENDPOINTS
// ==============================================

require_once 'includes/config.php';
require_once 'includes/functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? null;
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

// ==================== AUTHENTICATION ====================

if ($action === 'register') {
    $username = sanitize($input['username'] ?? '');
    $password = $input['password'] ?? '';
    
    if (strlen($username) < 3) {
        jsonResponse(false, [], 'Username must be at least 3 characters');
    }
    if (strlen($password) < 4) {
        jsonResponse(false, [], 'Password must be at least 4 characters');
    }
    
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    try {
        $stmt = $pdo->prepare("INSERT INTO users (username, password, preferences) VALUES (?, ?, ?)");
        $stmt->execute([$username, $hashedPassword, json_encode(['theme' => 'light'])]);
        jsonResponse(true, [], 'Registration successful');
    } catch(PDOException $e) {
        if ($e->errorInfo[1] == 1062) {
            jsonResponse(false, [], 'Username already exists');
        } else {
            jsonResponse(false, [], 'Registration failed');
        }
    }
}
elseif ($action === 'login') {
    $username = sanitize($input['username'] ?? '');
    $password = $input['password'] ?? '';
    
    $stmt = $pdo->prepare("SELECT id, username, password, preferences FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        
        $preferences = json_decode($user['preferences'] ?? '{}', true);
        logActivity($pdo, $user['id'], 'LOGIN', 'User logged in');
        
        jsonResponse(true, [
            'user_id' => $user['id'],
            'username' => $user['username'],
            'preferences' => $preferences
        ], 'Login successful');
    } else {
        jsonResponse(false, [], 'Invalid username or password');
    }
}
elseif ($action === 'logout') {
    if (isset($_SESSION['user_id'])) {
        logActivity($pdo, $_SESSION['user_id'], 'LOGOUT', 'User logged out');
    }
    session_destroy();
    jsonResponse(true, [], 'Logged out');
}
elseif ($action === 'checkSession') {
    if (isset($_SESSION['user_id'])) {
        $stmt = $pdo->prepare("SELECT id, username, preferences FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        if ($user) {
            jsonResponse(true, [
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'preferences' => json_decode($user['preferences'] ?? '{}', true)
                ]
            ]);
        } else {
            session_destroy();
            jsonResponse(false, [], 'Session expired');
        }
    } else {
        jsonResponse(false, [], 'Not logged in');
    }
}
elseif ($action === 'changePassword') {
    if (!isset($_SESSION['user_id'])) {
        jsonResponse(false, [], 'Not authenticated');
    }
    
    $oldPassword = $input['old_password'] ?? '';
    $newPassword = $input['new_password'] ?? '';
    
    if (strlen($newPassword) < 4) {
        jsonResponse(false, [], 'Password must be at least 4 characters');
    }
    
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($oldPassword, $user['password'])) {
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$newHash, $_SESSION['user_id']]);
        
        logActivity($pdo, $_SESSION['user_id'], 'CHANGE_PASSWORD', 'Password changed');
        jsonResponse(true, [], 'Password updated');
    } else {
        jsonResponse(false, [], 'Current password is incorrect');
    }
}
elseif ($action === 'setPreference') {
    if (!isset($_SESSION['user_id'])) {
        jsonResponse(false, [], 'Not authenticated');
    }
    
    $theme = $input['theme'] ?? 'light';
    $preferences = json_encode(['theme' => $theme]);
    
    $stmt = $pdo->prepare("UPDATE users SET preferences = ? WHERE id = ?");
    $stmt->execute([$preferences, $_SESSION['user_id']]);
    
    logActivity($pdo, $_SESSION['user_id'], 'PREFERENCE', "Theme changed to {$theme}");
    jsonResponse(true, [], 'Preferences saved');
}

// ==================== MOVIE CRUD ====================

elseif ($action === 'getMovies') {
    $stmt = $pdo->query("SELECT * FROM movies ORDER BY id DESC");
    jsonResponse(true, ['movies' => $stmt->fetchAll()]);
}
elseif ($action === 'createMovie') {
    if (!isset($_SESSION['user_id'])) {
        jsonResponse(false, [], 'Please login first');
    }
    
    $title = sanitize($input['title'] ?? '');
    $genre = sanitize($input['genre'] ?? '');
    $rating = floatval($input['rating'] ?? 0);
    $reviewer = sanitize($input['reviewer'] ?? '');
    $reviewText = sanitize($input['review_text'] ?? '');
    
    if (empty($title) || empty($genre) || $rating < 0 || $rating > 10 || empty($reviewer) || empty($reviewText)) {
        jsonResponse(false, [], 'All fields required, rating 0-10');
    }
    
    $stmt = $pdo->prepare("INSERT INTO movies (title, genre, rating, reviewer, review_text) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$title, $genre, $rating, $reviewer, $reviewText]);
    
    logActivity($pdo, $_SESSION['user_id'], 'CREATE_MOVIE', "Added movie: {$title}");
    jsonResponse(true, [], 'Movie review added');
}
elseif ($action === 'updateMovie') {
    if (!isset($_SESSION['user_id'])) {
        jsonResponse(false, [], 'Please login first');
    }
    
    $id = intval($input['id'] ?? 0);
    $title = sanitize($input['title'] ?? '');
    $genre = sanitize($input['genre'] ?? '');
    $rating = floatval($input['rating'] ?? 0);
    $reviewer = sanitize($input['reviewer'] ?? '');
    $reviewText = sanitize($input['review_text'] ?? '');
    
    if ($id <= 0 || empty($title) || empty($genre) || $rating < 0 || $rating > 10) {
        jsonResponse(false, [], 'Invalid data');
    }
    
    $stmt = $pdo->prepare("UPDATE movies SET title = ?, genre = ?, rating = ?, reviewer = ?, review_text = ? WHERE id = ?");
    $stmt->execute([$title, $genre, $rating, $reviewer, $reviewText, $id]);
    
    logActivity($pdo, $_SESSION['user_id'], 'UPDATE_MOVIE', "Updated movie ID {$id}");
    jsonResponse(true, [], 'Movie review updated');
}
elseif ($action === 'deleteMovie') {
    if (!isset($_SESSION['user_id'])) {
        jsonResponse(false, [], 'Please login first');
    }
    
    $id = intval($input['id'] ?? 0);
    
    $stmt = $pdo->prepare("SELECT title FROM movies WHERE id = ?");
    $stmt->execute([$id]);
    $movie = $stmt->fetch();
    $title = $movie ? $movie['title'] : "ID {$id}";
    
    $stmt = $pdo->prepare("DELETE FROM movies WHERE id = ?");
    $stmt->execute([$id]);
    
    logActivity($pdo, $_SESSION['user_id'], 'DELETE_MOVIE', "Deleted movie: {$title}");
    jsonResponse(true, [], 'Movie review deleted');
}
elseif ($action === 'getActivityLog') {
    if (!isset($_SESSION['user_id'])) {
        jsonResponse(false, [], 'Not authenticated');
    }
    
    $stmt = $pdo->query("
        SELECT l.*, u.username 
        FROM activity_logs l 
        JOIN users u ON l.user_id = u.id 
        ORDER BY l.created_at DESC 
        LIMIT 50
    ");
    jsonResponse(true, ['logs' => $stmt->fetchAll()]);
}
else {
    jsonResponse(false, [], 'Invalid action');
}
?>