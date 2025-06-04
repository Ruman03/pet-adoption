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
    
    // Get user's favorite pets with full pet information
    $query = "SELECT 
                f.id as favorite_id,
                f.pet_id,
                f.created_at as favorited_at,
                p.name,
                p.species,
                p.breed,
                p.age,
                p.gender,
                p.size,
                p.color,
                p.description,
                p.status,
                p.photo_url,
                p.adoption_fee,
                p.shelter_id,
                s.name as shelter_name,
                s.address as shelter_address,
                s.phone as shelter_phone
              FROM favorites f
              LEFT JOIN pets p ON f.pet_id = p.id
              LEFT JOIN shelters s ON p.shelter_id = s.id
              WHERE f.user_id = ?
              ORDER BY f.created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(1, $user_id);
    $stmt->execute();
    
    $favorites = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Convert numeric values
        $row['age'] = (int)$row['age'];
        $row['adoption_fee'] = (float)$row['adoption_fee'];
        $row['pet_id'] = (int)$row['pet_id'];
        $row['shelter_id'] = (int)$row['shelter_id'];
        
        $favorites[] = $row;
    }
    
    sendResponse(200, 'Favorite pets retrieved successfully', [
        'total_count' => count($favorites),
        'favorites' => $favorites
    ]);
    
} catch (PDOException $e) {
    sendResponse(500, 'Database error: ' . $e->getMessage());
} catch (Exception $e) {
    sendResponse(500, 'Server error: ' . $e->getMessage());
}
?>
