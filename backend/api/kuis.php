<?php
/**
 * kuis.php
 * API untuk submit jawaban kuis dan melihat hasil (admin)
 *
 * PUBLIK:
 *   POST kuis.php
 *   Body: {
 *     "module_id": 1,
 *     "user_name": "Nama User",
 *     "answers": { "1": "B", "2": "C", ... }   <- key = id soal, value = pilihan user
 *   }
 *   -> server otomatis menghitung skor yang benar (bukan dari klien, supaya tidak bisa dicurangi)
 *
 * KHUSUS ADMIN:
 *   GET kuis.php?module_id=1        -> semua hasil kuis untuk 1 modul (nama, skor, waktu)
 *   GET kuis.php?all=1              -> semua hasil kuis dari semua modul
 */

require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];

// ============================================================
// POST — submit jawaban kuis (publik, dipanggil dari detail-modul.html)
// ============================================================
if ($method === 'POST') {

    $body = json_decode(file_get_contents('php://input'), true);

    $moduleId = (int) ($body['module_id'] ?? 0);
    $userName = trim($body['user_name'] ?? '');
    $answers  = $body['answers'] ?? [];

    if (!$moduleId || empty($userName) || empty($answers)) {
        jsonResponse(['error' => 'module_id, user_name, dan answers wajib diisi'], 400);
    }

    // Ambil kunci jawaban yang benar dari database (bukan dari request user)
    $stmt = $pdo->prepare("SELECT id, correct_option FROM quiz_questions WHERE module_id = ?");
    $stmt->execute([$moduleId]);
    $questions = $stmt->fetchAll();

    if (empty($questions)) {
        jsonResponse(['error' => 'Modul ini tidak memiliki soal kuis'], 404);
    }

    // Hitung skor di server
    $score = 0;
    $detail = [];
    foreach ($questions as $q) {
        $userAnswer = $answers[$q['id']] ?? null;
        $isCorrect = ($userAnswer === $q['correct_option']);
        if ($isCorrect) $score++;

        $detail[] = [
            'question_id'    => (int) $q['id'],
            'correct_option' => $q['correct_option'],
            'user_answer'    => $userAnswer,
            'is_correct'     => $isCorrect,
        ];
    }

    $total = count($questions);

    // Simpan hasil ke database
    $stmt = $pdo->prepare("
        INSERT INTO quiz_results (module_id, user_name, score, total_questions)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$moduleId, $userName, $score, $total]);

    jsonResponse([
        'success'         => true,
        'score'           => $score,
        'total_questions' => $total,
        'detail'          => $detail,
    ], 201);
}

// ============================================================
// GET — lihat hasil kuis (khusus admin)
// ============================================================
if ($method === 'GET') {
    require_once 'auth_check.php'; // hanya admin yang boleh lihat data nama + nilai user

    // --- Semua hasil dari semua modul ---
    if (isset($_GET['all'])) {
        $stmt = $pdo->query("
            SELECT qr.*, m.title AS module_title, m.module_number
            FROM quiz_results qr
            JOIN modules m ON m.id = qr.module_id
            ORDER BY qr.submitted_at DESC
        ");
        jsonResponse($stmt->fetchAll());
    }

    // --- Hasil untuk satu modul tertentu ---
    if (isset($_GET['module_id'])) {
        $moduleId = (int) $_GET['module_id'];

        $stmt = $pdo->prepare("
            SELECT qr.*, m.title AS module_title
            FROM quiz_results qr
            JOIN modules m ON m.id = qr.module_id
            WHERE qr.module_id = ?
            ORDER BY qr.submitted_at DESC
        ");
        $stmt->execute([$moduleId]);
        jsonResponse($stmt->fetchAll());
    }

    jsonResponse(['error' => 'Sertakan parameter ?all=1 atau ?module_id=X'], 400);
}

jsonResponse(['error' => 'Method tidak didukung'], 405);