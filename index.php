<?php
require_once 'auth.php';
require_once 'config.php';

$isAdmin       = ($_SESSION['grupo'] == 1);
$isAluno       = ($_SESSION['grupo'] == 2);
$isFuncionario = ($_SESSION['grupo'] == 3);
$isGestor      = ($_SESSION['grupo'] == 4);
$isStaff       = ($isAdmin || $isFuncionario || $isGestor);

$hora = date('H');
if ($hora < 12) $saudacao = "Bom dia";
elseif ($hora < 18) $saudacao = "Boa tarde";
else $saudacao = "Boa noite";

$total_cursos = $total_disciplinas = $total_plano = $total_matriculas = 0;
$r1 = $pdo->query("SELECT COUNT(*) AS total FROM cursos"); if ($r1) $total_cursos = $r1->fetch()['total'];
$r2 = $pdo->query("SELECT COUNT(*) AS total FROM disciplinas"); if ($r2) $total_disciplinas = $r2->fetch()['total'];
$r3 = $pdo->query("SELECT COUNT(*) AS total FROM plano_estudos"); if ($r3) $total_plano = $r3->fetch()['total'];
$r4 = $pdo->query("SELECT COUNT(*) AS total FROM matriculas"); if ($r4) $total_matriculas = $r4->fetch()['total'];

$matricula = null;
if ($isAluno) {
    $login = $_SESSION['login'];
    $stmt = $pdo->prepare("SELECT c.Nome AS nome_curso FROM matriculas m JOIN cursos c ON m.curso = c.ID WHERE m.login = ?");
    $stmt->execute([$login]);
    $matricula = $stmt->fetch();
}

// Badge e label do perfil
$perfil_label = match((int)$_SESSION['grupo']) {
    1 => ['label' => 'Administrador', 'color' => '#4f8ef7'],
    2 => ['label' => 'Aluno',         'color' => '#10b981'],
    3 => ['label' => 'Funcionário',   'color' => '#f59e0b'],
    4 => ['label' => 'Gestor Pedagógico', 'color' => '#a78bfa'],
    default => ['label' => 'Utilizador', 'color' => '#6b7a99'],
};
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>IPCA — Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        :root { --bg:#0a0e1a; --surface:#111827; --surface2:#1a2236; --border:rgba(255,255,255,0.07); --accent:#4f8ef7; --accent2:#7c3aed; --accent3:#10b981; --text:#f0f4ff; --muted:#6b7a99; }
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
        .nav-right { display:flex; align-items:center; gap:10px; }
        .nav-user { display:flex; align-items:center; gap:8px; background:var(--surface2); padding:7px 14px; border-radius:20px; font-size:13px; color:var(--muted); border:1px solid var(--border); }
        .nav-user strong { color:var(--text); }
        .nav-avatar { width:28px; height:28px; background:linear-gradient(135deg,var(--accent),var(--accent2)); border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:700; color:white; }
        .perfil-badge { font-size:11px; font-weight:700; padding:3px 10px; border-radius:20px; letter-spacing:0.5px; text-transform:uppercase; }
        .btn-logout { background:rgba(239,68,68,0.1); color:#f87171; border:1px solid rgba(239,68,68,0.2); padding:7px 16px; border-radius:8px; font-size:13px; text-decoration:none; transition:all 0.2s; font-weight:500; }
        .btn-logout:hover { background:rgba(239,68,68,0.2); }
        main { position:relative; z-index:1; max-width:1100px; margin:0 auto; padding:60px 40px; }
        .hero { margin-bottom:50px; animation:fadeUp 0.5s ease both; }
        .hero-tag { display:inline-flex; align-items:center; gap:6px; background:rgba(79,142,247,0.1); border:1px solid rgba(79,142,247,0.2); color:var(--accent); font-size:11px; font-weight:600; letter-spacing:1px; text-transform:uppercase; padding:4px 12px; border-radius:20px; margin-bottom:16px; }
        .hero h1 { font-family:'Syne',sans-serif; font-size:clamp(28px,4vw,48px); font-weight:800; line-height:1.1; letter-spacing:-1.5px; margin-bottom:10px; }
        .hero h1 .highlight { background:linear-gradient(135deg,var(--accent),var(--accent2)); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text; }
        .hero p { color:var(--muted); font-size:15px; margin-bottom:14px; }
        .info-badge { display:inline-flex; align-items:center; gap:8px; padding:8px 16px; border-radius:20px; font-size:13px; font-weight:600; margin-top:6px; }
        .info-green { background:rgba(16,185,129,0.1); border:1px solid rgba(16,185,129,0.2); color:#34d399; }
        .info-red { background:rgba(239,68,68,0.1); border:1px solid rgba(239,68,68,0.2); color:#f87171; }
        .stats-row { display:flex; gap:16px; margin-bottom:50px; flex-wrap:wrap; animation:fadeUp 0.5s 0.1s ease both; }
        .stat-pill { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:12px 20px; display:flex; align-items:center; gap:10px; font-size:14px; color:var(--muted); }
        .stat-pill strong { color:var(--text); font-size:18px; font-family:'Syne',sans-serif; }
        .stat-dot { width:8px; height:8px; border-radius:50%; }
        .cards-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:20px; }
        .card { position:relative; background:var(--surface); border:1px solid var(--border); border-radius:20px; padding:32px; text-decoration:none; color:var(--text); overflow:hidden; transition:transform 0.3s,box-shadow 0.3s,border-color 0.3s; display:block; animation:fadeUp 0.5s ease both; }
        .card:nth-child(1){animation-delay:0.15s} .card:nth-child(2){animation-delay:0.2s} .card:nth-child(3){animation-delay:0.25s} .card:nth-child(4){animation-delay:0.3s} .card:nth-child(5){animation-delay:0.35s}
        .card::before { content:''; position:absolute; inset:0; opacity:0; transition:opacity 0.3s; border-radius:20px; }
        .card:hover { transform:translateY(-6px); box-shadow:0 20px 40px rgba(0,0,0,0.3); }
        .card:hover::before { opacity:1; }
        .card-icon { width:52px; height:52px; border-radius:14px; display:flex; align-items:center; justify-content:center; font-size:24px; margin-bottom:24px; }
        .card-title { font-family:'Syne',sans-serif; font-size:20px; font-weight:700; margin-bottom:8px; letter-spacing:-0.3px; }
        .card-desc { color:var(--muted); font-size:13px; line-height:1.5; margin-bottom:28px; }
        .card-arrow { display:inline-flex; align-items:center; gap:6px; font-size:13px; font-weight:600; transition:gap 0.2s; }
        .card:hover .card-arrow { gap:10px; }
        .card-corner { position:absolute; bottom:-20px; right:-20px; font-size:80px; opacity:0.04; transition:opacity 0.3s,transform 0.3s; pointer-events:none; }
        .card:hover .card-corner { opacity:0.07; transform:scale(1.1) rotate(-5deg); }
        .c-blue::before{background:linear-gradient(135deg,rgba(79,142,247,0.08),transparent)} .c-blue:hover{border-color:rgba(79,142,247,0.4)} .c-blue .card-icon{background:rgba(79,142,247,0.15)} .c-blue .card-arrow{color:#4f8ef7}
        .c-purple::before{background:linear-gradient(135deg,rgba(124,58,237,0.08),transparent)} .c-purple:hover{border-color:rgba(124,58,237,0.4)} .c-purple .card-icon{background:rgba(124,58,237,0.15)} .c-purple .card-arrow{color:#a78bfa}
        .c-green::before{background:linear-gradient(135deg,rgba(16,185,129,0.08),transparent)} .c-green:hover{border-color:rgba(16,185,129,0.4)} .c-green .card-icon{background:rgba(16,185,129,0.15)} .c-green .card-arrow{color:#34d399}
        .c-yellow::before{background:linear-gradient(135deg,rgba(245,158,11,0.08),transparent)} .c-yellow:hover{border-color:rgba(245,158,11,0.4)} .c-yellow .card-icon{background:rgba(245,158,11,0.15)} .c-yellow .card-arrow{color:#fbbf24}
        .c-pink::before{background:linear-gradient(135deg,rgba(236,72,153,0.08),transparent)} .c-pink:hover{border-color:rgba(236,72,153,0.4)} .c-pink .card-icon{background:rgba(236,72,153,0.15)} .c-pink .card-arrow{color:#f472b6}
        @keyframes fadeUp { from{opacity:0;transform:translateY(24px)} to{opacity:1;transform:translateY(0)} }
        @media(max-width:700px){.cards-grid{grid-template-columns:1fr} nav{padding:14px 20px} main{padding:40px 20px} .nav-links{display:none}}
    </style>
</head>
<body>
<div class="orb orb-1"></div>
<div class="orb orb-2"></div>
<?php include 'navbar.php'; ?>
<main>
    <div class="hero">
        <div class="hero-tag">✦ Painel de Gestão</div>
        <h1><?= $saudacao ?>, <span class="highlight"><?= htmlspecialchars($_SESSION['login']) ?></span></h1>
        <p>
            <?php if ($isAdmin): ?>
                Bem-vindo ao painel de administração. Tens acesso total ao sistema.
            <?php elseif ($isGestor): ?>
                Bem-vindo ao painel de Gestão Pedagógica. Gere cursos, disciplinas e planos de estudo.
            <?php elseif ($isFuncionario): ?>
                Bem-vindo ao painel dos Serviços Académicos. Gere matrículas e pedidos dos alunos.
            <?php else: ?>
                Bem-vindo à plataforma académica do IPCA.
            <?php endif; ?>
        </p>
        <?php if ($isAluno): ?>
            <?php if ($matricula): ?>
                <div class="info-badge info-green">🎓 Matriculado em: <?= htmlspecialchars($matricula['nome_curso']) ?></div>
            <?php else: ?>
                <div class="info-badge info-red">⚠️ Ainda não estás matriculado em nenhum curso</div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <div class="stats-row">
        <div class="stat-pill"><div class="stat-dot" style="background:#4f8ef7"></div><strong><?= $total_cursos ?></strong> Cursos</div>
        <div class="stat-pill"><div class="stat-dot" style="background:#7c3aed"></div><strong><?= $total_disciplinas ?></strong> Disciplinas</div>
        <div class="stat-pill"><div class="stat-dot" style="background:#10b981"></div><strong><?= $total_plano ?></strong> Vínculos</div>
        <?php if ($isStaff): ?>
        <div class="stat-pill"><div class="stat-dot" style="background:#f59e0b"></div><strong><?= $total_matriculas ?></strong> Matrículas</div>
        <?php endif; ?>
    </div>

    <div class="cards-grid">

        <?php if ($isGestor || $isAdmin): ?>
        <a href="cursos.php" class="card c-blue">
            <div class="card-icon">📚</div>
            <div class="card-title">Cursos</div>
            <div class="card-desc">Cria e configura cursos e planos de estudo.</div>
            <div class="card-arrow">Gerir cursos →</div>
            <div class="card-corner">📚</div>
        </a>
        <a href="disciplinas.php" class="card c-purple">
            <div class="card-icon">📖</div>
            <div class="card-title">Disciplinas</div>
            <div class="card-desc">Gere as unidades curriculares do sistema.</div>
            <div class="card-arrow">Gerir disciplinas →</div>
            <div class="card-corner">📖</div>
        </a>
        <a href="plano_estudos.php" class="card c-green">
            <div class="card-icon">📋</div>
            <div class="card-title">Plano de Estudos</div>
            <div class="card-desc">Define os planos curriculares por curso.</div>
            <div class="card-arrow">Ver plano →</div>
            <div class="card-corner">📋</div>
        </a>
        <?php endif; ?>

        <?php if ($isFuncionario || $isAdmin): ?>
        <a href="matriculas.php" class="card c-yellow">
            <div class="card-icon">🎓</div>
            <div class="card-title">Matrículas</div>
            <div class="card-desc">Valida e gere os pedidos de matrícula dos alunos.</div>
            <div class="card-arrow">Gerir matrículas →</div>
            <div class="card-corner">🎓</div>
        </a>
        <a href="pautas.php" class="card c-pink">
            <div class="card-icon">📊</div>
            <div class="card-title">Pautas</div>
            <div class="card-desc">Gera pautas e regista notas dos alunos.</div>
            <div class="card-arrow">Ver pautas →</div>
            <div class="card-corner">📊</div>
        </a>
        <?php endif; ?>

        <?php if ($isGestor || $isAdmin): ?>
        <a href="ficha_aluno.php" class="card c-yellow">
            <div class="card-icon">👤</div>
            <div class="card-title">Fichas de Alunos</div>
            <div class="card-desc">Valida ou rejeita as fichas submetidas pelos alunos.</div>
            <div class="card-arrow">Ver fichas →</div>
            <div class="card-corner">👤</div>
        </a>
        <?php endif; ?>

        <?php if ($isAluno): ?>
        <a href="cursos.php" class="card c-blue">
            <div class="card-icon">📚</div>
            <div class="card-title">Cursos</div>
            <div class="card-desc">Consulta os cursos disponíveis na plataforma.</div>
            <div class="card-arrow">Ver cursos →</div>
            <div class="card-corner">📚</div>
        </a>
        <a href="disciplinas.php" class="card c-purple">
            <div class="card-icon">📖</div>
            <div class="card-title">Disciplinas</div>
            <div class="card-desc">Consulta as disciplinas disponíveis.</div>
            <div class="card-arrow">Ver disciplinas →</div>
            <div class="card-corner">📖</div>
        </a>
        <a href="plano_estudos.php" class="card c-green">
            <div class="card-icon">📋</div>
            <div class="card-title">Plano de Estudos</div>
            <div class="card-desc">Consulta as disciplinas do teu curso.</div>
            <div class="card-arrow">Ver plano →</div>
            <div class="card-corner">📋</div>
        </a>
        <a href="ficha_aluno.php" class="card c-yellow">
            <div class="card-icon">👤</div>
            <div class="card-title">A Minha Ficha</div>
            <div class="card-desc">Preenche e submete a tua ficha de aluno.</div>
            <div class="card-arrow">Ver ficha →</div>
            <div class="card-corner">👤</div>
        </a>
        <a href="pedido_matricula.php" class="card c-pink">
            <div class="card-icon">🎓</div>
            <div class="card-title">Matrícula</div>
            <div class="card-desc">Submete e consulta os teus pedidos de matrícula.</div>
            <div class="card-arrow">Ver pedidos →</div>
            <div class="card-corner">🎓</div>
        </a>
        <a href="minhas_notas.php" class="card c-yellow">
            <div class="card-icon">📊</div>
            <div class="card-title">As Minhas Notas</div>
            <div class="card-desc">Consulta as tuas avaliações e resultados por disciplina.</div>
            <div class="card-arrow">Ver notas →</div>
            <div class="card-corner">📊</div>
        </a>
        <?php endif; ?>

        <?php if ($isAdmin): ?>
        <a href="qrcode.php" class="card c-pink">
            <div class="card-icon">📱</div>
            <div class="card-title">QR Code</div>
            <div class="card-desc">Partilha o acesso ao site na rede local.</div>
            <div class="card-arrow">Ver QR Code →</div>
            <div class="card-corner">📱</div>
        </a>
        <?php endif; ?>

    </div>
</main>
</body>
</html>
