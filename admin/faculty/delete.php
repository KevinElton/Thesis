<?php
session_start();
require_once __DIR__ . '/../../classes/faculty.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../../auth/login.php");
    exit;
}

if (isset($_GET['id'])) {
    $faculty = new Faculty();
    $faculty->deleteFaculty($_GET['id']);
}

header("Location: view.php");
exit;
?>







