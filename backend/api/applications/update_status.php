<?php
session_start();

require_once '../../config/db.php';
require_once '../../utils/response.php';
require_once '../../utils/validator.php';

// Allow only PUT or POST requests (PUT is more semantically correct for updates)
if (!in_array($_SERVER['REQUEST_METHOD'], ['PUT', 'POST'])) {
    send_response(['error' => 'Invalid request method. Use PUT or POST.'], 405);
    exit;
}

// Check if user is logged in and is staff or admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['staff', 'admin'])) {
    send_response(['error' => 'Unauthorized: Only staff or admin can update application status.'], 403);
    exit;
}

// Get Application ID from query string
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    send_response(['error' => 'Application ID is required in the URL and must be a number.'], 400);
    exit;
}
$application_id = intval($_GET['id']);

// Get input data from the request body (assuming JSON input)
$input = json_decode(file_get_contents('php://input'), true);

// Validate input
$validation_rules = [
    'status' => 'required|in:approved,rejected,withdrawn,pending' // Define allowed statuses
];

$errors = Validator::validate($input, $validation_rules);

if (!empty($errors)) {
    send_response(['error' => 'Validation failed', 'details' => $errors], 400);
    exit;
}

$new_status = $input['status'];

// Begin transaction for atomicity if updating multiple tables (e.g., applications and pets)
mysqli_begin_transaction($link);

// Prepare SQL statement to update application status
$sql_update_app = "UPDATE applications SET status = ? WHERE id = ?";

if ($stmt_app = mysqli_prepare($link, $sql_update_app)) {
    mysqli_stmt_bind_param($stmt_app, "si", $new_status, $application_id);

    if (mysqli_stmt_execute($stmt_app)) {
        if (mysqli_stmt_affected_rows($stmt_app) > 0) {
            // If application is approved, consider updating the pet's status
            if ($new_status === 'approved') {
                // First, get the pet_id from the application
                $pet_id_sql = "SELECT pet_id FROM applications WHERE id = ?";
                if($pet_stmt = mysqli_prepare($link, $pet_id_sql)){
                    mysqli_stmt_bind_param($pet_stmt, "i", $application_id);
                    mysqli_stmt_execute($pet_stmt);
                    $pet_result = mysqli_stmt_get_result($pet_stmt);
                    $pet_data = mysqli_fetch_assoc($pet_result);
                    mysqli_stmt_close($pet_stmt);

                    if($pet_data && isset($pet_data['pet_id'])){
                        $pet_id_to_update = $pet_data['pet_id'];
                        $sql_update_pet = "UPDATE pets SET status = 'adopted' WHERE id = ? AND status = 'available'"; // Or 'pending' then 'adopted'
                        if ($stmt_pet = mysqli_prepare($link, $sql_update_pet)) {
                            mysqli_stmt_bind_param($stmt_pet, "i", $pet_id_to_update);
                            if (!mysqli_stmt_execute($stmt_pet)) {
                                mysqli_rollback($link); // Rollback on pet update failure
                                send_response(['error' => 'Failed to update pet status. ' . mysqli_stmt_error($stmt_pet)], 500);
                                exit;
                            }
                            // Optionally, reject other pending applications for the same pet here
                            mysqli_stmt_close($stmt_pet);
                        } else {
                            mysqli_rollback($link);
                            send_response(['error' => 'Database error: Could not prepare pet update statement.'], 500);
                            exit;
                        }
                    } else {
                         mysqli_rollback($link);
                         send_response(['error' => 'Could not retrieve pet ID for the application.'], 500);
                         exit;
                    }
                }
            } // Add similar logic if $new_status === 'rejected' or 'withdrawn' to make pet 'available' again if it was 'pending' due to this app.

            mysqli_commit($link); // Commit transaction
            send_response(['message' => 'Application status updated successfully.'], 200);
        } else {
            mysqli_rollback($link); // No rows affected, rollback
            send_response(['message' => 'Application status was already set to this value or application not found.'], 200); // Or 404
        }
    } else {
        mysqli_rollback($link);
        send_response(['error' => 'Failed to update application status. ' . mysqli_stmt_error($stmt_app)], 500);
    }
    mysqli_stmt_close($stmt_app);
} else {
    mysqli_rollback($link);
    send_response(['error' => 'Database error: Could not prepare application update statement. ' . mysqli_error($link)], 500);
}

close_db_connection($link);
?>
