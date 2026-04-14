<?php
function requireAuth() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

function requireRole($roles) {
    requireAuth();
    if (!in_array($_SESSION['rol'], (array)$roles)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Acceso prohibido']);
        exit;
    }
}

function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

function getCurrentUserRole() {
    return $_SESSION['rol'] ?? null;
}

function getCurrentUserSede() {
    return $_SESSION['sede_id'] ?? null;
}

function isAdmin() {
    return getCurrentUserRole() === 'admon';
}

function isDistribuidor() {
    return getCurrentUserRole() === 'dist';
}

function isComprador() {
    return getCurrentUserRole() === 'comprador';
}