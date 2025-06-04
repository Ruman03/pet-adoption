<?php
// Add CORS headers for cross-origin requests
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/validator.php';

// Allow only POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_response(['error' => 'Invalid request method.'], 405);
    exit;
}

// Get input data from the request body (assuming JSON input)
$input = json_decode(file_get_contents('php://input'), true);

// Validate input
$errors = Validator::validate($input, [
    'username' => 'required|string|min:3|max:50',
    'password' => 'required|string|min:8',
    'email' => 'required|email|max:100',
    'first_name' => 'string|max:50',
    'last_name' => 'string|max:50',
    'role' => 'required|in:admin,adopter,shelter_staff,volunteer,veterinarian,foster_parent'
]);

if (!empty($errors)) {
    send_response(['error' => 'Validation failed', 'details' => $errors], 400);
    exit;
}

$username = $input['username'];
$password = $input['password'];
$email = $input['email'];
$first_name = $input['first_name'] ?? '';
$last_name = $input['last_name'] ?? '';
$role = $input['role'] ?? 'adopter';

// Hash the password
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// Prepare SQL statement to prevent SQL injection
$sql = "INSERT INTO users (username, password_hash, email, first_name, last_name, role) VALUES (?, ?, ?, ?, ?, ?)";

if ($stmt = mysqli_prepare($link, $sql)) {
    mysqli_stmt_bind_param($stmt, "ssssss", $username, $hashed_password, $email, $first_name, $last_name, $role);

    if (mysqli_stmt_execute($stmt)) {
        $user_id = mysqli_insert_id($link);
        send_response([
            'message' => 'User registered successfully.',
            'user_id' => $user_id
        ], 201);
    } else {
        // Check for duplicate entry (username or email)
        if (mysqli_errno($link) == 1062) { // 1062 is the MySQL error code for duplicate entry
            send_response(['error' => 'Username or email already exists.'], 409);
        } else {
            send_response(['error' => 'Failed to register user. ' . mysqli_stmt_error($stmt)], 500);
        }
    }
    mysqli_stmt_close($stmt);
} else {
    send_response(['error' => 'Database error: Could not prepare statement. ' . mysqli_error($link)], 500);
}

close_db_connection($link);
?>
