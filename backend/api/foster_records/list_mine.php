<?php
session_start();

require_once '../../config/db.php';
require_once '../../utils/response.php';

// Allow only GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_response(['error' => 'Invalid request method. Use GET.'], 405);
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    send_response(['error' => 'Unauthorized: You must be logged in to view your foster records.'], 401);
    exit;
}

$link = get_db_connection();
$user_id = $_SESSION['user_id'];

// SQL to get foster records for the logged-in user
// Joining with pets table to get pet's name and species for better display
$sql = "SELECT 
            fr.id, 
            fr.pet_id, 
            p.name AS pet_name, 
            p.species AS pet_species,
            fr.application_date, 
            fr.start_date, 
            fr.end_date, 
            fr.status, 
            fr.notes,
            fr.approved_by,
            fr.approved_at
        FROM 
            foster_records fr
        JOIN 
            pets p ON fr.pet_id = p.id
        WHERE 
            fr.foster_parent_id = ?
        ORDER BY 
            fr.application_date DESC";

if ($stmt = mysqli_prepare($link, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $foster_records = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $foster_records[] = $row;
    }

    send_response($foster_records, 200);
    mysqli_stmt_close($stmt);
} else {
    send_response(['error' => 'Database error: Could not retrieve your foster records. ' . mysqli_error($link)], 500);
}

close_db_connection($link);
?>
