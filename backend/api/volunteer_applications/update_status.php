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

// Only staff and admin can update volunteer application status
if (!in_array($user_data['role'], ['staff', 'admin'])) {
    sendResponse(403, 'Insufficient permissions');
}

// Get JSON input
$input = json_decode(file_get_contents("php://input"), true);

// Validate required fields
$validator = new Validator();
$validator->required($input, ['application_id', 'status']);
$validator->in($input['status'] ?? '', ['pending', 'under_review', 'approved', 'rejected']);

if ($validator->hasErrors()) {
    sendResponse(400, 'Validation failed', $validator->getErrors());
}

$application_id = $input['application_id'];
$status = $input['status'];
$notes = $input['notes'] ?? null;

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Start transaction
    $db->beginTransaction();
    
    // Check if application exists
    $check_query = "SELECT va.*, u.name as user_name, u.email as user_email, s.name as shelter_name 
                    FROM volunteer_applications va
                    LEFT JOIN users u ON va.user_id = u.id
                    LEFT JOIN shelters s ON va.shelter_id = s.id
                    WHERE va.id = ?";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(1, $application_id);
    $check_stmt->execute();
    
    $application = $check_stmt->fetch(PDO::FETCH_ASSOC);
    if (!$application) {
        $db->rollBack();
        sendResponse(404, 'Volunteer application not found');
    }
    
    // Update application status
    $update_query = "UPDATE volunteer_applications 
                     SET status = ?, notes = ?, updated_at = NOW() 
                     WHERE id = ?";
    $update_stmt = $db->prepare($update_query);
    $update_stmt->bindParam(1, $status);
    $update_stmt->bindParam(2, $notes);
    $update_stmt->bindParam(3, $application_id);
    
    if (!$update_stmt->execute()) {
        $db->rollBack();
        sendResponse(500, 'Failed to update application status');
    }
    
    // If approved, update user role to volunteer (if not already higher)
    if ($status === 'approved') {
        $role_query = "UPDATE users SET role = 'volunteer' WHERE id = ? AND role = 'user'";
        $role_stmt = $db->prepare($role_query);
        $role_stmt->bindParam(1, $application['user_id']);
        $role_stmt->execute();
        
        // Create notification for user
        $notification_query = "INSERT INTO notifications (user_id, title, message, type, created_at) 
                              VALUES (?, ?, ?, 'volunteer_approved', NOW())";
        $notification_stmt = $db->prepare($notification_query);
        $title = "Volunteer Application Approved";
        $message = "Congratulations! Your volunteer application for " . $application['shelter_name'] . " has been approved.";
        $notification_stmt->bindParam(1, $application['user_id']);
        $notification_stmt->bindParam(2, $title);
        $notification_stmt->bindParam(3, $message);
        $notification_stmt->execute();
    } elseif ($status === 'rejected') {
        // Create notification for rejection
        $notification_query = "INSERT INTO notifications (user_id, title, message, type, created_at) 
                              VALUES (?, ?, ?, 'volunteer_rejected', NOW())";
        $notification_stmt = $db->prepare($notification_query);
        $title = "Volunteer Application Update";
        $message = "Your volunteer application for " . $application['shelter_name'] . " has been reviewed. " . 
                   ($notes ? "Note: " . $notes : "Please contact the shelter for more information.");
        $notification_stmt->bindParam(1, $application['user_id']);
        $notification_stmt->bindParam(2, $title);
        $notification_stmt->bindParam(3, $message);
        $notification_stmt->execute();
    }
    
    // Commit transaction
    $db->commit();
    
    sendResponse(200, 'Volunteer application status updated successfully', [
        'application_id' => $application_id,
        'status' => $status,
        'user_name' => $application['user_name'],
        'shelter_name' => $application['shelter_name']
    ]);
    
} catch (PDOException $e) {
    $db->rollBack();
    sendResponse(500, 'Database error: ' . $e->getMessage());
} catch (Exception $e) {
    $db->rollBack();
    sendResponse(500, 'Server error: ' . $e->getMessage());
}
?>
