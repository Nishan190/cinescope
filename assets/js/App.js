// ==============================================
// CINESCOPE - FRONTEND APPLICATION
// ==============================================

let currentUser = null;
let moviesList = [];
let activityLog = [];

// API Call Helper
async function apiCall(action, method = 'GET', body = null) {
    const options = { 
        method, 
        headers: { 'Content-Type': 'application/json' } 
    };
    if (body && method !== 'GET') options.body = JSON.stringify(body);
    
    const url = `api.php?action=${action}`;
    const res = await fetch(url, options);
    return await res.json();
}

// Show Toast Notification
function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i> ${message}`;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}

// Render Main App
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
        navDiv.innerHTML = `
            <a href="#" id="navLoginBtn"><i class="fas fa-sign-in-alt"></i> Login</a>
            <a href="#" id="navRegisterBtn"><i class="fas fa-user-plus"></i> Register</a>
        `;
        document.getElementById('navLoginBtn')?.addEventListener('click', (e) => { e.preventDefault(); showLoginTab(); });
        document.getElementById('navRegisterBtn')?.addEventListener('click', (e) => { e.preventDefault(); showRegisterTab(); });
    } else {
        navDiv.innerHTML = `
            <span><i class="fas fa-user-circle"></i> ${escapeHtml(currentUser.username)}</span>
            <a href="#" id="prefBtn"><i class="fas fa-sliders-h"></i> Preferences</a>
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
    showToast('Logged out successfully');
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

// Modal Functions
function showChangePasswordModal() {
    const modalHtml = `
        <div id="modalOverlay" class="modal-overlay">
            <div class="card modal-content">
                <h3><i class="fas fa-key"></i> Change Password</h3>
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
        if (newPwd.length < 4) { showToast("Password must be at least 4 characters", 'error'); return; }
        
        const res = await apiCall('changePassword', 'POST', { old_password: oldPwd, new_password: newPwd });
        if (res.success) {
            showToast('Password updated successfully');
            closeModal();
        } else {
            showToast(res.message || 'Failed', 'error');
        }
    };
    document.getElementById('cancelModalBtn').onclick = closeModal;
}

function showPreferencesModal() {
    const currentTheme = currentUser?.preferences?.theme || 'light';
    const modalHtml = `
        <div id="modalOverlay" class="modal-overlay">
            <div class="card modal-content">
                <h3><i class="fas fa-sliders-h"></i> Display Preferences</h3>
                <div class="form-grid">
                    <label>Theme Mode:</label>
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
            showToast('Preferences saved');
            closeModal();
        } else {
            showToast('Error saving preferences', 'error');
        }
    };
    document.getElementById('cancelModalBtn').onclick = closeModal;
}

function applyTheme(theme) {
    if (theme === 'dark') {
        document.body.classList.add('dark-mode');
    } else {
        document.body.classList.remove('dark-mode');
    }
}

function closeModal() {
    document.getElementById('modalOverlay')?.remove();
}

// Dashboard Views
function getDashboardHTML() {
    return `
        <div style="margin: 1rem 0;" data-aos="fade-up">
            <div class="card">
                <h2><i class="fas fa-film"></i> Movie Reviews</h2>
                <div class="search-bar">
                    <input type="text" id="searchInput" placeholder="🔍 Search by title, genre, or reviewer..." autocomplete="off">
                    <button id="clearSearchBtn"><i class="fas fa-times"></i> Clear</button>
                    <button id="newMovieBtn" style="background:#facc15; color:#0f172a;"><i class="fas fa-plus-circle"></i> Add Review</button>
                </div>
            </div>
            <div id="moviesGridContainer" class="movie-grid"></div>
        </div>
        <div class="card" data-aos="fade-up" data-aos-delay="100">
            <h3><i class="fas fa-history"></i> Recent Activity Log</h3>
            <div id="activityLogList" style="max-height:300px; overflow-y:auto;"></div>
        </div>
    `;
}

function renderMovieGrid(searchTerm = '') {
    const container = document.getElementById('moviesGridContainer');
    if (!container) return;
    
    let filtered = [...moviesList];
    if (searchTerm.trim()) {
        const term = searchTerm.toLowerCase();
        filtered = moviesList.filter(m => 
            m.title.toLowerCase().includes(term) || 
            m.genre.toLowerCase().includes(term) || 
            m.reviewer.toLowerCase().includes(term)
        );
    }
    
    if (filtered.length === 0) {
        container.innerHTML = `<div class="card" style="text-align:center;">🎬 No movies found. Be the first to add a review!</div>`;
        return;
    }
    
    container.innerHTML = filtered.map((movie, idx) => `
        <div class="movie-card" data-id="${movie.id}" data-aos="fade-up" data-aos-delay="${idx * 50}">
            <div class="movie-img"><i class="fas fa-clapperboard"></i> 🎞️</div>
            <div class="movie-info">
                <div class="movie-title">
                    ${escapeHtml(movie.title)}
                    <span class="rating">⭐ ${movie.rating}/10</span>
                </div>
                <div class="genre"><i class="fas fa-tag"></i> ${escapeHtml(movie.genre)} | 👤 ${escapeHtml(movie.reviewer)}</div>
                <div class="review-text">${escapeHtml(movie.review_text.substring(0, 150))}${movie.review_text.length > 150 ? '...' : ''}</div>
                <div class="actions">
                    <button class="editMovieBtn" data-id="${movie.id}" style="background:#3b82f6;"><i class="fas fa-edit"></i> Edit</button>
                    <button class="deleteMovieBtn" data-id="${movie.id}" style="background:#dc2626;"><i class="fas fa-trash"></i> Delete</button>
                </div>
            </div>
        </div>
    `).join('');
    
    document.querySelectorAll('.editMovieBtn').forEach(btn => {
        btn.addEventListener('click', () => {
            const id = btn.getAttribute('data-id');
            const movie = moviesList.find(m => m.id == id);
            if (movie) showMovieForm(movie);
        });
    });
    
    document.querySelectorAll('.deleteMovieBtn').forEach(btn => {
        btn.addEventListener('click', async () => {
            if (confirm("Delete this review permanently?")) {
                const id = btn.getAttribute('data-id');
                const res = await apiCall('deleteMovie', 'POST', { id });
                if (res.success) {
                    await loadMovies();
                    renderMovieGrid(document.getElementById('searchInput')?.value || '');
                    await loadActivityLog();
                    renderActivityLog();
                    showToast('Movie review deleted');
                } else {
                    showToast(res.message || 'Failed', 'error');
                }
            }
        });
    });
}

function renderActivityLog() {
    const container = document.getElementById('activityLogList');
    if (!container) return;
    
    if (!activityLog.length) {
        container.innerHTML = '<div style="text-align:center; color:#94a3b8;">No activity recorded yet.</div>';
        return;
    }
    
    container.innerHTML = activityLog.slice(0, 20).map(log => `
        <div class="activity-log-item">
            <span><i class="fas fa-circle-info" style="color:#facc15;"></i> <strong>${escapeHtml(log.action)}</strong> by ${escapeHtml(log.username)}</span>
            <span style="font-size:0.7rem; color:#64748b;">${escapeHtml(log.details || '')} • ${log.created_at}</span>
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
                <h3>${isEdit ? '✏️ Edit Movie Review' : '➕ Add New Review'}</h3>
                <div class="form-grid">
                    <input type="text" id="movieTitle" placeholder="Movie Title *" value="${isEdit ? escapeHtml(movie.title) : ''}">
                    <input type="text" id="movieGenre" placeholder="Genre (e.g., Action, Drama) *" value="${isEdit ? escapeHtml(movie.genre) : ''}">
                    <input type="number" id="movieRating" step="0.1" min="0" max="10" placeholder="Rating (0-10) *" value="${isEdit ? movie.rating : ''}">
                    <input type="text" id="movieReviewer" placeholder="Your name / Reviewer *" value="${isEdit ? escapeHtml(movie.reviewer) : (currentUser?.username || '')}">
                    <textarea id="movieReviewText" rows="4" placeholder="Write your detailed review... *">${isEdit ? escapeHtml(movie.review_text) : ''}</textarea>
                    <div style="display:flex; gap:1rem; justify-content:flex-end;">
                        <button id="cancelFormBtn">Cancel</button>
                        <button id="submitMovieBtn">${isEdit ? 'Update Review' : 'Publish Review'}</button>
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
        
        if (!title || !genre || isNaN(rating) || rating < 0 || rating > 10 || !reviewer || !review_text) {
            showToast("Please fill all fields correctly (rating 0-10)", 'error');
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
            await loadActivityLog();
            renderActivityLog();
            closeModal();
            showToast(isEdit ? 'Review updated' : 'Review published');
        } else {
            showToast(res.message || 'Operation failed', 'error');
        }
    };
    document.getElementById('cancelFormBtn').onclick = closeModal;
}

// Login/Register Views
function getLoginHTML() {
    return `
        <div style="display:flex; justify-content:center; align-items:center; min-height: 70vh;" data-aos="fade-up">
            <div class="card" style="max-width:500px; width:100%;">
                <div style="display:flex; gap:1rem; border-bottom:2px solid #e2e8f0; margin-bottom:1.5rem;">
                    <button id="showLoginTab" class="tab-btn active">Login</button>
                    <button id="showRegisterTab" class="tab-btn">Register</button>
                </div>
                <div id="loginForm">
                    <div class="form-grid">
                        <input type="text" id="loginUsername" placeholder="Username">
                        <input type="password" id="loginPassword" placeholder="Password">
                        <button id="doLoginBtn"><i class="fas fa-sign-in-alt"></i> Sign In</button>
                    </div>
                </div>
                <div id="registerForm" style="display:none;">
                    <div class="form-grid">
                        <input type="text" id="regUsername" placeholder="Username (min 3 chars)">
                        <input type="password" id="regPassword" placeholder="Password (min 4 chars)">
                        <button id="doRegisterBtn"><i class="fas fa-user-plus"></i> Create Account</button>
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
    
    document.getElementById('doLoginBtn')?.addEventListener('click', async () => {
        const username = document.getElementById('loginUsername').value;
        const password = document.getElementById('loginPassword').value;
        const res = await apiCall('login', 'POST', { username, password });
        if (res.success) {
            currentUser = { id: res.user_id, username: res.username, preferences: res.preferences || {} };
            applyTheme(currentUser.preferences?.theme || 'light');
            showToast('Welcome back, ' + username + '!');
            renderApp();
        } else {
            showToast(res.message || 'Login failed', 'error');
        }
    });
    
    document.getElementById('doRegisterBtn')?.addEventListener('click', async () => {
        const username = document.getElementById('regUsername').value;
        const password = document.getElementById('regPassword').value;
        const res = await apiCall('register', 'POST', { username, password });
        if (res.success) {
            showToast('Registration successful! Please login.');
            document.getElementById('showLoginTab').click();
        } else {
            showToast(res.message || 'Registration failed', 'error');
        }
    });
}

function showLoginTab() { renderApp(); }
function showRegisterTab() { renderApp(); }

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/[&<>]/g, function(m) {
        if (m === '&') return '&amp;';
        if (m === '<') return '&lt;';
        if (m === '>') return '&gt;';
        return m;
    });
}

// Initialize
async function init() {
    const res = await apiCall('checkSession');
    if (res.success && res.user) {
        currentUser = res.user;
        applyTheme(currentUser.preferences?.theme || 'light');
        await loadMovies();
    }
    renderApp();
    
    // Initialize AOS
    if (typeof AOS !== 'undefined') {
        AOS.init({ duration: 600, once: true });
    }
}

init();