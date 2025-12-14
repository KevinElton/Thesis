<?php
session_start();
require_once __DIR__ . '/../../classes/faculty.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../../auth/login.php");
    exit;
}

$faculty = new Faculty();

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $facultyData = $faculty->getFacultyById($id);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $email = $_POST['email'];
    $department = $_POST['department'];
    $expertise = $_POST['expertise'];

    $faculty->updateFaculty($id, $first_name, $last_name, $email, $department, $expertise);
    header("Location: view.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Faculty</title>
    <script src="/Thesis/assets/js/tailwind.js"></script>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <form action="" method="POST" class="bg-white shadow-lg rounded-xl p-8 w-full max-w-md space-y-4">
        <h1 class="text-2xl font-bold text-gray-800 mb-4">Edit Faculty</h1>
        <input type="hidden" name="id" value="<?= $facultyData['id'] ?>">
        <input type="text" name="first_name" value="<?= htmlspecialchars($facultyData['first_name']) ?>" placeholder="First Name" class="w-full border rounded-lg p-2">
        <input type="text" name="last_name" value="<?= htmlspecialchars($facultyData['last_name']) ?>" placeholder="Last Name" class="w-full border rounded-lg p-2">
        <input type="email" name="email" value="<?= htmlspecialchars($facultyData['email']) ?>" placeholder="Email" class="w-full border rounded-lg p-2">
        <input type="text" name="department" value="<?= htmlspecialchars($facultyData['department']) ?>" placeholder="Department" class="w-full border rounded-lg p-2">
        <input type="text" name="expertise" value="<?= htmlspecialchars($facultyData['expertise']) ?>" placeholder="Expertise" class="w-full border rounded-lg p-2">
        <div class="flex justify-end gap-2">
            <a href="view.php" class="bg-gray-400 text-white px-4 py-2 rounded hover:bg-gray-500">Cancel</a>
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Update</button>
        </div>
    </form>
</body>
</html>







