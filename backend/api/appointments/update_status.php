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

$user_id = $user_data['user_id'];
$user_role = $user_data['role'];

// Get JSON input
$input = json_decode(file_get_contents("php://input"), true);

// Validate required fields
$validator = new Validator();
$validator->required($input, ['appointment_id', 'status']);
$validator->numeric($input['appointment_id'] ?? '', 'appointment_id');
$validator->in($input['status'] ?? '', ['scheduled', 'confirmed', 'in_progress', 'completed', 'cancelled', 'no_show']);

if ($validator->hasErrors()) {
    sendResponse(400, 'Validation failed', $validator->getErrors());
}

$appointment_id = (int)$input['appointment_id'];
$status = $input['status'];
$notes = $input['notes'] ?? null;

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Start transaction
    $db->beginTransaction();
    
    // Get appointment details
    $appointment_query = "SELECT a.*, u.name as user_name, p.name as pet_name, s.name as shelter_name
                         FROM appointments a
                         LEFT JOIN users u ON a.user_id = u.id
                         LEFT JOIN pets p ON a.pet_id = p.id
                         LEFT JOIN shelters s ON a.shelter_id = s.id
                         WHERE a.id = ?";
    $appointment_stmt = $db->prepare($appointment_query);
    $appointment_stmt->bindParam(1, $appointment_id);
    $appointment_stmt->execute();
    
    $appointment = $appointment_stmt->fetch(PDO::FETCH_ASSOC);
    if (!$appointment) {
        $db->rollBack();
        sendResponse(404, 'Appointment not found');
    }
    
    // Check permissions
    $can_update = false;
    if (in_array($user_role, ['admin', 'staff'])) {
        $can_update = true;
    } elseif ($user_id == $appointment['user_id']) {
        // Users can only cancel their own appointments
        $can_update = ($status === 'cancelled');
    }
    
    if (!$can_update) {
        $db->rollBack();
        sendResponse(403, 'Insufficient permissions to update this appointment');
    }
    
    // Prevent updating completed or cancelled appointments (except by admin)
    if (in_array($appointment['status'], ['completed', 'cancelled']) && $user_role !== 'admin') {
        $db->rollBack();
        sendResponse(400, 'Cannot update completed or cancelled appointments');
    }
    
    // Update appointment
    $update_fields = ['status = ?', 'updated_at = NOW()'];
    $params = [$status];
    
    if ($notes !== null) {
        $update_fields[] = 'notes = ?';
        $params[] = $notes;
    }
    
    $params[] = $appointment_id;
    
    $update_query = "UPDATE appointments SET " . implode(', ', $update_fields) . " WHERE id = ?";
    $update_stmt = $db->prepare($update_query);
    
    if (!$update_stmt->execute($params)) {
        $db->rollBack();
        sendResponse(500, 'Failed to update appointment');
    }
    
    // Create notifications based on status change
    $notification_title = '';
    $notification_message = '';
    $notification_type = 'appointment_update';
    
    switch ($status) {
        case 'confirmed':
            $notification_title = 'Appointment Confirmed';
            $notification_message = "Your {$appointment['appointment_type']} appointment has been confirmed for " . date('M j, Y \a\t g:i A', strtotime($appointment['appointment_datetime']));
            break;
        case 'cancelled':
            $notification_title = 'Appointment Cancelled';
            $notification_message = "Your {$appointment['appointment_type']} appointment for " . date('M j, Y \a\t g:i A', strtotime($appointment['appointment_datetime'])) . " has been cancelled.";
            break;
        case 'completed':
            $notification_title = 'Appointment Completed';
            $notification_message = "Your {$appointment['appointment_type']} appointment has been completed. Thank you!";
            break;
        case 'no_show':
            $notification_title = 'Missed Appointment';
            $notification_message = "You missed your {$appointment['appointment_type']} appointment. Please contact us to reschedule.";
            break;
    }
    
    // Send notification to appointment owner
    if ($notification_title && $user_id != $appointment['user_id']) {
        $notification_query = "INSERT INTO notifications (user_id, title, message, type, created_at) 
                              VALUES (?, ?, ?, ?, NOW())";
        $notification_stmt = $db->prepare($notification_query);
        $notification_stmt->bindParam(1, $appointment['user_id']);
        $notification_stmt->bindParam(2, $notification_title);
        $notification_stmt->bindParam(3, $notification_message);
        $notification_stmt->bindParam(4, $notification_type);
        $notification_stmt->execute();
    }
    
    // Commit transaction
    $db->commit();
    
    sendResponse(200, 'Appointment updated successfully', [
        'appointment_id' => $appointment_id,
        'status' => $status,
        'user_name' => $appointment['user_name'],
        'appointment_type' => $appointment['appointment_type'],
        'appointment_datetime' => $appointment['appointment_datetime']
    ]);
    
} catch (PDOException $e) {
    $db->rollBack();
    sendResponse(500, 'Database error: ' . $e->getMessage());
} catch (Exception $e) {
    $db->rollBack();
    sendResponse(500, 'Server error: ' . $e->getMessage());
}
?>
