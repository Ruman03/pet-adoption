<?php
require_once '../../config/db.php';
require_once '../../utils/response.php';
require_once '../../utils/validator.php';

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: PUT");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
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

// Get JSON input
$input = json_decode(file_get_contents("php://input"), true);

// Check if marking all as read or specific notifications
$mark_all = isset($input['mark_all']) ? (bool)$input['mark_all'] : false;
$notification_ids = $input['notification_ids'] ?? [];

if (!$mark_all && empty($notification_ids)) {
    sendResponse(400, 'Either mark_all must be true or notification_ids must be provided');
}

if (!$mark_all) {
    // Validate notification IDs
    $validator = new Validator();
    $validator->array($notification_ids, 'notification_ids');
    
    foreach ($notification_ids as $id) {
        if (!is_numeric($id)) {
            $validator->addError('notification_ids', 'All notification IDs must be numeric');
            break;
        }
    }
    
    if ($validator->hasErrors()) {
        sendResponse(400, 'Validation failed', $validator->getErrors());
    }
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if ($mark_all) {
        // Mark all notifications as read for the user
        $query = "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0";
        $stmt = $db->prepare($query);
        $stmt->bindParam(1, $user_id);
        
        if (!$stmt->execute()) {
            sendResponse(500, 'Failed to mark notifications as read');
        }
        
        $affected_rows = $stmt->rowCount();
        
        sendResponse(200, 'All notifications marked as read', [
            'marked_count' => $affected_rows,
            'user_id' => $user_id
        ]);
        
    } else {
        // Mark specific notifications as read
        $placeholders = str_repeat('?,', count($notification_ids) - 1) . '?';
        $query = "UPDATE notifications SET is_read = 1 
                 WHERE user_id = ? AND id IN ($placeholders) AND is_read = 0";
        
        $params = array_merge([$user_id], $notification_ids);
        $stmt = $db->prepare($query);
        
        if (!$stmt->execute($params)) {
            sendResponse(500, 'Failed to mark notifications as read');
        }
        
        $affected_rows = $stmt->rowCount();
        
        sendResponse(200, 'Notifications marked as read', [
            'marked_count' => $affected_rows,
            'notification_ids' => $notification_ids,
            'user_id' => $user_id
        ]);
    }
    
} catch (PDOException $e) {
    sendResponse(500, 'Database error: ' . $e->getMessage());
} catch (Exception $e) {
    sendResponse(500, 'Server error: ' . $e->getMessage());
}
?>
