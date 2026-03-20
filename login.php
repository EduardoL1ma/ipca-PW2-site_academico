<?php
session_start();
require_once 'config.php';

$erro = '';

if (isset($_POST['entrar'])) {
    $login    = trim($_POST['login'] ?? '');
    $password = trim($_POST['password'] ?? '');

    // Validação servidor
    if (empty($login) || empty($password)) {
        $erro = "Preenche todos os campos.";
    } elseif (strlen($login) > 20) {
        $erro = "Login inválido.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE login = ?");
        $stmt->execute([$login]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['pwd'])) {
            session_regenerate_id(true);
            $_SESSION['login']         = $user['login'];
            $_SESSION['grupo']         = $user['grupo'];
            $_SESSION['ultimo_acesso'] = time();
            header("Location: index.php");
            exit;
        } else {
            $erro = "Login ou password incorretos.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>IPCA — Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        :root { --bg:#0a0e1a; --surface:#111827; --surface2:#1a2236; --border:rgba(255,255,255,0.07); --accent:#4f8ef7; --accent2:#7c3aed; --text:#f0f4ff; --muted:#6b7a99; }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'DM Sans',sans-serif; background:var(--bg); color:var(--text); min-height:100vh; display:flex; align-items:center; justify-content:center; overflow:hidden; }
        body::before { content:''; position:fixed; inset:0; background-image:linear-gradient(rgba(79,142,247,0.03) 1px,transparent 1px),linear-gradient(90deg,rgba(79,142,247,0.03) 1px,transparent 1px); background-size:40px 40px; pointer-events:none; }
        .orb { position:fixed; border-radius:50%; filter:blur(80px); pointer-events:none; }
        .orb-1 { width:400px; height:400px; background:rgba(79,142,247,0.12); top:-100px; left:-100px; }
        .orb-2 { width:300px; height:300px; background:rgba(124,58,237,0.1); bottom:0; right:0; }
        .login-box { position:relative; z-index:1; background:var(--surface); border:1px solid var(--border); border-radius:24px; padding:48px 40px; width:100%; max-width:400px; box-shadow:0 30px 60px rgba(0,0,0,0.4); animation:fadeUp 0.5s ease both; }
        @keyframes fadeUp { from{opacity:0;transform:translateY(24px)} to{opacity:1;transform:translateY(0)} }
        .login-logo { font-family:'Syne',sans-serif; font-size:28px; font-weight:800; letter-spacing:-1px; margin-bottom:6px; }
        .login-logo span { color:var(--accent); }
        .login-sub { color:var(--muted); font-size:14px; margin-bottom:36px; }
        .form-group { margin-bottom:18px; }
        .form-group label { display:block; font-size:13px; font-weight:600; color:var(--muted); text-transform:uppercase; letter-spacing:0.5px; margin-bottom:8px; }
        .form-group input { width:100%; padding:12px 16px; background:var(--surface2); border:1px solid var(--border); border-radius:10px; color:var(--text); font-size:15px; font-family:'DM Sans',sans-serif; transition:border-color 0.2s; }
        .form-group input:focus { outline:none; border-color:rgba(79,142,247,0.5); }
        .btn-login { width:100%; padding:13px; background:linear-gradient(135deg,var(--accent),var(--accent2)); color:white; border:none; border-radius:10px; font-size:15px; font-weight:600; font-family:'DM Sans',sans-serif; cursor:pointer; margin-top:8px; transition:opacity 0.2s; }
        .btn-login:hover { opacity:0.9; }
        .erro { background:rgba(239,68,68,0.1); border:1px solid rgba(239,68,68,0.2); color:#f87171; padding:12px 16px; border-radius:10px; font-size:14px; margin-bottom:20px; }
    </style>
</head>
<body>
<div class="orb orb-1"></div>
<div class="orb orb-2"></div>
<div class="login-box">
    <div class="login-logo">IP<span>CA</span></div>
    <div class="login-sub">Introduz as tuas credenciais para entrar</div>
    <?php if ($erro): ?>
        <div class="erro">⚠️ <?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>
    <form method="POST" novalidate>
        <div class="form-group">
            <label>Utilizador</label>
            <input type="text" name="login" required maxlength="20"
                   value="<?= htmlspecialchars($_POST['login'] ?? '') ?>"
                   placeholder="O teu login" autofocus>
        </div>
        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" required placeholder="••••••••">
        </div>
        <button type="submit" name="entrar" class="btn-login">Entrar →</button>
    </form>
</div>
</body>
</html>
