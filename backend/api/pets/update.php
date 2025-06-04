<?php
session_start(); // Required for checking user role

require_once '../../config/db.php';
require_once '../../utils/response.php';
require_once '../../utils/validator.php';

// Allow only PUT requests (or POST if PUT is problematic for the client)
if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    // You could also check for POST and a _method=PUT parameter if needed
    send_response(['error' => 'Invalid request method. Use PUT for updates.'], 405);
    exit;
}

// Check if user is logged in and is staff or admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['staff', 'admin'])) {
    send_response(['error' => 'Unauthorized: Only staff or admin can update pets.'], 403);
    exit;
}

// Get Pet ID from query string
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    send_response(['error' => 'Pet ID is required in the URL and must be a number.'], 400);
    exit;
}
$pet_id = intval($_GET['id']);

// Get input data from the request body (assuming JSON input)
$input = json_decode(file_get_contents('php://input'), true);

if (empty($input)) {
    send_response(['error' => 'No data provided for update.'], 400);
    exit;
}

// Validate input data - fields are optional for update
// Define all possible fields that can be updated and their validation rules
$possible_fields_rules = [
    'name' => 'string|max:100',
    'species' => 'string|max:50',
    'breed' => 'string|max:50',
    'age' => 'integer',
    'gender' => 'in:male,female,unknown',
    'description' => 'string',
    'status' => 'in:available,adopted,fostered,pending',
    'shelter_id' => 'integer', // Assuming it can be null, validator should handle empty if not required
    'image_url' => 'string|max:255'
];

$errors = Validator::validate($input, $possible_fields_rules); // Validator needs to handle optional fields gracefully

// Filter out errors for fields not present in the input, as they are optional for update
$actual_errors = [];
foreach ($errors as $field => $field_errors) {
    if (array_key_exists($field, $input)) {
        $actual_errors[$field] = $field_errors;
    }
}

if (!empty($actual_errors)) {
    send_response(['error' => 'Validation failed', 'details' => $actual_errors], 400);
    exit;
}

// Build the SQL query dynamically
$sql_parts = [];
$bind_types = '';
$bind_values = [];

foreach ($possible_fields_rules as $field => $rules) {
    if (array_key_exists($field, $input)) {
        $sql_parts[] = "`$field` = ?";
        $bind_values[] = $input[$field];
        if (strpos($rules, 'integer') !== false || strpos($rules, 'int') !== false) {
            $bind_types .= 'i';
        } else {
            $bind_types .= 's'; // Default to string for simplicity, adjust if other types like double are needed
        }
    }
}

if (empty($sql_parts)) {
    send_response(['message' => 'No fields to update.'], 200); // Or 400 if an update must change something
    exit;
}

// Add pet_id to bind values and types for the WHERE clause
$bind_types .= 'i';
$bind_values[] = $pet_id;

$sql = "UPDATE pets SET " . implode(', ', $sql_parts) . " WHERE id = ?";

if ($stmt = mysqli_prepare($link, $sql)) {
    mysqli_stmt_bind_param($stmt, $bind_types, ...$bind_values);

    if (mysqli_stmt_execute($stmt)) {
        if (mysqli_stmt_affected_rows($stmt) > 0) {
            send_response(['message' => 'Pet updated successfully.'], 200);
        } else {
            // Check if pet exists, could be a separate check or inferred if no rows affected but no error
            send_response(['message' => 'Pet data was the same, or pet not found.'], 200); // Or 404 if pet not found
        }
    } else {
        send_response(['error' => 'Failed to update pet. ' . mysqli_stmt_error($stmt)], 500);
    }
    mysqli_stmt_close($stmt);
} else {
    send_response(['error' => 'Database error: Could not prepare statement. ' . mysqli_error($link)], 500);
}

close_db_connection($link);
?>
