<?php
require_once '../../config/db.php';
require_once '../../utils/response.php';
require_once '../../utils/validator.php';

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

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

$user_id = $user_data['user_id'];

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get user's volunteer applications with shelter information
    $query = "SELECT 
                va.id,
                va.user_id,
                va.shelter_id,
                va.availability,
                va.skills,
                va.experience,
                va.motivation,
                va.emergency_contact,
                va.emergency_phone,
                va.background_check_consent,
                va.status,
                va.notes,
                va.created_at,
                va.updated_at,
                s.name as shelter_name,
                s.address as shelter_address,
                s.phone as shelter_phone
              FROM volunteer_applications va
              LEFT JOIN shelters s ON va.shelter_id = s.id
              WHERE va.user_id = ?
              ORDER BY va.created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(1, $user_id);
    $stmt->execute();
    
    $applications = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Parse JSON fields
        $row['availability'] = json_decode($row['availability'], true);
        $row['skills'] = json_decode($row['skills'], true);
        
        $applications[] = $row;
    }
    
    sendResponse(200, 'Applications retrieved successfully', $applications);
    
} catch (PDOException $e) {
    sendResponse(500, 'Database error: ' . $e->getMessage());
} catch (Exception $e) {
    sendResponse(500, 'Server error: ' . $e->getMessage());
}
?>
