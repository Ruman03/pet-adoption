<?php
// Add CORS headers for cross-origin requests
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Allow only PUT or POST requests
if (!in_array($_SERVER['REQUEST_METHOD'], ['PUT', 'POST'])) {
    http_response_code(405);
    echo json_encode(['error' => 'Invalid request method. Use PUT or POST.']);
    exit;
}

// Get Task ID from query string
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Task ID is required in the URL and must be a number.']);
    exit;
}
$task_id = intval($_GET['id']);

try {
    // For demo purposes, simulate task assignment
    // In a real system, you would update a database table
    
    $response = [
        'success' => true,
        'message' => 'Task assigned successfully',
        'task_id' => $task_id,
        'assigned_to' => 7, // Default volunteer user_id
        'assigned_at' => date('Y-m-d H:i:s')
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to assign task: ' . $e->getMessage()]);
}
    if ($task_row = mysqli_fetch_assoc($check_result)) {
        $current_status = $task_row['status'];
        $current_assigned_to = $task_row['assigned_to'];
    } else {
        send_response(['error' => 'Task not found.'], 404);
        mysqli_stmt_close($check_stmt);
        close_db_connection($link);
        exit;
    }
    mysqli_stmt_close($check_stmt);
} else {
    send_response(['error' => 'Database error: Could not check task existence. ' . mysqli_error($link)], 500);
    close_db_connection($link);
    exit;
}

// Determine action
$action = $input['action'] ?? 'assign';

if ($action === 'assign') {
    // Assign task to volunteer
    $volunteer_id = $input['volunteer_id'] ?? $user_id; // Default to self-assignment
    
    // Only volunteers can self-assign, staff/admin can assign to others
    if ($user_role === 'volunteer' && $volunteer_id != $user_id) {
        send_response(['error' => 'Volunteers can only assign tasks to themselves.'], 403);
        close_db_connection($link);
        exit;
    }
    
    if (!in_array($user_role, ['volunteer', 'staff', 'admin'])) {
        send_response(['error' => 'Only volunteers, staff, or admins can assign tasks.'], 403);
        close_db_connection($link);
        exit;
    }
    
    if ($current_status !== 'open') {
        send_response(['error' => 'Task is not available for assignment. Current status: ' . $current_status], 400);
        close_db_connection($link);
        exit;
    }
    
    $update_sql = "UPDATE volunteer_tasks SET assigned_to = ?, status = 'assigned', assigned_at = NOW() WHERE id = ?";
    $success_message = 'Task assigned successfully.';
    
} elseif ($action === 'start') {
    // Start working on task (change status to in_progress)
    if ($current_assigned_to != $user_id && !in_array($user_role, ['staff', 'admin'])) {
        send_response(['error' => 'You can only start tasks assigned to you.'], 403);
        close_db_connection($link);
        exit;
    }
    
    if ($current_status !== 'assigned') {
        send_response(['error' => 'Task must be assigned before starting. Current status: ' . $current_status], 400);
        close_db_connection($link);
        exit;
    }
    
    $update_sql = "UPDATE volunteer_tasks SET status = 'in_progress' WHERE id = ?";
    $success_message = 'Task started successfully.';
    $volunteer_id = null; // No need to update assigned_to
    
} elseif ($action === 'complete') {
    // Complete task
    if ($current_assigned_to != $user_id && !in_array($user_role, ['staff', 'admin'])) {
        send_response(['error' => 'You can only complete tasks assigned to you.'], 403);
        close_db_connection($link);
        exit;
    }
    
    if (!in_array($current_status, ['assigned', 'in_progress'])) {
        send_response(['error' => 'Task must be assigned or in progress to be completed. Current status: ' . $current_status], 400);
        close_db_connection($link);
        exit;
    }
    
    $update_sql = "UPDATE volunteer_tasks SET status = 'completed', completed_at = NOW() WHERE id = ?";
    $success_message = 'Task completed successfully.';
    $volunteer_id = null; // No need to update assigned_to
    
} else {
    send_response(['error' => 'Invalid action. Use assign, start, or complete.'], 400);
    close_db_connection($link);
    exit;
}

// Execute update
if ($stmt = mysqli_prepare($link, $update_sql)) {
    if ($volunteer_id !== null) {
        mysqli_stmt_bind_param($stmt, "ii", $volunteer_id, $task_id);
    } else {
        mysqli_stmt_bind_param($stmt, "i", $task_id);
    }
    
    if (mysqli_stmt_execute($stmt)) {
        if (mysqli_stmt_affected_rows($stmt) > 0) {
            send_response(['message' => $success_message], 200);
        } else {
            send_response(['message' => 'No changes made to the task.'], 200);
        }
    } else {
        send_response(['error' => 'Failed to update task. ' . mysqli_stmt_error($stmt)], 500);
    }
    mysqli_stmt_close($stmt);
} else {
    send_response(['error' => 'Database error: Could not prepare statement. ' . mysqli_error($link)], 500);
}

close_db_connection($link);
?>
