<?php
session_start();

require_once '../../config/db.php';
require_once '../../utils/response.php';
require_once '../../utils/validator.php';

// Allow only PUT or POST requests
if (!in_array($_SERVER['REQUEST_METHOD'], ['PUT', 'POST'])) {
    send_response(['error' => 'Invalid request method. Use PUT or POST.'], 405);
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    send_response(['error' => 'Unauthorized: You must be logged in.'], 401);
    exit;
}

// Check user role: 'staff', 'admin', or 'vet' can update medical records
$allowed_roles = ['staff', 'admin', 'vet'];
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles)) {
    send_response(['error' => 'Forbidden: You do not have permission to update medical records.'], 403);
    exit;
}

// Get record ID from query string
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    send_response(['error' => 'Medical Record ID is required in the URL and must be a number.'], 400);
    exit;
}
$record_id = intval($_GET['id']);

// Get input data from the request body (assuming JSON input)
$input = json_decode(file_get_contents('php://input'), true);

if (empty($input)) {
    send_response(['error' => 'No update data provided.'], 400);
    exit;
}

// Validation rules for updatable fields
$validation_rules = [
    'vet_id' => 'optional|integer', // We might want to add a rule to check if vet_id exists in users table with role 'vet'
    'record_date' => 'optional|date', // YYYY-MM-DD
    'record_type' => 'optional|in:vaccination,checkup,surgery,medication,other',
    'details' => 'optional|string',
    'next_due_date' => 'optional|date' // YYYY-MM-DD, allows null
];

$errors = Validator::validate($input, $validation_rules);

if (!empty($errors)) {
    send_response(['error' => 'Validation failed', 'details' => $errors], 400);
    exit;
}

// Check if the medical record exists
$link = get_db_connection();
$check_sql = "SELECT id FROM medical_records WHERE id = ?";
if ($check_stmt = mysqli_prepare($link, $check_sql)) {
    mysqli_stmt_bind_param($check_stmt, "i", $record_id);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    if (mysqli_num_rows($check_result) == 0) {
        send_response(['error' => 'Medical record not found.'], 404);
        mysqli_stmt_close($check_stmt);
        close_db_connection($link);
        exit;
    }
    mysqli_stmt_close($check_stmt);
} else {
    send_response(['error' => 'Database error: Could not check record existence. ' . mysqli_error($link)], 500);
    close_db_connection($link);
    exit;
}


// Build the SET part of the SQL query dynamically
$set_clauses = [];
$bind_types = "";
$bind_values = [];

if (isset($input['vet_id'])) {
    $set_clauses[] = "vet_id = ?";
    $bind_types .= "i";
    $bind_values[] = $input['vet_id'] === '' ? null : intval($input['vet_id']); // Handle empty string as NULL for optional int
}
if (isset($input['record_date'])) {
    $set_clauses[] = "record_date = ?";
    $bind_types .= "s";
    $bind_values[] = $input['record_date'];
}
if (isset($input['record_type'])) {
    $set_clauses[] = "record_type = ?";
    $bind_types .= "s";
    $bind_values[] = $input['record_type'];
}
if (isset($input['details'])) {
    $set_clauses[] = "details = ?";
    $bind_types .= "s";
    $bind_values[] = $input['details'];
}
if (array_key_exists('next_due_date', $input)) { // Use array_key_exists to allow explicit null
    $set_clauses[] = "next_due_date = ?";
    $bind_types .= "s";
    $bind_values[] = $input['next_due_date']; // Validator ensures it's a date or null if allowed by schema
}


if (empty($set_clauses)) {
    send_response(['error' => 'No valid fields provided for update.'], 400);
    close_db_connection($link);
    exit;
}

$sql = "UPDATE medical_records SET " . implode(", ", $set_clauses) . " WHERE id = ?";
$bind_types .= "i";
$bind_values[] = $record_id;

if ($stmt = mysqli_prepare($link, $sql)) {
    mysqli_stmt_bind_param($stmt, $bind_types, ...$bind_values);

    if (mysqli_stmt_execute($stmt)) {
        if (mysqli_stmt_affected_rows($stmt) > 0) {
            send_response(['message' => 'Medical record updated successfully.'], 200);
        } else {
            // This could mean the data was the same, or record not found (though we checked).
            // For simplicity, if affected_rows is 0 but no error, assume data was same.
            send_response(['message' => 'No changes made to the medical record (data may be the same).'], 200);
        }
    } else {
        send_response(['error' => 'Failed to update medical record. ' . mysqli_stmt_error($stmt)], 500);
    }
    mysqli_stmt_close($stmt);
} else {
    send_response(['error' => 'Database error: Could not prepare statement. ' . mysqli_error($link)], 500);
}

close_db_connection($link);
?>
