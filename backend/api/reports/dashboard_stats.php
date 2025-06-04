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

// Only staff, admin, and vet can access dashboard statistics
if (!in_array($user_data['role'], ['staff', 'admin', 'vet'])) {
    sendResponse(403, 'Insufficient permissions');
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $statistics = [];
    
    // Pet Statistics
    $pet_stats_query = "SELECT 
                          COUNT(*) as total_pets,
                          SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available_pets,
                          SUM(CASE WHEN status = 'adopted' THEN 1 ELSE 0 END) as adopted_pets,
                          SUM(CASE WHEN status = 'fostered' THEN 1 ELSE 0 END) as fostered_pets,
                          SUM(CASE WHEN status = 'medical_hold' THEN 1 ELSE 0 END) as medical_hold_pets
                        FROM pets";
    $pet_stats_stmt = $db->prepare($pet_stats_query);
    $pet_stats_stmt->execute();
    $statistics['pets'] = $pet_stats_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Application Statistics
    $app_stats_query = "SELECT 
                          COUNT(*) as total_applications,
                          SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_applications,
                          SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_applications,
                          SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_applications
                        FROM applications";
    $app_stats_stmt = $db->prepare($app_stats_query);
    $app_stats_stmt->execute();
    $statistics['applications'] = $app_stats_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Foster Statistics
    $foster_stats_query = "SELECT 
                             COUNT(*) as total_foster_records,
                             SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_foster_applications,
                             SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_foster_records,
                             SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_foster_records
                           FROM foster_records";
    $foster_stats_stmt = $db->prepare($foster_stats_query);
    $foster_stats_stmt->execute();
    $statistics['foster_records'] = $foster_stats_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Volunteer Statistics
    $volunteer_stats_query = "SELECT 
                                COUNT(*) as total_volunteer_applications,
                                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_volunteer_applications,
                                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_volunteer_applications
                              FROM volunteer_applications";
    $volunteer_stats_stmt = $db->prepare($volunteer_stats_query);
    $volunteer_stats_stmt->execute();
    $statistics['volunteer_applications'] = $volunteer_stats_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Volunteer Task Statistics
    $task_stats_query = "SELECT 
                           COUNT(*) as total_tasks,
                           SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open_tasks,
                           SUM(CASE WHEN status = 'assigned' THEN 1 ELSE 0 END) as assigned_tasks,
                           SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_tasks,
                           SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_tasks
                         FROM volunteer_tasks";
    $task_stats_stmt = $db->prepare($task_stats_query);
    $task_stats_stmt->execute();
    $statistics['volunteer_tasks'] = $task_stats_stmt->fetch(PDO::FETCH_ASSOC);
    
    // User Statistics
    $user_stats_query = "SELECT 
                           COUNT(*) as total_users,
                           SUM(CASE WHEN role = 'user' THEN 1 ELSE 0 END) as regular_users,
                           SUM(CASE WHEN role = 'volunteer' THEN 1 ELSE 0 END) as volunteers,
                           SUM(CASE WHEN role = 'staff' THEN 1 ELSE 0 END) as staff,
                           SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admins,
                           SUM(CASE WHEN role = 'vet' THEN 1 ELSE 0 END) as vets
                         FROM users";
    $user_stats_stmt = $db->prepare($user_stats_query);
    $user_stats_stmt->execute();
    $statistics['users'] = $user_stats_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Medical Records Statistics (for vet role)
    if ($user_data['role'] === 'vet' || $user_data['role'] === 'admin') {
        $medical_stats_query = "SELECT 
                                  COUNT(*) as total_medical_records,
                                  COUNT(DISTINCT pet_id) as pets_with_records,
                                  SUM(CASE WHEN record_type = 'vaccination' THEN 1 ELSE 0 END) as vaccinations,
                                  SUM(CASE WHEN record_type = 'checkup' THEN 1 ELSE 0 END) as checkups,
                                  SUM(CASE WHEN record_type = 'treatment' THEN 1 ELSE 0 END) as treatments
                                FROM medical_records";
        $medical_stats_stmt = $db->prepare($medical_stats_query);
        $medical_stats_stmt->execute();
        $statistics['medical_records'] = $medical_stats_stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Recent Activity (last 30 days)
    $recent_adoptions_query = "SELECT COUNT(*) as recent_adoptions 
                              FROM applications 
                              WHERE status = 'approved' AND updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    $recent_adoptions_stmt = $db->prepare($recent_adoptions_query);
    $recent_adoptions_stmt->execute();
    $statistics['recent_activity']['adoptions'] = $recent_adoptions_stmt->fetch(PDO::FETCH_ASSOC)['recent_adoptions'];
    
    $recent_pets_query = "SELECT COUNT(*) as new_pets 
                         FROM pets 
                         WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    $recent_pets_stmt = $db->prepare($recent_pets_query);
    $recent_pets_stmt->execute();
    $statistics['recent_activity']['new_pets'] = $recent_pets_stmt->fetch(PDO::FETCH_ASSOC)['new_pets'];
    
    // Supply Requests (if any exist)
    $supply_stats_query = "SELECT 
                             COUNT(*) as total_supply_requests,
                             SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_supply_requests,
                             SUM(CASE WHEN urgency = 'urgent' THEN 1 ELSE 0 END) as urgent_supply_requests
                           FROM supply_requests";
    $supply_stats_stmt = $db->prepare($supply_stats_query);
    $supply_stats_stmt->execute();
    $statistics['supply_requests'] = $supply_stats_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Appointments (current and upcoming)
    $appointment_stats_query = "SELECT 
                                  COUNT(*) as total_appointments,
                                  SUM(CASE WHEN status = 'scheduled' AND appointment_datetime >= NOW() THEN 1 ELSE 0 END) as upcoming_appointments,
                                  SUM(CASE WHEN DATE(appointment_datetime) = CURDATE() THEN 1 ELSE 0 END) as today_appointments
                                FROM appointments";
    $appointment_stats_stmt = $db->prepare($appointment_stats_query);
    $appointment_stats_stmt->execute();
    $statistics['appointments'] = $appointment_stats_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Convert all numeric strings to integers
    foreach ($statistics as $category => &$stats) {
        if (is_array($stats)) {
            foreach ($stats as $key => &$value) {
                if (is_numeric($value)) {
                    $value = (int)$value;
                }
            }
        }
    }
    
    sendResponse(200, 'Dashboard statistics retrieved successfully', $statistics);
    
} catch (PDOException $e) {
    sendResponse(500, 'Database error: ' . $e->getMessage());
} catch (Exception $e) {
    sendResponse(500, 'Server error: ' . $e->getMessage());
}
?>
