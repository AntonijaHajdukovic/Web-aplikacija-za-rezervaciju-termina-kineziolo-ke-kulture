<?php

function requireLogin()
{
    if (!isset($_SESSION['user_id'])) {
        header('Location: ../login.php');
        exit;
    }
}

function requireRole($requiredRole)
{
    requireLogin();

    if ($_SESSION['role'] !== $requiredRole) {
        http_response_code(403);
        echo '<h1>403 - Zabranjen pristup</h1>';
        echo '<p>Nemate dopuštenje za pristup ovoj stranici.</p>';
        echo '<a href="../index.php">Povratak na početnu</a>';
        exit;
    }
}