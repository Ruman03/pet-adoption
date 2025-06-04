<?php
session_start();

require_once '../../config/db.php';
require_once '../../utils/response.php';

// Allow only DELETE requests
if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    send_response(['error' => 'Invalid request method. Use DELETE.'], 405);
    exit;
}

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    send_response(['error' => 'Forbidden: Only admins can delete shelters.'], 403);
    exit;
}

// Get Shelter ID from query string
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    send_response(['error' => 'Shelter ID is required in the URL and must be a number.'], 400);
    exit;
}
$shelter_id = intval($_GET['id']);

$link = get_db_connection();

// Check if the shelter exists
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

// Before deleting a shelter, consider implications: what happens to pets associated with this shelter?
// The schema sets shelter_id in `pets` to NULL ON DELETE. This is handled by the DB.
// If other behavior is desired (e.g., prevent deletion if pets are assigned), add checks here.

$sql = "DELETE FROM shelters WHERE id = ?";

if ($stmt = mysqli_prepare($link, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $shelter_id);

    if (mysqli_stmt_execute($stmt)) {
        if (mysqli_stmt_affected_rows($stmt) > 0) {
            send_response(['message' => 'Shelter deleted successfully.'], 200);
        } else {
            // Should not happen if existence check passed
            send_response(['error' => 'Failed to delete shelter or shelter not found.'], 404);
        }
    } else {
        send_response(['error' => 'Failed to delete shelter. ' . mysqli_stmt_error($stmt)], 500);
    }
    mysqli_stmt_close($stmt);
} else {
    send_response(['error' => 'Database error: Could not prepare statement. ' . mysqli_error($link)], 500);
}

close_db_connection($link);
?>
