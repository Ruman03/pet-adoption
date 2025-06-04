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
$urgency = $_GET['urgency'] ?? null;
$category = $_GET['category'] ?? null;

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Build query based on user role
    $where_conditions = [];
    $params = [];
    
    // Regular users can only see their own supply requests
    if (in_array($user_role, ['user', 'volunteer'])) {
        $where_conditions[] = "fr.user_id = ?";
        $params[] = $user_id;
    }
    
    // Add filters
    if ($status) {
        $where_conditions[] = "sr.status = ?";
        $params[] = $status;
    }
    
    if ($urgency) {
        $where_conditions[] = "sr.urgency = ?";
        $params[] = $urgency;
    }
    
    if ($category) {
        $where_conditions[] = "sr.category = ?";
        $params[] = $category;
    }
    
    $where_clause = empty($where_conditions) ? "" : "WHERE " . implode(" AND ", $where_conditions);
    
    // Get supply requests with related information
    $query = "SELECT 
                sr.id,
                sr.foster_record_id,
                sr.item_name,
                sr.quantity,
                sr.category,
                sr.urgency,
                sr.description,
                sr.estimated_cost,
                sr.actual_cost,
                sr.status,
                sr.notes,
                sr.created_at,
                sr.updated_at,
                sr.fulfilled_at,
                fr.user_id as foster_user_id,
                fr.pet_id,
                u.name as foster_user_name,
                u.email as foster_user_email,
                u.phone as foster_user_phone,
                p.name as pet_name,
                p.species as pet_species,
                p.breed as pet_breed
              FROM supply_requests sr
              LEFT JOIN foster_records fr ON sr.foster_record_id = fr.id
              LEFT JOIN users u ON fr.user_id = u.id
              LEFT JOIN pets p ON fr.pet_id = p.id
              $where_clause
              ORDER BY 
                CASE sr.urgency 
                  WHEN 'urgent' THEN 1
                  WHEN 'high' THEN 2
                  WHEN 'medium' THEN 3
                  WHEN 'low' THEN 4
                END,
                sr.created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    
    $supply_requests = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Convert numeric values
        $row['id'] = (int)$row['id'];
        $row['foster_record_id'] = (int)$row['foster_record_id'];
        $row['quantity'] = (int)$row['quantity'];
        $row['estimated_cost'] = $row['estimated_cost'] ? (float)$row['estimated_cost'] : null;
        $row['actual_cost'] = $row['actual_cost'] ? (float)$row['actual_cost'] : null;
        $row['foster_user_id'] = (int)$row['foster_user_id'];
        $row['pet_id'] = (int)$row['pet_id'];
        
        // Format display values
        $row['urgency_display'] = ucfirst($row['urgency']);
        $row['category_display'] = ucfirst($row['category']);
        $row['status_display'] = ucwords(str_replace('_', ' ', $row['status']));
        
        $supply_requests[] = $row;
    }
    
    sendResponse(200, 'Supply requests retrieved successfully', [
        'total_count' => count($supply_requests),
        'supply_requests' => $supply_requests
    ]);
    
} catch (PDOException $e) {
    sendResponse(500, 'Database error: ' . $e->getMessage());
} catch (Exception $e) {
    sendResponse(500, 'Server error: ' . $e->getMessage());
}
?>
