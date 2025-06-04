<?php
session_start();

require_once '../../config/db.php';
require_once '../../utils/response.php';

// Allow only DELETE requests (or POST with _method=DELETE)
if ($_SERVER['REQUEST_METHOD'] !== 'DELETE' && !($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_method']) && strtoupper($_POST['_method']) === 'DELETE')) {
    send_response(['error' => 'Invalid request method. Use DELETE.'], 405);
    exit;
}

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    send_response(['error' => 'Unauthorized: Only admins can delete users.'], 403);
    exit;
}

// Get User ID from query string or POST data if using _method override
$user_id_to_delete = null;
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        send_response(['error' => 'User ID is required in the URL and must be a number.'], 400);
        exit;
    }
    $user_id_to_delete = intval($_GET['id']);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id']) && is_numeric($_POST['id'])) {
    $user_id_to_delete = intval($_POST['id']);
} else {
    send_response(['error' => 'User ID is required and must be a number.'], 400);
    exit;
}


// Prevent admin from deleting themselves
if ($user_id_to_delete === $_SESSION['user_id']) {
    send_response(['error' => 'Admins cannot delete their own account.'], 403);
    exit;
}

// Optional: Add logic to ensure at least one admin account remains if this is critical.
// This would involve querying the count of admin users before deletion.

// Prepare SQL statement to delete user
// Database schema handles cascading deletes or setting NULL for related records:
// - applications: ON DELETE CASCADE (user's applications will be deleted)
// - pets (added_by_staff_id): ON DELETE SET NULL (pets added by this staff will have added_by_staff_id set to NULL)
// - medical_records (vet_id): ON DELETE SET NULL
// - foster_records (foster_parent_id): ON DELETE CASCADE
// - volunteer_tasks (assigned_to_volunteer_id): ON DELETE SET NULL
$sql = "DELETE FROM users WHERE id = ?";

if ($stmt = mysqli_prepare($link, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $user_id_to_delete);

    if (mysqli_stmt_execute($stmt)) {
        if (mysqli_stmt_affected_rows($stmt) > 0) {
            send_response(['message' => 'User deleted successfully.'], 200);
        } else {
            send_response(['error' => 'User not found or already deleted.'], 404);
        }
    } else {
        send_response(['error' => 'Failed to delete user. ' . mysqli_stmt_error($stmt)], 500);
    }
    mysqli_stmt_close($stmt);
} else {
    send_response(['error' => 'Database error: Could not prepare statement. ' . mysqli_error($link)], 500);
}

close_db_connection($link);
?>
