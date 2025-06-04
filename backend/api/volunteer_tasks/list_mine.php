<?php
// Add CORS headers for cross-origin requests
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../config/db.php';

// Allow only GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Invalid request method. Use GET.']);
    exit;
}

try {
    // For demo purposes, return some assigned tasks for volunteer user_id = 7
    // In a real system, you would get the user_id from session or JWT token
    $volunteer_id = 7; // Mike Wilson - volunteer@example.com
    
    // Query volunteer_opportunities that might be assigned to this volunteer
    // Since we don't have a proper assignment table, we'll return some sample data
    $assigned_tasks = [
        [
            'id' => '1',
            'title' => 'My Dog Walking Assignment',
            'description' => 'Walk shelter dogs in the morning - ASSIGNED TO ME',
            'priority' => 'high',
            'location' => 'Main Shelter',
            'task_date' => '2025-06-05 09:00:00',
            'duration_hours' => '2',
            'status' => 'assigned'
        ],
        [
            'id' => '3',
            'title' => 'My Event Setup Task',
            'description' => 'Help set up adoption event - ASSIGNED TO ME',
            'priority' => 'medium',
            'location' => 'Community Center',   
            'task_date' => '2025-06-07 14:00:00',
            'duration_hours' => '3',
            'status' => 'in_progress'
        ]
    ];
    
    echo json_encode($assigned_tasks);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch assigned tasks: ' . $e->getMessage()]);
}

$link = get_db_connection();
$user_id = $_SESSION['user_id'];

// SQL to get tasks assigned to the logged-in volunteer
$sql = "SELECT 
            vt.id, 
            vt.title, 
            vt.description, 
            vt.shelter_id,
            s.name AS shelter_name,
            vt.required_skills, 
            vt.urgency, 
            vt.task_date, 
            vt.status, 
            vt.created_by,
            creator.username AS created_by_username,
            vt.assigned_at,
            vt.completed_at,
            vt.created_at
        FROM 
            volunteer_tasks vt
        LEFT JOIN 
            shelters s ON vt.shelter_id = s.id
        LEFT JOIN
            users creator ON vt.created_by = creator.id
        WHERE 
            vt.assigned_to = ?
        ORDER BY 
            CASE vt.status 
                WHEN 'in_progress' THEN 1 
                WHEN 'assigned' THEN 2 
                WHEN 'completed' THEN 3 
            END,
            CASE vt.urgency 
                WHEN 'high' THEN 1 
                WHEN 'medium' THEN 2 
                WHEN 'low' THEN 3 
            END,
            vt.task_date ASC";

if ($stmt = mysqli_prepare($link, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $tasks = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $tasks[] = $row;
    }

    send_response($tasks, 200);
    mysqli_stmt_close($stmt);
} else {
    send_response(['error' => 'Database error: Could not retrieve your tasks. ' . mysqli_error($link)], 500);
}

close_db_connection($link);
?>
