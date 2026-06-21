-- =====================================================
-- DATABASE SCHEMA — RYTHME
-- Import file ini lewat phpMyAdmin di hosting kamu
-- =====================================================

CREATE DATABASE IF NOT EXISTS rythme_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE rythme_db;

-- ---------------------------------------------------
-- Tabel: admins
-- Menyimpan akun admin yang boleh login ke panel admin
-- ---------------------------------------------------
CREATE TABLE admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ---------------------------------------------------
-- Tabel: modules
-- Satu baris = satu modul (mis. "Modul 01 - Pengantar")
-- ---------------------------------------------------
CREATE TABLE modules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    module_number INT NOT NULL,                 -- urutan modul (01, 02, dst)
    title VARCHAR(255) NOT NULL,                 -- judul modul
    description TEXT,                            -- deskripsi singkat modul
    level ENUM('pemula', 'menengah', 'lanjutan') NOT NULL DEFAULT 'pemula',
    youtube_url VARCHAR(255),                     -- link video YouTube utama modul
    youtube_video_id VARCHAR(50),                 -- ID video saja, diekstrak otomatis dari URL
    duration_minutes INT DEFAULT 0,               -- estimasi durasi belajar (menit)
    is_published TINYINT(1) DEFAULT 1,            -- 1 = tampil di website, 0 = draft/disembunyikan
    show_on_homepage TINYINT(1) DEFAULT 0,        -- 1 = tampil juga di index.html
    sort_order INT DEFAULT 0,                     -- urutan tampil custom (opsional)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ---------------------------------------------------
-- Tabel: module_sections
-- Konten materi modul yang terstruktur: judul, sub judul, isi
-- Satu modul bisa punya banyak section (1.1, 1.2, 1.3, dst)
-- ---------------------------------------------------
CREATE TABLE module_sections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    module_id INT NOT NULL,
    section_order INT NOT NULL DEFAULT 0,         -- urutan tampil (1, 2, 3...)
    section_title VARCHAR(255) NOT NULL,          -- contoh: "Apa Itu Senam Irama?"
    section_tag VARCHAR(100),                     -- contoh: "Konsep Dasar" / "Praktis"
    section_body TEXT NOT NULL,                   -- isi paragraf (boleh berisi HTML sederhana)
    highlight_text TEXT,                          -- opsional: kotak highlight/quote box
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE
);

-- ---------------------------------------------------
-- Tabel: quiz_questions
-- Soal kuis untuk satu modul
-- ---------------------------------------------------
CREATE TABLE quiz_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    module_id INT NOT NULL,
    question_order INT NOT NULL DEFAULT 0,
    question_text TEXT NOT NULL,
    option_a VARCHAR(255) NOT NULL,
    option_b VARCHAR(255) NOT NULL,
    option_c VARCHAR(255) NOT NULL,
    option_d VARCHAR(255) NOT NULL,
    correct_option ENUM('A', 'B', 'C', 'D') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE
);

-- ---------------------------------------------------
-- Tabel: quiz_results
-- Hasil kuis tiap user — nama, modul, skor, waktu pengerjaan
-- ---------------------------------------------------
CREATE TABLE quiz_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    module_id INT NOT NULL,
    user_name VARCHAR(100) NOT NULL,              -- nama yang diinput user sebelum kuis
    score INT NOT NULL,                            -- jumlah jawaban benar
    total_questions INT NOT NULL,                  -- total soal saat itu
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,  -- waktu pengerjaan
    FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE
);

-- ---------------------------------------------------
-- DATA AWAL (opsional) — supaya langsung ada admin & contoh modul
-- Password default: admin123  (akan kita generate hash-nya di langkah PHP)
-- ---------------------------------------------------
-- INSERT INTO admins (username, password_hash) VALUES ('admin', '$2y$10$PLACEHOLDER');

-- Contoh 1 modul beserta section dan soal (boleh dihapus nanti dari admin panel)
INSERT INTO modules (module_number, title, description, level, youtube_url, youtube_video_id, duration_minutes, is_published, show_on_homepage)
VALUES (1, 'Pengantar & Konsep Dasar Senam Irama', 'Fondasi utama sebelum masuk ke materi lebih dalam.', 'pemula', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'dQw4w9WgXcQ', 12, 1, 1);

INSERT INTO module_sections (module_id, section_order, section_title, section_tag, section_body, highlight_text) VALUES
(1, 1, 'Apa Itu Senam Irama?', 'Konsep Dasar', 'Senam irama adalah cabang olahraga yang memadukan gerakan senam dengan seni pertunjukan, musik, dan tari.', 'Senam irama pertama kali dipertandingkan di Olimpiade pada tahun 1984 di Los Angeles.'),
(1, 2, 'Manfaat Senam Irama', 'Praktis', 'Latihan rutin memberikan manfaat fisik dan mental: kelenturan, koordinasi, musikalitas, dan kepercayaan diri.', NULL);

INSERT INTO quiz_questions (module_id, question_order, question_text, option_a, option_b, option_c, option_d, correct_option) VALUES
(1, 1, 'Senam irama pertama kali dipertandingkan di Olimpiade pada tahun?', '1980 di Moskow', '1984 di Los Angeles', '1988 di Seoul', '1992 di Barcelona', 'B');