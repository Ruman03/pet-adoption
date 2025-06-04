<?php
require_once '../../config/db.php';
require_once '../../utils/response.php';
require_once '../../utils/validator.php';

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: DELETE");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    sendResponse(405, 'Method not allowed');
}

// Verify authentication
$headers = apache_request_headers();
if (!isset($headers['Authorization'])) {
    sendResponse(401, 'Authorization header missing');
}

// Extract user info from token
$auth_header = $headers['Authorization'];
if (!preg_match('/Bearer\s(\S+)/', $auth_header, $matches)) {
    sendResponse(401, 'Invalid authorization format');
}

$token = $matches[1];
$user_data = json_decode(base64_decode($token), true);
if (!$user_data || !isset($user_data['user_id'])) {
    sendResponse(401, 'Invalid token');
}

$user_id = $user_data['user_id'];

// Get pet_id from query parameter
if (!isset($_GET['pet_id'])) {
    sendResponse(400, 'Pet ID is required');
}

$pet_id = $_GET['pet_id'];

// Validate pet_id
$validator = new Validator();
$validator->numeric($pet_id, 'pet_id');

if ($validator->hasErrors()) {
    sendResponse(400, 'Invalid pet ID');
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Check if favorite exists
    $check_query = "SELECT f.id, p.name as pet_name 
                    FROM favorites f
                    LEFT JOIN pets p ON f.pet_id = p.id
                    WHERE f.user_id = ? AND f.pet_id = ?";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(1, $user_id);
    $check_stmt->bindParam(2, $pet_id);
    $check_stmt->execute();
    
    $favorite = $check_stmt->fetch(PDO::FETCH_ASSOC);
    if (!$favorite) {
        sendResponse(404, 'Pet not found in your favorites');
    }
    
    // Remove from favorites
    $delete_query = "DELETE FROM favorites WHERE user_id = ? AND pet_id = ?";
    $delete_stmt = $db->prepare($delete_query);
    $delete_stmt->bindParam(1, $user_id);
    $delete_stmt->bindParam(2, $pet_id);
    
    if (!$delete_stmt->execute()) {
        sendResponse(500, 'Failed to remove pet from favorites');
    }
    
    sendResponse(200, 'Pet removed from favorites successfully', [
        'pet_id' => $pet_id,
        'pet_name' => $favorite['pet_name'],
        'user_id' => $user_id
    ]);
    
} catch (PDOException $e) {
    sendResponse(500, 'Database error: ' . $e->getMessage());
} catch (Exception $e) {
    sendResponse(500, 'Server error: ' . $e->getMessage());
}
?>
