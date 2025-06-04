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
require_once __DIR__ . '/../../utils/response.php';

// Allow only GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_response(['error' => 'Invalid request method. Use GET.'], 405);
    exit;
}

// Use the global $link variable from db.php

// Build query with optional filters
$where_clauses = [];
$bind_types = "";
$bind_values = [];

// Filter by status
if (isset($_GET['status']) && !empty($_GET['status'])) {
    $where_clauses[] = "vt.status = ?";
    $bind_types .= "s";
    $bind_values[] = $_GET['status'];
}

// Filter by urgency
if (isset($_GET['urgency']) && !empty($_GET['urgency'])) {
    $where_clauses[] = "vt.urgency = ?";
    $bind_types .= "s";
    $bind_values[] = $_GET['urgency'];
}

// Filter by shelter
if (isset($_GET['shelter_id']) && is_numeric($_GET['shelter_id'])) {
    $where_clauses[] = "vt.shelter_id = ?";
    $bind_types .= "i";
    $bind_values[] = intval($_GET['shelter_id']);
}

$where_sql = empty($where_clauses) ? "" : "WHERE " . implode(" AND ", $where_clauses);

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
            vt.assigned_to,
            volunteer.username AS volunteer_username,
            vt.created_at
        FROM 
            volunteer_tasks vt
        LEFT JOIN 
            shelters s ON vt.shelter_id = s.id
        LEFT JOIN
            users creator ON vt.created_by = creator.id
        LEFT JOIN
            users volunteer ON vt.assigned_to = volunteer.id
        $where_sql
        ORDER BY 
            CASE vt.urgency 
                WHEN 'high' THEN 1 
                WHEN 'medium' THEN 2 
                WHEN 'low' THEN 3 
            END,
            vt.task_date ASC,
            vt.created_at DESC";

if (empty($bind_values)) {
    // No parameters
    if ($result = mysqli_query($link, $sql)) {
        $tasks = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $tasks[] = $row;
        }
        send_response($tasks, 200);
        mysqli_free_result($result);
    } else {
        send_response(['error' => 'Database error: Could not retrieve volunteer tasks. ' . mysqli_error($link)], 500);
    }
} else {
    // With parameters
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, $bind_types, ...$bind_values);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $tasks = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $tasks[] = $row;
        }
        send_response($tasks, 200);
        mysqli_stmt_close($stmt);
    } else {
        send_response(['error' => 'Database error: Could not prepare statement. ' . mysqli_error($link)], 500);
    }
}

close_db_connection($link);
?>
