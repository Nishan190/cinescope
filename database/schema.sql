-- ==============================================
-- CINESCOPE - COMPLETE DATABASE SCHEMA
-- ==============================================

CREATE DATABASE IF NOT EXISTS movie_review_db;
USE movie_review_db;

-- USERS TABLE
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    preferences TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- MOVIES TABLE
CREATE TABLE IF NOT EXISTS movies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    genre VARCHAR(100) NOT NULL,
    rating DECIMAL(3,1) NOT NULL CHECK (rating >= 0 AND rating <= 10),
    reviewer VARCHAR(100) NOT NULL,
    review_text TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ACTIVITY LOGS TABLE
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action VARCHAR(50) NOT NULL,
    details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_action (user_id, action),
    INDEX idx_created_at (created_at)
);

-- DEMO USER (password = 'demo123')
INSERT INTO users (username, password, preferences) 
VALUES ('demo_user', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '{"theme":"light"}')
ON DUPLICATE KEY UPDATE id=id;

-- SAMPLE MOVIE REVIEWS
INSERT INTO movies (title, genre, rating, reviewer, review_text) VALUES
('The Dark Knight', 'Action/Crime', 9.0, 'Alex C.', 'A masterpiece from Christopher Nolan. Heath Ledger delivers an unforgettable, Oscar-winning performance as the Joker. The film explores deep themes of chaos, justice, and sacrifice.'),
('Inception', 'Sci-Fi/Thriller', 8.8, 'Maria S.', 'Mind-bending dream heist with stunning visuals. Leonardo DiCaprio leads a fantastic cast through layers of dreams within dreams. A must-watch for anyone who loves intellectual cinema.'),
('Parasite', 'Thriller/Drama', 8.6, 'James L.', 'Brilliant social commentary wrapped in a darkly comedic thriller. Bong Joon-ho masterfully blends genres to create something completely unique. Deserved every Oscar it won.'),
('Spirited Away', 'Animation/Fantasy', 8.9, 'Yuki T.', 'Hayao Miyazaki at his absolute best. A magical journey through a spirit world filled with unforgettable characters. Beautiful animation, touching story, and deep cultural roots.'),
('The Godfather', 'Crime/Drama', 9.2, 'Robert D.', 'The definitive mafia epic. Marlon Brando and Al Pacino deliver career-defining performances. A timeless classic about family, power, and corruption.')
ON DUPLICATE KEY UPDATE title=title;

-- VIEW ALL DATA (for verification)
SELECT 'Users:' as '';
SELECT * FROM users;
SELECT 'Movies:' as '';
SELECT * FROM movies;
SELECT 'Activity Logs:' as '';
SELECT * FROM activity_logs;