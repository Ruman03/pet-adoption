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

// Check if user is logged in and is staff or admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['staff', 'admin'])) {
    send_response(['error' => 'Forbidden: Only staff or admins can update foster record status.'], 403);
    exit;
}

// Get Foster Record ID from query string
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    send_response(['error' => 'Foster Record ID is required in the URL and must be a number.'], 400);
    exit;
}
$foster_record_id = intval($_GET['id']);

// Get input data from the request body
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['status'])) {
    send_response(['error' => 'Invalid input. Status is required.'], 400);
    exit;
}

// Validate status
$allowed_statuses = ['approved', 'rejected', 'active', 'completed', 'cancelled'];
if (!in_array($input['status'], $allowed_statuses)) {
    send_response(['error' => 'Invalid status value. Allowed values are: ' . implode(', ', $allowed_statuses)], 400);
    exit;
}

$new_status = $input['status'];
$admin_id = $_SESSION['user_id'];
$approved_at = ($new_status === 'approved' || $new_status === 'active') ? date('Y-m-d H:i:s') : null;

$link = get_db_connection();
mysqli_begin_transaction($link);

try {
    // Fetch the foster record to get pet_id
    $fr_sql = "SELECT pet_id, status FROM foster_records WHERE id = ?";
    $fr_stmt = mysqli_prepare($link, $fr_sql);
    mysqli_stmt_bind_param($fr_stmt, "i", $foster_record_id);
    mysqli_stmt_execute($fr_stmt);
    $fr_result = mysqli_stmt_get_result($fr_stmt);
    $foster_record = mysqli_fetch_assoc($fr_result);
    mysqli_stmt_close($fr_stmt);

    if (!$foster_record) {
        throw new Exception('Foster record not found.', 404);
    }

    $pet_id = $foster_record['pet_id'];
    $current_foster_status = $foster_record['status'];

    // Update foster record status
    $update_fr_sql = "UPDATE foster_records SET status = ?, approved_by = ?, approved_at = ? WHERE id = ?";
    $update_fr_stmt = mysqli_prepare($link, $update_fr_sql);
    mysqli_stmt_bind_param($update_fr_stmt, "sisi", $new_status, $admin_id, $approved_at, $foster_record_id);
    if (!mysqli_stmt_execute($update_fr_stmt)) {
        throw new Exception('Failed to update foster record status: ' . mysqli_stmt_error($update_fr_stmt));
    }
    mysqli_stmt_close($update_fr_stmt);

    // Update pet status if foster application is approved and pet is 'available' or if foster period ends/cancelled
    $pet_update_needed = false;
    $new_pet_status = '';

    if ($new_status === 'approved' || $new_status === 'active') {
        // Check current pet status before changing to 'fostered'
        $pet_status_sql = "SELECT status FROM pets WHERE id = ?";
        $pet_status_stmt = mysqli_prepare($link, $pet_status_sql);
        mysqli_stmt_bind_param($pet_status_stmt, "i", $pet_id);
        mysqli_stmt_execute($pet_status_stmt);
        $pet_status_result = mysqli_stmt_get_result($pet_status_stmt);
        $current_pet_info = mysqli_fetch_assoc($pet_status_result);
        mysqli_stmt_close($pet_status_stmt);

        if (!$current_pet_info) {
            throw new Exception('Pet associated with foster record not found.', 404);
        }
        // Only change to 'fostered' if pet is 'available' or already 'fostered' (e.g. extending existing foster)
        if ($current_pet_info['status'] === 'available' || $current_pet_info['status'] === 'fostered') {
             $pet_update_needed = true;
             $new_pet_status = 'fostered';
        } else if ($current_foster_status !== 'active' && $current_foster_status !== 'approved') { // if not already in a foster state
            // If trying to approve for a pet that is not available (e.g. adopted, pending other adoption)
            throw new Exception('Cannot approve foster for a pet that is currently not available (status: ' . $current_pet_info['status'] . ').', 409); // 409 Conflict
        }

    } elseif ($new_status === 'completed' || $new_status === 'rejected' || $new_status === 'cancelled') {
        // If a foster period ends, is rejected, or cancelled, set pet back to 'available'
        // Check if this was the active foster record for the pet
        if ($current_foster_status === 'active' || $current_foster_status === 'approved') {
            $pet_update_needed = true;
            $new_pet_status = 'available';
        }
    }

    if ($pet_update_needed) {
        $update_pet_sql = "UPDATE pets SET status = ? WHERE id = ?";
        $update_pet_stmt = mysqli_prepare($link, $update_pet_sql);
        mysqli_stmt_bind_param($update_pet_stmt, "si", $new_pet_status, $pet_id);
        if (!mysqli_stmt_execute($update_pet_stmt)) {
            throw new Exception('Failed to update pet status: ' . mysqli_stmt_error($update_pet_stmt));
        }
        mysqli_stmt_close($update_pet_stmt);
    }

    mysqli_commit($link);
    send_response(['message' => 'Foster record status updated successfully.' . ($pet_update_needed ? ' Pet status also updated to ' . $new_pet_status . '.' : '')], 200);

} catch (Exception $e) {
    mysqli_rollback($link);
    $error_code = $e->getCode() ?: 500; // Default to 500 if no code set
    if ($error_code < 400 || $error_code > 599) $error_code = 500; // Ensure valid HTTP status code range
    send_response(['error' => $e->getMessage()], $error_code);
}

close_db_connection($link);
?>
