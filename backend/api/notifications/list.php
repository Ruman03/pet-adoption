<?php
require_once '../../config/db.php';
require_once '../../utils/response.php';

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

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

// Get query parameters
$unread_only = isset($_GET['unread_only']) ? (bool)$_GET['unread_only'] : false;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
$type = $_GET['type'] ?? null;

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Build query with filters
    $where_conditions = ["user_id = ?"];
    $params = [$user_id];
    
    if ($unread_only) {
        $where_conditions[] = "is_read = 0";
    }
    
    if ($type) {
        $where_conditions[] = "type = ?";
        $params[] = $type;
    }
    
    $where_clause = "WHERE " . implode(" AND ", $where_conditions);
    
    // Get total count
    $count_query = "SELECT COUNT(*) as total FROM notifications $where_clause";
    $count_stmt = $db->prepare($count_query);
    $count_stmt->execute($params);
    $total_count = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get notifications
    $params[] = $limit;
    $params[] = $offset;
    
    $query = "SELECT 
                id,
                title,
                message,
                type,
                is_read,
                created_at
              FROM notifications 
              $where_clause
              ORDER BY created_at DESC
              LIMIT ? OFFSET ?";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    
    $notifications = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Convert values
        $row['id'] = (int)$row['id'];
        $row['is_read'] = (bool)$row['is_read'];
        
        // Format type for display
        $row['type_display'] = ucwords(str_replace('_', ' ', $row['type']));
        
        $notifications[] = $row;
    }
    
    // Get unread count
    $unread_query = "SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = 0";
    $unread_stmt = $db->prepare($unread_query);
    $unread_stmt->bindParam(1, $user_id);
    $unread_stmt->execute();
    $unread_count = $unread_stmt->fetch(PDO::FETCH_ASSOC)['unread_count'];
    
    sendResponse(200, 'Notifications retrieved successfully', [
        'total_count' => (int)$total_count,
        'unread_count' => (int)$unread_count,
        'current_page_count' => count($notifications),
        'offset' => $offset,
        'limit' => $limit,
        'notifications' => $notifications
    ]);
    
} catch (PDOException $e) {
    sendResponse(500, 'Database error: ' . $e->getMessage());
} catch (Exception $e) {
    sendResponse(500, 'Server error: ' . $e->getMessage());
}
?>
