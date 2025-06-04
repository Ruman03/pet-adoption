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
if (!$user_data || !isset($user_data['user_id']) || !isset($user_data['role'])) {
    sendResponse(401, 'Invalid token');
}

// Only admin and staff can create training modules
if (!in_array($user_data['role'], ['admin', 'staff'])) {
    sendResponse(403, 'Insufficient permissions');
}

// Get JSON input
$input = json_decode(file_get_contents("php://input"), true);

// Validate required fields
$validator = new Validator();
$validator->required($input, ['title', 'description', 'content', 'duration_minutes']);
$validator->minLength($input['title'] ?? '', 5, 'title');
$validator->minLength($input['description'] ?? '', 10, 'description');
$validator->minLength($input['content'] ?? '', 50, 'content');
$validator->numeric($input['duration_minutes'] ?? '', 'duration_minutes');
$validator->in($input['difficulty'] ?? 'beginner', ['beginner', 'intermediate', 'advanced']);
$validator->in($input['category'] ?? 'general', ['general', 'animal_care', 'customer_service', 'safety', 'administrative']);

if ($validator->hasErrors()) {
    sendResponse(400, 'Validation failed', $validator->getErrors());
}

$title = $input['title'];
$description = $input['description'];
$content = $input['content'];
$duration_minutes = (int)$input['duration_minutes'];
$difficulty = $input['difficulty'] ?? 'beginner';
$category = $input['category'] ?? 'general';
$prerequisites = $input['prerequisites'] ?? null;
$is_required = isset($input['is_required']) ? (bool)$input['is_required'] : false;

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Create training module
    $query = "INSERT INTO training_modules 
              (title, description, content, duration_minutes, difficulty, category, prerequisites, is_required, created_by, created_at) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(1, $title);
    $stmt->bindParam(2, $description);
    $stmt->bindParam(3, $content);
    $stmt->bindParam(4, $duration_minutes);
    $stmt->bindParam(5, $difficulty);
    $stmt->bindParam(6, $category);
    $stmt->bindParam(7, $prerequisites);
    $stmt->bindParam(8, $is_required, PDO::PARAM_BOOL);
    $stmt->bindParam(9, $user_data['user_id']);
    
    if (!$stmt->execute()) {
        sendResponse(500, 'Failed to create training module');
    }
    
    $module_id = $db->lastInsertId();
    
    sendResponse(201, 'Training module created successfully', [
        'module_id' => $module_id,
        'title' => $title,
        'category' => $category,
        'difficulty' => $difficulty,
        'duration_minutes' => $duration_minutes,
        'is_required' => $is_required
    ]);
    
} catch (PDOException $e) {
    sendResponse(500, 'Database error: ' . $e->getMessage());
} catch (Exception $e) {
    sendResponse(500, 'Server error: ' . $e->getMessage());
}
?>
