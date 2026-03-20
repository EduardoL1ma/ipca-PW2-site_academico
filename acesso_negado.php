<?php
session_start();
$logado = isset($_SESSION['login']);
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>IPCA — Acesso Negado</title>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        :root{--bg:#0a0e1a;--surface:#111827;--surface2:#1a2236;--border:rgba(255,255,255,0.07);--accent:#4f8ef7;--accent2:#7c3aed;--text:#f0f4ff;--muted:#6b7a99}
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;display:flex;align-items:center;justify-content:center;overflow:hidden}
        body::before{content:'';position:fixed;inset:0;background-image:linear-gradient(rgba(79,142,247,0.03) 1px,transparent 1px),linear-gradient(90deg,rgba(79,142,247,0.03) 1px,transparent 1px);background-size:40px 40px;pointer-events:none}
        .orb{position:fixed;border-radius:50%;filter:blur(80px);pointer-events:none}
        .orb-1{width:400px;height:400px;background:rgba(239,68,68,0.08);top:-100px;right:-100px}
        .orb-2{width:300px;height:300px;background:rgba(124,58,237,0.08);bottom:0;left:0}
        .box{position:relative;z-index:1;text-align:center;padding:60px 40px;max-width:480px;animation:fadeUp 0.5s ease both}
        .code{font-family:'Syne',sans-serif;font-size:96px;font-weight:800;letter-spacing:-4px;background:linear-gradient(135deg,#ef4444,#7c3aed);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;line-height:1;margin-bottom:16px}
        h1{font-family:'Syne',sans-serif;font-size:24px;font-weight:800;letter-spacing:-0.5px;margin-bottom:12px}
        p{color:var(--muted);font-size:15px;line-height:1.6;margin-bottom:32px}
        .btns{display:flex;gap:12px;justify-content:center;flex-wrap:wrap}
        .btn{padding:11px 24px;border-radius:10px;font-size:14px;font-weight:600;font-family:'DM Sans',sans-serif;text-decoration:none;transition:opacity 0.2s}
        .btn-primary{background:linear-gradient(135deg,var(--accent),var(--accent2));color:white}
        .btn-secondary{background:var(--surface2);color:var(--muted);border:1px solid var(--border)}
        .btn:hover{opacity:0.85}
        @keyframes fadeUp{from{opacity:0;transform:translateY(24px)}to{opacity:1;transform:translateY(0)}}
    </style>
</head>
<body>
<div class="orb orb-1"></div>
<div class="orb orb-2"></div>
<div class="box">
    <div class="code">403</div>
    <h1>Acesso Negado</h1>
    <p>Não tens permissão para aceder a esta página.<br>Se acreditas que isto é um erro, contacta o administrador.</p>
    <div class="btns">
        <?php if ($logado): ?>
            <a href="index.php" class="btn btn-primary">🏠 Voltar ao Dashboard</a>
        <?php else: ?>
            <a href="login.php" class="btn btn-primary">🔐 Fazer Login</a>
        <?php endif; ?>
        <a href="javascript:history.back()" class="btn btn-secondary">← Voltar</a>
    </div>
</div>
</body>
</html>
