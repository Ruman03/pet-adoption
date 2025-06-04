<?php
session_start();

require_once '../../config/db.php';
require_once '../../utils/response.php';

// Allow only DELETE requests
if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    send_response(['error' => 'Invalid request method. Use DELETE.'], 405);
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    send_response(['error' => 'Unauthorized: You must be logged in.'], 401);
    exit;
}

// Check user role: 'staff', 'admin', or 'vet' can delete medical records
$allowed_roles = ['staff', 'admin', 'vet'];
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles)) {
    send_response(['error' => 'Forbidden: You do not have permission to delete medical records.'], 403);
    exit;
}

// Get record ID from query string
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    send_response(['error' => 'Medical Record ID is required in the URL and must be a number.'], 400);
    exit;
}
$record_id = intval($_GET['id']);

$link = get_db_connection();

// Check if the medical record exists before attempting to delete
$check_sql = "SELECT id FROM medical_records WHERE id = ?";
if ($check_stmt = mysqli_prepare($link, $check_sql)) {
    mysqli_stmt_bind_param($check_stmt, "i", $record_id);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    if (mysqli_num_rows($check_result) == 0) {
        send_response(['error' => 'Medical record not found.'], 404);
        mysqli_stmt_close($check_stmt);
        close_db_connection($link);
        exit;
    }
    mysqli_stmt_close($check_stmt);
} else {
    send_response(['error' => 'Database error: Could not check record existence. ' . mysqli_error($link)], 500);
    close_db_connection($link);
    exit;
}

// Proceed with deletion
$sql = "DELETE FROM medical_records WHERE id = ?";

if ($stmt = mysqli_prepare($link, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $record_id);

    if (mysqli_stmt_execute($stmt)) {
        if (mysqli_stmt_affected_rows($stmt) > 0) {
            send_response(['message' => 'Medical record deleted successfully.'], 200);
        } else {
            // This case should ideally not be reached if the existence check passed
            // and the ID is correct, but it's a safeguard.
            send_response(['error' => 'Failed to delete medical record or record not found.'], 404); 
        }
    } else {
        send_response(['error' => 'Failed to delete medical record. ' . mysqli_stmt_error($stmt)], 500);
    }
    mysqli_stmt_close($stmt);
} else {
    send_response(['error' => 'Database error: Could not prepare statement. ' . mysqli_error($link)], 500);
}

close_db_connection($link);
?>
