<?php
session_start();

$host = 'localhost';
$dbname = 'movie_review_db';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $dbConnected = true;
} catch(PDOException $e) {
    $dbConnected = false;
}

// Handle API requests
if (isset($_GET['api'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'] ?? '';
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    
    if (!$dbConnected) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit;
    }
    
    // REGISTER
    if ($action === 'register') {
        $username = trim($input['username'] ?? '');
        $password = $input['password'] ?? '';
        if (strlen($username) < 3) {
            echo json_encode(['success' => false, 'message' => 'Username must be at least 3 characters']);
            exit;
        }
        if (strlen($password) < 4) {
            echo json_encode(['success' => false, 'message' => 'Password must be at least 4 characters']);
            exit;
        }
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        try {
            $stmt = $pdo->prepare("INSERT INTO users (username, password, preferences) VALUES (?, ?, ?)");
            $stmt->execute([$username, $hashed, json_encode(['theme' => 'light'])]);
            echo json_encode(['success' => true, 'message' => 'Registration successful']);
        } catch(PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Username already exists']);
        }
        exit;
    }
    
    // LOGIN
    if ($action === 'login') {
        $username = trim($input['username'] ?? '');
        $password = $input['password'] ?? '';
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            echo json_encode([
                'success' => true, 
                'user_id' => $user['id'], 
                'username' => $user['username'], 
                'preferences' => json_decode($user['preferences'] ?? '{}', true)
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid username or password']);
        }
        exit;
    }
    
    // LOGOUT
    if ($action === 'logout') {
        session_destroy();
        echo json_encode(['success' => true]);
        exit;
    }
    
    // CHECK SESSION
    if ($action === 'checkSession') {
        if (isset($_SESSION['user_id'])) {
            $stmt = $pdo->prepare("SELECT id, username, preferences FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                echo json_encode(['success' => true, 'user' => [
                    'id' => $user['id'], 
                    'username' => $user['username'], 
                    'preferences' => json_decode($user['preferences'] ?? '{}', true)
                ]]);
            } else {
                echo json_encode(['success' => false]);
            }
        } else {
            echo json_encode(['success' => false]);
        }
        exit;
    }
    
    // CHANGE PASSWORD
    if ($action === 'changePassword') {
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Not authenticated']);
            exit;
        }
        $old = $input['old_password'] ?? '';
        $new = $input['new_password'] ?? '';
        if (strlen($new) < 4) {
            echo json_encode(['success' => false, 'message' => 'Password too short']);
            exit;
        }
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && password_verify($old, $user['password'])) {
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([password_hash($new, PASSWORD_DEFAULT), $_SESSION['user_id']]);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Current password incorrect']);
        }
        exit;
    }
    
    // SET PREFERENCE
    if ($action === 'setPreference') {
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false]);
            exit;
        }
        $theme = $input['theme'] ?? 'light';
        $stmt = $pdo->prepare("UPDATE users SET preferences = ? WHERE id = ?");
        $stmt->execute([json_encode(['theme' => $theme]), $_SESSION['user_id']]);
        echo json_encode(['success' => true]);
        exit;
    }
    
    // GET MOVIES
    if ($action === 'getMovies') {
        $stmt = $pdo->query("SELECT * FROM movies ORDER BY id DESC");
        echo json_encode(['success' => true, 'movies' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }
    
    // CREATE MOVIE
    if ($action === 'createMovie') {
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Login required']);
            exit;
        }
        $title = trim($input['title'] ?? '');
        $genre = trim($input['genre'] ?? '');
        $rating = floatval($input['rating'] ?? 0);
        $reviewer = trim($input['reviewer'] ?? '');
        $review = trim($input['review_text'] ?? '');
        if (empty($title) || empty($genre) || $rating < 0 || $rating > 10) {
            echo json_encode(['success' => false, 'message' => 'Invalid data']);
            exit;
        }
        $stmt = $pdo->prepare("INSERT INTO movies (title, genre, rating, reviewer, review_text) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$title, $genre, $rating, $reviewer, $review]);
        echo json_encode(['success' => true]);
        exit;
    }
    
    // UPDATE MOVIE
    if ($action === 'updateMovie') {
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false]);
            exit;
        }
        $id = intval($input['id'] ?? 0);
        $title = trim($input['title'] ?? '');
        $genre = trim($input['genre'] ?? '');
        $rating = floatval($input['rating'] ?? 0);
        $reviewer = trim($input['reviewer'] ?? '');
        $review = trim($input['review_text'] ?? '');
        $stmt = $pdo->prepare("UPDATE movies SET title=?, genre=?, rating=?, reviewer=?, review_text=? WHERE id=?");
        $stmt->execute([$title, $genre, $rating, $reviewer, $review, $id]);
        echo json_encode(['success' => true]);
        exit;
    }
    
    // DELETE MOVIE
    if ($action === 'deleteMovie') {
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false]);
            exit;
        }
        $id = intval($input['id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM movies WHERE id=?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
        exit;
    }
    
    // GET ACTIVITY LOG
    if ($action === 'getActivityLog') {
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false]);
            exit;
        }
        $stmt = $pdo->query("SELECT l.*, u.username FROM activity_logs l JOIN users u ON l.user_id = u.id ORDER BY l.created_at DESC LIMIT 50");
        echo json_encode(['success' => true, 'logs' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }
    
    echo json_encode(['success' => false, 'message' => 'Unknown action']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CineScope - Movie Reviews</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }
        
        body {
            background: #f0f4f8;
            transition: all 0.3s;
        }
        
        body.dark-mode {
            background: #0f172a;
            color: #e2e8f0;
        }
        
        body.dark-mode .card {
            background: #1e293b;
            border: 1px solid #334155;
        }
        
        body.dark-mode input, body.dark-mode textarea, body.dark-mode select {
            background: #334155;
            border-color: #475569;
            color: white;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 2rem;
        }
        
        .navbar {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: white;
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }
        
        .nav-flex {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .logo h2 {
            font-weight: 800;
            font-size: 1.8rem;
        }
        
        .logo span {
            color: #facc15;
        }
        
        .nav-links {
            display: flex;
            gap: 1.5rem;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .nav-links a, .nav-links button {
            color: #f1f5f9;
            text-decoration: none;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 0.95rem;
            transition: 0.2s;
        }
        
        .nav-links a:hover, .nav-links button:hover {
            color: #facc15;
        }
        
        .btn-logout {
            background: rgba(250,204,21,0.15);
            padding: 0.5rem 1.2rem;
            border-radius: 40px;
        }
        
        .card {
            background: white;
            border-radius: 24px;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .form-grid {
            display: flex;
            flex-direction: column;
            gap: 1.2rem;
        }
        
        input, select, textarea {
            padding: 0.9rem 1.2rem;
            border-radius: 16px;
            border: 2px solid #e2e8f0;
            font-size: 0.95rem;
            width: 100%;
        }
        
        input:focus, textarea:focus, select:focus {
            outline: none;
            border-color: #facc15;
        }
        
        button {
            background: #0f172a;
            color: white;
            border: none;
            padding: 0.8rem 1.8rem;
            border-radius: 40px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
        }
        
        button:hover {
            background: #facc15;
            color: #0f172a;
            transform: translateY(-2px);
        }
        
        .movie-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 1.8rem;
            margin-top: 1.5rem;
        }
        
        .movie-card {
            background: white;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 15px 35px -12px rgba(0,0,0,0.1);
            transition: 0.25s;
        }
        
        body.dark-mode .movie-card {
            background: #1e293b;
        }
        
        .movie-card:hover {
            transform: translateY(-5px);
        }
        
        .movie-img {
            background: linear-gradient(135deg, #eef2ff, #e0e7ff);
            height: 160px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
        }
        
        .movie-info {
            padding: 1.5rem;
        }
        
        .movie-title {
            font-size: 1.25rem;
            font-weight: 700;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        
        .rating {
            background: #facc15;
            padding: 0.2rem 0.7rem;
            border-radius: 40px;
            font-weight: bold;
            color: #0f172a;
            font-size: 0.85rem;
        }
        
        .genre {
            color: #64748b;
            margin: 0.5rem 0;
            font-size: 0.85rem;
        }
        
        .review-text {
            margin: 0.8rem 0;
            line-height: 1.5;
            color: #334155;
        }
        
        body.dark-mode .review-text {
            color: #cbd5e1;
        }
        
        .actions {
            display: flex;
            gap: 0.7rem;
            margin-top: 1rem;
        }
        
        .actions button {
            flex: 1;
            padding: 0.5rem;
            font-size: 0.85rem;
        }
        
        .search-bar {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }
        
        .search-bar input {
            flex: 1;
        }
        
        .toast {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: #0f172a;
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 50px;
            z-index: 1100;
            animation: slideIn 0.3s ease;
        }
        
        .toast.success { background: #10b981; }
        .toast.error { background: #ef4444; }
        
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            backdrop-filter: blur(4px);
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            max-width: 550px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .tab-btn {
            background: transparent !important;
            color: #0f172a !important;
        }
        
        body.dark-mode .tab-btn {
            color: #e2e8f0 !important;
        }
        
        .tab-btn.active {
            background: #facc15 !important;
            color: #0f172a !important;
        }
        
        .loading {
            display: inline-block;
            width: 40px;
            height: 40px;
            border: 3px solid #e2e8f0;
            border-radius: 50%;
            border-top-color: #facc15;
            animation: spin 0.8s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .footer {
            text-align: center;
            padding: 2rem;
            background: #0f172a;
            color: #94a3b8;
            margin-top: 2rem;
        }
        
        @media (max-width: 768px) {
            .container { padding: 0 1rem; }
            .nav-flex { flex-direction: column; }
            .movie-grid { grid-template-columns: 1fr; }
        }
        
        .activity-item {
            border-bottom: 1px solid #e2e8f0;
            padding: 0.7rem 0;
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
        }
        
        .demo-box {
            margin-top: 1rem;
            padding: 0.8rem;
            background: #f1f5f9;
            border-radius: 12px;
            text-align: center;
            font-size: 0.85rem;
        }
        
        body.dark-mode .demo-box {
            background: #334155;
        }
    </style>
</head>
<body>

<div class="navbar">
    <div class="container nav-flex">
        <div class="logo">
            <h2>Cine<span>Scope</span></h2>
        </div>
        <div class="nav-links" id="navLinks"></div>
    </div>
</div>

<main class="container" id="appRoot">
    <div style="display:flex; justify-content:center; align-items:center; min-height: 60vh;">
        <div class="loading"></div>
    </div>
</main>

<div class="footer">
    <p>&copy; 2025 CineScope — Professional Movie Reviews Platform</p>
</div>

<script>
    let currentUser = null;
    let moviesList = [];
    let activityLog = [];
    
    async function apiCall(action, method = 'GET', body = null) {
        const options = { method, headers: { 'Content-Type': 'application/json' } };
        if (body && method !== 'GET') options.body = JSON.stringify(body);
        const res = await fetch(`?api=1&action=${action}`, options);
        return await res.json();
    }
    
    function showToast(message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i> ${message}`;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    }
    
    async function renderApp() {
        const app = document.getElementById('appRoot');
        if (!currentUser) {
            app.innerHTML = getLoginHTML();
            attachLoginEvents();
        } else {
            await loadMovies();
            await loadActivityLog();
            app.innerHTML = getDashboardHTML();
            attachDashboardEvents();
            renderMovieGrid('');
            renderActivityLog();
        }
        updateNavbar();
    }
    
    function updateNavbar() {
        const navDiv = document.getElementById('navLinks');
        if (!navDiv) return;
        if (!currentUser) {
            navDiv.innerHTML = `<a href="#" id="navLoginBtn"><i class="fas fa-sign-in-alt"></i> Login</a><a href="#" id="navRegisterBtn"><i class="fas fa-user-plus"></i> Register</a>`;
            document.getElementById('navLoginBtn')?.addEventListener('click', (e) => { e.preventDefault(); renderApp(); });
            document.getElementById('navRegisterBtn')?.addEventListener('click', (e) => { e.preventDefault(); renderApp(); });
        } else {
            navDiv.innerHTML = `
                <span><i class="fas fa-user-circle"></i> ${escapeHtml(currentUser.username)}</span>
                <a href="#" id="prefBtn"><i class="fas fa-sliders-h"></i> Theme</a>
                <a href="#" id="changePwdBtn"><i class="fas fa-key"></i> Change Password</a>
                <button class="btn-logout" id="logoutBtn"><i class="fas fa-sign-out-alt"></i> Logout</button>
            `;
            document.getElementById('logoutBtn')?.addEventListener('click', logout);
            document.getElementById('changePwdBtn')?.addEventListener('click', showChangePasswordModal);
            document.getElementById('prefBtn')?.addEventListener('click', showPreferencesModal);
        }
    }
    
    async function logout() {
        await apiCall('logout', 'POST');
        currentUser = null;
        showToast('Logged out');
        renderApp();
    }
    
    async function loadMovies() {
        const res = await apiCall('getMovies');
        if (res.success) moviesList = res.movies || [];
    }
    
    async function loadActivityLog() {
        const res = await apiCall('getActivityLog');
        if (res.success) activityLog = res.logs || [];
    }
    
    function showChangePasswordModal() {
        const modalHtml = `
            <div id="modalOverlay" class="modal-overlay">
                <div class="card modal-content">
                    <h3>Change Password</h3>
                    <div class="form-grid">
                        <input type="password" id="oldPass" placeholder="Current Password">
                        <input type="password" id="newPass" placeholder="New Password (min 4 chars)">
                        <input type="password" id="confirmPass" placeholder="Confirm Password">
                        <div style="display:flex; gap:1rem; justify-content:flex-end;">
                            <button id="cancelModalBtn">Cancel</button>
                            <button id="submitChangeBtn">Update</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        document.getElementById('submitChangeBtn').onclick = async () => {
            const oldPwd = document.getElementById('oldPass').value;
            const newPwd = document.getElementById('newPass').value;
            const confirm = document.getElementById('confirmPass').value;
            if (newPwd !== confirm) { showToast("Passwords don't match", 'error'); return; }
            const res = await apiCall('changePassword', 'POST', { old_password: oldPwd, new_password: newPwd });
            if (res.success) { showToast('Password updated'); closeModal(); }
            else showToast(res.message || 'Failed', 'error');
        };
        document.getElementById('cancelModalBtn').onclick = closeModal;
    }
    
    function showPreferencesModal() {
        const currentTheme = currentUser?.preferences?.theme || 'light';
        const modalHtml = `
            <div id="modalOverlay" class="modal-overlay">
                <div class="card modal-content">
                    <h3>Display Preferences</h3>
                    <div class="form-grid">
                        <select id="prefThemeSelect">
                            <option value="light" ${currentTheme === 'light' ? 'selected' : ''}>☀️ Light Mode</option>
                            <option value="dark" ${currentTheme === 'dark' ? 'selected' : ''}>🌙 Dark Mode</option>
                        </select>
                        <div style="display:flex; gap:1rem; justify-content:flex-end;">
                            <button id="cancelModalBtn">Cancel</button>
                            <button id="savePrefBtn">Save</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        document.getElementById('savePrefBtn').onclick = async () => {
            const theme = document.getElementById('prefThemeSelect').value;
            const res = await apiCall('setPreference', 'POST', { theme });
            if (res.success) {
                currentUser.preferences = { theme };
                applyTheme(theme);
                showToast('Theme saved');
                closeModal();
            }
        };
        document.getElementById('cancelModalBtn').onclick = closeModal;
    }
    
    function applyTheme(theme) {
        if (theme === 'dark') document.body.classList.add('dark-mode');
        else document.body.classList.remove('dark-mode');
    }
    
    function closeModal() {
        document.getElementById('modalOverlay')?.remove();
    }
    
    function getDashboardHTML() {
        return `
            <div class="card">
                <h2><i class="fas fa-film"></i> Movie Reviews</h2>
                <div class="search-bar">
                    <input type="text" id="searchInput" placeholder="🔍 Search by title, genre, or reviewer...">
                    <button id="clearSearchBtn">Clear</button>
                    <button id="newMovieBtn" style="background:#facc15; color:#0f172a;">+ Add Review</button>
                </div>
            </div>
            <div id="moviesGridContainer" class="movie-grid"></div>
            <div class="card">
                <h3><i class="fas fa-history"></i> Recent Activity</h3>
                <div id="activityLogList"></div>
            </div>
        `;
    }
    
    function renderMovieGrid(searchTerm = '') {
        const container = document.getElementById('moviesGridContainer');
        if (!container) return;
        let filtered = [...moviesList];
        if (searchTerm.trim()) {
            const term = searchTerm.toLowerCase();
            filtered = moviesList.filter(m => m.title.toLowerCase().includes(term) || m.genre.toLowerCase().includes(term));
        }
        if (filtered.length === 0) {
            container.innerHTML = `<div class="card" style="text-align:center;">No movies found. Add your first review!</div>`;
            return;
        }
        container.innerHTML = filtered.map(movie => `
            <div class="movie-card">
                <div class="movie-img"><i class="fas fa-film"></i></div>
                <div class="movie-info">
                    <div class="movie-title">
                        ${escapeHtml(movie.title)}
                        <span class="rating">⭐ ${movie.rating}</span>
                    </div>
                    <div class="genre">${escapeHtml(movie.genre)} | by ${escapeHtml(movie.reviewer)}</div>
                    <div class="review-text">${escapeHtml(movie.review_text.substring(0, 100))}...</div>
                    <div class="actions">
                        <button class="editMovieBtn" data-id="${movie.id}" style="background:#3b82f6;">Edit</button>
                        <button class="deleteMovieBtn" data-id="${movie.id}" style="background:#dc2626;">Delete</button>
                    </div>
                </div>
            </div>
        `).join('');
        
        document.querySelectorAll('.editMovieBtn').forEach(btn => {
            btn.onclick = () => {
                const movie = moviesList.find(m => m.id == btn.dataset.id);
                if (movie) showMovieForm(movie);
            };
        });
        document.querySelectorAll('.deleteMovieBtn').forEach(btn => {
            btn.onclick = async () => {
                if (confirm('Delete this review?')) {
                    const res = await apiCall('deleteMovie', 'POST', { id: btn.dataset.id });
                    if (res.success) {
                        await loadMovies();
                        renderMovieGrid(document.getElementById('searchInput')?.value || '');
                        showToast('Review deleted');
                    }
                }
            };
        });
    }
    
    function renderActivityLog() {
        const container = document.getElementById('activityLogList');
        if (!container) return;
        if (!activityLog.length) {
            container.innerHTML = '<div style="text-align:center; color:#94a3b8;">No activity yet</div>';
            return;
        }
        container.innerHTML = activityLog.slice(0, 10).map(log => `
            <div class="activity-item">
                <span>${escapeHtml(log.action)} by ${escapeHtml(log.username)}</span>
                <span style="font-size:0.7rem;">${log.created_at}</span>
            </div>
        `).join('');
    }
    
    function attachDashboardEvents() {
        document.getElementById('newMovieBtn')?.addEventListener('click', () => showMovieForm(null));
        document.getElementById('clearSearchBtn')?.addEventListener('click', () => {
            document.getElementById('searchInput').value = '';
            renderMovieGrid('');
        });
        document.getElementById('searchInput')?.addEventListener('input', (e) => renderMovieGrid(e.target.value));
    }
    
    async function showMovieForm(movie = null) {
        const isEdit = movie !== null;
        const modalHtml = `
            <div id="modalOverlay" class="modal-overlay">
                <div class="card modal-content">
                    <h3>${isEdit ? 'Edit Review' : 'Add Review'}</h3>
                    <div class="form-grid">
                        <input type="text" id="movieTitle" placeholder="Movie Title" value="${isEdit ? escapeHtml(movie.title) : ''}">
                        <input type="text" id="movieGenre" placeholder="Genre" value="${isEdit ? escapeHtml(movie.genre) : ''}">
                        <input type="number" id="movieRating" step="0.1" min="0" max="10" placeholder="Rating (0-10)" value="${isEdit ? movie.rating : ''}">
                        <input type="text" id="movieReviewer" placeholder="Your Name" value="${isEdit ? escapeHtml(movie.reviewer) : (currentUser?.username || '')}">
                        <textarea id="movieReviewText" rows="4" placeholder="Your review">${isEdit ? escapeHtml(movie.review_text) : ''}</textarea>
                        <div style="display:flex; gap:1rem; justify-content:flex-end;">
                            <button id="cancelFormBtn">Cancel</button>
                            <button id="submitMovieBtn">${isEdit ? 'Update' : 'Publish'}</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        document.getElementById('submitMovieBtn').onclick = async () => {
            const title = document.getElementById('movieTitle').value.trim();
            const genre = document.getElementById('movieGenre').value.trim();
            const rating = parseFloat(document.getElementById('movieRating').value);
            const reviewer = document.getElementById('movieReviewer').value.trim();
            const review_text = document.getElementById('movieReviewText').value.trim();
            if (!title || !genre || isNaN(rating) || !reviewer || !review_text) {
                showToast('Please fill all fields', 'error');
                return;
            }
            const payload = { title, genre, rating, reviewer, review_text };
            let res;
            if (isEdit) {
                payload.id = movie.id;
                res = await apiCall('updateMovie', 'POST', payload);
            } else {
                res = await apiCall('createMovie', 'POST', payload);
            }
            if (res.success) {
                await loadMovies();
                renderMovieGrid(document.getElementById('searchInput')?.value || '');
                closeModal();
                showToast(isEdit ? 'Review updated' : 'Review published');
            } else {
                showToast(res.message || 'Failed', 'error');
            }
        };
        document.getElementById('cancelFormBtn').onclick = closeModal;
    }
    
    function getLoginHTML() {
        return `
            <div style="display:flex; justify-content:center; align-items:center; min-height:70vh;">
                <div class="card" style="max-width:450px; width:100%;">
                    <div style="display:flex; gap:1rem; border-bottom:2px solid #e2e8f0; margin-bottom:1.5rem;">
                        <button id="showLoginTab" class="tab-btn active">Login</button>
                        <button id="showRegisterTab" class="tab-btn">Register</button>
                    </div>
                    <div id="loginForm">
                        <div class="form-grid">
                            <input type="text" id="loginUsername" placeholder="Username">
                            <input type="password" id="loginPassword" placeholder="Password">
                            <button id="doLoginBtn">Sign In</button>
                        </div>
                        <div class="demo-box">
                            <strong>Demo Account:</strong> demo_user / demo123
                        </div>
                    </div>
                    <div id="registerForm" style="display:none;">
                        <div class="form-grid">
                            <input type="text" id="regUsername" placeholder="Username (min 3 chars)">
                            <input type="password" id="regPassword" placeholder="Password (min 4 chars)">
                            <button id="doRegisterBtn">Create Account</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }
    
    function attachLoginEvents() {
        const loginTab = document.getElementById('showLoginTab');
        const regTab = document.getElementById('showRegisterTab');
        const loginDiv = document.getElementById('loginForm');
        const regDiv = document.getElementById('registerForm');
        
        if (loginTab && regTab) {
            loginTab.onclick = () => {
                loginDiv.style.display = 'block';
                regDiv.style.display = 'none';
                loginTab.classList.add('active');
                regTab.classList.remove('active');
            };
            regTab.onclick = () => {
                loginDiv.style.display = 'none';
                regDiv.style.display = 'block';
                regTab.classList.add('active');
                loginTab.classList.remove('active');
            };
        }
        
        document.getElementById('doLoginBtn').onclick = async () => {
            const username = document.getElementById('loginUsername').value;
            const password = document.getElementById('loginPassword').value;
            const res = await apiCall('login', 'POST', { username, password });
            if (res.success) {
                currentUser = { id: res.user_id, username: res.username, preferences: res.preferences || {} };
                applyTheme(currentUser.preferences?.theme || 'light');
                showToast('Welcome ' + username);
                renderApp();
            } else {
                showToast(res.message || 'Login failed', 'error');
            }
        };
        
        document.getElementById('doRegisterBtn').onclick = async () => {
            const username = document.getElementById('regUsername').value;
            const password = document.getElementById('regPassword').value;
            const res = await apiCall('register', 'POST', { username, password });
            if (res.success) {
                showToast('Registration successful! Please login.');
                if (loginTab) loginTab.click();
            } else {
                showToast(res.message || 'Registration failed', 'error');
            }
        };
    }
    
    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>]/g, function(m) {
            return m === '&' ? '&amp;' : m === '<' ? '&lt;' : '&gt;';
        });
    }
    
    async function init() {
        const res = await apiCall('checkSession');
        if (res.success && res.user) {
            currentUser = res.user;
            applyTheme(currentUser.preferences?.theme || 'light');
            await loadMovies();
        }
        renderApp();
    }
    
    init();
</script>
</body>
</html>