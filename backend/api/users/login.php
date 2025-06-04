<?php
session_start(); // Start the session at the very beginning

require_once '../../config/db.php';
require_once '../../utils/response.php';
require_once '../../utils/validator.php';

// Allow only POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_response(['error' => 'Invalid request method.'], 405);
    exit;
}

// Get input data
$input = json_decode(file_get_contents('php://input'), true);

// Validate input
$errors = Validator::validate($input, [
    'username' => 'required|string',
    'password' => 'required|string'
]);

if (!empty($errors)) {
    send_response(['error' => 'Validation failed', 'details' => $errors], 400);
    exit;
}

$username = $input['username'];
$password = $input['password'];

// Prepare SQL to fetch user by username
$sql = "SELECT id, username, password, role, full_name, email FROM users WHERE username = ? LIMIT 1";

if ($stmt = mysqli_prepare($link, $sql)) {
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);

    if ($user && password_verify($password, $user['password'])) {
        // Password is correct, store user data in session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['email'] = $user['email'];

        send_response([
            'message' => 'Login successful.',
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'role' => $user['role'],
                'full_name' => $user['full_name'],
                'email' => $user['email']
            ]
        ], 200);
    } else {
        // Invalid username or password
        send_response(['error' => 'Invalid username or password.'], 401);
    }
    mysqli_stmt_close($stmt);
} else {
    send_response(['error' => 'Database error: Could not prepare statement. ' . mysqli_error($link)], 500);
}

close_db_connection($link);
?>
