<?php
require_once 'auth.php';
require_once 'config.php';

$isAdmin       = ($_SESSION['grupo'] == 1);
$isGestor      = ($_SESSION['grupo'] == 4);
$isFuncionario = ($_SESSION['grupo'] == 3);
$isAluno       = ($_SESSION['grupo'] == 2);
$podeGerir     = ($isAdmin || $isGestor);

$msg = ''; $msg_type = '';

// Apagar vínculo
if ($podeGerir && isset($_GET['del_curso'], $_GET['del_disc'], $_GET['ano'], $_GET['sem'])) {
    $stmt = $pdo->prepare("DELETE FROM plano_estudos WHERE CURSOS=? AND DISCIPLINA=? AND ano=? AND semestre=?");
    $stmt->execute([intval($_GET['del_curso']), intval($_GET['del_disc']), intval($_GET['ano']), intval($_GET['sem'])]);
    $msg = "✅ Vínculo removido!"; $msg_type = 'ok';
}

// Adicionar vínculo
if ($podeGerir && isset($_POST['add'])) {
    $curso_id = intval($_POST['curso_id']);
    $disc_id  = intval($_POST['disciplina_id']);
    $ano      = intval($_POST['ano']);
    $semestre = intval($_POST['semestre']);

    if (!$curso_id || !$disc_id || !$ano || !$semestre) {
        $msg = "⚠️ Preenche todos os campos."; $msg_type = 'err';
    } else {
        $check = $pdo->prepare("SELECT * FROM plano_estudos WHERE CURSOS=? AND DISCIPLINA=? AND ano=? AND semestre=?");
        $check->execute([$curso_id, $disc_id, $ano, $semestre]);
        if ($check->fetch()) {
            $msg = "⚠️ Esta disciplina já existe neste curso/ano/semestre!"; $msg_type = 'err';
        } else {
            $stmt = $pdo->prepare("INSERT INTO plano_estudos (CURSOS, DISCIPLINA, ano, semestre) VALUES (?,?,?,?)");
            $stmt->execute([$curso_id, $disc_id, $ano, $semestre]);
            $msg = "✅ Disciplina adicionada ao plano!"; $msg_type = 'ok';
        }
    }
}

$cursos      = $pdo->query("SELECT * FROM cursos WHERE ativo=1 ORDER BY Nome")->fetchAll();
$disciplinas = $pdo->query("SELECT * FROM disciplinas WHERE ativo=1 ORDER BY Nome_disc")->fetchAll();
$filtro_curso = intval($_GET['curso'] ?? 0);

if ($podeGerir) {
    if ($filtro_curso) {
        $stmt = $pdo->prepare("
            SELECT p.CURSOS AS curso_id, p.DISCIPLINA AS disc_id, p.ano, p.semestre,
                   c.Nome AS nome_curso, d.Nome_disc AS nome_disc, d.codigo, d.ects
            FROM plano_estudos p
            JOIN cursos c ON p.CURSOS=c.ID
            JOIN disciplinas d ON p.DISCIPLINA=d.ID
            WHERE p.CURSOS=?
            ORDER BY p.ano, p.semestre, d.Nome_disc
        ");
        $stmt->execute([$filtro_curso]);
    } else {
        $stmt = $pdo->query("
            SELECT p.CURSOS AS curso_id, p.DISCIPLINA AS disc_id, p.ano, p.semestre,
                   c.Nome AS nome_curso, d.Nome_disc AS nome_disc, d.codigo, d.ects
            FROM plano_estudos p
            JOIN cursos c ON p.CURSOS=c.ID
            JOIN disciplinas d ON p.DISCIPLINA=d.ID
            ORDER BY c.Nome, p.ano, p.semestre, d.Nome_disc
        ");
    }
    $rows = $stmt->fetchAll();
    $grupos = [];
    foreach ($rows as $row) {
        $key = ($podeGerir && !$filtro_curso)
            ? $row['nome_curso'].' — '.$row['ano'].'º Ano / '.$row['semestre'].'º Semestre'
            : $row['ano'].'º Ano / '.$row['semestre'].'º Semestre';
        $grupos[$key][] = $row;
    }
} elseif ($isAluno) {
    $stmt = $pdo->prepare("SELECT c.ID AS curso_id, c.Nome AS nome_curso FROM matriculas m JOIN cursos c ON m.curso=c.ID WHERE m.login=? AND m.estado='aprovada'");
    $stmt->execute([$_SESSION['login']]);
    $matricula = $stmt->fetch();
    if ($matricula) {
        $stmt2 = $pdo->prepare("
            SELECT p.ano, p.semestre, d.Nome_disc AS nome_disc, d.codigo, d.ects, c.Nome AS nome_curso
            FROM plano_estudos p
            JOIN disciplinas d ON p.DISCIPLINA=d.ID
            JOIN cursos c ON p.CURSOS=c.ID
            WHERE p.CURSOS=?
            ORDER BY p.ano, p.semestre, d.Nome_disc
        ");
        $stmt2->execute([$matricula['curso_id']]);
        $rows_aluno = $stmt2->fetchAll();
        $grupos = [];
        foreach ($rows_aluno as $row) {
            $key = $row['ano'].'º Ano / '.$row['semestre'].'º Semestre';
            $grupos[$key][] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8"><title>IPCA — Plano de Estudos</title>
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
        .box-title{font-family:'Syne',sans-serif;font-size:13px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:20px}
        .form-grid{display:grid;grid-template-columns:2fr 2fr 1fr 1fr auto;gap:12px;align-items:flex-end}
        .form-group{display:flex;flex-direction:column;gap:6px}
        .form-group label{font-size:12px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:0.5px}
        .form-group select,.form-group input{padding:11px 14px;background:var(--surface2);border:1px solid var(--border);border-radius:10px;color:var(--text);font-size:14px;font-family:'DM Sans',sans-serif}
        .form-group select option{background:var(--surface2)}
        .btn{padding:11px 20px;border:none;border-radius:10px;font-size:14px;font-weight:600;font-family:'DM Sans',sans-serif;cursor:pointer;white-space:nowrap}
        .btn-primary{background:linear-gradient(135deg,var(--accent),#3b6fd4);color:white}
        .btn-filter{background:var(--surface2);color:var(--text);border:1px solid var(--border)}
        .btn-del{background:rgba(239,68,68,0.15);color:#f87171;border:1px solid rgba(239,68,68,0.3);font-size:12px;padding:5px 10px;text-decoration:none;border-radius:7px}
        table{width:100%;border-collapse:collapse}
        th{padding:10px 16px;text-align:left;font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:1px;border-bottom:1px solid var(--border)}
        td{padding:12px 16px;font-size:14px;border-bottom:1px solid var(--border);vertical-align:middle}
        tr:last-child td{border-bottom:none}
        tr:hover td{background:rgba(255,255,255,0.02)}
        .grupo-header{background:var(--surface2);padding:10px 16px;font-family:'Syne',sans-serif;font-size:12px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:1px;border-bottom:1px solid var(--border)}
        .codigo-badge{background:rgba(16,185,129,0.1);color:#34d399;border:1px solid rgba(16,185,129,0.2);padding:3px 8px;border-radius:6px;font-size:11px;font-weight:700;font-family:monospace}
        .ects-badge{background:rgba(245,158,11,0.1);color:#fbbf24;border:1px solid rgba(245,158,11,0.2);padding:3px 8px;border-radius:6px;font-size:11px;font-weight:700}
        .msg{padding:14px 18px;border-radius:10px;margin-bottom:20px;font-size:14px}
        .msg-ok{background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.2);color:#34d399}
        .msg-err{background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.2);color:#f87171}
        .empty{color:var(--muted);text-align:center;padding:30px 0;font-size:14px}
        @keyframes fadeUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
        @media(max-width:700px){nav{padding:14px 20px}main{padding:30px 20px}.nav-links{display:none}.form-grid{grid-template-columns:1fr}}
    </style>
</head>
<body>
<div class="orb orb-1"></div><div class="orb orb-2"></div>
<?php include 'navbar.php'; ?>
<main>
    <div class="page-header">
        <div class="page-tag">📋 Gestão</div>
        <h1>Plano de Estudos</h1>
        <p><?= $podeGerir ? 'Associa disciplinas aos cursos por ano e semestre.' : 'Consulta as disciplinas do teu curso.' ?></p>
    </div>
    <?php if ($msg): ?><div class="msg msg-<?= $msg_type ?>"><?= $msg ?></div><?php endif; ?>

    <?php if ($podeGerir): ?>
    <div class="box">
        <div class="box-title">Adicionar Disciplina ao Plano</div>
        <form method="POST" novalidate>
            <div class="form-grid">
                <div class="form-group"><label>Curso</label>
                    <select name="curso_id" required><option value="">-- Seleciona --</option>
                    <?php foreach ($cursos as $c): ?><option value="<?= $c['ID'] ?>"><?= htmlspecialchars($c['Nome']) ?></option><?php endforeach; ?>
                    </select></div>
                <div class="form-group"><label>Disciplina</label>
                    <select name="disciplina_id" required><option value="">-- Seleciona --</option>
                    <?php foreach ($disciplinas as $d): ?><option value="<?= $d['ID'] ?>"><?= htmlspecialchars($d['Nome_disc']) ?></option><?php endforeach; ?>
                    </select></div>
                <div class="form-group"><label>Ano</label>
                    <select name="ano" required><option value="1">1º Ano</option><option value="2">2º Ano</option><option value="3">3º Ano</option></select></div>
                <div class="form-group"><label>Semestre</label>
                    <select name="semestre" required><option value="1">1º Sem</option><option value="2">2º Sem</option></select></div>
                <button type="submit" name="add" class="btn btn-primary">🔗 Adicionar</button>
            </div>
        </form>
    </div>
    <div class="box">
        <div class="box-title">Filtrar por Curso</div>
        <form method="GET" style="display:flex;gap:12px;align-items:flex-end;">
            <div class="form-group" style="flex:1"><label>Curso</label>
                <select name="curso">
                    <option value="">-- Todos --</option>
                    <?php foreach ($cursos as $c): ?>
                    <option value="<?= $c['ID'] ?>" <?= $filtro_curso==$c['ID'] ? 'selected' : '' ?>><?= htmlspecialchars($c['Nome']) ?></option>
                    <?php endforeach; ?>
                </select></div>
            <button type="submit" class="btn btn-filter">🔍 Filtrar</button>
        </form>
    </div>
    <?php endif; ?>

    <div class="box">
        <div class="box-title"><?= $podeGerir ? 'Plano de Estudos' : 'As tuas Disciplinas' ?></div>
        <?php if (!empty($grupos)): ?>
            <?php foreach ($grupos as $label => $rows): ?>
            <div class="grupo-header"><?= htmlspecialchars($label) ?></div>
            <table>
                <tr><th>Código</th><th>Disciplina</th><th>ECTS</th><?php if ($podeGerir): ?><th>Ações</th><?php endif; ?></tr>
                <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= $row['codigo'] ? '<span class="codigo-badge">'.htmlspecialchars($row['codigo']).'</span>' : '<span style="color:var(--muted)">—</span>' ?></td>
                    <td><?= htmlspecialchars($row['nome_disc']) ?></td>
                    <td><?= $row['ects'] ? '<span class="ects-badge">'.$row['ects'].' ECTS</span>' : '<span style="color:var(--muted)">—</span>' ?></td>
                    <?php if ($podeGerir): ?>
                    <td><a href="?del_curso=<?= $row['curso_id'] ?>&del_disc=<?= $row['disc_id'] ?>&ano=<?= $row['ano'] ?>&sem=<?= $row['semestre'] ?><?= $filtro_curso ? '&curso='.$filtro_curso : '' ?>"
                           class="btn-del" onclick="return confirm('Remover do plano?')">🗑️ Remover</a></td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </table>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="empty"><?= $isAluno ? 'Não estás matriculado em nenhum curso aprovado.' : 'Nenhuma disciplina no plano ainda.' ?></p>
        <?php endif; ?>
    </div>
</main>
</body>
</html>
