<?php
define('DB_SERVER', 'localhost'); // Changed from 'db' to 'localhost' for XAMPP
define('DB_USERNAME', 'root'); // Default XAMPP MySQL user
define('DB_PASSWORD', ''); // Default XAMPP MySQL password is empty
define('DB_NAME', 'pet_adoption_system');

try {
    // Create PDO connection
    $pdo = new PDO("mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USERNAME, DB_PASSWORD);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("ERROR: Could not connect to database. " . $e->getMessage());
}

// Keep mysqli connection for backward compatibility
$link = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if($link === false){
    die("ERROR: Could not connect. " . mysqli_connect_error());
}

// Optional: Set character set to utf8mb4 for better Unicode support
mysqli_set_charset($link, "utf8mb4");

// Function to close connection (optional, as PHP usually closes it at script end)
function close_db_connection($link) {
    mysqli_close($link);
}
?>
