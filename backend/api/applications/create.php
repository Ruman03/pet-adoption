<?php
session_start();

require_once '../../config/db.php';
require_once '../../utils/response.php';
require_once '../../utils/validator.php';

// Allow only POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_response(['error' => 'Invalid request method.'], 405);
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    send_response(['error' => 'Unauthorized: You must be logged in to submit an application.'], 401);
    exit;
}

// Get input data from the request body (assuming JSON input)
$input = json_decode(file_get_contents('php://input'), true);

// Validate input
$validation_rules = [
    'pet_id' => 'required|integer',
    'notes' => 'string' // Notes are optional
];

$errors = Validator::validate($input, $validation_rules);

if (!empty($errors)) {
    send_response(['error' => 'Validation failed', 'details' => $errors], 400);
    exit;
}

$user_id = $_SESSION['user_id'];
$pet_id = $input['pet_id'];
$notes = $input['notes'] ?? null;
$status = 'pending'; // Default status for new applications

// Optional: Check if the pet exists and is available before creating an application
// This would involve an additional query to the `pets` table.
// For example:
// $pet_check_sql = "SELECT status FROM pets WHERE id = ?";
// if ($pet_stmt = mysqli_prepare($link, $pet_check_sql)) {
//     mysqli_stmt_bind_param($pet_stmt, "i", $pet_id);
//     mysqli_stmt_execute($pet_stmt);
//     $pet_result = mysqli_stmt_get_result($pet_stmt);
//     $pet_data = mysqli_fetch_assoc($pet_result);
//     mysqli_stmt_close($pet_stmt);
//     if (!$pet_data) {
//         send_response(['error' => 'Pet not found.'], 404);
//         exit;
//     }
//     if ($pet_data['status'] !== 'available') {
//         send_response(['error' => 'This pet is not currently available for adoption.'], 400);
//         exit;
//     }
// }

// Prepare SQL statement to insert application
$sql = "INSERT INTO applications (user_id, pet_id, status, notes) VALUES (?, ?, ?, ?)";

if ($stmt = mysqli_prepare($link, $sql)) {
    mysqli_stmt_bind_param($stmt, "iiss", $user_id, $pet_id, $status, $notes);

    if (mysqli_stmt_execute($stmt)) {
        $application_id = mysqli_insert_id($link);
        send_response([
            'message' => 'Application submitted successfully.',
            'application_id' => $application_id
        ], 201);
    } else {
        // Check for specific errors, e.g., foreign key constraint if pet_id is invalid
        if (mysqli_errno($link) == 1452) { // Error code for foreign key constraint failure
             send_response(['error' => 'Invalid Pet ID or User ID.'], 400);
        } else {
            send_response(['error' => 'Failed to submit application. ' . mysqli_stmt_error($stmt)], 500);
        }
    }
    mysqli_stmt_close($stmt);
} else {
    send_response(['error' => 'Database error: Could not prepare statement. ' . mysqli_error($link)], 500);
}

close_db_connection($link);
?>
