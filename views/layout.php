<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="description" content="Platform pembelajaran coding untuk siswa SMP dan SMA.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="page-shell">
        <header class="topbar">
            <div class="brand-wrap">
                <div class="brand-mark">E</div>
                <div>
                    <p class="eyebrow">Learning Platform</p>
                    <h1><?= htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8'); ?></h1>
                </div>
            </div>

            <button class="nav-toggle" type="button" aria-label="Toggle menu">
                <span></span>
                <span></span>
                <span></span>
            </button>

            <nav class="topnav" aria-label="Main Navigation">
                <a href="#overview">Overview</a>
                <a href="#features">Fitur</a>
                <a href="#architecture">Arsitektur</a>
                <a href="#status">Status</a>
                <a href="login.php">Masuk</a>
                <button type="button" class="theme-toggle">Light mode</button>
            </nav>
        </header>

        <main>
            <section class="hero" id="overview">
                <div class="hero-copy">
                    <span class="badge">Phase 1 · Setup & Architecture</span>
                    <h2>Platform coding education yang siap untuk siswa SMP & SMA</h2>
                    <p>
                        EduCode mencakup sistem autentikasi, workspace siswa, database sekolah, dan antarmuka modern yang responsif.
                        Backend menggunakan PHP dengan PDO SQLite dan frontend menggunakan JavaScript serta CSS modern.
                    </p>
                    <div class="cta-row">
                        <a class="button primary" href="login.php">Masuk ke aplikasi</a>
                        <a class="button secondary" href="#features">Jelajahi fitur</a>
                    </div>
                    <div class="cta-row" style="margin-top: 12px; flex-wrap: wrap;">
                        <a class="button small" href="login.php?role=admin&username=admin&password=admin123">Demo Admin</a>
                        <a class="button small secondary" href="login.php?role=teacher&username=teacher&password=teacher123">Demo Guru</a>
                        <a class="button small secondary" href="login.php?role=student&username=siswa01&password=siswa123">Demo Siswa</a>
                    </div>
                </div>

                <div class="hero-panel">
                    <div class="window-bar">
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>
                    <div class="code-preview">
                        <div class="line"><span class="token keyword">const</span> <span class="token var">student</span> = <span class="token string">'login'</span>;</div>
                        <div class="line"><span class="token keyword">const</span> <span class="token var">workspace</span> = <span class="token string">'/workspace/student_id/'</span>;</div>
                        <div class="line"><span class="token keyword">const</span> <span class="token var">db</span> = <span class="token string">'student_db.sqlite'</span>;</div>
                        <div class="line"><span class="token keyword">const</span> <span class="token var">preview</span> = <span class="token string">'live'</span>;</div>
                    </div>
                </div>
            </section>

            <section class="stats-grid" id="status">
                <article class="stat-card">
                    <span class="label">Database utama</span>
                    <strong><?= htmlspecialchars(MAIN_DB_PATH, ENT_QUOTES, 'UTF-8'); ?></strong>
                    <small>PDO SQLite siap dipakai</small>
                </article>
                <article class="stat-card">
                    <span class="label">Tabel user</span>
                    <strong>users</strong>
                    <small>Role: admin, teacher, student</small>
                </article>
                <article class="stat-card">
                    <span class="label">Workspace</span>
                    <strong>/workspace</strong>
                    <small>Direktori siswa terisolasi</small>
                </article>
                <article class="stat-card">
                    <span class="label">Koneksi</span>
                    <strong><?= isset($databaseStatus) ? 'OK' : 'Ready'; ?></strong>
                    <small>Pengaturan aman dan siap ditingkatkan</small>
                </article>
            </section>

            <section class="feature-section" id="features">
                <div class="section-heading">
                    <span class="badge secondary">Fitur utama</span>
                    <h3>Fondasi yang sudah dibangun</h3>
                </div>

                <div class="feature-grid">
                    <article class="feature-card">
                        <h4>Arsitektur proyek</h4>
                        <p>Struktur folder terorganisasi untuk assets, includes, view, workspace, dan database.</p>
                    </article>
                    <article class="feature-card">
                        <h4>Database utama</h4>
                        <p>Tabel users dibuat dengan SQLite, prepared statements, dan role yang memisahkan admin, guru, dan siswa.</p>
                    </article>
                    <article class="feature-card">
                        <h4>Layout responsif</h4>
                        <p>UI modern dengan grid, warna gelap, responsivitas mobile, dan panel editor-like untuk pengalaman coding.</p>
                    </article>
                    <article class="feature-card">
                        <h4>Keamanan dasar</h4>
                        <p>Folder workspace dipersiapkan untuk isolasi, serta pola pengembangan mengikuti prinsip keamanan dari Skill.md.</p>
                    </article>
                </div>
            </section>

            <section class="architecture" id="architecture">
                <div class="section-heading">
                    <span class="badge secondary">Class diagram</span>
                    <h3>Arsitektur platform</h3>
                </div>

                <div class="architecture-box">
                    <ul>
                        <li>Frontend: HTML + CSS + JavaScript</li>
                        <li>Backend: PHP 8 + PDO</li>
                        <li>Database utama: SQLite</li>
                        <li>Workspace siswa: /workspace/student_id/</li>
                        <li>Preview: iframe sandbox</li>
                    </ul>
                </div>
            </section>
        </main>
    </div>

    <script src="assets/js/app.js"></script>
</body>
</html>
