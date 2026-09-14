<?php
// admin_videos.php
require_once 'includes/admin_header.php';

// Handle Video Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['video_file'])) {
    $title = trim(filter_input(INPUT_POST, 'title', FILTER_SANITIZE_STRING));
    $description = trim(filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING));
    $file = $_FILES['video_file'];

    if (empty($title)) {
        $error = "Video title is required.";
    } elseif ($file['error'] !== UPLOAD_ERR_OK) {
        $error = "File upload error.";
    } else {
        $allowed_types = ['video/mp4', 'video/webm', 'video/ogg'];
        $max_size = 50 * 1024 * 1024; // 50MB

        if (!in_array($file['type'], $allowed_types)) {
            $error = "Invalid file type. Only MP4, WebM, and OGG are allowed.";
        } elseif ($file['size'] > $max_size) {
            $error = "File size exceeds 50MB limit.";
        } else {
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = uniqid() . '.' . $ext;
            $upload_dir = '../uploads/videos/';
            
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $filepath = $upload_dir . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                $db_path = 'uploads/videos/' . $filename;
                try {
                    $stmt = $pdo->prepare("INSERT INTO videos (title, description, video_path) VALUES (:title, :desc, :path)");
                    $stmt->execute([
                        ':title' => $title,
                        ':desc' => $description,
                        ':path' => $db_path
                    ]);
                    $success = "Video uploaded successfully.";
                } catch (\PDOException $e) {
                    $error = "Database error: " . $e->getMessage();
                }
            } else {
                $error = "Failed to move uploaded file.";
            }
        }
    }
}

// Handle Delete Video
if (isset($_GET['delete'])) {
    $id = filter_input(INPUT_GET, 'delete', FILTER_VALIDATE_INT);
    if ($id) {
        try {
            $stmt = $pdo->prepare("SELECT video_path FROM videos WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $video = $stmt->fetch();
            if ($video) {
                $file_to_delete = '../' . $video['video_path'];
                if (file_exists($file_to_delete)) {
                    unlink($file_to_delete);
                }
                $pdo->prepare("DELETE FROM videos WHERE id = :id")->execute([':id' => $id]);
                $success = "Video deleted successfully.";
            }
        } catch (\PDOException $e) {
            $error = "Failed to delete video.";
        }
    }
}

// Fetch all videos
try {
    $videos = $pdo->query("SELECT * FROM videos ORDER BY created_at DESC")->fetchAll();
} catch (\PDOException $e) {
    $videos = [];
}

$videosTable = adminTableState($videos, ['title', 'description', 'video_path', 'created_at'], 'videos');
?>

<div class="mb-6 flex justify-between items-end">
    <div>
        <h2 class="text-2xl font-bold text-[#13324a]">Manage Gallery</h2>
        <p class="text-slate-500 text-sm mt-1">Upload and manage videos showcasing successful implant cases.</p>
    </div>
</div>

<?php if(isset($error)): ?>
    <div class="bg-red-50 text-red-600 p-4 rounded-xl mb-6 text-sm font-semibold flex items-center gap-2">
        <i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<?php if(isset($success)): ?>
    <div class="bg-emerald-50 text-emerald-600 p-4 rounded-xl mb-6 text-sm font-semibold flex items-center gap-2">
        <i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($success) ?>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    
    <!-- Upload Form -->
    <div class="lg:col-span-1">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
            <h3 class="text-lg font-bold text-[#13324a] mb-4 border-b border-slate-100 pb-3">Upload New Video</h3>
            <form action="" method="POST" enctype="multipart/form-data" class="space-y-4">
                <div>
                    <label class="block text-sm font-bold text-[#13324a] mb-2">Video Title</label>
                    <input type="text" name="title" required class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:ring-[#1d5f8c] focus:border-[#1d5f8c] bg-slate-50 focus:bg-white transition">
                </div>
                <div>
                    <label class="block text-sm font-bold text-[#13324a] mb-2">Description (Optional)</label>
                    <textarea name="description" rows="3" class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:ring-[#1d5f8c] focus:border-[#1d5f8c] bg-slate-50 focus:bg-white transition"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-bold text-[#13324a] mb-2">Video File</label>
                    <input type="file" name="video_file" accept="video/mp4,video/webm,video/ogg" required class="w-full text-sm text-slate-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-[#1d5f8c] file:text-white hover:file:bg-[#13324a] transition cursor-pointer">
                    <p class="text-xs text-slate-400 mt-2">Max size: 50MB. Formats: MP4, WebM, OGG.</p>
                </div>
                <button type="submit" class="w-full bg-[#13324a] hover:bg-[#1d5f8c] text-white px-4 py-2.5 rounded-xl text-sm font-bold transition flex justify-center items-center gap-2">
                    <i class="fa-solid fa-cloud-arrow-up"></i> Upload Video
                </button>
            </form>
        </div>
    </div>

    <!-- Video List -->
    <div class="lg:col-span-2">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="px-6 py-5 border-b border-slate-100 bg-slate-50">
                <h3 class="text-lg font-bold text-[#13324a]">Uploaded Videos</h3>
            </div>
            <?php adminTableToolbar('videos', $videosTable, 'Search videos...'); ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="uppercase tracking-wider border-b-2 border-slate-100 bg-white text-slate-500 font-semibold text-[11px]">
                        <tr>
                            <th class="px-6 py-4">Preview</th>
                            <th class="px-6 py-4">Video</th>
                            <th class="px-6 py-4">Uploaded</th>
                            <th class="px-6 py-4 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700" data-admin-table-body="videos">
                        <?php if (!$videosTable['rows']): ?>
                            <tr><td colspan="4" class="px-6 py-12 text-center text-slate-500">No videos match your search.</td></tr>
                        <?php endif; ?>
                        <?php foreach($videosTable['rows'] as $video): ?>
                            <tr class="hover:bg-slate-50/60 transition">
                                <td class="px-6 py-4">
                                    <video src="../<?= htmlspecialchars($video['video_path']) ?>" controls preload="metadata" class="h-20 w-32 rounded-lg bg-black object-cover"></video>
                                </td>
                                <td class="px-6 py-4 min-w-64">
                                    <p class="font-bold text-[#13324a]"><?= htmlspecialchars($video['title']) ?></p>
                                    <p class="mt-1 max-w-md whitespace-normal text-xs leading-5 text-slate-500"><?= htmlspecialchars($video['description'] ?: 'No description') ?></p>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-slate-500"><?= date('M d, Y', strtotime($video['created_at'])) ?></td>
                                <td class="px-6 py-4 text-right">
                                    <a href="?delete=<?= (int) $video['id'] ?>" onclick="return confirm('Are you sure you want to delete this video?');" class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-red-50 text-red-500 transition hover:bg-red-500 hover:text-white" title="Delete Video">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php adminTablePagination('videos', $videosTable); ?>
        </div>
    </div>

</div>

<?php require_once 'includes/admin_footer.php'; ?>
