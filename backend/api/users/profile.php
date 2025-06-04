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

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get user profile information
    $query = "SELECT 
                id,
                name,
                email,
                phone,
                address,
                role,
                created_at,
                updated_at
              FROM users 
              WHERE id = ?";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(1, $user_id);
    $stmt->execute();
    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        sendResponse(404, 'User not found');
    }
    
    // Get additional statistics based on user role
    $stats = [];
    
    if ($user['role'] === 'user' || $user['role'] === 'volunteer') {
        // Get adoption applications count
        $app_query = "SELECT COUNT(*) as total_applications,
                             SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_applications
                      FROM applications WHERE user_id = ?";
        $app_stmt = $db->prepare($app_query);
        $app_stmt->bindParam(1, $user_id);
        $app_stmt->execute();
        $app_stats = $app_stmt->fetch(PDO::FETCH_ASSOC);
        $stats['applications'] = $app_stats;
        
        // Get foster records count
        $foster_query = "SELECT COUNT(*) as total_fosters,
                                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_fosters
                         FROM foster_records WHERE user_id = ?";
        $foster_stmt = $db->prepare($foster_query);
        $foster_stmt->bindParam(1, $user_id);
        $foster_stmt->execute();
        $foster_stats = $foster_stmt->fetch(PDO::FETCH_ASSOC);
        $stats['foster_records'] = $foster_stats;
        
        // Get favorites count
        $fav_query = "SELECT COUNT(*) as total_favorites FROM favorites WHERE user_id = ?";
        $fav_stmt = $db->prepare($fav_query);
        $fav_stmt->bindParam(1, $user_id);
        $fav_stmt->execute();
        $fav_stats = $fav_stmt->fetch(PDO::FETCH_ASSOC);
        $stats['favorites'] = $fav_stats;
    }
    
    if ($user['role'] === 'volunteer') {
        // Get volunteer tasks count
        $task_query = "SELECT COUNT(*) as total_tasks,
                              SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_tasks
                       FROM volunteer_tasks WHERE assigned_to = ?";
        $task_stmt = $db->prepare($task_query);
        $task_stmt->bindParam(1, $user_id);
        $task_stmt->execute();
        $task_stats = $task_stmt->fetch(PDO::FETCH_ASSOC);
        $stats['volunteer_tasks'] = $task_stats;
    }
    
    if (in_array($user['role'], ['staff', 'admin', 'vet'])) {
        // Get pets managed count
        $pet_query = "SELECT COUNT(*) as total_pets FROM pets";
        $pet_stmt = $db->prepare($pet_query);
        $pet_stmt->execute();
        $pet_stats = $pet_stmt->fetch(PDO::FETCH_ASSOC);
        $stats['pets_managed'] = $pet_stats;
    }
    
    $response = [
        'user' => $user,
        'statistics' => $stats
    ];
    
    sendResponse(200, 'Profile retrieved successfully', $response);
    
} catch (PDOException $e) {
    sendResponse(500, 'Database error: ' . $e->getMessage());
} catch (Exception $e) {
    sendResponse(500, 'Server error: ' . $e->getMessage());
}
?>
