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

// Get query parameters
$category = $_GET['category'] ?? null;
$difficulty = $_GET['difficulty'] ?? null;
$required_only = isset($_GET['required_only']) ? (bool)$_GET['required_only'] : false;

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Build query with optional filters
    $where_conditions = [];
    $params = [];
    
    if ($category) {
        $where_conditions[] = "tm.category = ?";
        $params[] = $category;
    }
    
    if ($difficulty) {
        $where_conditions[] = "tm.difficulty = ?";
        $params[] = $difficulty;
    }
    
    if ($required_only) {
        $where_conditions[] = "tm.is_required = 1";
    }
    
    $where_clause = empty($where_conditions) ? "" : "WHERE " . implode(" AND ", $where_conditions);
    
    // Get training modules with progress for current user
    $query = "SELECT 
                tm.id,
                tm.title,
                tm.description,
                tm.duration_minutes,
                tm.difficulty,
                tm.category,
                tm.prerequisites,
                tm.is_required,
                tm.created_at,
                u.name as created_by_name,
                CASE 
                    WHEN tp.id IS NOT NULL THEN tp.status
                    ELSE 'not_started'
                END as progress_status,
                tp.progress_percentage,
                tp.completed_at,
                tp.started_at
              FROM training_modules tm
              LEFT JOIN users u ON tm.created_by = u.id
              LEFT JOIN training_progress tp ON tm.id = tp.module_id AND tp.user_id = ?
              $where_clause
              ORDER BY tm.is_required DESC, tm.category, tm.title";
    
    array_unshift($params, $user_data['user_id']); // Add user_id as first parameter
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    
    $modules = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Convert boolean and numeric values
        $row['id'] = (int)$row['id'];
        $row['duration_minutes'] = (int)$row['duration_minutes'];
        $row['is_required'] = (bool)$row['is_required'];
        $row['progress_percentage'] = $row['progress_percentage'] ? (int)$row['progress_percentage'] : 0;
        
        $modules[] = $row;
    }
    
    sendResponse(200, 'Training modules retrieved successfully', [
        'total_count' => count($modules),
        'modules' => $modules
    ]);
    
} catch (PDOException $e) {
    sendResponse(500, 'Database error: ' . $e->getMessage());
} catch (Exception $e) {
    sendResponse(500, 'Server error: ' . $e->getMessage());
}
?>
