<?php
require_once 'auth.php';
require_once 'config.php';

$isAdmin       = ($_SESSION['grupo'] == 1);
$isAluno       = ($_SESSION['grupo'] == 2);
$isFuncionario = ($_SESSION['grupo'] == 3);
$isGestor      = ($_SESSION['grupo'] == 4);
$podeDecidir   = ($isAdmin || $isFuncionario);

$login = $_SESSION['login'];
$msg = ''; $msg_type = '';

// Aluno submete pedido
if ($isAluno && isset($_POST['pedir'])) {
    $curso_id = intval($_POST['curso_id'] ?? 0);
    $stmt = $pdo->prepare("SELECT estado FROM ficha_aluno WHERE login=?");
    $stmt->execute([$login]);
    $ficha = $stmt->fetch();

    if (!$ficha || $ficha['estado'] !== 'validada') {
        $msg = "⚠️ A tua ficha tem de estar aprovada antes de poderes pedir matrícula."; $msg_type = 'err';
    } elseif (!$curso_id) {
        $msg = "⚠️ Seleciona um curso."; $msg_type = 'err';
    } else {
        $check = $pdo->prepare("SELECT ID, estado FROM matriculas WHERE login=? AND curso=?");
        $check->execute([$login, $curso_id]);
        $existente = $check->fetch();
        if ($existente) {
            $msg = "⚠️ Já tens um pedido '{$existente['estado']}' para este curso."; $msg_type = 'err';
        } else {
            $stmt = $pdo->prepare("INSERT INTO matriculas (login, curso, estado, data_pedido) VALUES (?,?,'pendente',NOW())");
            $stmt->execute([$login, $curso_id]);
            $msg = "✅ Pedido submetido! Aguarda aprovação dos Serviços Académicos."; $msg_type = 'ok';
        }
    }
}

// Funcionário/Admin aprova
if ($podeDecidir && isset($_POST['aprovar'])) {
    $id  = intval($_POST['mat_id']);
    $obs = trim($_POST['observacoes'] ?? '');
    $stmt = $pdo->prepare("UPDATE matriculas SET estado='aprovada', observacoes=?, decidido_por=?, data_decisao=NOW() WHERE ID=? AND estado='pendente'");
    $stmt->execute([$obs, $login, $id]);
    $msg = $stmt->rowCount() > 0 ? "✅ Matrícula aprovada!" : "⚠️ Pedido já processado.";
    $msg_type = $stmt->rowCount() > 0 ? 'ok' : 'err';
}

// Funcionário/Admin rejeita
if ($podeDecidir && isset($_POST['rejeitar'])) {
    $id  = intval($_POST['mat_id']);
    $obs = trim($_POST['observacoes'] ?? '');
    if (empty($obs)) { $msg = "⚠️ A rejeição requer justificação obrigatória."; $msg_type = 'err'; }
    else {
        $stmt = $pdo->prepare("UPDATE matriculas SET estado='rejeitada', observacoes=?, decidido_por=?, data_decisao=NOW() WHERE ID=? AND estado='pendente'");
        $stmt->execute([$obs, $login, $id]);
        $msg = $stmt->rowCount() > 0 ? "❌ Matrícula rejeitada." : "⚠️ Pedido já processado.";
        $msg_type = 'err';
    }
}

$estado_cores = [
    'pendente'  => ['cor'=>'#f59e0b','label'=>'Pendente', 'icon'=>'⏳'],
    'aprovada'  => ['cor'=>'#10b981','label'=>'Aprovada', 'icon'=>'✅'],
    'rejeitada' => ['cor'=>'#ef4444','label'=>'Rejeitada','icon'=>'❌'],
];

if ($isAluno) {
    $cursos = $pdo->query("SELECT * FROM cursos WHERE ativo=1 ORDER BY Nome")->fetchAll();
    $stmt = $pdo->prepare("SELECT m.*, c.Nome AS nome_curso FROM matriculas m JOIN cursos c ON m.curso=c.ID WHERE m.login=? ORDER BY m.data_pedido DESC");
    $stmt->execute([$login]);
    $meus_pedidos = $stmt->fetchAll();
} else {
    $stmt = $pdo->query("SELECT m.*, c.Nome AS nome_curso, f.nome_completo FROM matriculas m JOIN cursos c ON m.curso=c.ID LEFT JOIN ficha_aluno f ON m.login=f.login WHERE m.estado='pendente' ORDER BY m.data_pedido ASC");
    $pendentes = $stmt->fetchAll();
    $stmt2 = $pdo->query("SELECT m.*, c.Nome AS nome_curso, f.nome_completo FROM matriculas m JOIN cursos c ON m.curso=c.ID LEFT JOIN ficha_aluno f ON m.login=f.login WHERE m.estado!='pendente' ORDER BY m.data_decisao DESC");
    $processados = $stmt2->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8"><title>IPCA — Pedidos de Matrícula</title>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        :root{--bg:#0a0e1a;--surface:#111827;--surface2:#1a2236;--border:rgba(255,255,255,0.07);--accent:#4f8ef7;--accent2:#7c3aed;--text:#f0f4ff;--muted:#6b7a99}
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);min-height:100vh}
        body::before{content:'';position:fixed;inset:0;background-image:linear-gradient(rgba(79,142,247,0.03) 1px,transparent 1px),linear-gradient(90deg,rgba(79,142,247,0.03) 1px,transparent 1px);background-size:40px 40px;pointer-events:none;z-index:0}
        .orb{position:fixed;border-radius:50%;filter:blur(80px);pointer-events:none;z-index:0}
        .orb-1{width:400px;height:400px;background:rgba(79,142,247,0.12);top:-100px;left:-100px}
        .orb-2{width:300px;height:300px;background:rgba(124,58,237,0.1);bottom:0;right:0}
        nav{position:relative;z-index:10;display:flex;justify-content:space-between;align-items:center;padding:18px 40px;border-bottom:1px solid var(--border);background:rgba(10,14,26,0.8);backdrop-filter:blur(12px)}
        .nav-brand{font-family:'Syne',sans-serif;font-weight:800;font-size:20px;letter-spacing:-0.5px}.nav-brand span{color:var(--accent)}
        .nav-links{display:flex;gap:6px}.nav-links a{color:var(--muted);text-decoration:none;font-size:14px;font-weight:500;padding:7px 14px;border-radius:8px;transition:all 0.2s}
        .nav-links a:hover,.nav-links a.active{color:var(--text);background:var(--surface2)}
        .nav-right{display:flex;align-items:center;gap:10px}
        .nav-user{display:flex;align-items:center;gap:8px;background:var(--surface2);padding:7px 14px;border-radius:20px;font-size:13px;color:var(--muted);border:1px solid var(--border)}.nav-user strong{color:var(--text)}
        .nav-avatar{width:28px;height:28px;background:linear-gradient(135deg,var(--accent),var(--accent2));border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:white}
        .btn-logout{background:rgba(239,68,68,0.1);color:#f87171;border:1px solid rgba(239,68,68,0.2);padding:7px 16px;border-radius:8px;font-size:13px;text-decoration:none;font-weight:500}
        main{position:relative;z-index:1;max-width:900px;margin:0 auto;padding:50px 40px}
        .page-header{margin-bottom:36px;animation:fadeUp 0.4s ease both}
        .page-tag{display:inline-flex;align-items:center;gap:6px;background:rgba(79,142,247,0.1);border:1px solid rgba(79,142,247,0.2);color:var(--accent);font-size:11px;font-weight:600;letter-spacing:1px;text-transform:uppercase;padding:4px 12px;border-radius:20px;margin-bottom:14px}
        .page-header h1{font-family:'Syne',sans-serif;font-size:32px;font-weight:800;letter-spacing:-1px;margin-bottom:6px}
        .page-header p{color:var(--muted);font-size:14px}
        .box{background:var(--surface);border:1px solid var(--border);border-radius:20px;padding:32px;margin-bottom:24px;animation:fadeUp 0.4s 0.1s ease both}
        .box-title{font-family:'Syne',sans-serif;font-size:13px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:24px}
        .form-row{display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap}
        .form-group{flex:1;min-width:180px;display:flex;flex-direction:column;gap:6px}
        .form-group label{font-size:12px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:0.5px}
        .form-group select{padding:11px 14px;background:var(--surface2);border:1px solid var(--border);border-radius:10px;color:var(--text);font-size:14px;font-family:'DM Sans',sans-serif}
        .form-group select option{background:var(--surface2)}
        .btn{padding:11px 20px;border:none;border-radius:10px;font-size:14px;font-weight:600;font-family:'DM Sans',sans-serif;cursor:pointer;white-space:nowrap}
        .btn-primary{background:linear-gradient(135deg,var(--accent),#3b6fd4);color:white}
        .pedido-card{background:var(--surface2);border:1px solid var(--border);border-radius:14px;padding:20px 24px;margin-bottom:14px}
        .pedido-top{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:10px}
        .pedido-curso{font-family:'Syne',sans-serif;font-size:16px;font-weight:700}
        .pedido-meta{font-size:13px;color:var(--muted);margin-bottom:6px}
        .pedido-obs{background:var(--surface);border:1px solid var(--border);border-radius:8px;padding:10px 14px;font-size:13px;margin-top:10px}
        .mat-card{background:var(--surface2);border:1px solid var(--border);border-radius:14px;padding:22px 24px;margin-bottom:14px}
        .mat-top{display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-bottom:14px}
        .mat-info h3{font-family:'Syne',sans-serif;font-size:15px;font-weight:700;margin-bottom:4px}
        .mat-info p{font-size:13px;color:var(--muted)}
        .mat-details{display:flex;gap:16px;flex-wrap:wrap;margin-bottom:14px;font-size:13px;color:var(--muted)}
        .mat-details span strong{color:var(--text)}
        .mat-actions{border-top:1px solid var(--border);padding-top:14px}
        .obs-textarea{width:100%;padding:10px 14px;background:var(--bg);border:1px solid var(--border);border-radius:10px;color:var(--text);font-size:13px;font-family:'DM Sans',sans-serif;resize:vertical;min-height:65px;margin-bottom:10px}
        .obs-nota{font-size:11px;color:#f87171;margin-bottom:6px}
        .action-btns{display:flex;gap:10px}
        .btn-success{background:rgba(16,185,129,0.15);color:#34d399;border:1px solid rgba(16,185,129,0.3);font-size:13px;padding:9px 16px;border-radius:8px;cursor:pointer;font-weight:600;font-family:'DM Sans',sans-serif}
        .btn-danger{background:rgba(239,68,68,0.15);color:#f87171;border:1px solid rgba(239,68,68,0.3);font-size:13px;padding:9px 16px;border-radius:8px;cursor:pointer;font-weight:600;font-family:'DM Sans',sans-serif}
        .estado-badge{display:inline-flex;align-items:center;gap:6px;padding:5px 12px;border-radius:20px;font-size:12px;font-weight:700;text-transform:uppercase}
        .msg{padding:14px 18px;border-radius:10px;margin-bottom:20px;font-size:14px}
        .msg-ok{background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.2);color:#34d399}
        .msg-err{background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.2);color:#f87171}
        .empty{color:var(--muted);text-align:center;padding:24px 0;font-size:14px}
        .secao-titulo{font-family:'Syne',sans-serif;font-size:14px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:14px;padding-bottom:10px;border-bottom:1px solid var(--border)}
        @keyframes fadeUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
        @media(max-width:700px){nav{padding:14px 20px}main{padding:30px 20px}.nav-links{display:none}.form-row{flex-direction:column}}
    </style>
</head>
<body>
<div class="orb orb-1"></div><div class="orb orb-2"></div>
<?php include 'navbar.php'; ?>
<main>
    <?php if ($msg): ?><div class="msg msg-<?= $msg_type ?>"><?= $msg ?></div><?php endif; ?>

    <?php if ($isAluno): ?>
    <div class="page-header">
        <div class="page-tag">🎓 Matrícula</div>
        <h1>Pedido de Matrícula</h1>
        <p>Submete um pedido de inscrição. A tua ficha tem de estar aprovada.</p>
    </div>
    <div class="box">
        <div class="box-title">Novo Pedido</div>
        <form method="POST" novalidate>
            <div class="form-row">
                <div class="form-group"><label>Curso</label>
                    <select name="curso_id" required><option value="">-- Seleciona --</option>
                    <?php foreach ($cursos as $c): ?><option value="<?= $c['ID'] ?>"><?= htmlspecialchars($c['Nome']) ?></option><?php endforeach; ?>
                    </select></div>
                <button type="submit" name="pedir" class="btn btn-primary">📤 Submeter</button>
            </div>
        </form>
    </div>
    <div class="box">
        <div class="box-title">Os Meus Pedidos</div>
        <?php if (empty($meus_pedidos)): ?>
            <p class="empty">Ainda não fizeste nenhum pedido.</p>
        <?php else: ?>
        <?php foreach ($meus_pedidos as $p):
            $ec = $estado_cores[$p['estado']] ?? ['cor'=>'#6b7a99','label'=>$p['estado'],'icon'=>''];
        ?>
        <div class="pedido-card">
            <div class="pedido-top">
                <div class="pedido-curso"><?= htmlspecialchars($p['nome_curso']) ?></div>
                <span class="estado-badge" style="background:<?= $ec['cor'] ?>22;color:<?= $ec['cor'] ?>;border:1px solid <?= $ec['cor'] ?>44"><?= $ec['icon'] ?> <?= $ec['label'] ?></span>
            </div>
            <div class="pedido-meta">📅 Pedido em <?= date('d/m/Y \à\s H:i', strtotime($p['data_pedido'])) ?></div>
            <?php if ($p['data_decisao']): ?>
                <div style="font-size:12px;color:var(--muted)">⚖️ Decisão em <?= date('d/m/Y H:i', strtotime($p['data_decisao'])) ?> por <strong><?= htmlspecialchars($p['decidido_por']) ?></strong></div>
            <?php endif; ?>
            <?php if ($p['observacoes']): ?>
            <div class="pedido-obs">
                <div style="font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px">💬 Observações</div>
                <?= htmlspecialchars($p['observacoes']) ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php else: ?>
    <div class="page-header">
        <div class="page-tag">🎓 Serviços Académicos</div>
        <h1>Pedidos de Matrícula</h1>
        <p>Aprova ou rejeita pedidos. Rejeição requer justificação obrigatória. Decisões são registadas.</p>
    </div>
    <div class="box">
        <div class="secao-titulo">⏳ Pendentes (<?= count($pendentes) ?>)</div>
        <?php if (empty($pendentes)): ?>
            <p class="empty">Sem pedidos pendentes.</p>
        <?php else: ?>
        <?php foreach ($pendentes as $p): ?>
        <div class="mat-card" style="border-left:3px solid #f59e0b">
            <div class="mat-top">
                <div class="mat-info">
                    <h3><?= htmlspecialchars($p['login']) ?><?= $p['nome_completo'] ? ' — '.htmlspecialchars($p['nome_completo']) : '' ?></h3>
                    <p>🎓 <?= htmlspecialchars($p['nome_curso']) ?></p>
                </div>
                <span class="estado-badge" style="background:#f59e0b22;color:#f59e0b;border:1px solid #f59e0b44">⏳ Pendente</span>
            </div>
            <div class="mat-details"><span>📅 Pedido em <strong><?= date('d/m/Y H:i', strtotime($p['data_pedido'])) ?></strong></span></div>
            <div class="mat-actions">
                <form method="POST">
                    <input type="hidden" name="mat_id" value="<?= $p['ID'] ?>">
                    <p class="obs-nota">* Justificação obrigatória para rejeitar.</p>
                    <textarea name="observacoes" class="obs-textarea" placeholder="Observações..."></textarea>
                    <div class="action-btns">
                        <button type="submit" name="aprovar" class="btn-success">✅ Aprovar</button>
                        <button type="submit" name="rejeitar" class="btn-danger">❌ Rejeitar</button>
                    </div>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <div class="box">
        <div class="secao-titulo">📋 Processados (<?= count($processados) ?>)</div>
        <?php if (empty($processados)): ?>
            <p class="empty">Sem pedidos processados.</p>
        <?php else: ?>
        <?php foreach ($processados as $p):
            $ec = $estado_cores[$p['estado']] ?? ['cor'=>'#6b7a99','label'=>$p['estado'],'icon'=>''];
        ?>
        <div class="mat-card" style="border-left:3px solid <?= $ec['cor'] ?>;opacity:0.85">
            <div class="mat-top">
                <div class="mat-info">
                    <h3><?= htmlspecialchars($p['login']) ?><?= $p['nome_completo'] ? ' — '.htmlspecialchars($p['nome_completo']) : '' ?></h3>
                    <p>🎓 <?= htmlspecialchars($p['nome_curso']) ?></p>
                </div>
                <span class="estado-badge" style="background:<?= $ec['cor'] ?>22;color:<?= $ec['cor'] ?>;border:1px solid <?= $ec['cor'] ?>44"><?= $ec['icon'] ?> <?= $ec['label'] ?></span>
            </div>
            <div class="mat-details">
                <span>📅 Pedido em <strong><?= date('d/m/Y H:i', strtotime($p['data_pedido'])) ?></strong></span>
                <?php if ($p['data_decisao']): ?>
                <span>⚖️ Decisão em <strong><?= date('d/m/Y H:i', strtotime($p['data_decisao'])) ?></strong> por <strong><?= htmlspecialchars($p['decidido_por']) ?></strong></span>
                <?php endif; ?>
            </div>
            <?php if ($p['observacoes']): ?>
            <div style="font-size:13px;color:var(--muted)">💬 <?= htmlspecialchars($p['observacoes']) ?></div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</main>
</body>
</html>
