/**
 * admin.js
 * Konfigurasi dan helper yang dipakai di semua halaman admin
 */

// ===== GANTI INI SESUAI LOKASI BACKEND KAMU =====
// Saat di Laragon (lokal):
const API_BASE = 'http://localhost/rythme/backend/api/';
// Saat sudah di hosting, ganti jadi misalnya:
// const API_BASE = 'https://namadomainmu.com/backend/api/';
// ===================================================

/**
 * Wrapper fetch yang otomatis menyertakan token admin di header
 */
async function apiFetch(endpoint, options = {}) {
  const token = localStorage.getItem('admin_token');

  const headers = {
    'Content-Type': 'application/json',
    ...(options.headers || {}),
  };

  if (token) {
    headers['Authorization'] = 'Bearer ' + token;
  }

  const res = await fetch(API_BASE + endpoint, {
    ...options,
    headers,
    credentials: 'include',
  });

  // Kalau token invalid/expired, otomatis lempar ke halaman login
  if (res.status === 401) {
    localStorage.removeItem('admin_token');
    localStorage.removeItem('admin_username');
    window.location.href = 'login.html';
    return null;
  }

  return res;
}

/**
 * Cek apakah admin sudah login. Panggil di awal halaman dashboard/form.
 * Kalau belum login, otomatis redirect ke login.html
 */
function requireLogin() {
  const token = localStorage.getItem('admin_token');
  if (!token) {
    window.location.href = 'login.html';
  }
}

/**
 * Logout: hapus token dan kembali ke halaman login
 */
async function adminLogout() {
  try {
    await apiFetch('auth.php?action=logout', { method: 'POST' });
  } catch (e) {
    // tetap lanjut hapus token walau request gagal
  }
  localStorage.removeItem('admin_token');
  localStorage.removeItem('admin_username');
  window.location.href = 'login.html';
}

/**
 * Ekstrak video ID YouTube dari berbagai format link (dipakai untuk preview thumbnail di form)
 */
function extractYoutubeId(url) {
  if (!url) return null;
  const patterns = [
    /youtube\.com\/watch\?v=([a-zA-Z0-9_-]{11})/,
    /youtu\.be\/([a-zA-Z0-9_-]{11})/,
    /youtube\.com\/embed\/([a-zA-Z0-9_-]{11})/,
  ];
  for (const pattern of patterns) {
    const match = url.match(pattern);
    if (match) return match[1];
  }
  return null;
}

/**
 * Format tanggal dari database (format MySQL) ke format yang mudah dibaca
 */
function formatTanggal(mysqlDateTime) {
  const d = new Date(mysqlDateTime.replace(' ', 'T'));
  return d.toLocaleString('id-ID', {
    day: '2-digit', month: 'short', year: 'numeric',
    hour: '2-digit', minute: '2-digit'
  });
}