<?php
session_start(); // Required for checking user role and staff ID

require_once '../../config/db.php';
require_once '../../utils/response.php';
require_once '../../utils/validator.php';

// Allow only POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_response(['error' => 'Invalid request method.'], 405);
    exit;
}

// Check if user is logged in and is staff or admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['staff', 'admin'])) {
    send_response(['error' => 'Unauthorized: Only staff or admin can add pets.'], 403);
    exit;
}

// Get input data from the request body (assuming JSON input)
$input = json_decode(file_get_contents('php://input'), true);

// Validate input
$validation_rules = [
    'name' => 'required|string|max:100',
    'species' => 'string|max:50',
    'breed' => 'string|max:50',
    'age' => 'integer', // Assuming age is in years or a numeric representation
    'gender' => 'in:male,female,unknown',
    'description' => 'string',
    'status' => 'in:available,adopted,fostered,pending', // Default is 'available' in DB
    'shelter_id' => 'integer', // Optional, assuming it can be null
    'image_url' => 'string|max:255' // Optional
];

$errors = Validator::validate($input, $validation_rules);

if (!empty($errors)) {
    send_response(['error' => 'Validation failed', 'details' => $errors], 400);
    exit;
}

// Assign variables from input, providing defaults or null where appropriate
$name = $input['name'];
$species = $input['species'] ?? null;
$breed = $input['breed'] ?? null;
$age = $input['age'] ?? null;
$gender = $input['gender'] ?? 'unknown';
$description = $input['description'] ?? null;
$status = $input['status'] ?? 'available';
$shelter_id = $input['shelter_id'] ?? null; // Assuming shelter_id can be nullable
$image_url = $input['image_url'] ?? null;
$added_by_staff_id = $_SESSION['user_id']; // Logged-in staff member

// Prepare SQL statement
$sql = "INSERT INTO pets (name, species, breed, age, gender, description, status, shelter_id, added_by_staff_id, image_url) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

if ($stmt = mysqli_prepare($link, $sql)) {
    // 'i' for integer, 's' for string, 'd' for double/decimal
    mysqli_stmt_bind_param($stmt, "sssisssiis", 
        $name, $species, $breed, $age, $gender, 
        $description, $status, $shelter_id, $added_by_staff_id, $image_url
    );

    if (mysqli_stmt_execute($stmt)) {
        $pet_id = mysqli_insert_id($link);
        send_response([
            'message' => 'Pet added successfully.',
            'pet_id' => $pet_id
        ], 201);
    } else {
        send_response(['error' => 'Failed to add pet. ' . mysqli_stmt_error($stmt)], 500);
    }
    mysqli_stmt_close($stmt);
} else {
    send_response(['error' => 'Database error: Could not prepare statement. ' . mysqli_error($link)], 500);
}

close_db_connection($link);
?>
