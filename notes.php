<?php
session_start();
if (isset($_SESSION["user_id"])) { try { $pdo = new PDO("mysql:host=yamabiko.proxy.rlwy.net;port=27745;dbname=railway;charset=utf8mb4", "appuser", "AppP@ssw0rd!"); $pdo->prepare("UPDATE students SET last_seen_notes = NOW() WHERE id = :id")->execute(["id" => $_SESSION["user_id"]]); } catch (PDOException $e) {} }
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$message = '';
if (isset($_GET['uploaded'])) $message = 'Note uploaded successfully.';
if (isset($_GET['updated'])) $message = 'Note updated successfully.';
if (isset($_GET['deleted'])) $message = 'Note deleted successfully.';
$error = '';

try {
    $pdo = new PDO("mysql:host=yamabiko.proxy.rlwy.net;port=27745;dbname=railway;charset=utf8mb4", 'root', 'lpBBXfReELFhpzVsXbKvsUVjAmTJhDCs');
    
    $stmt = $pdo->prepare('SELECT s.*, c.name AS course_name, c.code AS course_code FROM students s LEFT JOIN courses c ON s.course_id = c.id WHERE s.id = :id');
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $student = $stmt->fetch();
    
    // Handle note upload
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['note_file'])) {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $file = $_FILES['note_file'];
        
        $allowed = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'text/plain', 'image/png', 'image/jpeg'];
        $maxSize = 10 * 1024 * 1024;
        
        if (!$title) { $error = 'Please enter a title.'; }
        elseif ($file['error'] !== UPLOAD_ERR_OK) { $error = 'File upload failed.'; }
        elseif ($file['size'] > $maxSize) { $error = 'File too large. Maximum 10MB.'; }
        elseif (!in_array($file['type'], $allowed)) { $error = 'Invalid file type. Allowed: PDF, DOC, DOCX, TXT, PNG, JPG.'; }
        else {
            $uploadDir = '/data/data/com.termux/files/home/web_uploads/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['name']);
            if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                $stmt = $pdo->prepare('INSERT INTO notes (title, description, file_path, course_id, uploaded_by, file_size) VALUES (:t, :d, :fp, :cid, :uid, :fs)');
                $stmt->execute(['t' => $title, 'd' => $description, 'fp' => $filename, 'cid' => $student['course_id'], 'uid' => $_SESSION['user_id'], 'fs' => $file['size']]);
                header('Location: notes.php?uploaded=1'); exit;
            } else { $error = 'Failed to save file.'; }
        }
    }
    
    // Handle note deletion
    if (isset($_GET['delete'])) {
        $noteId = intval($_GET['delete']);
        $stmt = $pdo->prepare('SELECT file_path FROM notes WHERE id = :id AND uploaded_by = :uid');
        $stmt->execute(['id' => $noteId, 'uid' => $_SESSION['user_id']]);
        $noteToDelete = $stmt->fetch();
        if ($noteToDelete) {
            $filepath = '/data/data/com.termux/files/home/web_uploads/' . $noteToDelete['file_path'];
            if (file_exists($filepath)) unlink($filepath);
            $stmt = $pdo->prepare('DELETE FROM notes WHERE id = :id');
            $stmt->execute(['id' => $noteId]);
            header('Location: notes.php?deleted=1'); exit;
        }
    }
    
    // Handle note edit
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_note'])) {
        $noteId = intval($_POST['note_id']);
        $newTitle = trim($_POST['edit_title'] ?? '');
        $newDesc = trim($_POST['edit_description'] ?? '');
        if ($newTitle && $noteId) {
            $stmt = $pdo->prepare('UPDATE notes SET title = :t, description = :d WHERE id = :id AND uploaded_by = :uid');
            $stmt->execute(['t' => $newTitle, 'd' => $newDesc, 'id' => $noteId, 'uid' => $_SESSION['user_id']]);
            header('Location: notes.php?updated=1'); exit;
        }
    }
    
    $stmt = $pdo->prepare('SELECT n.*, s.full_name AS uploader_name FROM notes n JOIN students s ON n.uploaded_by = s.id WHERE n.course_id = :cid ORDER BY n.created_at DESC');
    $stmt->execute(['cid' => $student['course_id']]);
    $notes = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = 'Error loading notes.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Notes - Student Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f8f9fa; color: #1a1a1a; min-height: 100vh; }
        .mobile-header { display: none; background: #fff; padding: 1rem 1.25rem; border-bottom: 1px solid #e5e7eb; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 100; }
        .hamburger { background: none; border: none; cursor: pointer; padding: 0.5rem; display: flex; flex-direction: column; gap: 5px; }
        .hamburger span { display: block; width: 24px; height: 2px; background: #1a1a1a; border-radius: 2px; }
        .mobile-logo { font-weight: 700; font-size: 1.1rem; }
        .sidebar { width: 260px; background: #fff; border-right: 1px solid #e5e7eb; padding: 1.5rem; position: fixed; top: 0; left: 0; bottom: 0; display: flex; flex-direction: column; z-index: 200; transition: transform 0.3s ease; }
        .sidebar-logo { font-size: 1.5rem; font-weight: 800; margin-bottom: 2rem; letter-spacing: -0.5px; }
        .sidebar-nav { list-style: none; flex: 1; }
        .sidebar-nav li { margin-bottom: 0.35rem; }
        .sidebar-nav a { display: flex; align-items: center; gap: 0.75rem; padding: 0.65rem 0.85rem; border-radius: 12px; color: #4b5563; text-decoration: none; font-size: 0.875rem; font-weight: 500; transition: all 0.15s; border-left: 3px solid transparent; }
        .sidebar-nav a:hover { background: #f3f4f6; color: #1a1a1a; }
        .sidebar-nav a.active { background: #f3f4f6; color: #1a1a1a; font-weight: 600; border-left-color: #1a1a1a; }
        .sidebar-nav .nav-icon { width: 20px; text-align: center; font-size: 0.8rem; opacity: 0.5; }
        .sidebar-footer { border-top: 1px solid #e5e7eb; padding-top: 1rem; }
        .sidebar-footer a { color: #6b7280; text-decoration: none; font-size: 0.85rem; }
        .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.4); z-index: 150; }
        .sidebar-overlay.active { display: block; }
        .main-content { margin-left: 260px; padding: 2rem; }
        .page-header { margin-bottom: 1.5rem; }
        .page-title { font-size: 1.5rem; font-weight: 700; }
        .card { background: #fff; border-radius: 12px; border: none; padding: 1.5rem; margin-bottom: 1.5rem; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .card-title { font-size: 1rem; font-weight: 600; margin-bottom: 1rem; }
        .alert { padding: 0.75rem 1rem; border-radius: 12px; font-size: 0.875rem; margin-bottom: 1rem; }
        .alert-success { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }
        .alert-error { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
        .form-input, .form-textarea { width: 100%; padding: 0.75rem 0.85rem; background: #f9fafb; border: 1.5px solid #e5e7eb; border-radius: 12px; font-size: 0.9rem; font-family: inherit; margin-bottom: 0.75rem; }
        .form-textarea { resize: vertical; min-height: 80px; }
        .form-input:focus, .form-textarea:focus { outline: none; border-color: #1a1a1a; background: #fff; }
        .btn { padding: 0.55rem 1rem; background: #1a1a1a; color: white; border: none; border-radius: 12px; font-size: 0.82rem; font-weight: 600; cursor: pointer; font-family: inherit; text-decoration: none; display: inline-block; }
        .btn:hover { background: #333; }
        .btn-sm { padding: 0.35rem 0.7rem; font-size: 0.75rem; border-radius: 10px; }
        .btn-danger { background: #ef4444; }
        .btn-danger:hover { background: #dc2626; }
        .btn-outline { background: transparent; color: #1a1a1a; border: 1.5px solid #d1d5db; }
        .btn-outline:hover { background: #f3f4f6; }
        .note-table { width: 100%; border-collapse: collapse; }
        .note-table th { text-align: left; padding: 0.75rem; font-size: 0.78rem; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid #e5e7eb; }
        .note-table td { padding: 0.75rem; border-bottom: 1px solid #f3f4f6; font-size: 0.88rem; }
        .note-table tr:hover td { background: #fafafa; }
        .badge { display: inline-block; padding: 0.2rem 0.6rem; background: #f3f4f6; border-radius: 20px; font-size: 0.72rem; font-weight: 500; color: #374151; }
        .file-input-wrapper { position: relative; overflow: hidden; display: inline-block; margin-bottom: 0.75rem; width: 100%; }
        .file-input-wrapper input[type="file"] { position: absolute; left: 0; top: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; }
        .file-input-label { display: block; padding: 0.75rem 0.85rem; background: #f9fafb; border: 1.5px dashed #d1d5db; border-radius: 12px; text-align: center; color: #6b7280; font-size: 0.9rem; cursor: pointer; }
        .action-group { display: flex; gap: 0.35rem; flex-wrap: wrap; }
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 300; align-items: center; justify-content: center; padding: 1rem; }
        .modal-overlay.active { display: flex; }
        .modal { background: #fff; border-radius: 16px; padding: 2rem; width: 100%; max-width: 450px; box-shadow: 0 20px 60px rgba(0,0,0,0.2); position: relative; }
        .modal-close { position: absolute; top: 1rem; right: 1rem; background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #6b7280; }
        .modal-title { font-size: 1.1rem; font-weight: 700; margin-bottom: 1.25rem; }
        @media (max-width: 1024px) {
            .note-table { display: block; overflow-x: auto; white-space: nowrap; }
            .note-table th, .note-table td { padding: 0.5rem; font-size: 0.75rem; }
            .action-group .btn-sm { padding: 0.3rem 0.5rem; font-size: 0.7rem; }
            .mobile-header { display: flex; }
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 1rem; }
            .note-table { font-size: 0.8rem; }
            .note-table th:nth-child(3), .note-table td:nth-child(3) { display: none; }
        }
        body.dark { background: #0f172a; color: #e2e8f0; }
        body.dark .sidebar, body.dark .card, body.dark .mobile-header { background: #1e293b; color: #e2e8f0; border-color: #334155; }
        body.dark .sidebar-nav a { color: #94a3b8; }
        body.dark .sidebar-nav a:hover, body.dark .sidebar-nav a.active { background: #334155; color: #fff; }
        body.dark .sidebar-logo, body.dark .page-title { color: #f1f5f9; }
        body.dark .sidebar { background: #1e293b; border-color: #334155; }
        body.dark .mobile-header { border-color: #334155; }
        body.dark .hamburger span { background: #e2e8f0; }
    </style>
</head>
<body>
    <header class="mobile-header">
        <button class="hamburger" onclick="toggleSidebar()"><span></span><span></span><span></span></button>
        <div class="mobile-logo">Student Portal</div>
            <button onclick="toggleTheme()" style="background:none;border:none;cursor:pointer;font-size:1.2rem;" id="themeBtn"><i class="fas fa-moon"></i></button>
        <div></div>
    </header>
    <div class="sidebar-overlay" id="overlay" onclick="toggleSidebar()"></div>
    
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-logo">Student Portal</div>
        <ul class="sidebar-nav">
            <li><a href="dashboard.php"><span class="nav-icon"><i class="fas fa-th-large"></i></span> Dashboard</a></li>
<li><a href="assignments.php"><i class="fas fa-clipboard-list"></i> Assignments</a></li>
            <li><a href="notes.php" class="active"><span class="nav-icon"><i class="fas fa-file-alt"></i></span> Course Notes</a></li>
            <?php if ($student["role"] === "teacher" || $student["role"] === "admin"): ?><li><a href="teacher.php"><i class="fas fa-clipboard-check"></i> Teacher Panel</a></li><?php endif; ?>
            <li><a href="profile.php"><span class="nav-icon"><i class="fas fa-user"></i></span> My Profile</a></li>
        </ul>
        <div class="sidebar-footer"><a href="logout.php">Sign out</a></div>
    </aside>
    
    <main class="main-content">
        <div class="page-header">
            <h1 class="page-title">Course Notes</h1>
            <p style="color:#6b7280;"><?php echo htmlspecialchars(($student['course_name'] ?? 'N/A') . ' (' . ($student['course_code'] ?? 'N/A') . ')'); ?></p>
        </div>
        
        <?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        
        <div class="card">
            <div class="card-title">Upload New Note</div>
            <form method="POST" enctype="multipart/form-data">
                <input type="text" name="title" class="form-input" placeholder="Note title..." required>
                <textarea name="description" class="form-textarea" placeholder="Description (optional)..."></textarea>
                <div class="file-input-wrapper">
                    <div class="file-input-label" id="fileLabel">Choose file (PDF, DOC, TXT, PNG, JPG - Max 10MB)</div>
                    <input type="file" name="note_file" id="note_file" accept=".pdf,.doc,.docx,.txt,.png,.jpg,.jpeg" required onchange="document.getElementById('fileLabel').textContent = this.files[0].name;">
                </div>
                <button type="submit" class="btn">Upload Note</button>
            </form>
        </div>
        
        <div class="card">
            <div class="card-title">All Notes (<?php echo count($notes); ?>)</div>
            <?php if (count($notes) > 0): ?>
                <table class="note-table">
                    <thead><tr><th>Title</th><th>Uploaded By</th><th>Date</th><th>Downloads</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php foreach ($notes as $note): 
                            $isOwner = ($note['uploaded_by'] == $_SESSION['user_id']);
                        ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($note['title']); ?></strong></td>
                                <td><?php echo htmlspecialchars($note['uploader_name']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($note['created_at'])); ?></td>
                                <td><span class="badge"><?php echo $note['downloads']; ?></span></td>
                                <td>
                                    <div class="action-group">
                                        <a href="download.php?id=<?php echo $note['id']; ?>" class="btn btn-sm"><i class="fas fa-download"></i></a>
                                        <?php if ($isOwner): ?>
                                            <button class="btn btn-sm btn-outline" onclick="openEditModal(<?php echo $note['id']; ?>, '<?php echo addslashes($note['title']); ?>', '<?php echo addslashes($note['description']); ?>')"><i class="fas fa-edit"></i></button>
                                            <a href="notes.php?delete=<?php echo $note['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this note?')"><i class="fas fa-trash"></i></a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="color:#6b7280;text-align:center;padding:1.5rem;">No notes uploaded yet. Be the first to share!</p>
            <?php endif; ?>
        </div>
    </main>
    
    <!-- Edit Note Modal -->
    <div class="modal-overlay" id="editModal">
        <div class="modal">
            <button class="modal-close" onclick="closeEditModal()">&times;</button>
            <h2 class="modal-title">Edit Note</h2>
            <form method="POST">
                <input type="hidden" name="note_id" id="edit_note_id">
                <label style="font-size:0.85rem;font-weight:500;color:#374151;">Title</label>
                <input type="text" name="edit_title" id="edit_title" class="form-input" required>
                <label style="font-size:0.85rem;font-weight:500;color:#374151;">Description</label>
                <textarea name="edit_description" id="edit_description" class="form-textarea"></textarea>
                <button type="submit" name="edit_note" class="btn" style="width:100%;">Save Changes</button>
            </form>
        </div>
    </div>
    
    <script>
        function toggleSidebar() { document.getElementById('sidebar').classList.toggle('open'); document.getElementById('overlay').classList.toggle('active'); }
        function openEditModal(id, title, desc) {
            document.getElementById('edit_note_id').value = id;
            document.getElementById('edit_title').value = title;
            document.getElementById('edit_description').value = desc;
            document.getElementById('editModal').classList.add('active');
        }
        function closeEditModal() { document.getElementById('editModal').classList.remove('active'); }
        document.getElementById('editModal').addEventListener('click', function(e) { if (e.target === this) closeEditModal(); });
    </script>
</body>
</html>
<script>
function toggleTheme() {
    document.body.classList.toggle("dark");
    var icon = document.querySelector("#themeBtn i");
    if (document.body.classList.contains("dark")) {
        if(icon) icon.className = "fas fa-sun";
        localStorage.setItem("theme", "dark");
    } else {
        if(icon) icon.className = "fas fa-moon";
        localStorage.setItem("theme", "light");
    }
}
if (localStorage.getItem("theme") === "dark") { document.body.classList.add("dark"); }
</script>
