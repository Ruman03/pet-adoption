<?php
session_start();

require_once '../../config/db.php';
require_once '../../utils/response.php';

// Allow only GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_response(['error' => 'Invalid request method. Use GET.'], 405);
    exit;
}

// Check if user is logged in and is staff or admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['staff', 'admin'])) {
    send_response(['error' => 'Forbidden: Only staff or admins can view all volunteer applications.'], 403);
    exit;
}

$link = get_db_connection();

// Build query with optional filters
$where_clauses = [];
$bind_types = "";
$bind_values = [];

// Filter by status
if (isset($_GET['status']) && !empty($_GET['status'])) {
    $where_clauses[] = "va.status = ?";
    $bind_types .= "s";
    $bind_values[] = $_GET['status'];
}

// Filter by application type
if (isset($_GET['application_type']) && !empty($_GET['application_type'])) {
    $where_clauses[] = "va.application_type = ?";
    $bind_types .= "s";
    $bind_values[] = $_GET['application_type'];
}

$where_sql = empty($where_clauses) ? "" : "WHERE " . implode(" AND ", $where_clauses);

$sql = "SELECT 
            va.id, 
            va.user_id,
            u.username,
            u.full_name,
            u.email,
            u.phone,
            va.application_type,
            va.availability,
            va.experience,
            va.skills,
            va.motivation,
            va.emergency_contact_name,
            va.emergency_contact_phone,
            va.status,
            va.applied_at,
            va.reviewed_by,
            reviewer.username AS reviewer_username,
            va.reviewed_at,
            va.notes
        FROM 
            volunteer_applications va
        JOIN 
            users u ON va.user_id = u.id
        LEFT JOIN
            users reviewer ON va.reviewed_by = reviewer.id
        $where_sql
        ORDER BY 
            CASE va.status 
                WHEN 'pending' THEN 1 
                WHEN 'interview_scheduled' THEN 2 
                WHEN 'approved' THEN 3 
                WHEN 'rejected' THEN 4 
            END,
            va.applied_at DESC";

if (empty($bind_values)) {
    // No parameters
    if ($result = mysqli_query($link, $sql)) {
        $applications = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $applications[] = $row;
        }
        send_response($applications, 200);
        mysqli_free_result($result);
    } else {
        send_response(['error' => 'Database error: Could not retrieve volunteer applications. ' . mysqli_error($link)], 500);
    }
} else {
    // With parameters
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, $bind_types, ...$bind_values);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $applications = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $applications[] = $row;
        }
        send_response($applications, 200);
        mysqli_stmt_close($stmt);
    } else {
        send_response(['error' => 'Database error: Could not prepare statement. ' . mysqli_error($link)], 500);
    }
}

close_db_connection($link);
?>
