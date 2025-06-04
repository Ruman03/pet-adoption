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
if (!$user_data || !isset($user_data['user_id']) || !isset($user_data['role'])) {
    sendResponse(401, 'Invalid token');
}

// Only staff and admin can update supply request status
if (!in_array($user_data['role'], ['staff', 'admin'])) {
    sendResponse(403, 'Insufficient permissions');
}

// Get JSON input
$input = json_decode(file_get_contents("php://input"), true);

// Validate required fields
$validator = new Validator();
$validator->required($input, ['request_id', 'status']);
$validator->numeric($input['request_id'] ?? '', 'request_id');
$validator->in($input['status'] ?? '', ['pending', 'approved', 'ordered', 'shipped', 'delivered', 'cancelled']);

if (isset($input['actual_cost'])) {
    $validator->numeric($input['actual_cost'], 'actual_cost');
}

if ($validator->hasErrors()) {
    sendResponse(400, 'Validation failed', $validator->getErrors());
}

$request_id = (int)$input['request_id'];
$status = $input['status'];
$actual_cost = isset($input['actual_cost']) ? (float)$input['actual_cost'] : null;
$notes = $input['notes'] ?? null;

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Start transaction
    $db->beginTransaction();
    
    // Get supply request details
    $request_query = "SELECT sr.*, fr.user_id as foster_user_id, u.name as foster_user_name, 
                             p.name as pet_name
                     FROM supply_requests sr
                     LEFT JOIN foster_records fr ON sr.foster_record_id = fr.id
                     LEFT JOIN users u ON fr.user_id = u.id
                     LEFT JOIN pets p ON fr.pet_id = p.id
                     WHERE sr.id = ?";
    $request_stmt = $db->prepare($request_query);
    $request_stmt->bindParam(1, $request_id);
    $request_stmt->execute();
    
    $supply_request = $request_stmt->fetch(PDO::FETCH_ASSOC);
    if (!$supply_request) {
        $db->rollBack();
        sendResponse(404, 'Supply request not found');
    }
    
    // Build update query
    $update_fields = ['status = ?', 'updated_at = NOW()'];
    $params = [$status];
    
    if ($actual_cost !== null) {
        $update_fields[] = 'actual_cost = ?';
        $params[] = $actual_cost;
    }
    
    if ($notes !== null) {
        $update_fields[] = 'notes = ?';
        $params[] = $notes;
    }
    
    // Set fulfilled_at if status is delivered
    if ($status === 'delivered') {
        $update_fields[] = 'fulfilled_at = NOW()';
    }
    
    $params[] = $request_id;
    
    $update_query = "UPDATE supply_requests SET " . implode(', ', $update_fields) . " WHERE id = ?";
    $update_stmt = $db->prepare($update_query);
    
    if (!$update_stmt->execute($params)) {
        $db->rollBack();
        sendResponse(500, 'Failed to update supply request');
    }
    
    // Create notification for foster user based on status
    $notification_title = '';
    $notification_message = '';
    $notification_type = 'supply_update';
    
    switch ($status) {
        case 'approved':
            $notification_title = 'Supply Request Approved';
            $notification_message = "Your request for {$supply_request['item_name']} has been approved and will be processed soon.";
            break;
        case 'ordered':
            $notification_title = 'Supply Request Ordered';
            $notification_message = "Your requested {$supply_request['item_name']} has been ordered and will be shipped soon.";
            break;
        case 'shipped':
            $notification_title = 'Supply Request Shipped';
            $notification_message = "Your requested {$supply_request['item_name']} has been shipped and should arrive soon.";
            break;
        case 'delivered':
            $notification_title = 'Supply Request Delivered';
            $notification_message = "Your requested {$supply_request['item_name']} has been delivered. Thank you for fostering!";
            break;
        case 'cancelled':
            $notification_title = 'Supply Request Cancelled';
            $notification_message = "Your request for {$supply_request['item_name']} has been cancelled. " . ($notes ? "Reason: " . $notes : "Please contact us for more information.");
            break;
    }
    
    // Send notification to foster user
    if ($notification_title) {
        $notification_query = "INSERT INTO notifications (user_id, title, message, type, created_at) 
                              VALUES (?, ?, ?, ?, NOW())";
        $notification_stmt = $db->prepare($notification_query);
        $notification_stmt->bindParam(1, $supply_request['foster_user_id']);
        $notification_stmt->bindParam(2, $notification_title);
        $notification_stmt->bindParam(3, $notification_message);
        $notification_stmt->bindParam(4, $notification_type);
        $notification_stmt->execute();
    }
    
    // Commit transaction
    $db->commit();
    
    sendResponse(200, 'Supply request updated successfully', [
        'request_id' => $request_id,
        'status' => $status,
        'item_name' => $supply_request['item_name'],
        'foster_user_name' => $supply_request['foster_user_name'],
        'pet_name' => $supply_request['pet_name'],
        'actual_cost' => $actual_cost
    ]);
    
} catch (PDOException $e) {
    $db->rollBack();
    sendResponse(500, 'Database error: ' . $e->getMessage());
} catch (Exception $e) {
    $db->rollBack();
    sendResponse(500, 'Server error: ' . $e->getMessage());
}
?>
