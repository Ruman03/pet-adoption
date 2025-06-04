<?php
require_once '../../config/db.php';
require_once '../../utils/response.php';

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Get query parameters for filtering
$status = $_GET['status'] ?? null;
$species = $_GET['species'] ?? null;
$shelter_id = $_GET['shelter_id'] ?? null;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Build query with optional filters
    $where_conditions = [];
    $params = [];
    
    if ($status) {
        $where_conditions[] = "p.status = ?";
        $params[] = $status;
    }
    
    if ($species) {
        $where_conditions[] = "p.species = ?";
        $params[] = $species;
    }
    
    if ($shelter_id) {
        $where_conditions[] = "p.shelter_id = ?";
        $params[] = $shelter_id;
    }
    
    $where_clause = empty($where_conditions) ? "" : "WHERE " . implode(" AND ", $where_conditions);
    
    // Get total count
    $count_query = "SELECT COUNT(*) as total FROM pets p $where_clause";
    $count_stmt = $db->prepare($count_query);
    $count_stmt->execute($params);
    $total_count = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get pets with shelter information
    $params[] = $limit;
    $params[] = $offset;
    
    $query = "SELECT 
                p.id,
                p.name,
                p.species,
                p.breed,
                p.age,
                p.gender,
                p.size,
                p.color,
                p.description,
                p.status,
                p.photo_url,
                p.adoption_fee,
                p.shelter_id,
                p.created_at,
                p.updated_at,
                s.name as shelter_name,
                s.address as shelter_address,
                s.phone as shelter_phone,
                s.email as shelter_email
              FROM pets p
              LEFT JOIN shelters s ON p.shelter_id = s.id
              $where_clause
              ORDER BY p.created_at DESC
              LIMIT ? OFFSET ?";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    
    $pets = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Convert numeric values
        $row['id'] = (int)$row['id'];
        $row['age'] = (int)$row['age'];
        $row['adoption_fee'] = (float)$row['adoption_fee'];
        $row['shelter_id'] = (int)$row['shelter_id'];
        
        $pets[] = $row;
    }
    
    sendResponse(200, 'Pets retrieved successfully', [
        'total_count' => (int)$total_count,
        'current_page_count' => count($pets),
        'offset' => $offset,
        'limit' => $limit,
        'pets' => $pets
    ]);
    
} catch (PDOException $e) {
    sendResponse(500, 'Database error: ' . $e->getMessage());
} catch (Exception $e) {
    sendResponse(500, 'Server error: ' . $e->getMessage());
}
?>