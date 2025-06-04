<?php
session_start();

require_once '../../config/db.php';
require_once '../../utils/response.php';
require_once '../../utils/validator.php';

// Allow only POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_response(['error' => 'Invalid request method. Use POST.'], 405);
    exit;
}

// Check if user is logged in and is staff or admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['staff', 'admin'])) {
    send_response(['error' => 'Forbidden: Only staff or admins can create volunteer tasks.'], 403);
    exit;
}

// Get input data from the request body
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    send_response(['error' => 'Invalid JSON input.'], 400);
    exit;
}

// Validation rules
$rules = [
    'title' => 'required|string|max:255',
    'description' => 'optional|string',
    'shelter_id' => 'optional|integer',
    'required_skills' => 'optional|string',
    'urgency' => 'optional|in:low,medium,high',
    'task_date' => 'optional|datetime', // Assuming validator has a datetime rule like YYYY-MM-DD HH:MM:SS
    'status' => 'optional|in:open,assigned,in_progress,completed,cancelled'
];

// Add a datetime validation rule to validator.php if it doesn't exist
// For now, we assume it exists or dates are passed in correct SQL format string.

$errors = Validator::validate($input, $rules);

if (!empty($errors)) {
    send_response(['error' => 'Validation failed', 'details' => $errors], 400);
    exit;
}

$link = get_db_connection();

// Check if shelter_id exists if provided
if (isset($input['shelter_id']) && $input['shelter_id'] !== null) {
    $shelter_check_sql = "SELECT id FROM shelters WHERE id = ?";
    if ($shelter_stmt = mysqli_prepare($link, $shelter_check_sql)) {
        mysqli_stmt_bind_param($shelter_stmt, "i", $input['shelter_id']);
        mysqli_stmt_execute($shelter_stmt);
        $shelter_result = mysqli_stmt_get_result($shelter_stmt);
        if (mysqli_num_rows($shelter_result) == 0) {
            send_response(['error' => 'Validation failed', 'details' => ['shelter_id' => 'Shelter not found.']], 400);
            mysqli_stmt_close($shelter_stmt);
            close_db_connection($link);
            exit;
        }
        mysqli_stmt_close($shelter_stmt);
    } else {
        send_response(['error' => 'Database error: Could not verify shelter.'], 500);
        close_db_connection($link);
        exit;
    }
}

$title = $input['title'];
$description = $input['description'] ?? null;
$shelter_id = isset($input['shelter_id']) && $input['shelter_id'] !== '' ? (int)$input['shelter_id'] : null;
$required_skills = $input['required_skills'] ?? null;
$urgency = $input['urgency'] ?? 'medium';
$task_date = $input['task_date'] ?? null; // Validator should ensure format or handle conversion
$status = $input['status'] ?? 'open';
$created_by = $_SESSION['user_id'];

$sql = "INSERT INTO volunteer_tasks (title, description, shelter_id, required_skills, urgency, task_date, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

if ($stmt = mysqli_prepare($link, $sql)) {
    mysqli_stmt_bind_param(
        $stmt,
        "ssissssi",
        $title,
        $description,
        $shelter_id,
        $required_skills,
        $urgency,
        $task_date,
        $status,
        $created_by
    );

    if (mysqli_stmt_execute($stmt)) {
        $new_task_id = mysqli_insert_id($link);
        send_response(['message' => 'Volunteer task created successfully.', 'task_id' => $new_task_id], 201);
    } else {
        send_response(['error' => 'Failed to create volunteer task. ' . mysqli_stmt_error($stmt)], 500);
    }
    mysqli_stmt_close($stmt);
} else {
    send_response(['error' => 'Database error: Could not prepare statement. ' . mysqli_error($link)], 500);
}

close_db_connection($link);
?>
