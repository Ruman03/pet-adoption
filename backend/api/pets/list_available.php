<?php
require_once '../../config/db.php';
require_once '../../utils/response.php';

// Allow only GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_response(['error' => 'Invalid request method.'], 405);
    exit;
}

// SQL to fetch available pets
// We select all columns for now, but you might want to select specific ones for a list view
$sql = "SELECT id, name, species, breed, age, gender, description, image_url, added_at FROM pets WHERE status = 'available' ORDER BY added_at DESC";

$result = mysqli_query($link, $sql);

if ($result) {
    $pets = [];
    while ($row = mysqli_fetch_assoc($result)) {
        // Sanitize output or format as needed here
        // Example: Convert age to a more readable format if it's in months/years
        $pets[] = $row;
    }
    send_response($pets, 200);
    mysqli_free_result($result);
} else {
    send_response(['error' => 'Database query failed: ' . mysqli_error($link)], 500);
}

close_db_connection($link);
?>
