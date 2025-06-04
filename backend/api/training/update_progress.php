<?php
require_once '../../config/db.php';
require_once '../../utils/response.php';
require_once '../../utils/validator.php';

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, PUT");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT'])) {
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
$validator->required($input, ['module_id']);
$validator->numeric($input['module_id'] ?? '', 'module_id');

if (isset($input['progress_percentage'])) {
    $validator->numeric($input['progress_percentage'], 'progress_percentage');
    if ($input['progress_percentage'] < 0 || $input['progress_percentage'] > 100) {
        $validator->addError('progress_percentage', 'Progress percentage must be between 0 and 100');
    }
}

if (isset($input['status'])) {
    $validator->in($input['status'], ['not_started', 'in_progress', 'completed']);
}

if ($validator->hasErrors()) {
    sendResponse(400, 'Validation failed', $validator->getErrors());
}

$module_id = (int)$input['module_id'];
$progress_percentage = isset($input['progress_percentage']) ? (int)$input['progress_percentage'] : null;
$status = $input['status'] ?? null;

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Start transaction
    $db->beginTransaction();
    
    // Check if training module exists
    $module_query = "SELECT id, title FROM training_modules WHERE id = ?";
    $module_stmt = $db->prepare($module_query);
    $module_stmt->bindParam(1, $module_id);
    $module_stmt->execute();
    
    $module = $module_stmt->fetch(PDO::FETCH_ASSOC);
    if (!$module) {
        $db->rollBack();
        sendResponse(404, 'Training module not found');
    }
    
    // Check if progress record exists
    $progress_query = "SELECT id, status, progress_percentage FROM training_progress 
                      WHERE user_id = ? AND module_id = ?";
    $progress_stmt = $db->prepare($progress_query);
    $progress_stmt->bindParam(1, $user_id);
    $progress_stmt->bindParam(2, $module_id);
    $progress_stmt->execute();
    
    $existing_progress = $progress_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing_progress) {
        // Update existing progress
        $update_fields = [];
        $params = [];
        
        if ($progress_percentage !== null) {
            $update_fields[] = "progress_percentage = ?";
            $params[] = $progress_percentage;
        }
        
        if ($status) {
            $update_fields[] = "status = ?";
            $params[] = $status;
            
            // If completing, set completed_at
            if ($status === 'completed') {
                $update_fields[] = "completed_at = NOW()";
                if ($progress_percentage === null) {
                    $update_fields[] = "progress_percentage = 100";
                }
            }
        } elseif ($progress_percentage !== null) {
            // Auto-determine status based on percentage
            if ($progress_percentage >= 100) {
                $update_fields[] = "status = 'completed'";
                $update_fields[] = "completed_at = NOW()";
            } elseif ($progress_percentage > 0) {
                $update_fields[] = "status = 'in_progress'";
            }
        }
        
        $update_fields[] = "updated_at = NOW()";
        $params[] = $user_id;
        $params[] = $module_id;
        
        $update_query = "UPDATE training_progress SET " . implode(', ', $update_fields) . " 
                        WHERE user_id = ? AND module_id = ?";
        
        $update_stmt = $db->prepare($update_query);
        if (!$update_stmt->execute($params)) {
            $db->rollBack();
            sendResponse(500, 'Failed to update training progress');
        }
        
    } else {
        // Create new progress record
        $insert_status = $status ?? ($progress_percentage > 0 ? 'in_progress' : 'not_started');
        $insert_percentage = $progress_percentage ?? 0;
        
        $insert_query = "INSERT INTO training_progress 
                        (user_id, module_id, status, progress_percentage, started_at, created_at) 
                        VALUES (?, ?, ?, ?, NOW(), NOW())";
        
        $insert_stmt = $db->prepare($insert_query);
        $insert_stmt->bindParam(1, $user_id);
        $insert_stmt->bindParam(2, $module_id);
        $insert_stmt->bindParam(3, $insert_status);
        $insert_stmt->bindParam(4, $insert_percentage);
        
        if (!$insert_stmt->execute()) {
            $db->rollBack();
            sendResponse(500, 'Failed to create training progress');
        }
        
        // If completing on first try, update completed_at
        if ($insert_status === 'completed') {
            $complete_query = "UPDATE training_progress SET completed_at = NOW() 
                              WHERE user_id = ? AND module_id = ?";
            $complete_stmt = $db->prepare($complete_query);
            $complete_stmt->bindParam(1, $user_id);
            $complete_stmt->bindParam(2, $module_id);
            $complete_stmt->execute();
        }
    }
    
    // Get updated progress
    $final_query = "SELECT tp.*, tm.title as module_title 
                   FROM training_progress tp
                   LEFT JOIN training_modules tm ON tp.module_id = tm.id
                   WHERE tp.user_id = ? AND tp.module_id = ?";
    $final_stmt = $db->prepare($final_query);
    $final_stmt->bindParam(1, $user_id);
    $final_stmt->bindParam(2, $module_id);
    $final_stmt->execute();
    
    $final_progress = $final_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Commit transaction
    $db->commit();
    
    sendResponse(200, 'Training progress updated successfully', [
        'module_id' => $module_id,
        'module_title' => $final_progress['module_title'],
        'status' => $final_progress['status'],
        'progress_percentage' => (int)$final_progress['progress_percentage'],
        'started_at' => $final_progress['started_at'],
        'completed_at' => $final_progress['completed_at']
    ]);
    
} catch (PDOException $e) {
    $db->rollBack();
    sendResponse(500, 'Database error: ' . $e->getMessage());
} catch (Exception $e) {
    $db->rollBack();
    sendResponse(500, 'Server error: ' . $e->getMessage());
}
?>
