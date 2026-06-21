<?php
/**
 * modules.php
 * API utama untuk modul. Method dan parameter:
 *
 * PUBLIK (tidak perlu login):
 *   GET  modules.php                    -> daftar semua modul yang published
 *   GET  modules.php?homepage=1         -> hanya modul yang show_on_homepage=1
 *   GET  modules.php?id=5               -> detail 1 modul lengkap (section + soal kuis)
 *
 * KHUSUS ADMIN (butuh header Authorization: Bearer <token>):
 *   GET  modules.php?admin=1            -> daftar SEMUA modul (termasuk draft) untuk admin panel
 *   GET  modules.php?id=5&admin=1       -> detail 1 modul (termasuk draft + correct_option soal) untuk form edit
 *   POST modules.php                    -> tambah modul baru
 *   PUT  modules.php?id=5               -> edit modul
 *   DELETE modules.php?id=5             -> hapus modul
 */

require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];

// ============================================================
// GET — lihat data (sebagian publik, sebagian butuh login)
// ============================================================
if ($method === 'GET') {

    // --- Detail satu modul lengkap UNTUK ADMIN (termasuk draft + correct_option soal) ---
    if (isset($_GET['id']) && isset($_GET['admin'])) {
        require_once 'auth_check.php';

        $id = (int) $_GET['id'];

        $stmt = $pdo->prepare("SELECT * FROM modules WHERE id = ?");
        $stmt->execute([$id]);
        $module = $stmt->fetch();

        if (!$module) {
            jsonResponse(['error' => 'Modul tidak ditemukan'], 404);
        }

        $stmt = $pdo->prepare("SELECT * FROM module_sections WHERE module_id = ? ORDER BY section_order ASC");
        $stmt->execute([$id]);
        $module['sections'] = $stmt->fetchAll();

        // Untuk admin, SERTAKAN correct_option supaya bisa di-edit
        $stmt = $pdo->prepare("SELECT * FROM quiz_questions WHERE module_id = ? ORDER BY question_order ASC");
        $stmt->execute([$id]);
        $module['quiz_questions'] = $stmt->fetchAll();

        $module['thumbnail_url'] = $module['youtube_video_id']
            ? "https://img.youtube.com/vi/{$module['youtube_video_id']}/hqdefault.jpg"
            : null;

        jsonResponse($module);
    }

    // --- Detail satu modul lengkap (publik, dipakai detail-modul.html) ---
    if (isset($_GET['id'])) {
        $id = (int) $_GET['id'];

        $stmt = $pdo->prepare("SELECT * FROM modules WHERE id = ? AND is_published = 1");
        $stmt->execute([$id]);
        $module = $stmt->fetch();

        if (!$module) {
            jsonResponse(['error' => 'Modul tidak ditemukan'], 404);
        }

        // Ambil semua section materi, urut sesuai section_order
        $stmt = $pdo->prepare("SELECT * FROM module_sections WHERE module_id = ? ORDER BY section_order ASC");
        $stmt->execute([$id]);
        $module['sections'] = $stmt->fetchAll();

        // Ambil semua soal kuis, urut sesuai question_order
        // (kita TIDAK kirim correct_option ke publik supaya tidak bisa dicontek dari Network tab,
        //  pengecekan jawaban dilakukan di server lewat endpoint kuis.php)
        $stmt = $pdo->prepare("SELECT id, question_order, question_text, option_a, option_b, option_c, option_d FROM quiz_questions WHERE module_id = ? ORDER BY question_order ASC");
        $stmt->execute([$id]);
        $module['quiz_questions'] = $stmt->fetchAll();

        $module['thumbnail_url'] = $module['youtube_video_id']
            ? "https://img.youtube.com/vi/{$module['youtube_video_id']}/hqdefault.jpg"
            : null;

        jsonResponse($module);
    }

    // --- Daftar modul untuk ADMIN PANEL (lihat semua, termasuk draft) ---
    if (isset($_GET['admin'])) {
        require_once 'auth_check.php'; // wajib login

        $stmt = $pdo->query("SELECT * FROM modules ORDER BY module_number ASC");
        $modules = $stmt->fetchAll();

        foreach ($modules as &$m) {
            $m['thumbnail_url'] = $m['youtube_video_id']
                ? "https://img.youtube.com/vi/{$m['youtube_video_id']}/hqdefault.jpg"
                : null;
        }

        jsonResponse($modules);
    }

    // --- Daftar modul untuk HOMEPAGE (publik, hanya yang show_on_homepage=1) ---
    if (isset($_GET['homepage'])) {
        $stmt = $pdo->query("SELECT * FROM modules WHERE is_published = 1 AND show_on_homepage = 1 ORDER BY sort_order ASC, module_number ASC LIMIT 3");
        $modules = $stmt->fetchAll();

        foreach ($modules as &$m) {
            $m['thumbnail_url'] = $m['youtube_video_id']
                ? "https://img.youtube.com/vi/{$m['youtube_video_id']}/hqdefault.jpg"
                : null;
        }

        jsonResponse($modules);
    }

    // --- Daftar SEMUA modul publik (dipakai modul.html) ---
    $stmt = $pdo->query("SELECT * FROM modules WHERE is_published = 1 ORDER BY sort_order ASC, module_number ASC");
    $modules = $stmt->fetchAll();

    foreach ($modules as &$m) {
        $m['thumbnail_url'] = $m['youtube_video_id']
            ? "https://img.youtube.com/vi/{$m['youtube_video_id']}/hqdefault.jpg"
            : null;
    }

    jsonResponse($modules);
}

// ============================================================
// POST — tambah modul baru (khusus admin)
// ============================================================
if ($method === 'POST') {
    require_once 'auth_check.php';

    $body = json_decode(file_get_contents('php://input'), true);

    $required = ['module_number', 'title', 'level'];
    foreach ($required as $field) {
        if (empty($body[$field]) && $body[$field] !== 0) {
            jsonResponse(['error' => "Field '$field' wajib diisi"], 400);
        }
    }

    $youtubeId = extractYoutubeId($body['youtube_url'] ?? '');

    $stmt = $pdo->prepare("
        INSERT INTO modules
        (module_number, title, description, level, youtube_url, youtube_video_id, duration_minutes, is_published, show_on_homepage, sort_order)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $body['module_number'],
        $body['title'],
        $body['description'] ?? '',
        $body['level'],
        $body['youtube_url'] ?? null,
        $youtubeId,
        $body['duration_minutes'] ?? 0,
        $body['is_published'] ?? 1,
        $body['show_on_homepage'] ?? 0,
        $body['sort_order'] ?? 0,
    ]);

    $newId = $pdo->lastInsertId();

    // Simpan section materi (jika dikirim sekaligus)
    if (!empty($body['sections']) && is_array($body['sections'])) {
        $stmtSection = $pdo->prepare("
            INSERT INTO module_sections (module_id, section_order, section_title, section_tag, section_body, highlight_text)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        foreach ($body['sections'] as $i => $section) {
            $stmtSection->execute([
                $newId,
                $i + 1,
                $section['title'] ?? '',
                $section['tag'] ?? '',
                $section['body'] ?? '',
                $section['highlight'] ?? null,
            ]);
        }
    }

    // Simpan soal kuis (jika dikirim sekaligus)
    if (!empty($body['quiz_questions']) && is_array($body['quiz_questions'])) {
        $stmtQuiz = $pdo->prepare("
            INSERT INTO quiz_questions (module_id, question_order, question_text, option_a, option_b, option_c, option_d, correct_option)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        foreach ($body['quiz_questions'] as $i => $q) {
            $stmtQuiz->execute([
                $newId,
                $i + 1,
                $q['question_text'] ?? '',
                $q['option_a'] ?? '',
                $q['option_b'] ?? '',
                $q['option_c'] ?? '',
                $q['option_d'] ?? '',
                $q['correct_option'] ?? 'A',
            ]);
        }
    }

    jsonResponse(['success' => true, 'id' => $newId], 201);
}

// ============================================================
// PUT — edit modul (khusus admin)
// ============================================================
if ($method === 'PUT') {
    require_once 'auth_check.php';

    $id = (int) ($_GET['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'ID modul wajib diisi'], 400);

    $body = json_decode(file_get_contents('php://input'), true);
    $youtubeId = extractYoutubeId($body['youtube_url'] ?? '');

    $stmt = $pdo->prepare("
        UPDATE modules SET
            module_number = ?, title = ?, description = ?, level = ?,
            youtube_url = ?, youtube_video_id = ?, duration_minutes = ?,
            is_published = ?, show_on_homepage = ?, sort_order = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $body['module_number'],
        $body['title'],
        $body['description'] ?? '',
        $body['level'],
        $body['youtube_url'] ?? null,
        $youtubeId,
        $body['duration_minutes'] ?? 0,
        $body['is_published'] ?? 1,
        $body['show_on_homepage'] ?? 0,
        $body['sort_order'] ?? 0,
        $id,
    ]);

    // Ganti semua section lama dengan yang baru (lebih simpel daripada diff satu-satu)
    if (isset($body['sections']) && is_array($body['sections'])) {
        $pdo->prepare("DELETE FROM module_sections WHERE module_id = ?")->execute([$id]);

        $stmtSection = $pdo->prepare("
            INSERT INTO module_sections (module_id, section_order, section_title, section_tag, section_body, highlight_text)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        foreach ($body['sections'] as $i => $section) {
            $stmtSection->execute([
                $id,
                $i + 1,
                $section['title'] ?? '',
                $section['tag'] ?? '',
                $section['body'] ?? '',
                $section['highlight'] ?? null,
            ]);
        }
    }

    // Ganti semua soal kuis lama dengan yang baru
    if (isset($body['quiz_questions']) && is_array($body['quiz_questions'])) {
        $pdo->prepare("DELETE FROM quiz_questions WHERE module_id = ?")->execute([$id]);

        $stmtQuiz = $pdo->prepare("
            INSERT INTO quiz_questions (module_id, question_order, question_text, option_a, option_b, option_c, option_d, correct_option)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        foreach ($body['quiz_questions'] as $i => $q) {
            $stmtQuiz->execute([
                $id,
                $i + 1,
                $q['question_text'] ?? '',
                $q['option_a'] ?? '',
                $q['option_b'] ?? '',
                $q['option_c'] ?? '',
                $q['option_d'] ?? '',
                $q['correct_option'] ?? 'A',
            ]);
        }
    }

    jsonResponse(['success' => true]);
}

// ============================================================
// DELETE — hapus modul (khusus admin)
// ============================================================
if ($method === 'DELETE') {
    require_once 'auth_check.php';

    $id = (int) ($_GET['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'ID modul wajib diisi'], 400);

    // Section, soal kuis, dan hasil kuis otomatis ikut terhapus
    // karena ada ON DELETE CASCADE di schema.sql
    $stmt = $pdo->prepare("DELETE FROM modules WHERE id = ?");
    $stmt->execute([$id]);

    jsonResponse(['success' => true]);
}

jsonResponse(['error' => 'Method tidak didukung'], 405);