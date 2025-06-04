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
$validator->required($input, ['appointment_type', 'appointment_date', 'appointment_time']);
$validator->in($input['appointment_type'] ?? '', ['meet_greet', 'adoption_finalization', 'medical_checkup', 'behavioral_assessment', 'home_visit']);
$validator->datetime($input['appointment_date'] ?? '', 'appointment_date');

if (isset($input['pet_id'])) {
    $validator->numeric($input['pet_id'], 'pet_id');
}

if (isset($input['shelter_id'])) {
    $validator->numeric($input['shelter_id'], 'shelter_id');
}

if ($validator->hasErrors()) {
    sendResponse(400, 'Validation failed', $validator->getErrors());
}

$appointment_type = $input['appointment_type'];
$appointment_date = $input['appointment_date'];
$appointment_time = $input['appointment_time'];
$pet_id = isset($input['pet_id']) ? (int)$input['pet_id'] : null;
$shelter_id = isset($input['shelter_id']) ? (int)$input['shelter_id'] : null;
$notes = $input['notes'] ?? null;

// Combine date and time
$appointment_datetime = $appointment_date . ' ' . $appointment_time;

// Validate that appointment is in the future
if (strtotime($appointment_datetime) <= time()) {
    sendResponse(400, 'Appointment must be scheduled for a future date and time');
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Start transaction
    $db->beginTransaction();
    
    // If pet_id is provided, verify pet exists and get shelter_id
    if ($pet_id) {
        $pet_query = "SELECT id, name, shelter_id, status FROM pets WHERE id = ?";
        $pet_stmt = $db->prepare($pet_query);
        $pet_stmt->bindParam(1, $pet_id);
        $pet_stmt->execute();
        
        $pet = $pet_stmt->fetch(PDO::FETCH_ASSOC);
        if (!$pet) {
            $db->rollBack();
            sendResponse(404, 'Pet not found');
        }
        
        // Use pet's shelter if not explicitly provided
        if (!$shelter_id) {
            $shelter_id = $pet['shelter_id'];
        }
    }
    
    // If shelter_id is provided, verify shelter exists
    if ($shelter_id) {
        $shelter_query = "SELECT id, name FROM shelters WHERE id = ?";
        $shelter_stmt = $db->prepare($shelter_query);
        $shelter_stmt->bindParam(1, $shelter_id);
        $shelter_stmt->execute();
        
        if (!$shelter_stmt->fetch()) {
            $db->rollBack();
            sendResponse(404, 'Shelter not found');
        }
    }
    
    // Check for scheduling conflicts (same user, same time)
    $conflict_query = "SELECT id FROM appointments 
                      WHERE user_id = ? AND appointment_datetime = ? AND status != 'cancelled'";
    $conflict_stmt = $db->prepare($conflict_query);
    $conflict_stmt->bindParam(1, $user_id);
    $conflict_stmt->bindParam(2, $appointment_datetime);
    $conflict_stmt->execute();
    
    if ($conflict_stmt->fetch()) {
        $db->rollBack();
        sendResponse(409, 'You already have an appointment scheduled at this time');
    }
    
    // Create appointment
    $insert_query = "INSERT INTO appointments 
                    (user_id, pet_id, shelter_id, appointment_type, appointment_datetime, notes, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, 'scheduled', NOW())";
    
    $insert_stmt = $db->prepare($insert_query);
    $insert_stmt->bindParam(1, $user_id);
    $insert_stmt->bindParam(2, $pet_id);
    $insert_stmt->bindParam(3, $shelter_id);
    $insert_stmt->bindParam(4, $appointment_type);
    $insert_stmt->bindParam(5, $appointment_datetime);
    $insert_stmt->bindParam(6, $notes);
    
    if (!$insert_stmt->execute()) {
        $db->rollBack();
        sendResponse(500, 'Failed to create appointment');
    }
    
    $appointment_id = $db->lastInsertId();
    
    // Create notification for staff if shelter is specified
    if ($shelter_id) {
        $notification_query = "INSERT INTO notifications (user_id, title, message, type, created_at)
                              SELECT u.id, ?, ?, 'new_appointment', NOW()
                              FROM users u 
                              WHERE u.role IN ('staff', 'admin')";
        
        $notification_stmt = $db->prepare($notification_query);
        $title = "New Appointment Scheduled";
        $message = "A new " . str_replace('_', ' ', $appointment_type) . " appointment has been scheduled for " . $appointment_datetime;
        $notification_stmt->bindParam(1, $title);
        $notification_stmt->bindParam(2, $message);
        $notification_stmt->execute();
    }
    
    // Commit transaction
    $db->commit();
    
    sendResponse(201, 'Appointment created successfully', [
        'appointment_id' => $appointment_id,
        'appointment_type' => $appointment_type,
        'appointment_datetime' => $appointment_datetime,
        'pet_id' => $pet_id,
        'shelter_id' => $shelter_id,
        'status' => 'scheduled'
    ]);
    
} catch (PDOException $e) {
    $db->rollBack();
    sendResponse(500, 'Database error: ' . $e->getMessage());
} catch (Exception $e) {
    $db->rollBack();
    sendResponse(500, 'Server error: ' . $e->getMessage());
}
?>
