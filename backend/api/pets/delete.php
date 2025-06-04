<?php
session_start(); // Required for checking user role

require_once '../../config/db.php';
require_once '../../utils/response.php';

// Allow only DELETE requests (or POST with _method=DELETE if preferred)
if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    // If using POST for DELETE: if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['_method']) || strtoupper($_POST['_method']) !== 'DELETE') {
    send_response(['error' => 'Invalid request method. Use DELETE for deleting pets.'], 405);
    exit;
}

// Check if user is logged in and is staff or admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['staff', 'admin'])) {
    send_response(['error' => 'Unauthorized: Only staff or admin can delete pets.'], 403);
    exit;
}

// Get Pet ID from query string
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    send_response(['error' => 'Pet ID is required in the URL and must be a number.'], 400);
    exit;
}
$pet_id = intval($_GET['id']);

// Prepare SQL statement to delete the pet
$sql = "DELETE FROM pets WHERE id = ?";

if ($stmt = mysqli_prepare($link, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $pet_id);

    if (mysqli_stmt_execute($stmt)) {
        if (mysqli_stmt_affected_rows($stmt) > 0) {
            send_response(['message' => 'Pet deleted successfully.'], 200);
        } else {
            send_response(['error' => 'Pet not found or already deleted.'], 404);
        }
    } else {
        send_response(['error' => 'Failed to delete pet. ' . mysqli_stmt_error($stmt)], 500);
    }
    mysqli_stmt_close($stmt);
} else {
    send_response(['error' => 'Database error: Could not prepare statement. ' . mysqli_error($link)], 500);
}

close_db_connection($link);
?>
