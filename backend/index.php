<?php
header('Content-Type: application/json');
echo json_encode(['message' => 'Backend server is running', 'timestamp' => date('Y-m-d H:i:s')]);
?>