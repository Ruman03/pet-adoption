<?php
define('DB_SERVER', 'db'); // This usually matches the service name in docker-compose.yml
define('DB_USERNAME', 'root'); // Default XAMPP/Docker MySQL user
define('DB_PASSWORD', 'rootpassword'); // The password you set in docker-compose.yml
define('DB_NAME', 'pet_adoption_db');

/* Attempt to connect to MySQL database */
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
