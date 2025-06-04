<?php
session_start();

require_once '../../config/db.php';
require_once '../../utils/response.php';

// Allow only GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_response(['error' => 'Invalid request method.'], 405);
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    send_response(['error' => 'Unauthorized: You must be logged in to view your applications.'], 401);
    exit;
}

$user_id = $_SESSION['user_id'];

// Prepare SQL to fetch applications for the logged-in user
// Join with pets table to get pet details (e.g., name, image_url)
$sql = "SELECT 
            a.id as application_id, 
            a.pet_id, 
            p.name as pet_name, 
            p.image_url as pet_image_url, 
            a.status as application_status, 
            a.application_date, 
            a.notes
        FROM applications a
        JOIN pets p ON a.pet_id = p.id
        WHERE a.user_id = ?
        ORDER BY a.application_date DESC";

if ($stmt = mysqli_prepare($link, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $applications = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $applications[] = $row;
    }
    
    send_response($applications, 200);
    mysqli_stmt_close($stmt);
} else {
    send_response(['error' => 'Database error: Could not prepare statement. ' . mysqli_error($link)], 500);
}

close_db_connection($link);
?>
