<?php
session_start();

require_once '../../config/db.php';
require_once '../../utils/response.php';

// Allow only GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_response(['error' => 'Invalid request method.'], 405);
    exit;
}

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    send_response(['error' => 'Unauthorized: Only admins can view user details.'], 403);
    exit;
}

// Check if ID is provided and is numeric
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    send_response(['error' => 'User ID is required and must be a number.'], 400);
    exit;
}
$user_id_to_view = intval($_GET['id']);

// Prepare SQL to fetch a single user by ID. IMPORTANT: Do NOT select the password hash.
$sql = "SELECT id, username, email, full_name, role, created_at FROM users WHERE id = ? LIMIT 1";

if ($stmt = mysqli_prepare($link, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $user_id_to_view);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);

    if ($user) {
        send_response($user, 200);
    } else {
        send_response(['error' => 'User not found.'], 404);
    }
    mysqli_stmt_close($stmt);
} else {
    send_response(['error' => 'Database error: Could not prepare statement. ' . mysqli_error($link)], 500);
}

close_db_connection($link);
?>
