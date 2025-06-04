<?php
require_once '../../config/db.php';
require_once '../../utils/response.php';

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(405, 'Method not allowed');
}

// Verify authentication
$headers = apache_request_headers();
if (!isset($headers['Authorization'])) {
    sendResponse(401, 'Authorization header missing');
}

// Extract user info from token (simplified - in production use JWT properly)
$auth_header = $headers['Authorization'];
if (!preg_match('/Bearer\s(\S+)/', $auth_header, $matches)) {
    sendResponse(401, 'Invalid authorization format');
}

$token = $matches[1];
$user_data = json_decode(base64_decode($token), true);
if (!$user_data || !isset($user_data['user_id'])) {
    sendResponse(401, 'Invalid token');
}

try {
    // In a real application, you would:
    // 1. Add the token to a blacklist
    // 2. Update last_logout_at in the database
    // 3. Clear any session data
    
    $database = new Database();
    $db = $database->getConnection();
    
    // Update user's last logout time
    $query = "UPDATE users SET updated_at = NOW() WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->bindParam(1, $user_data['user_id']);
    $stmt->execute();
    
    sendResponse(200, 'Logout successful', [
        'message' => 'You have been successfully logged out',
        'user_id' => $user_data['user_id']
    ]);
    
} catch (PDOException $e) {
    sendResponse(500, 'Database error: ' . $e->getMessage());
} catch (Exception $e) {
    sendResponse(500, 'Server error: ' . $e->getMessage());
}
?>