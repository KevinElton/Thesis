<?php
session_start();
require_once __DIR__ . '/../classes/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit;
}

$db = new Database();
$conn = $db->connect();
$message = '';

// Create evaluation table if not exists
try {
    $conn->exec("CREATE TABLE IF NOT EXISTS evaluation (
        evaluation_id INT AUTO_INCREMENT PRIMARY KEY,
        group_id INT NOT NULL,
        panelist_id INT NOT NULL,
        content_score INT,
        presentation_score INT,
        defense_score INT,
        overall_score DECIMAL(5,2),
        comments TEXT,
        evaluated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (group_id) REFERENCES thesis_group(group_id) ON DELETE CASCADE,
        FOREIGN KEY (panelist_id) REFERENCES faculty(panelist_id) ON DELETE CASCADE
    )");
} catch (PDOException $e) {
    // Table might already exist
}

// Handle evaluation submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_evaluation'])) {
    $group_id = $_POST['group_id'];
    $panelist_id = $_POST['panelist_id'];
    $content = $_POST['content_score'];
    $presentation = $_POST['presentation_score'];
    $defense = $_POST['defense_score'];
    $comments = trim($_POST['comments']);
    $overall = ($content + $presentation + $defense) / 3;

    try {
        $stmt = $conn->prepare("INSERT INTO evaluation (group_id, panelist_id, content_score, presentation_score, defense_score, overall_score, comments) 
                               VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$group_id, $panelist_id, $content, $presentation, $defense, $overall, $comments]);
        $message = '<div class="bg-green-100 text-green-700 p-4 rounded-lg mb-4"><i data-lucide="check-circle" class="inline w-5 h-5 mr-2"></i>Evaluation submitted successfully!</div>';
    } catch (PDOException $e) {
        $message = '<div class="bg-red-100 text-red-700 p-4 rounded-lg mb-4"><i data-lucide="alert-triangle" class="inline w-5 h-5 mr-2"></i>Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

// Fetch all evaluations
$evaluations = $conn->query("
    SELECT e.*, 
           tg.leader_name, tg.course,
           CONCAT(f.first_name, ' ', f.last_name) as panelist_name,
           t.title as thesis_title
    FROM evaluation e
    INNER JOIN thesis_group tg ON e.group_id = tg.group_id
    INNER JOIN faculty f ON e.panelist_id = f.panelist_id
    LEFT JOIN thesis t ON tg.group_id = t.group_id
    ORDER BY e.evaluated_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch groups for dropdown
$groups = $conn->query("
    SELECT tg.group_id, tg.leader_name, tg.course, t.title
    FROM thesis_group tg
    LEFT JOIN thesis t ON tg.group_id = t.group_id
    ORDER BY tg.group_id DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch panelists
$panelists = $conn->query("
    SELECT panelist_id, CONCAT(first_name, ' ', last_name) as name
    FROM faculty
    ORDER BY last_name
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Evaluations</title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="bg-gray-100 min-h-screen flex">
<?php include 'sidebar.php'; ?>
<main class="flex-1 ml-64 p-8">
<div class="flex justify-between items-center mb-6">
    <h1 class="text-3xl font-bold text-gray-800 flex items-center gap-2">
        <i data-lucide="clipboard-check"></i> Defense Evaluations
    </h1>
    <button onclick="toggleModal()" class="bg-blue-600 text-white px-5 py-2 rounded-lg hover:bg-blue-700 flex items-center gap-2 shadow-md">
        <i data-lucide="plus-circle"></i> Add Evaluation
    </button>
</div>

<?= $message ?>

<!-- Evaluations List -->
<div class="bg-white shadow-lg rounded-2xl p-6 overflow-x-auto">
    <h2 class="text-xl font-semibold mb-4 flex items-center gap-2 text-gray-800">
        <i data-lucide="list"></i> All Evaluations
    </h2>
    <table class="min-w-full border border-gray-200">
        <thead class="bg-blue-600 text-white">
            <tr>
                <th class="px-4 py-3 text-left">Group</th>
                <th class="px-4 py-3 text-left">Panelist</th>
                <th class="px-4 py-3 text-center">Content</th>
                <th class="px-4 py-3 text-center">Presentation</th>
                <th class="px-4 py-3 text-center">Defense</th>
                <th class="px-4 py-3 text-center">Overall</th>
                <th class="px-4 py-3 text-left">Date</th>
                <th class="px-4 py-3 text-center">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($evaluations)): ?>
                <?php foreach ($evaluations as $eval): ?>
                    <tr class="border-b hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <div class="font-semibold"><?= htmlspecialchars($eval['leader_name']) ?></div>
                            <div class="text-xs text-gray-500"><?= htmlspecialchars($eval['course']) ?></div>
                        </td>
                        <td class="px-4 py-3"><?= htmlspecialchars($eval['panelist_name']) ?></td>
                        <td class="px-4 py-3 text-center font-bold text-blue-600"><?= $eval['content_score'] ?></td>
                        <td class="px-4 py-3 text-center font-bold text-green-600"><?= $eval['presentation_score'] ?></td>
                        <td class="px-4 py-3 text-center font-bold text-purple-600"><?= $eval['defense_score'] ?></td>
                        <td class="px-4 py-3 text-center">
                            <span class="px-3 py-1 rounded-full font-bold <?= $eval['overall_score'] >= 85 ? 'bg-green-100 text-green-700' : ($eval['overall_score'] >= 75 ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700') ?>">
                                <?= number_format($eval['overall_score'], 2) ?>%
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm"><?= date('M d, Y', strtotime($eval['evaluated_at'])) ?></td>
                        <td class="px-4 py-3 text-center">
                            <button onclick="viewComments('<?= htmlspecialchars($eval['comments'], ENT_QUOTES) ?>')" class="text-blue-600 hover:text-blue-800">
                                <i data-lucide="eye" class="w-4 h-4"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="8" class="text-center py-6 text-gray-500">No evaluations found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
</main>

<!-- Add Evaluation Modal -->
<div id="evalModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
        <div class="bg-blue-600 text-white p-6 rounded-t-2xl flex justify-between items-center">
            <h3 class="text-2xl font-bold"><i data-lucide="clipboard-check"></i> Add Evaluation</h3>
            <button onclick="toggleModal()" class="text-white hover:bg-blue-700 p-2 rounded-full">
                <i data-lucide="x"></i>
            </button>
        </div>

        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="submit_evaluation" value="1">
            
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Thesis Group *</label>
                <select name="group_id" required class="w-full border border-gray-300 rounded-lg p-3">
                    <option value="">Select Group</option>
                    <?php foreach ($groups as $g): ?>
                        <option value="<?= $g['group_id'] ?>"><?= htmlspecialchars($g['leader_name'] . ' - ' . ($g['title'] ?: $g['course'])) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Panelist *</label>
                <select name="panelist_id" required class="w-full border border-gray-300 rounded-lg p-3">
                    <option value="">Select Panelist</option>
                    <?php foreach ($panelists as $p): ?>
                        <option value="<?= $p['panelist_id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Content (0-100) *</label>
                    <input type="number" name="content_score" min="0" max="100" required class="w-full border rounded-lg p-3">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Presentation (0-100) *</label>
                    <input type="number" name="presentation_score" min="0" max="100" required class="w-full border rounded-lg p-3">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Defense (0-100) *</label>
                    <input type="number" name="defense_score" min="0" max="100" required class="w-full border rounded-lg p-3">
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Comments</label>
                <textarea name="comments" rows="4" class="w-full border border-gray-300 rounded-lg p-3" placeholder="Optional feedback..."></textarea>
            </div>

            <div class="flex gap-4 pt-4 border-t">
                <button type="submit" class="flex-1 bg-blue-600 text-white py-3 rounded-lg font-semibold hover:bg-blue-700">
                    Submit Evaluation
                </button>
                <button type="button" onclick="toggleModal()" class="bg-gray-300 text-gray-800 px-6 py-3 rounded-lg font-semibold hover:bg-gray-400">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Comments Modal -->
<div id="commentsModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold text-gray-800">Evaluation Comments</h3>
            <button onclick="closeComments()" class="text-gray-500 hover:text-gray-700">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>
        </div>
        <div id="commentsContent" class="text-gray-700 whitespace-pre-wrap"></div>
    </div>
</div>

<script>
lucide.createIcons();

function toggleModal() {
    document.getElementById('evalModal').classList.toggle('hidden');
    setTimeout(() => lucide.createIcons(), 100);
}

function viewComments(comments) {
    document.getElementById('commentsContent').textContent = comments || 'No comments provided.';
    document.getElementById('commentsModal').classList.remove('hidden');
    lucide.createIcons();
}

function closeComments() {
    document.getElementById('commentsModal').classList.add('hidden');
}
</script>
</body>
</html>