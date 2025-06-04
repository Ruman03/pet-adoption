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

// Check if user is logged in and is staff, vet, or admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['staff', 'vet', 'admin'])) {
    send_response(['error' => 'Unauthorized: Only staff, vets, or admins can add medical records.'], 403);
    exit;
}

// Get input data from the request body (assuming JSON input)
$input = json_decode(file_get_contents('php://input'), true);

// Validate input
$validation_rules = [
    'pet_id' => 'required|integer',
    'record_date' => 'required|date',
    'condition_notes' => 'string',
    'treatment_given' => 'string',
    'vaccinations' => 'string', // Could be JSON or text
    'next_checkup_date' => 'date' // Optional
];

$errors = Validator::validate($input, $validation_rules);

if (!empty($errors)) {
    send_response(['error' => 'Validation failed', 'details' => $errors], 400);
    exit;
}

$pet_id = $input['pet_id'];
$record_date = $input['record_date'];
$condition_notes = $input['condition_notes'] ?? null;
$treatment_given = $input['treatment_given'] ?? null;
$vaccinations = $input['vaccinations'] ?? null;
$next_checkup_date = $input['next_checkup_date'] ?? null;

// Vet ID should be the logged-in user if their role is 'vet'. 
// If staff/admin is adding, vet_id might be optional or selected from a list (not implemented here).
$vet_id = null;
if ($_SESSION['role'] === 'vet') {
    $vet_id = $_SESSION['user_id'];
} elseif (isset($input['vet_id']) && is_numeric($input['vet_id'])) {
    // Allow staff/admin to specify a vet_id if provided and valid (e.g., from a dropdown)
    // Further validation could check if the provided vet_id actually belongs to a user with 'vet' role.
    $vet_id = intval($input['vet_id']);
}

// Prepare SQL statement
$sql = "INSERT INTO medical_records (pet_id, vet_id, record_date, condition_notes, treatment_given, vaccinations, next_checkup_date) 
        VALUES (?, ?, ?, ?, ?, ?, ?)";

if ($stmt = mysqli_prepare($link, $sql)) {
    mysqli_stmt_bind_param($stmt, "iisssss", 
        $pet_id, $vet_id, $record_date, $condition_notes, 
        $treatment_given, $vaccinations, $next_checkup_date
    );

    if (mysqli_stmt_execute($stmt)) {
        $record_id = mysqli_insert_id($link);
        send_response([
            'message' => 'Medical record added successfully.',
            'record_id' => $record_id
        ], 201);
    } else {
        if (mysqli_errno($link) == 1452) { // Foreign key constraint (e.g. pet_id or vet_id invalid)
            send_response(['error' => 'Invalid Pet ID or Vet ID provided.'], 400);
        } else {
            send_response(['error' => 'Failed to add medical record. ' . mysqli_stmt_error($stmt)], 500);
        }
    }
    mysqli_stmt_close($stmt);
} else {
    send_response(['error' => 'Database error: Could not prepare statement. ' . mysqli_error($link)], 500);
}

close_db_connection($link);
?>
