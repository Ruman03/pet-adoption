<?php
require_once '../../config/db.php';
require_once '../../utils/response.php';
require_once '../../utils/validator.php';

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(405, 'Method not allowed');
}

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

// Get JSON input
$input = json_decode(file_get_contents("php://input"), true);

// Validate required fields
$validator = new Validator();
$validator->required($input, ['pet_id']);
$validator->numeric($input['pet_id'] ?? '', 'pet_id');

if ($validator->hasErrors()) {
    sendResponse(400, 'Validation failed', $validator->getErrors());
}

$pet_id = $input['pet_id'];

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Check if pet exists
    $pet_query = "SELECT id, name, status FROM pets WHERE id = ?";
    $pet_stmt = $db->prepare($pet_query);
    $pet_stmt->bindParam(1, $pet_id);
    $pet_stmt->execute();
    
    $pet = $pet_stmt->fetch(PDO::FETCH_ASSOC);
    if (!$pet) {
        sendResponse(404, 'Pet not found');
    }
    
    // Check if already in favorites
    $check_query = "SELECT id FROM favorites WHERE user_id = ? AND pet_id = ?";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(1, $user_id);
    $check_stmt->bindParam(2, $pet_id);
    $check_stmt->execute();
    
    if ($check_stmt->fetch()) {
        sendResponse(409, 'Pet is already in your favorites');
    }
    
    // Add to favorites
    $insert_query = "INSERT INTO favorites (user_id, pet_id, created_at) VALUES (?, ?, NOW())";
    $insert_stmt = $db->prepare($insert_query);
    $insert_stmt->bindParam(1, $user_id);
    $insert_stmt->bindParam(2, $pet_id);
    
    if (!$insert_stmt->execute()) {
        sendResponse(500, 'Failed to add pet to favorites');
    }
    
    sendResponse(201, 'Pet added to favorites successfully', [
        'pet_id' => $pet_id,
        'pet_name' => $pet['name'],
        'user_id' => $user_id
    ]);
    
} catch (PDOException $e) {
    sendResponse(500, 'Database error: ' . $e->getMessage());
} catch (Exception $e) {
    sendResponse(500, 'Server error: ' . $e->getMessage());
}
?>
