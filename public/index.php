<?php
/**
 * public/index.php — Landing Page dengan data realtime
 * Tim: Frontend + Backend
 */
require_once __DIR__ . '/../core/bootstrap.php';

if (isLoggedIn()) {
    redirect('/' . currentRole() . '/dashboard.php');
}

// ── Realtime stats ────────────────────────────
$pdo = getDB();
$stats = [
    'alumni'    => (int)$pdo->query("SELECT COUNT(*) FROM alumni_profiles")->fetchColumn(),
    'companies' => (int)$pdo->query("SELECT COUNT(*) FROM companies WHERE verified=1")->fetchColumn(),
    'vacancies' => (int)$pdo->query("SELECT COUNT(*) FROM job_vacancies WHERE status='approved' AND deleted_at IS NULL")->fetchColumn(),
    'placed'    => (int)$pdo->query("SELECT COUNT(*) FROM alumni_profiles WHERE work_status='employed'")->fetchColumn(),
];
$placement_pct = $stats['alumni'] > 0 ? round($stats['placed'] / $stats['alumni'] * 100) : 0;

// Lowongan terbaru (6 cards)
$latestVacancies = $pdo->query(
    "SELECT jv.*, c.company_name, c.logo_path, c.city as company_city,
            (SELECT COUNT(*) FROM applications WHERE vacancy_id=jv.id) as app_count
     FROM job_vacancies jv
     JOIN companies c ON c.id=jv.company_id
     WHERE jv.status='approved' AND jv.deleted_at IS NULL
       AND (jv.deadline IS NULL OR jv.deadline >= CURDATE())
     ORDER BY jv.approved_at DESC LIMIT 6"
)->fetchAll();

// Perusahaan partner (logo)
$partners = $pdo->query(
    "SELECT company_name, logo_path FROM companies WHERE verified=1 AND logo_path IS NOT NULL LIMIT 12"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>LORDIK — Loker Cerdik | Platform BKK Digital SMK</title>
<meta name="description" content="LORDIK menghubungkan alumni SMK dengan perusahaan terpercaya. Platform Bursa Kerja Khusus digital modern.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<style>
:root {
  --navy:#0f172a; --blue:#2563eb; --blue2:#3b82f6;
  --cyan:#06b6d4; --gold:#f59e0b; --green:#10b981;
  --light:#f0f6ff; --white:#fff;
  --font:'Plus Jakarta Sans',sans-serif;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{font-family:var(--font);background:var(--navy);color:var(--white);overflow-x:hidden}

/* ── NAVBAR ─────────────────────────────── */
.lp-nav{position:fixed;top:0;left:0;right:0;z-index:100;padding:.875rem 2rem;
  display:flex;align-items:center;justify-content:space-between;
  background:rgba(15,23,42,.85);backdrop-filter:blur(12px);
  border-bottom:1px solid rgba(255,255,255,.07)}
.lp-brand{display:flex;align-items:center;gap:.75rem;text-decoration:none}
.lp-brand-icon{width:38px;height:38px;background:linear-gradient(135deg,var(--blue),var(--cyan));
  border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.1rem}
.lp-brand-name{font-weight:800;font-size:1.2rem;color:var(--white)}
.lp-brand-tag{font-size:.65rem;color:#64748b;font-weight:500;letter-spacing:.05em;text-transform:uppercase}
.lp-nav-links{display:flex;gap:.25rem;list-style:none}
.lp-nav-links a{padding:.45rem .9rem;border-radius:8px;color:#94a3b8;text-decoration:none;font-size:.875rem;font-weight:500;transition:.2s}
.lp-nav-links a:hover{color:var(--white);background:rgba(255,255,255,.07)}
.btn-lp-login{padding:.5rem 1.25rem;border-radius:9px;border:1.5px solid #334155;color:#cbd5e1;font-size:.875rem;font-weight:600;text-decoration:none;transition:.2s}
.btn-lp-login:hover{border-color:var(--blue2);color:var(--white)}
.btn-lp-cta{padding:.5rem 1.25rem;border-radius:9px;background:var(--blue);color:var(--white);font-size:.875rem;font-weight:600;text-decoration:none;transition:.2s}
.btn-lp-cta:hover{background:#1d4ed8;color:var(--white)}
@media(max-width:640px){.lp-nav-links,.lp-brand-tag{display:none}}

/* ── HERO ────────────────────────────────── */
.hero{min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;
  text-align:center;padding:6rem 1.5rem 4rem;position:relative;overflow:hidden}
.hero-bg{position:absolute;inset:0;background:radial-gradient(ellipse 80% 60% at 50% 30%,rgba(37,99,235,.18) 0%,transparent 70%)}
.hero-grid{position:absolute;inset:0;background-image:linear-gradient(rgba(255,255,255,.025) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.025) 1px,transparent 1px);background-size:60px 60px;mask-image:radial-gradient(ellipse at center,black 20%,transparent 80%)}
.hero-badge{display:inline-flex;align-items:center;gap:.5rem;padding:.375rem 1rem;border:1px solid rgba(37,99,235,.4);border-radius:100px;font-size:.75rem;font-weight:600;color:var(--blue2);background:rgba(37,99,235,.08);margin-bottom:1.75rem;text-transform:uppercase;letter-spacing:.08em}
.hero-title{font-size:clamp(2.2rem,5.5vw,4rem);font-weight:800;line-height:1.2;margin-bottom:1.5rem;max-width:900px}
.hero-title .grad{background:linear-gradient(135deg,var(--blue2),var(--cyan));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;display:block}
.hero-sub{font-size:clamp(1rem,1.8vw,1.1rem);color:#94a3b8;max-width:600px;line-height:1.75;margin-bottom:2.5rem;margin-left:auto;margin-right:auto}
.hero-actions{display:flex;gap:.875rem;flex-wrap:wrap;justify-content:center;margin-bottom:3.5rem}
.btn-hero-primary{padding:.875rem 2rem;border-radius:12px;background:var(--blue);color:var(--white);font-weight:700;font-size:1rem;text-decoration:none;transition:.2s;display:inline-flex;align-items:center;gap:.5rem}
.btn-hero-primary:hover{background:#1d4ed8;color:var(--white);transform:translateY(-2px);box-shadow:0 8px 24px rgba(37,99,235,.4)}
.btn-hero-sec{padding:.875rem 2rem;border-radius:12px;border:1.5px solid #334155;color:#cbd5e1;font-weight:600;font-size:1rem;text-decoration:none;transition:.2s;display:inline-flex;align-items:center;gap:.5rem}
.btn-hero-sec:hover{border-color:#475569;color:var(--white)}

/* ── LIVE STATS ─────────────────────────── */
.stats-row{display:flex;gap:2rem;flex-wrap:wrap;justify-content:center;position:relative}
.stat-card{background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:16px;padding:1.25rem 1.75rem;min-width:140px;text-align:center;position:relative;overflow:hidden}
.stat-card::before{content:'';position:absolute;inset:0;background:linear-gradient(135deg,rgba(37,99,235,.06),transparent);pointer-events:none}
.stat-num{font-size:2rem;font-weight:800;line-height:1.1;margin-bottom:.3rem}
.stat-num.blue{color:var(--blue2)} .stat-num.cyan{color:var(--cyan)} .stat-num.green{color:var(--green)} .stat-num.gold{color:var(--gold)}
.stat-label{font-size:.75rem;color:#64748b;font-weight:500;text-transform:uppercase;letter-spacing:.06em}
.stat-live{position:absolute;top:.6rem;right:.6rem;width:7px;height:7px;background:var(--green);border-radius:50%;animation:pulse-dot 2s infinite}
@keyframes pulse-dot{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.5;transform:scale(1.4)}}

/* ── SECTIONS ────────────────────────────── */
section{padding:5rem 0}
.container{max-width:1200px;margin:0 auto;padding:0 1.5rem}
.section-label{font-size:.72rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--blue2);margin-bottom:.75rem}
.section-title{font-size:clamp(1.6rem,3vw,2.25rem);font-weight:800;margin-bottom:1rem;line-height:1.2}
.section-sub{color:#94a3b8;max-width:540px;line-height:1.7}

/* ── VACANCY CARDS ───────────────────────── */
.vacancy-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:1.25rem;margin-top:2.5rem}
.vcard{background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.07);border-radius:16px;padding:1.5rem;transition:.2s;text-decoration:none;display:block;color:var(--white)}
.vcard:hover{border-color:rgba(37,99,235,.4);background:rgba(37,99,235,.06);transform:translateY(-3px);color:var(--white)}
.vcard-header{display:flex;gap:.875rem;align-items:flex-start;margin-bottom:1rem}
.vcard-logo{width:44px;height:44px;min-width:44px;border-radius:10px;background:rgba(255,255,255,.08);display:flex;align-items:center;justify-content:center;overflow:hidden}
.vcard-logo img{width:100%;height:100%;object-fit:contain}
.vcard-title{font-size:.95rem;font-weight:700;line-height:1.3;margin-bottom:.2rem}
.vcard-company{font-size:.8rem;color:#64748b;font-weight:500}
.vcard-tags{display:flex;flex-wrap:wrap;gap:.375rem;margin-bottom:.875rem}
.vtag{font-size:.68rem;font-weight:600;padding:.25rem .625rem;border-radius:100px;border:1px solid rgba(255,255,255,.1);color:#94a3b8}
.vtag.type{border-color:rgba(37,99,235,.3);color:var(--blue2);background:rgba(37,99,235,.08)}
.vtag.loc{border-color:rgba(100,116,139,.3);color:#94a3b8}
.vtag.sal{border-color:rgba(16,185,129,.3);color:var(--green);background:rgba(16,185,129,.07)}
.vcard-footer{display:flex;justify-content:space-between;align-items:center;font-size:.72rem;color:#475569;border-top:1px solid rgba(255,255,255,.06);padding-top:.875rem;margin-top:.875rem}
.btn-apply{padding:.35rem .875rem;background:var(--blue);color:var(--white);border-radius:8px;font-size:.75rem;font-weight:600;text-decoration:none;transition:.15s}
.btn-apply:hover{background:#1d4ed8;color:var(--white)}

/* ── HOW IT WORKS ────────────────────────── */
.how-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1.5rem;margin-top:2.5rem}
.how-card{background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.07);border-radius:16px;padding:1.75rem}
.how-icon{width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.3rem;margin-bottom:1.25rem}
.how-num{font-size:2.5rem;font-weight:800;color:rgba(37,99,235,.2);line-height:1;margin-bottom:.5rem}
.how-title{font-size:.95rem;font-weight:700;margin-bottom:.5rem}
.how-desc{font-size:.825rem;color:#64748b;line-height:1.6}

/* ── PARTNERS ────────────────────────────── */
.partners-track{overflow:hidden;position:relative}
.partners-inner{display:flex;gap:1.5rem;animation:scroll-x 25s linear infinite;width:max-content}
.partners-inner:hover{animation-play-state:paused}
@keyframes scroll-x{to{transform:translateX(-50%)}}
.partner-chip{background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.07);border-radius:12px;padding:.75rem 1.25rem;display:flex;align-items:center;gap:.625rem;white-space:nowrap;color:#94a3b8;font-size:.8rem;font-weight:500}
.partner-chip img{width:28px;height:28px;object-fit:contain;border-radius:6px}

/* ── CTA BAND ────────────────────────────── */
.cta-band{background:linear-gradient(135deg,rgba(37,99,235,.15),rgba(6,182,212,.1));border:1px solid rgba(37,99,235,.2);border-radius:24px;padding:3.5rem 2rem;text-align:center;margin:0 auto;max-width:700px}

/* ── FOOTER ──────────────────────────────── */
.lp-footer{border-top:1px solid rgba(255,255,255,.07);padding:2rem 1.5rem;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;color:#475569;font-size:.8rem}
.lp-footer a{color:#64748b;text-decoration:none}
.lp-footer a:hover{color:#94a3b8}
</style>
</head>
<body>

<!-- NAVBAR -->
<nav class="lp-nav">
  <a href="<?= APP_URL ?>" class="lp-brand">
    <img src="<?= APP_URL ?>/assets/images/logo.jpg" alt="LORDIK Logo" class="lp-brand-icon">
    <div>
      <div class="lp-brand-name">LORDIK</div>
      <div class="lp-brand-tag">Loker Cerdik</div>
    </div>
  </a>
  <ul class="lp-nav-links">
    <li><a href="#lowongan">Lowongan</a></li>
    <li><a href="#cara-kerja">Cara Kerja</a></li>
    <li><a href="#mitra">Mitra</a></li>
  </ul>
  <div class="d-flex gap-2">
    <a href="<?= APP_URL ?>/auth/login.php" class="btn-lp-login">Masuk</a>
    <a href="<?= APP_URL ?>/auth/login.php" class="btn-lp-cta">Daftar Gratis</a>
  </div>
</nav>

<!-- HERO -->
<section class="hero">
  <div class="hero-bg"></div>
  <div class="hero-grid"></div>
  <div style="position:relative;z-index:1;max-width:900px;margin:0 auto">
    <div class="hero-badge">
      <i class="bi bi-lightning-charge-fill"></i>
      Platform BKK Digital untuk SMK
    </div>
    <h1 class="hero-title">
      Wujudkan Karir Impian
      <span class="grad">Alumni SMK Bersama Kami</span>
    </h1>
    <p class="hero-sub">
      LORDIK menghubungkan alumni SMK dengan ratusan perusahaan terpercaya.
      Lamar, pantau, dan raih pekerjaan impian dalam satu platform.
    </p>
    <div class="hero-actions">
      <a href="<?= APP_URL ?>/auth/login.php" class="btn-hero-primary">
        <i class="bi bi-rocket-takeoff-fill"></i> Mulai Melamar
      </a>
      <a href="<?= APP_URL ?>/auth/login.php" class="btn-hero-sec">
        <i class="bi bi-briefcase"></i> Lihat Lowongan
      </a>
    </div>

    <!-- LIVE STATS -->
    <div class="stats-row">
      <?php $statDefs=[
        ['num'=>$stats['alumni'],   'label'=>'Alumni Terdaftar',    'color'=>'blue', 'icon'=>'mortarboard'],
        ['num'=>$stats['companies'],'label'=>'Perusahaan Mitra',    'color'=>'cyan', 'icon'=>'building'],
        ['num'=>$stats['vacancies'],'label'=>'Lowongan Aktif',      'color'=>'gold', 'icon'=>'briefcase'],
        ['num'=>$placement_pct.'%','label'=>'Tingkat Penempatan',   'color'=>'green','icon'=>'check-circle'],
      ];
      foreach($statDefs as $s): ?>
      <div class="stat-card">
        <div class="stat-live"></div>
        <div class="stat-num <?= $s['color'] ?>"><?= $s['num'] ?></div>
        <div class="stat-label"><i class="bi bi-<?= $s['icon'] ?> me-1"></i><?= $s['label'] ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- LOWONGAN TERBARU -->
<?php if ($latestVacancies): ?>
<section id="lowongan" style="background:rgba(255,255,255,.015)">
  <div class="container">
    <div class="d-flex justify-content-between align-items-end flex-wrap gap-3">
      <div>
        <div class="section-label"><i class="bi bi-lightning me-1"></i>Terbaru</div>
        <h2 class="section-title mb-1">Lowongan Tersedia</h2>
        <p class="section-sub mb-0">Posisi terbaru dari perusahaan yang sudah terverifikasi.</p>
      </div>
      <a href="<?= APP_URL ?>/auth/login.php" class="btn-apply" style="font-size:.85rem;padding:.55rem 1.25rem">
        Lihat Semua <i class="bi bi-arrow-right ms-1"></i>
      </a>
    </div>

    <div class="vacancy-grid">
      <?php foreach($latestVacancies as $v):
        $types=['full_time'=>'Full Time','part_time'=>'Part Time','contract'=>'Kontrak','internship'=>'Magang'];
      ?>
      <a href="<?= APP_URL ?>/auth/login.php" class="vcard">
        <div class="vcard-header">
          <div class="vcard-logo">
            <?php if ($v['logo_path']): ?>
            <img src="<?= APP_URL ?>/files/serve.php?type=logo&path=<?= urlencode($v['logo_path']) ?>" alt="">
            <?php else: ?>
            <i class="bi bi-building" style="color:#64748b;font-size:1.2rem"></i>
            <?php endif; ?>
          </div>
          <div style="flex:1;min-width:0">
            <div class="vcard-title text-truncate"><?= e($v['title']) ?></div>
            <div class="vcard-company text-truncate"><?= e($v['company_name']) ?></div>
          </div>
        </div>
        <div class="vcard-tags">
          <span class="vtag type"><i class="bi bi-briefcase me-1"></i><?= $types[$v['job_type']]??$v['job_type'] ?></span>
          <?php if ($v['city']||$v['company_city']): ?>
          <span class="vtag loc"><i class="bi bi-geo-alt me-1"></i><?= e($v['city']??$v['company_city']) ?></span>
          <?php endif; ?>
          <?php if ($v['salary_min']): ?>
          <span class="vtag sal"><i class="bi bi-cash me-1"></i><?= formatCurrency($v['salary_min']) ?>+</span>
          <?php endif; ?>
          <?php if ($v['jurusan_required']): ?>
          <span class="vtag"><i class="bi bi-mortarboard me-1"></i><?= e($v['jurusan_required']) ?></span>
          <?php endif; ?>
        </div>
        <div class="vcard-footer">
          <span><i class="bi bi-people me-1"></i><?= $v['app_count'] ?> pelamar</span>
          <?php if ($v['deadline']): ?>
          <span><i class="bi bi-calendar3 me-1"></i>Tutup <?= formatDate($v['deadline'],'d M Y') ?></span>
          <?php endif; ?>
          <span class="btn-apply">Lamar →</span>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- CARA KERJA -->
<section id="cara-kerja">
  <div class="container">
    <div class="section-label"><i class="bi bi-map me-1"></i>Panduan</div>
    <h2 class="section-title">Cara Kerja LORDIK</h2>
    <p class="section-sub">Tiga langkah mudah menuju karir yang lebih baik.</p>
    <div class="how-grid">
      <?php $steps=[
        ['01','Buat Profil','bi-person-badge','rgba(37,99,235,.15)','var(--blue2)','Lengkapi data diri, unggah CV, dan tambahkan sertifikat untuk menarik perhatian perusahaan.'],
        ['02','Lamar Lowongan','bi-send-fill','rgba(16,185,129,.12)','var(--green)','Temukan lowongan sesuai jurusan. Lamar lebih dari satu sekaligus dan pantau statusnya secara realtime.'],
        ['03','Diterima Bekerja','bi-award-fill','rgba(245,158,11,.12)','var(--gold)','Perusahaan menghubungi langsung melalui chat. Terima tawaran dan mulai karir impianmu.'],
        ['🏢','Untuk Perusahaan','bi-building-fill','rgba(6,182,212,.12)','var(--cyan)','Pasang lowongan, tinjau profil lengkap pelamar, dan pilih kandidat terbaik dengan mudah.'],
      ];
      foreach($steps as [$num,$title,$icon,$bg,$col,$desc]): ?>
      <div class="how-card">
        <div class="how-icon" style="background:<?= $bg ?>"><i class="bi <?= $icon ?>" style="color:<?= $col ?>"></i></div>
        <div class="how-num"><?= $num ?></div>
        <div class="how-title"><?= $title ?></div>
        <div class="how-desc"><?= $desc ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- MITRA PERUSAHAAN -->
<?php if ($partners): ?>
<section id="mitra" style="padding:3rem 0">
  <div class="container">
    <div class="section-label text-center mb-3"><i class="bi bi-buildings me-1"></i>Mitra Perusahaan</div>
    <div class="partners-track">
      <div class="partners-inner">
        <?php
        $doubled = array_merge($partners,$partners); // double for infinite scroll
        foreach($doubled as $p): ?>
        <div class="partner-chip">
          <?php if ($p['logo_path']): ?>
          <img src="<?= APP_URL ?>/files/serve.php?type=logo&path=<?= urlencode($p['logo_path']) ?>" alt="">
          <?php else: ?>
          <i class="bi bi-building" style="font-size:.9rem"></i>
          <?php endif; ?>
          <?= e($p['company_name']) ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- CTA -->
<section style="padding:4rem 0">
  <div class="container">
    <div class="cta-band">
      <div style="font-size:2.5rem;margin-bottom:.75rem">🎯</div>
      <h2 style="font-size:1.75rem;font-weight:800;margin-bottom:.875rem">
        Siap Memulai Karir Impian?
      </h2>
      <p style="color:#94a3b8;margin-bottom:2rem;max-width:440px;margin-left:auto;margin-right:auto">
        Bergabung dengan ribuan alumni yang sudah mendapatkan pekerjaan melalui LORDIK.
      </p>
      <div class="d-flex gap-3 justify-content-center flex-wrap">
        <a href="<?= APP_URL ?>/auth/login.php" class="btn-hero-primary">
          <i class="bi bi-person-plus-fill"></i> Daftar sebagai Alumni
        </a>
        <a href="<?= APP_URL ?>/auth/login.php" class="btn-hero-sec">
          <i class="bi bi-building"></i> Daftar sebagai Perusahaan
        </a>
      </div>
    </div>
  </div>
</section>

<!-- FOOTER -->
<footer>
  <div class="lp-footer" style="max-width:1200px;margin:0 auto">
    <div>© <?= date('Y') ?> LORDIK — Loker Cerdik. Platform BKK Digital SMK.</div>
    <div class="d-flex gap-3">
      <a href="<?= APP_URL ?>/auth/login.php">Login</a>
      <a href="<?= APP_URL ?>/auth/reset_password.php">Reset Password</a>
    </div>
  </div>
</footer>

</body>
</html>
