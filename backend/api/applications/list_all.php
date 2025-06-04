<?php
session_start();

require_once '../../config/db.php';
require_once '../../utils/response.php';

// Allow only GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_response(['error' => 'Invalid request method.'], 405);
    exit;
}

// Check if user is logged in and is staff or admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['staff', 'admin'])) {
    send_response(['error' => 'Unauthorized: Only staff or admin can view all applications.'], 403);
    exit;
}

// Prepare SQL to fetch all applications
// Join with pets table to get pet details and users table to get adopter details
$sql = "SELECT 
            a.id as application_id, 
            a.user_id as adopter_id,
            u.username as adopter_username,
            u.full_name as adopter_full_name,
            u.email as adopter_email,
            a.pet_id, 
            p.name as pet_name, 
            p.image_url as pet_image_url, 
            a.status as application_status, 
            a.application_date, 
            a.notes
        FROM applications a
        JOIN pets p ON a.pet_id = p.id
        JOIN users u ON a.user_id = u.id
        ORDER BY a.application_date DESC";

// Optional: Add pagination later if the list becomes too long
// For example: You might want to add LIMIT and OFFSET clauses based on GET parameters

if ($stmt = mysqli_prepare($link, $sql)) {
    // No parameters to bind for fetching all
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
