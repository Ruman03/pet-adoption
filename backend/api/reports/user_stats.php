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
    
    $user_stats = [];
    
    // Get user's basic info
    $user_query = "SELECT name, email, role, created_at FROM users WHERE id = ?";
    $user_stmt = $db->prepare($user_query);
    $user_stmt->bindParam(1, $user_id);
    $user_stmt->execute();
    $user_info = $user_stmt->fetch(PDO::FETCH_ASSOC);
    
    $user_stats['user_info'] = $user_info;
    
    // Applications statistics
    $app_query = "SELECT 
                    COUNT(*) as total_applications,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_applications,
                    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_applications,
                    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_applications
                  FROM applications WHERE user_id = ?";
    $app_stmt = $db->prepare($app_query);
    $app_stmt->bindParam(1, $user_id);
    $app_stmt->execute();
    $user_stats['applications'] = $app_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Foster records statistics
    $foster_query = "SELECT 
                       COUNT(*) as total_foster_records,
                       SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_foster_records,
                       SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_foster_records
                     FROM foster_records WHERE user_id = ?";
    $foster_stmt = $db->prepare($foster_query);
    $foster_stmt->bindParam(1, $user_id);
    $foster_stmt->execute();
    $user_stats['foster_records'] = $foster_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Favorites count
    $fav_query = "SELECT COUNT(*) as total_favorites FROM favorites WHERE user_id = ?";
    $fav_stmt = $db->prepare($fav_query);
    $fav_stmt->bindParam(1, $user_id);
    $fav_stmt->execute();
    $user_stats['favorites'] = $fav_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Volunteer-specific statistics
    if (in_array($user_info['role'], ['volunteer'])) {
        // Volunteer applications
        $vol_app_query = "SELECT 
                            COUNT(*) as total_volunteer_applications,
                            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_volunteer_applications
                          FROM volunteer_applications WHERE user_id = ?";
        $vol_app_stmt = $db->prepare($vol_app_query);
        $vol_app_stmt->bindParam(1, $user_id);
        $vol_app_stmt->execute();
        $user_stats['volunteer_applications'] = $vol_app_stmt->fetch(PDO::FETCH_ASSOC);
        
        // Volunteer tasks
        $task_query = "SELECT 
                         COUNT(*) as total_tasks,
                         SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
                         SUM(CASE WHEN status IN ('assigned', 'in_progress') THEN 1 ELSE 0 END) as active_tasks
                       FROM volunteer_tasks WHERE assigned_to = ?";
        $task_stmt = $db->prepare($task_query);
        $task_stmt->bindParam(1, $user_id);
        $task_stmt->execute();
        $user_stats['volunteer_tasks'] = $task_stmt->fetch(PDO::FETCH_ASSOC);
        
        // Training progress
        $training_query = "SELECT 
                             COUNT(*) as total_training_modules,
                             SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_training,
                             SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_training
                           FROM training_progress WHERE user_id = ?";
        $training_stmt = $db->prepare($training_query);
        $training_stmt->bindParam(1, $user_id);
        $training_stmt->execute();
        $user_stats['training'] = $training_stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Recent activity
    $recent_apps_query = "SELECT COUNT(*) as recent_applications 
                         FROM applications 
                         WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    $recent_apps_stmt = $db->prepare($recent_apps_query);
    $recent_apps_stmt->bindParam(1, $user_id);
    $recent_apps_stmt->execute();
    $user_stats['recent_activity']['applications'] = $recent_apps_stmt->fetch(PDO::FETCH_ASSOC)['recent_applications'];
    
    // Appointments
    $appointment_query = "SELECT 
                            COUNT(*) as total_appointments,
                            SUM(CASE WHEN appointment_datetime >= NOW() AND status = 'scheduled' THEN 1 ELSE 0 END) as upcoming_appointments
                          FROM appointments WHERE user_id = ?";
    $appointment_stmt = $db->prepare($appointment_query);
    $appointment_stmt->bindParam(1, $user_id);
    $appointment_stmt->execute();
    $user_stats['appointments'] = $appointment_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Notifications
    $notification_query = "SELECT 
                             COUNT(*) as total_notifications,
                             SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread_notifications
                           FROM notifications WHERE user_id = ?";
    $notification_stmt = $db->prepare($notification_query);
    $notification_stmt->bindParam(1, $user_id);
    $notification_stmt->execute();
    $user_stats['notifications'] = $notification_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Convert all numeric strings to integers
    foreach ($user_stats as $category => &$stats) {
        if (is_array($stats)) {
            foreach ($stats as $key => &$value) {
                if (is_numeric($value)) {
                    $value = (int)$value;
                }
            }
        }
    }
    
    sendResponse(200, 'User statistics retrieved successfully', $user_stats);
    
} catch (PDOException $e) {
    sendResponse(500, 'Database error: ' . $e->getMessage());
} catch (Exception $e) {
    sendResponse(500, 'Server error: ' . $e->getMessage());
}
?>
