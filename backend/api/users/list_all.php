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
if (!$user_data || !isset($user_data['user_id']) || !isset($user_data['role'])) {
    sendResponse(401, 'Invalid token');
}

// Only admin and staff can list all users
if (!in_array($user_data['role'], ['admin', 'staff'])) {
    sendResponse(403, 'Insufficient permissions');
}

// Get query parameters
$role = $_GET['role'] ?? null;
$search = $_GET['search'] ?? null;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Build query with optional filters
    $where_conditions = [];
    $params = [];
    
    if ($role) {
        $where_conditions[] = "role = ?";
        $params[] = $role;
    }
    
    if ($search) {
        $where_conditions[] = "(full_name LIKE ? OR email LIKE ?)";
        $search_term = "%{$search}%";
        $params[] = $search_term;
        $params[] = $search_term;
    }
    
    $where_clause = empty($where_conditions) ? "" : "WHERE " . implode(" AND ", $where_conditions);
    
    // Get total count
    $count_query = "SELECT COUNT(*) as total FROM users $where_clause";
    $count_stmt = $db->prepare($count_query);
    $count_stmt->execute($params);
    $total_count = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get users (excluding password)
    $params[] = $limit;
    $params[] = $offset;
    
    $query = "SELECT 
                id,
                full_name,
                email,
                phone,
                address,
                role,
                created_at,
                updated_at
              FROM users 
              $where_clause
              ORDER BY created_at DESC
              LIMIT ? OFFSET ?";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    
    $users = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Convert numeric values
        $row['id'] = (int)$row['id'];
        
        // Add user statistics if admin is viewing
        if ($user_data['role'] === 'admin') {
            // Get basic statistics for each user
            $stats_query = "SELECT 
                              (SELECT COUNT(*) FROM applications WHERE user_id = ?) as total_applications,
                              (SELECT COUNT(*) FROM foster_records WHERE user_id = ?) as total_foster_records,
                              (SELECT COUNT(*) FROM volunteer_applications WHERE user_id = ?) as total_volunteer_applications
                           ";
            $stats_stmt = $db->prepare($stats_query);
            $stats_stmt->bindParam(1, $row['id']);
            $stats_stmt->bindParam(2, $row['id']);
            $stats_stmt->bindParam(3, $row['id']);
            $stats_stmt->execute();
            $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
            
            $row['statistics'] = [
                'applications' => (int)$stats['total_applications'],
                'foster_records' => (int)$stats['total_foster_records'],
                'volunteer_applications' => (int)$stats['total_volunteer_applications']
            ];
        }
        
        $users[] = $row;
    }
    
    sendResponse(200, 'Users retrieved successfully', [
        'total_count' => (int)$total_count,
        'current_page_count' => count($users),
        'offset' => $offset,
        'limit' => $limit,
        'users' => $users
    ]);
    
} catch (PDOException $e) {
    sendResponse(500, 'Database error: ' . $e->getMessage());
} catch (Exception $e) {
    sendResponse(500, 'Server error: ' . $e->getMessage());
}
?>