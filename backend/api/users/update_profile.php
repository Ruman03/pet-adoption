<?php
require_once '../../config/db.php';
require_once '../../utils/response.php';
require_once '../../utils/validator.php';

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: PUT");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
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

// Validate input
$validator = new Validator();

// At least one field should be provided
if (empty($input) || (!isset($input['name']) && !isset($input['phone']) && !isset($input['address']) && !isset($input['password']))) {
    sendResponse(400, 'At least one field must be provided for update');
}

// Validate fields if provided
if (isset($input['name'])) {
    $validator->required($input, ['name']);
    $validator->minLength($input['name'] ?? '', 2, 'name');
}

if (isset($input['phone'])) {
    $validator->phone($input['phone'], 'phone');
}

if (isset($input['password'])) {
    $validator->minLength($input['password'] ?? '', 6, 'password');
}

if ($validator->hasErrors()) {
    sendResponse(400, 'Validation failed', $validator->getErrors());
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Check if user exists
    $check_query = "SELECT id, email FROM users WHERE id = ?";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(1, $user_id);
    $check_stmt->execute();
    
    if (!$check_stmt->fetch()) {
        sendResponse(404, 'User not found');
    }
    
    // Build dynamic update query
    $update_fields = [];
    $params = [];
    
    if (isset($input['name'])) {
        $update_fields[] = "name = ?";
        $params[] = $input['name'];
    }
    
    if (isset($input['phone'])) {
        $update_fields[] = "phone = ?";
        $params[] = $input['phone'];
    }
    
    if (isset($input['address'])) {
        $update_fields[] = "address = ?";
        $params[] = $input['address'];
    }
    
    if (isset($input['password'])) {
        $update_fields[] = "password = ?";
        $params[] = password_hash($input['password'], PASSWORD_DEFAULT);
    }
    
    $update_fields[] = "updated_at = NOW()";
    $params[] = $user_id; // For WHERE clause
    
    $query = "UPDATE users SET " . implode(', ', $update_fields) . " WHERE id = ?";
    
    $stmt = $db->prepare($query);
    
    if (!$stmt->execute($params)) {
        sendResponse(500, 'Failed to update profile');
    }
    
    // Get updated user information
    $select_query = "SELECT id, name, email, phone, address, role, created_at, updated_at 
                     FROM users WHERE id = ?";
    $select_stmt = $db->prepare($select_query);
    $select_stmt->bindParam(1, $user_id);
    $select_stmt->execute();
    
    $updated_user = $select_stmt->fetch(PDO::FETCH_ASSOC);
    
    sendResponse(200, 'Profile updated successfully', $updated_user);
    
} catch (PDOException $e) {
    if ($e->getCode() == 23000) {
        sendResponse(409, 'Profile update failed due to duplicate data');
    }
    sendResponse(500, 'Database error: ' . $e->getMessage());
} catch (Exception $e) {
    sendResponse(500, 'Server error: ' . $e->getMessage());
}
?>
