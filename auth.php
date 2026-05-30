<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isAuthenticated() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
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

function restrictAdminApiAccess() {
    restrictApiAccess();
    if (!isAdmin()) {
        header('Content-Type: application/json');
        http_response_code(403);
        die(json_encode(['success' => false, 'error' => 'Forbidden: admin only']));
    }
}
