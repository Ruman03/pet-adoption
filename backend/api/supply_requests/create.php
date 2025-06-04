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
$validator->required($input, ['foster_record_id', 'item_name', 'quantity']);
$validator->numeric($input['foster_record_id'] ?? '', 'foster_record_id');
$validator->minLength($input['item_name'] ?? '', 2, 'item_name');
$validator->numeric($input['quantity'] ?? '', 'quantity');

if (isset($input['urgency'])) {
    $validator->in($input['urgency'], ['low', 'medium', 'high', 'urgent']);
}

if (isset($input['category'])) {
    $validator->in($input['category'], ['food', 'medical', 'toys', 'bedding', 'cleaning', 'other']);
}

if ($validator->hasErrors()) {
    sendResponse(400, 'Validation failed', $validator->getErrors());
}

$foster_record_id = (int)$input['foster_record_id'];
$item_name = $input['item_name'];
$quantity = (int)$input['quantity'];
$category = $input['category'] ?? 'other';
$urgency = $input['urgency'] ?? 'medium';
$description = $input['description'] ?? null;
$estimated_cost = isset($input['estimated_cost']) ? (float)$input['estimated_cost'] : null;

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Start transaction
    $db->beginTransaction();
    
    // Verify foster record exists and belongs to user
    $foster_query = "SELECT fr.id, fr.user_id, fr.pet_id, fr.status, p.name as pet_name, u.name as user_name
                    FROM foster_records fr
                    LEFT JOIN pets p ON fr.pet_id = p.id
                    LEFT JOIN users u ON fr.user_id = u.id
                    WHERE fr.id = ? AND fr.user_id = ?";
    $foster_stmt = $db->prepare($foster_query);
    $foster_stmt->bindParam(1, $foster_record_id);
    $foster_stmt->bindParam(2, $user_id);
    $foster_stmt->execute();
    
    $foster_record = $foster_stmt->fetch(PDO::FETCH_ASSOC);
    if (!$foster_record) {
        $db->rollBack();
        sendResponse(404, 'Foster record not found or you do not have permission to request supplies for it');
    }
    
    // Only allow supply requests for active foster records
    if ($foster_record['status'] !== 'active') {
        $db->rollBack();
        sendResponse(400, 'Supply requests can only be made for active foster records');
    }
    
    // Create supply request
    $insert_query = "INSERT INTO supply_requests 
                    (foster_record_id, item_name, quantity, category, urgency, description, estimated_cost, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())";
    
    $insert_stmt = $db->prepare($insert_query);
    $insert_stmt->bindParam(1, $foster_record_id);
    $insert_stmt->bindParam(2, $item_name);
    $insert_stmt->bindParam(3, $quantity);
    $insert_stmt->bindParam(4, $category);
    $insert_stmt->bindParam(5, $urgency);
    $insert_stmt->bindParam(6, $description);
    $insert_stmt->bindParam(7, $estimated_cost);
    
    if (!$insert_stmt->execute()) {
        $db->rollBack();
        sendResponse(500, 'Failed to create supply request');
    }
    
    $request_id = $db->lastInsertId();
    
    // Create notification for staff about new supply request
    $notification_query = "INSERT INTO notifications (user_id, title, message, type, created_at)
                          SELECT u.id, ?, ?, 'supply_request', NOW()
                          FROM users u 
                          WHERE u.role IN ('staff', 'admin')";
    
    $notification_stmt = $db->prepare($notification_query);
    $title = "New Supply Request";
    $message = "{$foster_record['user_name']} has requested {$item_name} (x{$quantity}) for foster pet {$foster_record['pet_name']}. Urgency: {$urgency}.";
    $notification_stmt->bindParam(1, $title);
    $notification_stmt->bindParam(2, $message);
    $notification_stmt->execute();
    
    // Commit transaction
    $db->commit();
    
    sendResponse(201, 'Supply request created successfully', [
        'request_id' => $request_id,
        'foster_record_id' => $foster_record_id,
        'item_name' => $item_name,
        'quantity' => $quantity,
        'category' => $category,
        'urgency' => $urgency,
        'status' => 'pending',
        'pet_name' => $foster_record['pet_name']
    ]);
    
} catch (PDOException $e) {
    $db->rollBack();
    sendResponse(500, 'Database error: ' . $e->getMessage());
} catch (Exception $e) {
    $db->rollBack();
    sendResponse(500, 'Server error: ' . $e->getMessage());
}
?>
