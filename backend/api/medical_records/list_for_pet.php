<?php
session_start();

require_once '../../config/db.php';
require_once '../../utils/response.php';
// No validator needed for GET request with only a query parameter

// Allow only GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_response(['error' => 'Invalid request method. Use GET.'], 405);
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    send_response(['error' => 'Unauthorized: You must be logged in to view medical records.'], 401);
    exit;
}

// Get pet_id from query string
if (!isset($_GET['pet_id']) || !is_numeric($_GET['pet_id'])) {
    send_response(['error' => 'Pet ID is required in the URL and must be a number.'], 400);
    exit;
}
$pet_id = intval($_GET['pet_id']);

// Check if pet exists
$check_pet_sql = "SELECT id FROM pets WHERE id = ?";
if ($check_stmt = mysqli_prepare($link, $check_pet_sql)) {
    mysqli_stmt_bind_param($check_stmt, "i", $pet_id);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    if (mysqli_num_rows($check_result) == 0) {
        send_response(['error' => 'Pet not found.'], 404);
        mysqli_stmt_close($check_stmt);
        close_db_connection($link);
        exit;
    }
    mysqli_stmt_close($check_stmt);
} else {
    send_response(['error' => 'Database error: Could not prepare pet check statement. ' . mysqli_error($link)], 500);
    close_db_connection($link);
    exit;
}


// Prepare SQL statement to fetch medical records for the pet
// Join with users table to get vet's name (assuming vet_id in medical_records links to users.id)
$sql = "SELECT mr.*, u.full_name AS vet_name 
        FROM medical_records mr
        LEFT JOIN users u ON mr.vet_id = u.id AND u.role = 'vet'
        WHERE mr.pet_id = ?
        ORDER BY mr.record_date DESC, mr.created_at DESC";

if ($stmt = mysqli_prepare($link, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $pet_id);

    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        $records = mysqli_fetch_all($result, MYSQLI_ASSOC);

        if (empty($records)) {
            send_response(['message' => 'No medical records found for this pet.', 'data' => []], 200);
        } else {
            send_response($records, 200);
        }
    } else {
        send_response(['error' => 'Failed to fetch medical records. ' . mysqli_stmt_error($stmt)], 500);
    }
    mysqli_stmt_close($stmt);
} else {
    send_response(['error' => 'Database error: Could not prepare statement. ' . mysqli_error($link)], 500);
}

close_db_connection($link);
?>
