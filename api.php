<?php
/**
 * Backend API PHP para Panfitrion CRM
 * Maneja persistencia de datos en JSON, autenticación CORS y respuestas JSON.
 */

// Permitir solicitudes CORS desde cualquier origen (ej. GitHub Pages)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

header('Content-Type: application/json; charset=utf-8');

$dbDir = __DIR__ . '/data';
if (!file_exists($dbDir)) {
    mkdir($dbDir, 0777, true);
}

// Rutas de almacenamiento
$stateFile = $dbDir . '/state.json';
$sessionFile = $dbDir . '/session.json';

// Inicializar archivo de estado si no existe
if (!file_exists($stateFile)) {
    $initialState = [
        "cafeterias" => [],
        "pedidos" => [],
        "cuentas" => []
    ];
    file_put_contents($stateFile, json_encode($initialState, JSON_PRETTY_PRINT));
}

$requestUri = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? null;
$keyParam = $_GET['key'] ?? null;

// Parsear cuerpo JSON si aplica
$rawInput = file_get_contents('php://input');
$inputData = json_decode($rawInput, true);

// Routing principal basado en URL o parámetro ?action=

// 1. Obtener estado completo (?action=state o /api/state)
if ($action === 'state' || (strpos($requestUri, '/api/state') !== false && $method === 'GET' && strpos($requestUri, '/api/state/') === false)) {
    if (file_exists($stateFile)) {
        echo file_get_contents($stateFile);
    } else {
        echo json_encode(["cafeterias" => [], "pedidos" => [], "cuentas" => []]);
    }
    exit();
}

// 2. Guardar estado por clave (?action=save_state&key=... o /api/state/{key})
if ($action === 'save_state' || (strpos($requestUri, '/api/state/') !== false && ($method === 'POST' || $method === 'PUT'))) {
    $key = $keyParam;
    if (!$key && strpos($requestUri, '/api/state/') !== false) {
        $parts = explode('/api/state/', $requestUri);
        $key = explode('?', $parts[1])[0];
    }

    if (!$key) {
        http_response_code(400);
        echo json_encode(["error" => "Se requiere parámetro key"]);
        exit();
    }

    $currentState = json_decode(file_get_contents($stateFile), true) ?? [];
    $currentState[$key] = $inputData;

    file_put_contents($stateFile, json_encode($currentState, JSON_PRETTY_PRINT));
    echo json_encode(["success" => true, "key" => $key]);
    exit();
}

// 3. Sesión (?action=session o /api/session o /api/delivery/session)
if ($action === 'session' || strpos($requestUri, '/api/session') !== false || strpos($requestUri, '/api/delivery/session') !== false) {
    if ($method === 'GET') {
        if (file_exists($sessionFile)) {
            echo file_get_contents($sessionFile);
        } else {
            echo json_encode(["active" => true, "user" => "Master"]);
        }
        exit();
    } elseif ($method === 'POST') {
        file_put_contents($sessionFile, json_encode($inputData, JSON_PRETTY_PRINT));
        echo json_encode(["success" => true, "session" => $inputData]);
        exit();
    }
}

// 4. Endpoint de configuración inicial / Master Setup (/api/master-user/setup)
if (strpos($requestUri, '/api/master-user/setup') !== false) {
    echo json_encode(["success" => true, "token" => "token_master_panfitrion"]);
    exit();
}

// Endpoint por defecto para peticiones no reconocidas
http_response_code(200);
echo json_encode(["status" => "online", "message" => "Panfitrion CRM API activa"]);
