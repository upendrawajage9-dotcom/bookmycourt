<?php
/**
 * BookMyCourt — Healthcheck Endpoint
 * 
 * Lightweight endpoint for Railway / load-balancer health checks.
 * Does NOT require a database connection.
 */
http_response_code(200);
header('Content-Type: application/json');
echo json_encode([
    'status' => 'ok',
    'app'    => 'BookMyCourt',
    'time'   => date('c'),
]);
