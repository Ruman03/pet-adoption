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

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    send_response(['error' => 'Unauthorized: You must be logged in to apply for volunteering.'], 401);
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
    'application_type' => 'required|in:animal_care,event_support,administrative,transportation,other',
    'availability' => 'required|string',
    'experience' => 'optional|string',
    'skills' => 'optional|string',
    'motivation' => 'required|string',
    'emergency_contact_name' => 'required|string|max:100',
    'emergency_contact_phone' => 'required|phone'
];

$errors = Validator::validate($input, $rules);

if (!empty($errors)) {
    send_response(['error' => 'Validation failed', 'details' => $errors], 400);
    exit;
}

$link = get_db_connection();

// Check if user already has a pending or approved application
$check_sql = "SELECT id, status FROM volunteer_applications WHERE user_id = ? AND status IN ('pending', 'approved', 'interview_scheduled')";
if ($check_stmt = mysqli_prepare($link, $check_sql)) {
    mysqli_stmt_bind_param($check_stmt, "i", $_SESSION['user_id']);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    if ($existing = mysqli_fetch_assoc($check_result)) {
        send_response(['error' => 'You already have an active volunteer application with status: ' . $existing['status']], 400);
        mysqli_stmt_close($check_stmt);
        close_db_connection($link);
        exit;
    }
    mysqli_stmt_close($check_stmt);
} else {
    send_response(['error' => 'Database error: Could not check existing applications.'], 500);
    close_db_connection($link);
    exit;
}

$sql = "INSERT INTO volunteer_applications (user_id, application_type, availability, experience, skills, motivation, emergency_contact_name, emergency_contact_phone) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

if ($stmt = mysqli_prepare($link, $sql)) {
    mysqli_stmt_bind_param(
        $stmt,
        "isssssss",
        $_SESSION['user_id'],
        $input['application_type'],
        $input['availability'],
        $input['experience'] ?? null,
        $input['skills'] ?? null,
        $input['motivation'],
        $input['emergency_contact_name'],
        $input['emergency_contact_phone']
    );

    if (mysqli_stmt_execute($stmt)) {
        $new_application_id = mysqli_insert_id($link);
        send_response(['message' => 'Volunteer application submitted successfully.', 'application_id' => $new_application_id], 201);
    } else {
        send_response(['error' => 'Failed to submit volunteer application. ' . mysqli_stmt_error($stmt)], 500);
    }
    mysqli_stmt_close($stmt);
} else {
    send_response(['error' => 'Database error: Could not prepare statement. ' . mysqli_error($link)], 500);
}

close_db_connection($link);
?>
