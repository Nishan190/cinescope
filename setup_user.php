<?php
// Run this file once to create the demo user
$host = 'localhost';
$dbname = 'movie_review_db';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create tables if they don't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        preferences TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS movies (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(200) NOT NULL,
        genre VARCHAR(100) NOT NULL,
        rating DECIMAL(3,1) NOT NULL,
        reviewer VARCHAR(100) NOT NULL,
        review_text TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS activity_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        action VARCHAR(50) NOT NULL,
        details TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Clear existing demo user
    $pdo->exec("DELETE FROM users WHERE username = 'demo_user'");
    
    // Create new demo user with password 'demo123'
    $hashedPassword = password_hash('demo123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (username, password, preferences) VALUES (?, ?, ?)");
    $stmt->execute(['demo_user', $hashedPassword, json_encode(['theme' => 'light'])]);
    
    // Insert sample movies
    $pdo->exec("DELETE FROM movies");
    $sampleMovies = [
        ['The Dark Knight', 'Action', 9.0, 'Alex C.', 'A masterpiece from Christopher Nolan. Heath Ledger delivers an unforgettable performance as the Joker. The film explores deep themes of chaos and justice.'],
        ['Inception', 'Sci-Fi', 8.8, 'Maria S.', 'Mind-bending dream heist with stunning visuals. Leonardo DiCaprio leads a fantastic cast through layers of dreams within dreams.'],
        ['Parasite', 'Thriller', 8.6, 'James L.', 'Brilliant social commentary wrapped in a darkly comedic thriller. Bong Joon-ho masterfully blends genres.'],
        ['Spirited Away', 'Animation', 8.9, 'Yuki T.', 'Hayao Miyazaki at his absolute best. A magical journey through a spirit world filled with unforgettable characters.'],
        ['The Godfather', 'Crime', 9.2, 'Robert D.', 'The definitive mafia epic. Marlon Brando and Al Pacino deliver career-defining performances. A timeless classic.']
    ];
    
    $stmt = $pdo->prepare("INSERT INTO movies (title, genre, rating, reviewer, review_text) VALUES (?, ?, ?, ?, ?)");
    foreach ($sampleMovies as $movie) {
        $stmt->execute($movie);
    }
    
    echo "<h1 style='color: green;'>✅ Setup Complete!</h1>";
    echo "<p>Demo user created successfully!</p>";
    echo "<p><strong>Username:</strong> demo_user</p>";
    echo "<p><strong>Password:</strong> demo123</p>";
    echo "<p><a href='index.php'>Go to Login Page →</a></p>";
    
} catch(PDOException $e) {
    echo "<h1 style='color: red;'>❌ Error: " . $e->getMessage() . "</h1>";
    echo "<p>Make sure MySQL is running in XAMPP</p>";
}
?>