<?php
/**
 * RUN THIS FILE ONCE TO CREATE THE ROOMS TABLE
 * Access: http://localhost/Thesis/app/setup_rooms.php
 * Then delete this file after running!
 */

require_once __DIR__ . '/../classes/database.php';

$db = new Database();
$conn = $db->connect();

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <title>Setup Rooms Table</title>
    <script src='https://cdn.tailwindcss.com'></script>
</head>
<body class='bg-gray-100 p-8'>
    <div class='max-w-4xl mx-auto'>
        <div class='bg-white rounded-lg shadow-lg p-8'>
            <h1 class='text-3xl font-bold mb-6 text-blue-600'>🔧 Database Setup: Rooms Table</h1>";

try {
    // Create rooms table
    $sql = "CREATE TABLE IF NOT EXISTS `rooms` (
        `room_id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(100) NOT NULL,
        `capacity` INT DEFAULT 30,
        `mode` ENUM('Online', 'Face-to-Face', 'Hybrid') DEFAULT 'Face-to-Face',
        `location_details` TEXT COMMENT 'Building location or Meet link',
        `is_available` TINYINT(1) DEFAULT 1,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    $conn->exec($sql);
    echo "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4'>
            ✅ <strong>Success:</strong> Rooms table created successfully!
          </div>";
    
    // Insert sample rooms
    $sampleRooms = [
        ['Room 101', 40, 'Face-to-Face', 'CCS Building, 1st Floor', 1],
        ['Room 201', 35, 'Face-to-Face', 'CCS Building, 2nd Floor', 1],
        ['Room 301', 30, 'Face-to-Face', 'CCS Building, 3rd Floor', 1],
        ['Room 401', 25, 'Face-to-Face', 'CCS Building, 4th Floor', 1],
        ['Conference Room A', 50, 'Face-to-Face', 'Admin Building, 2nd Floor', 1],
        ['Conference Room B', 50, 'Face-to-Face', 'Admin Building, 3rd Floor', 1],
        ['Online Room A', 100, 'Online', 'https://meet.google.com/xxx-yyyy-zzz', 1],
        ['Online Room B', 100, 'Online', 'https://zoom.us/j/1234567890', 1],
        ['Hybrid Lab 1', 30, 'Hybrid', 'CCS Lab 1 + Google Meet', 1],
        ['Hybrid Lab 2', 30, 'Hybrid', 'CCS Lab 2 + Zoom', 1],
    ];
    
    $stmt = $conn->prepare("INSERT INTO rooms (name, capacity, mode, location_details, is_available) 
                           VALUES (?, ?, ?, ?, ?)");
    
    $insertCount = 0;
    foreach ($sampleRooms as $room) {
        try {
            $stmt->execute($room);
            $insertCount++;
        } catch (PDOException $e) {
            // Skip if already exists
        }
    }
    
    echo "<div class='bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded mb-4'>
             <strong>Success:</strong> Inserted $insertCount sample rooms!
          </div>";
    
    // Display created rooms
    $stmt = $conn->query("SELECT * FROM rooms ORDER BY room_id");
    $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2 class='text-2xl font-bold mb-4 text-gray-800'>📋 Created Rooms:</h2>
          <div class='overflow-x-auto'>
            <table class='min-w-full border border-gray-300'>
                <thead class='bg-blue-600 text-white'>
                    <tr>
                        <th class='px-4 py-2 text-left'>ID</th>
                        <th class='px-4 py-2 text-left'>Name</th>
                        <th class='px-4 py-2 text-left'>Capacity</th>
                        <th class='px-4 py-2 text-left'>Mode</th>
                        <th class='px-4 py-2 text-left'>Location</th>
                        <th class='px-4 py-2 text-left'>Available</th>
                    </tr>
                </thead>
                <tbody>";
    
    foreach ($rooms as $room) {
        $available = $room['is_available'] ? 'Yes' : ' No';
        echo "<tr class='border-b hover:bg-gray-50'>
                <td class='px-4 py-2'>{$room['room_id']}</td>
                <td class='px-4 py-2 font-semibold'>{$room['name']}</td>
                <td class='px-4 py-2'>{$room['capacity']}</td>
                <td class='px-4 py-2'><span class='px-2 py-1 bg-green-100 text-green-700 rounded text-xs'>{$room['mode']}</span></td>
                <td class='px-4 py-2 text-sm'>{$room['location_details']}</td>
                <td class='px-4 py-2'>$available</td>
              </tr>";
    }
    
    echo "</tbody></table></div>";
    
    echo "<div class='mt-8 p-4 bg-yellow-50 border-l-4 border-yellow-400'>
            <h3 class='font-bold text-yellow-800 mb-2'>⚠️ Important:</h3>
            <ul class='text-yellow-700 space-y-1'>
                <li>Rooms table has been created successfully</li>
                <li> Sample rooms have been added</li>
                <li> You can now <strong>DELETE this file (setup_rooms.php)</strong></li>
                <li> Go back to <a href='manageSchedules.php' class='underline font-bold'>Manage Schedules</a></li>
            </ul>
          </div>";
    
} catch (PDOException $e) {
    echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4'>
            <strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "
          </div>";
}

echo "      <div class='mt-6 flex gap-4'>
                <a href='adminDashboard.php' class='bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition'>
                    🏠 Go to Dashboard
                </a>
                <a href='manageSchedules.php' class='bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 transition'>
                    📅 Manage Schedules
                </a>
            </div>
        </div>
    </div>
</body>
</html>";
?>