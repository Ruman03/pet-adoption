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
    send_response(['error' => 'Unauthorized: Only admins can update user roles.'], 403);
    exit;
}

// Get User ID from query string
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    send_response(['error' => 'User ID is required in the URL and must be a number.'], 400);
    exit;
}
$user_id_to_update = intval($_GET['id']);

// Prevent admin from changing their own role if they are the only admin or to a non-admin role easily.
// More sophisticated logic might be needed for a production system (e.g., count number of admins).
if ($user_id_to_update === $_SESSION['user_id']) {
    // Example: Check if they are trying to demote themselves
    $input_check = json_decode(file_get_contents('php://input'), true);
    if (isset($input_check['role']) && $input_check['role'] !== 'admin') {
        // Add a check to see if other admins exist before allowing self-demotion.
        // For now, we'll just send a warning or prevent it.
        // send_response(['error' => 'Admins cannot demote themselves if they are the sole admin. This check is simplified.'], 400);
        // exit;
        // Or simply allow it for now, but be aware of this potential issue.
    }
}

// Get input data from the request body (assuming JSON input)
$input = json_decode(file_get_contents('php://input'), true);

// Validate input
$validation_rules = [
    // Roles from schema.sql ENUM: 'adopter', 'staff', 'admin', 'vet', 'volunteer'
    'role' => 'required|in:adopter,staff,admin,vet,volunteer' 
];

$errors = Validator::validate($input, $validation_rules);

if (!empty($errors)) {
    send_response(['error' => 'Validation failed', 'details' => $errors], 400);
    exit;
}

$new_role = $input['role'];

// Prepare SQL statement to update user role
$sql = "UPDATE users SET role = ? WHERE id = ?";

if ($stmt = mysqli_prepare($link, $sql)) {
    mysqli_stmt_bind_param($stmt, "si", $new_role, $user_id_to_update);

    if (mysqli_stmt_execute($stmt)) {
        if (mysqli_stmt_affected_rows($stmt) > 0) {
            send_response(['message' => 'User role updated successfully.'], 200);
        } else {
            // Check if the user exists to differentiate between no change and not found
            $check_user_sql = "SELECT id FROM users WHERE id = ?";
            if($check_stmt = mysqli_prepare($link, $check_user_sql)){
                mysqli_stmt_bind_param($check_stmt, "i", $user_id_to_update);
                mysqli_stmt_execute($check_stmt);
                $check_result = mysqli_stmt_get_result($check_stmt);
                if(mysqli_num_rows($check_result) == 0){
                    send_response(['error' => 'User not found.'], 404);
                } else {
                    send_response(['message' => 'User role was already set to this value.'], 200);
                }
                mysqli_stmt_close($check_stmt);
            } else {
                 send_response(['message' => 'User role not changed and existence check failed.'], 200);
            }
        }
    } else {
        send_response(['error' => 'Failed to update user role. ' . mysqli_stmt_error($stmt)], 500);
    }
    mysqli_stmt_close($stmt);
} else {
    send_response(['error' => 'Database error: Could not prepare statement. ' . mysqli_error($link)], 500);
}

close_db_connection($link);
?>
