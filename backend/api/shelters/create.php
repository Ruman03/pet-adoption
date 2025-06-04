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

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    send_response(['error' => 'Forbidden: Only admins can create shelters.'], 403);
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
    'name' => 'required|string|max:100',
    'address' => 'required|string',
    'phone' => 'optional|string|max:20',
    'email' => 'optional|email|max:100',
    'website' => 'optional|string|max:255', // Consider adding a URL validation rule to validator if needed
    'operating_hours' => 'optional|string|max:255'
];

$errors = Validator::validate($input, $rules);

if (!empty($errors)) {
    send_response(['error' => 'Validation failed', 'details' => $errors], 400);
    exit;
}

$link = get_db_connection();

$sql = "INSERT INTO shelters (name, address, phone, email, website, operating_hours) VALUES (?, ?, ?, ?, ?, ?)";

if ($stmt = mysqli_prepare($link, $sql)) {
    mysqli_stmt_bind_param(
        $stmt,
        "ssssss",
        $input['name'],
        $input['address'],
        $input['phone'] ?? null,
        $input['email'] ?? null,
        $input['website'] ?? null,
        $input['operating_hours'] ?? null
    );

    if (mysqli_stmt_execute($stmt)) {
        $new_shelter_id = mysqli_insert_id($link);
        send_response(['message' => 'Shelter created successfully.', 'shelter_id' => $new_shelter_id], 201);
    } else {
        send_response(['error' => 'Failed to create shelter. ' . mysqli_stmt_error($stmt)], 500);
    }
    mysqli_stmt_close($stmt);
} else {
    send_response(['error' => 'Database error: Could not prepare statement. ' . mysqli_error($link)], 500);
}

close_db_connection($link);
?>
