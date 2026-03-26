<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isAuthenticated() {
    return isset($_SESSION['user_id']);
}

function restrictAccess() {
    if (!isAuthenticated()) {
        header('Location: index.php');
        exit;
    }
}

function restrictApiAccess() {
    if (!isAuthenticated()) {
        header('Content-Type: application/json');
        http_response_code(401);
        die(json_encode(['error' => 'Unauthorized access']));
    }
}
