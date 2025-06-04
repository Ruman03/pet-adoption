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

$user_id = $user_data['user_id'];
$user_role = $user_data['role'];

// Get query parameters
$status = $_GET['status'] ?? null;
$appointment_type = $_GET['appointment_type'] ?? null;
$from_date = $_GET['from_date'] ?? null;
$to_date = $_GET['to_date'] ?? null;

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Build query based on user role
    $where_conditions = [];
    $params = [];
    
    // Regular users can only see their own appointments
    if (in_array($user_role, ['user', 'volunteer'])) {
        $where_conditions[] = "a.user_id = ?";
        $params[] = $user_id;
    }
    
    // Add filters
    if ($status) {
        $where_conditions[] = "a.status = ?";
        $params[] = $status;
    }
    
    if ($appointment_type) {
        $where_conditions[] = "a.appointment_type = ?";
        $params[] = $appointment_type;
    }
    
    if ($from_date) {
        $where_conditions[] = "DATE(a.appointment_datetime) >= ?";
        $params[] = $from_date;
    }
    
    if ($to_date) {
        $where_conditions[] = "DATE(a.appointment_datetime) <= ?";
        $params[] = $to_date;
    }
    
    $where_clause = empty($where_conditions) ? "" : "WHERE " . implode(" AND ", $where_conditions);
    
    // Get appointments with related information
    $query = "SELECT 
                a.id,
                a.user_id,
                a.pet_id,
                a.shelter_id,
                a.appointment_type,
                a.appointment_datetime,
                a.notes,
                a.status,
                a.created_at,
                a.updated_at,
                u.name as user_name,
                u.email as user_email,
                u.phone as user_phone,
                p.name as pet_name,
                p.species as pet_species,
                p.breed as pet_breed,
                s.name as shelter_name,
                s.address as shelter_address,
                s.phone as shelter_phone
              FROM appointments a
              LEFT JOIN users u ON a.user_id = u.id
              LEFT JOIN pets p ON a.pet_id = p.id
              LEFT JOIN shelters s ON a.shelter_id = s.id
              $where_clause
              ORDER BY a.appointment_datetime ASC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    
    $appointments = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Convert numeric values
        $row['id'] = (int)$row['id'];
        $row['user_id'] = (int)$row['user_id'];
        $row['pet_id'] = $row['pet_id'] ? (int)$row['pet_id'] : null;
        $row['shelter_id'] = $row['shelter_id'] ? (int)$row['shelter_id'] : null;
        
        // Format appointment type for display
        $row['appointment_type_display'] = ucwords(str_replace('_', ' ', $row['appointment_type']));
        
        $appointments[] = $row;
    }
    
    sendResponse(200, 'Appointments retrieved successfully', [
        'total_count' => count($appointments),
        'appointments' => $appointments
    ]);
    
} catch (PDOException $e) {
    sendResponse(500, 'Database error: ' . $e->getMessage());
} catch (Exception $e) {
    sendResponse(500, 'Server error: ' . $e->getMessage());
}
?>
