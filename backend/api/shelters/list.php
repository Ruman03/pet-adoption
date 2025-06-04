<?php
require_once '../../config/db.php';
require_once '../../utils/response.php';

// Allow only GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_response(['error' => 'Invalid request method. Use GET.'], 405);
    exit;
}

// No authentication required to list shelters, they are public information

$link = get_db_connection();

$sql = "SELECT id, name, address, phone, email, website, operating_hours, created_at FROM shelters ORDER BY name";

if ($result = mysqli_query($link, $sql)) {
    $shelters = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $shelters[] = $row;
    }
    send_response($shelters, 200);
    mysqli_free_result($result);
} else {
    send_response(['error' => 'Database error: Could not retrieve shelters. ' . mysqli_error($link)], 500);
}

close_db_connection($link);
?>
