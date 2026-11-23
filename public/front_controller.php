<?php

header('Access-Control-Allow-Origin: http://localhost');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit(0);
}

require_once __DIR__ . '/../helpers/global_helper.php';
require_once __DIR__ . '/../helpers/http_response_helper.php';
require_once __DIR__ . '/../helpers/jwt_helper.php';
require_once __DIR__ . '/../src/Security/JwtService.php';
require_once __DIR__ . '/../src/Controller/UserController.php';
require_once __DIR__ . '/../src/Controller/GameController.php';

$userController = new UserController();
$gameController = new GameController();
$jwtService = new JwtService('ma_cle_secrete');

$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$requestMethod = $_SERVER['REQUEST_METHOD'];

if (!str_starts_with($requestUri, '/api/')) {
    sendResponse403();
} elseif ($requestUri === '/api/login') {
    switch ($requestMethod) {
        case 'POST':
            $userController->authenticate();
            break;
        default:
            sendResponse405();
            break;
    }
} elseif ($requestUri === '/api/token/refresh') {
    switch ($requestMethod) {
        case 'POST':
            $userController->reauthenticate();
            break;
        default:
            sendResponse405();
            break;
    }
} elseif ($requestUri === '/api/user' && $requestMethod === 'POST') {
    $userController->create();
} else {
    $payload = authenticateRequest($jwtService);
    if (!$payload) {
        sendResponse401();
        exit(1);
    } else {
        try {
            $userId = $payload['user_id'];
            $user = $userController->getUser()->findById($userId);
//            sendResponseCustom('Successfully retrieved user data');
            // Chaque requête authentifiée prouve que l'utilisateur est en ligne (Motus : joueurs connectés)
            $userController->getUser()->touchLastConnected($userId);
        } catch (Exception $e) {
            sendResponse404();
            exit(1);
        }
    }
    if ($requestUri === '/api/logout') {
        switch ($requestMethod) {
            case 'POST':
                $userController->deauthenticate($userId);
                break;
            default:
                sendResponse405();
                break;
        }
    } elseif ($requestUri === '/api/players') {
        switch ($requestMethod) {
            case 'GET':
                $userController->availablePlayers($userId);
                break;
            default:
                sendResponse405();
                break;
        }
    } elseif ($requestUri === '/api/game') {
        switch ($requestMethod) {
            case 'POST':
                $gameController->create($userId);
                break;
            default:
                sendResponse405();
                break;
        }
    } elseif ($requestUri === '/api/game/current') {
        switch ($requestMethod) {
            case 'GET':
                $gameController->current($userId);
                break;
            default:
                sendResponse405();
                break;
        }
    } elseif (preg_match('#^/api/game/(\d+)$#', $requestUri, $matches)) {
        switch ($requestMethod) {
            case 'GET':
                $gameController->show($userId, $matches[1]);
                break;
            default:
                sendResponse405();
                break;
        }
    } elseif (preg_match('#^/api/game/(\d+)/guess$#', $requestUri, $matches)) {
        switch ($requestMethod) {
            case 'POST':
                $gameController->guess($userId, $matches[1]);
                break;
            default:
                sendResponse405();
                break;
        }
    } elseif ($requestUri === '/api/user') {
        switch ($requestMethod) {
            case 'GET':
                $userController->list();
                break;
            default:
                sendResponse405();
                break;
        }
    } elseif (preg_match('#^/api/user/(\d+)$#', $requestUri, $matches)) {
        $authUserId = (int) $payload['user_id']; // utilisateur connecté (token)
        $userId = (int) $matches[1];             // compte visé (URL)

        // Seul le propriétaire du compte peut le modifier ou le supprimer
        if (in_array($requestMethod, ['PUT', 'PATCH', 'DELETE']) && $userId !== $authUserId) {
            sendResponse403();
            exit(1);
        }

        switch ($requestMethod) {
            case 'GET':
                $userController->get($userId);
                break;
            case 'PUT':
                $userController->replace($userId);
                break;
            case 'PATCH':
                $userController->update($userId);
                break;
            case 'DELETE':
                $userController->delete($userId);
                break;
            default:
                sendResponse405();
                break;
        }
    } else {
        sendResponse404();
    }
}