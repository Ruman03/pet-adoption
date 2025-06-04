<?php
session_start();

require_once '../../config/db.php';
require_once '../../utils/response.php';

// Allow only GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_response(['error' => 'Invalid request method. Use GET.'], 405);
    exit;
}

// Check if user is logged in and is staff or admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['staff', 'admin'])) {
    send_response(['error' => 'Forbidden: Only staff or admins can view all foster records.'], 403);
    exit;
}

$link = get_db_connection();

// SQL to get all foster records, joining with pets and users tables for more details
$sql = "SELECT 
            fr.id, 
            fr.pet_id, 
            p.name AS pet_name, 
            p.species AS pet_species,
            fr.foster_parent_id,
            u.username AS foster_parent_username,
            u.email AS foster_parent_email,
            fr.application_date, 
            fr.start_date, 
            fr.end_date, 
            fr.status, 
            fr.notes,
            fr.approved_by,
            admin_approver.username AS approver_username,
            fr.approved_at
        FROM 
            foster_records fr
        JOIN 
            pets p ON fr.pet_id = p.id
        JOIN
            users u ON fr.foster_parent_id = u.id
        LEFT JOIN
            users admin_approver ON fr.approved_by = admin_approver.id
        ORDER BY 
            fr.application_date DESC";

if ($result = mysqli_query($link, $sql)) {
    $foster_records = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $foster_records[] = $row;
    }
    send_response($foster_records, 200);
    mysqli_free_result($result);
} else {
    send_response(['error' => 'Database error: Could not retrieve foster records. ' . mysqli_error($link)], 500);
}

close_db_connection($link);
?>
