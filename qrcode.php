<?php
session_start();
if (!isset($_SESSION['login'])) { header("Location: login.php"); exit; }
require_once 'config.php';
$isAdmin = ($_SESSION['grupo'] == 1);
if (!$isAdmin) { header("Location: index.php"); exit; }

// Detecta o IP automaticamente
$ip = '172.16.64.61';
$port = '80';
$portStr = ($port == '80') ? '' : ":$port";
$url = "http://" . $ip . $portStr . "/CURSOS/login.php";
$qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($url);
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>IPCA — QR Code</title>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        :root { --bg:#0a0e1a; --surface:#111827; --surface2:#1a2236; --border:rgba(255,255,255,0.07); --accent:#4f8ef7; --accent2:#7c3aed; --text:#f0f4ff; --muted:#6b7a99; }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'DM Sans',sans-serif; background:var(--bg); color:var(--text); min-height:100vh; overflow-x:hidden; }
        body::before { content:''; position:fixed; inset:0; background-image:linear-gradient(rgba(79,142,247,0.03) 1px,transparent 1px),linear-gradient(90deg,rgba(79,142,247,0.03) 1px,transparent 1px); background-size:40px 40px; pointer-events:none; z-index:0; }
        .orb { position:fixed; border-radius:50%; filter:blur(80px); pointer-events:none; z-index:0; }
        .orb-1 { width:400px; height:400px; background:rgba(79,142,247,0.12); top:-100px; left:-100px; }
        .orb-2 { width:300px; height:300px; background:rgba(124,58,237,0.1); bottom:0; right:0; }
        nav { position:relative; z-index:10; display:flex; justify-content:space-between; align-items:center; padding:18px 40px; border-bottom:1px solid var(--border); background:rgba(10,14,26,0.8); backdrop-filter:blur(12px); }
        .nav-brand { font-family:'Syne',sans-serif; font-weight:800; font-size:20px; letter-spacing:-0.5px; }
        .nav-brand span { color:var(--accent); }
        .nav-links { display:flex; gap:6px; }
        .nav-links a { color:var(--muted); text-decoration:none; font-size:14px; font-weight:500; padding:7px 14px; border-radius:8px; transition:all 0.2s; }
        .nav-links a:hover, .nav-links a.active { color:var(--text); background:var(--surface2); }
        .nav-right { display:flex; align-items:center; gap:14px; }
        .nav-user { display:flex; align-items:center; gap:8px; background:var(--surface2); padding:7px 14px; border-radius:20px; font-size:13px; color:var(--muted); border:1px solid var(--border); }
        .nav-user strong { color:var(--text); }
        .nav-avatar { width:28px; height:28px; background:linear-gradient(135deg,var(--accent),var(--accent2)); border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:700; color:white; }
        .btn-logout { background:rgba(239,68,68,0.1); color:#f87171; border:1px solid rgba(239,68,68,0.2); padding:7px 16px; border-radius:8px; font-size:13px; text-decoration:none; transition:all 0.2s; font-weight:500; }
        .btn-logout:hover { background:rgba(239,68,68,0.2); }
        main { position:relative; z-index:1; max-width:600px; margin:0 auto; padding:60px 40px; text-align:center; }
        .page-tag { display:inline-flex; align-items:center; gap:6px; background:rgba(79,142,247,0.1); border:1px solid rgba(79,142,247,0.2); color:var(--accent); font-size:11px; font-weight:600; letter-spacing:1px; text-transform:uppercase; padding:4px 12px; border-radius:20px; margin-bottom:16px; }
        h1 { font-family:'Syne',sans-serif; font-size:32px; font-weight:800; letter-spacing:-1px; margin-bottom:8px; }
        .subtitle { color:var(--muted); font-size:14px; margin-bottom:40px; }
        .qr-box { background:var(--surface); border:1px solid var(--border); border-radius:24px; padding:40px; display:inline-block; animation:fadeUp 0.5s ease both; }
        .qr-box img { border-radius:12px; display:block; }
        .url-pill { margin-top:24px; background:var(--surface2); border:1px solid var(--border); border-radius:10px; padding:12px 20px; font-size:13px; color:var(--accent); font-family:monospace; word-break:break-all; }
        .hint { margin-top:16px; font-size:13px; color:var(--muted); }
        @keyframes fadeUp { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:translateY(0)} }
    </style>
</head>
<body>
<div class="orb orb-1"></div>
<div class="orb orb-2"></div>
<nav>
    <div class="nav-brand">IP<span>CA</span></div>
    <div class="nav-links">
        <a href="index.php">Dashboard</a>
        <a href="cursos.php">Cursos</a>
        <a href="disciplinas.php">Disciplinas</a>
        <a href="plano_estudos.php">Plano de Estudos</a>
        <a href="matriculas.php">Matrículas</a>
        <a href="qrcode.php" class="active">QR Code</a>
    </div>
    <div class="nav-right">
        <div class="nav-user">
            <div class="nav-avatar"><?= strtoupper(substr($_SESSION['login'], 0, 1)) ?></div>
            <strong><?= htmlspecialchars($_SESSION['login']) ?></strong>
        </div>
        <a href="logout.php" class="btn-logout">Logout</a>
    </div>
</nav>
<main>
    <div class="page-tag">📱 Acesso Rápido</div>
    <h1>QR Code do Site</h1>
    <p class="subtitle">Aponta a câmara do telemóvel para aceder ao site na mesma rede WiFi.</p>

    <div class="qr-box">
        <img src="<?= $qrUrl ?>" alt="QR Code" width="300" height="300">
        <div class="url-pill"><?= htmlspecialchars($url) ?></div>
        <p class="hint">📶 Certifica-te que estás na mesma rede WiFi</p>
    </div>
</main>
</body>
</html>
