<?php
require_once '../../config/db.php'; // Adjust path as needed
require_once '../../utils/response.php'; // For sending JSON responses
require_once '../../utils/validator.php'; // For input validation

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
    'full_name' => 'string|max:100',
    'role' => 'required|in:adopter,staff,admin,vet,volunteer' // Ensure role is one of the ENUM values
]);

if (!empty($errors)) {
    send_response(['error' => 'Validation failed', 'details' => $errors], 400);
    exit;
}

$username = $input['username'];
$password = $input['password'];
$email = $input['email'];
$full_name = $input['full_name'] ?? null;
$role = $input['role'] ?? 'adopter'; // Default role if not provided, though 'required' validation should catch it

// Hash the password
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// Prepare SQL statement to prevent SQL injection
$sql = "INSERT INTO users (username, password, email, full_name, role) VALUES (?, ?, ?, ?, ?)";

if ($stmt = mysqli_prepare($link, $sql)) {
    mysqli_stmt_bind_param($stmt, "sssss", $username, $hashed_password, $email, $full_name, $role);

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
