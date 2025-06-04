<?php
require_once '../../config/db.php';
require_once '../../utils/response.php';

// Allow only GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_response(['error' => 'Invalid request method. Use GET.'], 405);
    exit;
}

// Get Shelter ID from query string
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    send_response(['error' => 'Shelter ID is required in the URL and must be a number.'], 400);
    exit;
}
$shelter_id = intval($_GET['id']);

// No authentication required to read a shelter, public information

$link = get_db_connection();

$sql = "SELECT id, name, address, phone, email, website, operating_hours, created_at FROM shelters WHERE id = ?";

if ($stmt = mysqli_prepare($link, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $shelter_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($shelter = mysqli_fetch_assoc($result)) {
        send_response($shelter, 200);
    } else {
        send_response(['error' => 'Shelter not found.'], 404);
    }
    mysqli_stmt_close($stmt);
} else {
    send_response(['error' => 'Database error: Could not prepare statement. ' . mysqli_error($link)], 500);
}

close_db_connection($link);
?>
