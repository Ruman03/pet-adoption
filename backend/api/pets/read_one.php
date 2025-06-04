<?php
require_once '../../config/db.php';
require_once '../../utils/response.php';

// Allow only GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_response(['error' => 'Invalid request method.'], 405);
    exit;
}

// Check if ID is provided and is numeric
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    send_response(['error' => 'Pet ID is required and must be a number.'], 400);
    exit;
}

$pet_id = intval($_GET['id']);

// SQL to fetch a single pet by ID
// It's good practice to also join with users table to get staff name who added the pet, if available
$sql = "SELECT p.id, p.name, p.species, p.breed, p.age, p.gender, p.description, p.status, p.shelter_id, 
               p.added_by_staff_id, u.full_name as added_by_staff_name, p.added_at, p.image_url 
        FROM pets p
        LEFT JOIN users u ON p.added_by_staff_id = u.id
        WHERE p.id = ? LIMIT 1";

if ($stmt = mysqli_prepare($link, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $pet_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $pet = mysqli_fetch_assoc($result);

    if ($pet) {
        // You might want to format or sanitize data here before sending
        send_response($pet, 200);
    } else {
        send_response(['error' => 'Pet not found.'], 404);
    }
    mysqli_stmt_close($stmt);
} else {
    send_response(['error' => 'Database error: Could not prepare statement. ' . mysqli_error($link)], 500);
}

close_db_connection($link);
?>
