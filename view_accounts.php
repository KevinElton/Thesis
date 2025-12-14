<?php
require_once __DIR__ . '/config/database.php';

$db = new Database();
$conn = $db->connect();

echo "<h2>Panelist Accounts</h2>";
echo "<table border='1' cellpadding='10' style='border-collapse:collapse;'>";
echo "<tr style='background:#333;color:white;'><th>Name</th><th>Email</th><th>Status</th><th>Password Type</th><th>Plain Password (if any)</th></tr>";

$stmt = $conn->query("SELECT * FROM panelist");
$panelists = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($panelists as $p) {
    $isHashed = (substr($p['password'], 0, 1) === '$');
    $pwdType = $isHashed ? '🔒 Hashed (secure)' : '⚠️ Plain Text';
    $plainPwd = $isHashed ? '-' : $p['password'];
    
    echo "<tr>";
    echo "<td>" . htmlspecialchars($p['first_name'] . ' ' . $p['last_name']) . "</td>";
    echo "<td>" . htmlspecialchars($p['email']) . "</td>";
    echo "<td>" . htmlspecialchars($p['status']) . "</td>";
    echo "<td>" . $pwdType . "</td>";
    echo "<td><code>" . htmlspecialchars($plainPwd) . "</code></td>";
    echo "</tr>";
}
echo "</table>";

echo "<hr><h2>Admin Accounts</h2>";
echo "<table border='1' cellpadding='10' style='border-collapse:collapse;'>";
echo "<tr style='background:#333;color:white;'><th>Name</th><th>Email</th><th>Password Type</th><th>Plain Password (if any)</th></tr>";

$stmt = $conn->query("SELECT * FROM admin");
$admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($admins as $a) {
    $isHashed = (substr($a['password'], 0, 1) === '$');
    $pwdType = $isHashed ? '🔒 Hashed (secure)' : '⚠️ Plain Text';
    $plainPwd = $isHashed ? '-' : $a['password'];
    
    echo "<tr>";
    echo "<td>" . htmlspecialchars($a['name'] ?? 'Admin') . "</td>";
    echo "<td>" . htmlspecialchars($a['email']) . "</td>";
    echo "<td>" . $pwdType . "</td>";
    echo "<td><code>" . htmlspecialchars($plainPwd) . "</code></td>";
    echo "</tr>";
}
echo "</table>";

echo "<p style='color:red;margin-top:20px;'><strong>⚠️ DELETE THIS FILE after viewing!</strong></p>";
?>
