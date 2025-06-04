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

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    send_response(['error' => 'Forbidden: Only admins can update shelters.'], 403);
    exit;
}

// Get Shelter ID from query string
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    send_response(['error' => 'Shelter ID is required in the URL and must be a number.'], 400);
    exit;
}
$shelter_id = intval($_GET['id']);

// Get input data from the request body
$input = json_decode(file_get_contents('php://input'), true);

if (empty($input)) {
    send_response(['error' => 'No data provided for update.'], 400);
    exit;
}

// Validation rules for updatable fields
$rules = [
    'name' => 'optional|string|max:100',
    'address' => 'optional|string',
    'phone' => 'optional|string|max:20',
    'email' => 'optional|email|max:100',
    'website' => 'optional|string|max:255',
    'operating_hours' => 'optional|string|max:255'
];

$errors = Validator::validate($input, $rules);

if (!empty($errors)) {
    send_response(['error' => 'Validation failed', 'details' => $errors], 400);
    exit;
}

$link = get_db_connection();

// Check if shelter exists
$check_sql = "SELECT id FROM shelters WHERE id = ?";
if ($check_stmt = mysqli_prepare($link, $check_sql)) {
    mysqli_stmt_bind_param($check_stmt, "i", $shelter_id);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    if (mysqli_num_rows($check_result) == 0) {
        send_response(['error' => 'Shelter not found.'], 404);
        mysqli_stmt_close($check_stmt);
        close_db_connection($link);
        exit;
    }
    mysqli_stmt_close($check_stmt);
} else {
    send_response(['error' => 'Database error: Could not check shelter existence. ' . mysqli_error($link)], 500);
    close_db_connection($link);
    exit;
}

// Build the SET part of the SQL query dynamically
$set_clauses = [];
$bind_types = "";
$bind_values = [];

// Helper to add to update query
function add_to_update($field_name, $value, &$set_clauses, &$bind_types, &$bind_values, $type = 's') {
    if (isset($value) || array_key_exists($field_name, $_POST) || array_key_exists($field_name, (array)json_decode(file_get_contents('php://input')))) { // Check if field was explicitly sent
        $set_clauses[] = "`{$field_name}` = ?";
        $bind_types .= $type;
        $bind_values[] = $value;
    }
}

if (array_key_exists('name', $input)) add_to_update('name', $input['name'], $set_clauses, $bind_types, $bind_values);
if (array_key_exists('address', $input)) add_to_update('address', $input['address'], $set_clauses, $bind_types, $bind_values);
if (array_key_exists('phone', $input)) add_to_update('phone', $input['phone'], $set_clauses, $bind_types, $bind_values);
if (array_key_exists('email', $input)) add_to_update('email', $input['email'], $set_clauses, $bind_types, $bind_values);
if (array_key_exists('website', $input)) add_to_update('website', $input['website'], $set_clauses, $bind_types, $bind_values);
if (array_key_exists('operating_hours', $input)) add_to_update('operating_hours', $input['operating_hours'], $set_clauses, $bind_types, $bind_values);

if (empty($set_clauses)) {
    send_response(['message' => 'No valid fields provided for update.'], 200); // Or 400 if an update must change something
    close_db_connection($link);
    exit;
}

$sql = "UPDATE shelters SET " . implode(", ", $set_clauses) . " WHERE id = ?";
$bind_types .= "i";
$bind_values[] = $shelter_id;

if ($stmt = mysqli_prepare($link, $sql)) {
    mysqli_stmt_bind_param($stmt, $bind_types, ...$bind_values);

    if (mysqli_stmt_execute($stmt)) {
        if (mysqli_stmt_affected_rows($stmt) > 0) {
            send_response(['message' => 'Shelter updated successfully.'], 200);
        } else {
            send_response(['message' => 'No changes made to the shelter (data may be the same).'], 200);
        }
    } else {
        send_response(['error' => 'Failed to update shelter. ' . mysqli_stmt_error($stmt)], 500);
    }
    mysqli_stmt_close($stmt);
} else {
    send_response(['error' => 'Database error: Could not prepare statement. ' . mysqli_error($link)], 500);
}

close_db_connection($link);
?>
