# Development Plan: EduCode Platform

## Phase 1: Setup & Main Architecture
- [x] Buat struktur folder proyek (contoh: `/assets`, `/includes`, `/views`, `/workspace`, `/db`).
- [x] Buat koneksi database utama (`main_db.sqlite`) menggunakan PHP PDO.
- [x] Buat tabel `users` (id, username, password_hash, role: 'admin'/'student', created_at).
- [x] Buat template dasar HTML dengan layout CSS modern dan responsif.

## Phase 2: Authentication System
- [x] Buat halaman Login (`login.php`) dengan validasi input.
- [x] Buat fitur autentikasi, inisialisasi Session, dan routing berdasarkan role.
- [x] Buat script untuk Admin mendaftarkan siswa baru (generate username & password).
- [x] Buat sistem perlindungan halaman (hanya user login yang bisa akses dashboard).

## Phase 3: Dashboard & File Management (Backend)
- [x] Buat UI Dashboard untuk Siswa dan Guru.
- [x] Guru: Bisa melihat daftar siswa dan masuk ke workspace mereka secara read-only.
- [x] Siswa: Logika untuk membuat folder workspace otomatis (misal: `/workspace/student_id/`) saat login pertama kali.
- [x] Buat API endpoint (PHP) untuk operasi CRUD file/folder siswa:
  - Create file/folder (dengan filter nama file yang aman).
  - Read daftar file (direktori tree).
  - Update isi file.
  - Delete file/folder.

## Phase 4: Code Editor & UI Integration (Frontend)
- [x] Integrasikan CodeMirror atau Monaco Editor ke halaman workspace siswa.
- [x] Buat UI Sidebar untuk menampilkan struktur file/folder (File Tree).
- [x] Hubungkan File Tree dengan Editor: klik file di sidebar akan membuka isi file di Editor menggunakan fetch/AJAX ke backend API (Phase 3).
- [x] Buat tombol "Save" (atau auto-save via JS) untuk mengirim perubahan kode kembali ke server.

## Phase 5: Real-time Web Preview & SQLite Integration
- [x] Buat panel `<iframe>` di sebelah editor untuk Live Preview.
- [x] Tambahkan JS listener: saat kode HTML/CSS/JS di editor berubah dan disimpan, refresh `<iframe>` yang mengarah ke file HTML utama siswa.
- [x] Implementasikan fitur SQLite untuk siswa:
  - Buatkan file `student_db.sqlite` terisolasi di dalam workspace tiap siswa.
  - Sediakan UI/Editor khusus bagi siswa untuk menulis query SQL.
  - Buat endpoint PHP khusus untuk mengeksekusi query SQL ke `student_db.sqlite` siswa tersebut dan mengembalikan hasilnya dalam bentuk tabel JSON/HTML.

## Phase 6: Refinement & Security Hardening
- [x] Percantik UI/UX dengan CSS (animasi transisi, hover effect, dark/light mode toggle).
- [x] Pastikan responsivitas di layar kecil (sembunyikan sidebar, susun editor dan preview secara vertikal).
- [x] Lakukan pengecekan Path Traversal: pastikan tidak ada cara untuk mengakses file sistem di luar folder `/workspace/student_id/`.
- [x] Tambahkan log aktivitas sederhana untuk Guru (melihat kapan terakhir siswa aktif).

Status: Semua fase telah diselesaikan dan diverifikasi di lingkungan lokal pada 2026-09-29.


Baca file skill.md untuk memahami aturan main dan standar keamanan. Kemudian, jalankan tugas pada plan.md mulai dari Phase 1 secara berurutan. Jangan lanjut ke fase berikutnya sebelum saya menyetujui hasil kerjamu.