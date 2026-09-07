<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('TRAINHUBSESSID');
    session_start();
}

if (!isset($_SESSION['staffID']) && !isset($_SESSION['staff_id'])) {
    header('Location: login.php');
    exit();
}
