<?php
session_start();

require_once '../../config/db.php';
require_once '../../utils/response.php';
require_once '../../utils/validator.php';

// Allow only POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_response(['error' => 'Invalid request method. Use POST.'], 405);
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    send_response(['error' => 'Unauthorized: You must be logged in to apply for fostering.'], 401);
    exit;
}

// Get input data from the request body
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    send_response(['error' => 'Invalid JSON input.'], 400);
    exit;
}

// Validation rules
$rules = [
    'pet_id' => 'required|integer',
    'start_date' => 'required|date', // Proposed start date
    'end_date' => 'optional|date',   // Proposed end date, can be null
    'notes' => 'optional|string'
];

$errors = Validator::validate($input, $rules);

if (!empty($errors)) {
    send_response(['error' => 'Validation failed', 'details' => $errors], 400);
    exit;
}

$link = get_db_connection();

// Check if pet exists and is available for fostering (e.g., status is 'available')
$pet_id = $input['pet_id'];
$pet_check_sql = "SELECT status FROM pets WHERE id = ?";
if ($pet_stmt = mysqli_prepare($link, $pet_check_sql)) {
    mysqli_stmt_bind_param($pet_stmt, "i", $pet_id);
    mysqli_stmt_execute($pet_stmt);
    $pet_result = mysqli_stmt_get_result($pet_stmt);
    if ($pet_row = mysqli_fetch_assoc($pet_result)) {
        if ($pet_row['status'] !== 'available') {
            send_response(['error' => 'Pet is not available for fostering at the moment. Current status: ' . $pet_row['status']], 400);
            mysqli_stmt_close($pet_stmt);
            close_db_connection($link);
            exit;
        }
    } else {
        send_response(['error' => 'Pet not found.'], 404);
        mysqli_stmt_close($pet_stmt);
        close_db_connection($link);
        exit;
    }
    mysqli_stmt_close($pet_stmt);
} else {
    send_response(['error' => 'Database error: Could not verify pet status.'], 500);
    close_db_connection($link);
    exit;
}

// Proceed to create foster record
$foster_parent_id = $_SESSION['user_id'];
$application_date = date('Y-m-d'); // Today's date
$status = 'pending'; // Initial status

$sql = "INSERT INTO foster_records (pet_id, foster_parent_id, application_date, start_date, end_date, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?)";

if ($stmt = mysqli_prepare($link, $sql)) {
    mysqli_stmt_bind_param(
        $stmt,
        "iisssss",
        $input['pet_id'],
        $foster_parent_id,
        $application_date,
        $input['start_date'],
        $input['end_date'] ?? null,
        $status,
        $input['notes'] ?? null
    );

    if (mysqli_stmt_execute($stmt)) {
        $new_foster_record_id = mysqli_insert_id($link);
        send_response(['message' => 'Foster application submitted successfully.', 'foster_record_id' => $new_foster_record_id], 201);
    } else {
        send_response(['error' => 'Failed to submit foster application. ' . mysqli_stmt_error($stmt)], 500);
    }
    mysqli_stmt_close($stmt);
} else {
    send_response(['error' => 'Database error: Could not prepare statement. ' . mysqli_error($link)], 500);
}

close_db_connection($link);
?>
