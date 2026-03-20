<?php
require_once 'auth.php';
require_once 'config.php';

$isAdmin       = ($_SESSION['grupo'] == 1);
$isAluno       = ($_SESSION['grupo'] == 2);
$isFuncionario = ($_SESSION['grupo'] == 3);
$isGestor      = ($_SESSION['grupo'] == 4);

if (!$isAluno) { header("Location: acesso_negado.php"); exit; }

$login = $_SESSION['login'];

// Buscar matrícula aprovada
$stmt = $pdo->prepare("SELECT c.ID AS curso_id, c.Nome AS nome_curso FROM matriculas m JOIN cursos c ON m.curso=c.ID WHERE m.login=? AND m.estado='aprovada'");
$stmt->execute([$login]);
$matricula = $stmt->fetch();

// Buscar notas do aluno agrupadas por época/ano letivo
$notas = [];
if ($matricula) {
    $stmt2 = $pdo->prepare("
        SELECT p.nota, p.data_registo, p.ano_letivo, p.epoca,
               d.Nome_disc AS nome_disc, d.codigo, d.ects,
               ph.ano_letivo AS pal, ph.epoca AS pep
        FROM pautas p
        JOIN pautas_header ph ON p.pauta_id = ph.ID
        JOIN disciplinas d ON ph.disciplina = d.ID
        WHERE p.login_aluno = ?
        ORDER BY ph.ano_letivo DESC, ph.epoca, d.Nome_disc
    ");
    $stmt2->execute([$login]);
    $rows = $stmt2->fetchAll();

    foreach ($rows as $row) {
        $key = $row['pal'] . ' — Época ' . $row['pep'];
        $notas[$key][] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>IPCA — As Minhas Notas</title>
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
        main{position:relative;z-index:1;max-width:800px;margin:0 auto;padding:50px 40px}
        .page-header{margin-bottom:36px;animation:fadeUp 0.4s ease both}
        .page-tag{display:inline-flex;align-items:center;gap:6px;background:rgba(79,142,247,0.1);border:1px solid rgba(79,142,247,0.2);color:var(--accent);font-size:11px;font-weight:600;letter-spacing:1px;text-transform:uppercase;padding:4px 12px;border-radius:20px;margin-bottom:14px}
        .page-header h1{font-family:'Syne',sans-serif;font-size:32px;font-weight:800;letter-spacing:-1px;margin-bottom:6px}
        .page-header p{color:var(--muted);font-size:14px}
        .box{background:var(--surface);border:1px solid var(--border);border-radius:20px;padding:32px;margin-bottom:24px;animation:fadeUp 0.4s 0.1s ease both}
        .box-title{font-family:'Syne',sans-serif;font-size:13px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:20px}
        .curso-badge{display:inline-flex;align-items:center;gap:8px;background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.2);color:#34d399;padding:8px 16px;border-radius:20px;font-size:13px;font-weight:600;margin-bottom:24px}
        .grupo-header{background:var(--surface2);padding:10px 16px;font-family:'Syne',sans-serif;font-size:12px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:1px;border-radius:8px;margin-bottom:12px;display:flex;justify-content:space-between;align-items:center}
        .epoca-badge{font-size:11px;font-weight:700;padding:3px 10px;border-radius:20px;text-transform:uppercase;letter-spacing:0.5px}
        .epoca-Normal{background:rgba(79,142,247,0.15);color:#4f8ef7;border:1px solid rgba(79,142,247,0.3)}
        .epoca-Recurso{background:rgba(245,158,11,0.15);color:#fbbf24;border:1px solid rgba(245,158,11,0.3)}
        .epoca-Especial{background:rgba(124,58,237,0.15);color:#a78bfa;border:1px solid rgba(124,58,237,0.3)}
        table{width:100%;border-collapse:collapse;margin-bottom:20px}
        th{padding:10px 16px;text-align:left;font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:1px;border-bottom:1px solid var(--border)}
        td{padding:14px 16px;font-size:14px;border-bottom:1px solid var(--border);vertical-align:middle}
        tr:last-child td{border-bottom:none}
        tr:hover td{background:rgba(255,255,255,0.02)}
        .nota-badge{display:inline-flex;align-items:center;justify-content:center;min-width:60px;height:32px;border-radius:8px;font-size:15px;font-weight:800;font-family:'Syne',sans-serif;padding:0 12px}
        .nota-pos{background:rgba(16,185,129,0.15);color:#34d399;border:1px solid rgba(16,185,129,0.3)}
        .nota-neg{background:rgba(239,68,68,0.15);color:#f87171;border:1px solid rgba(239,68,68,0.3)}
        .nota-vazia{background:var(--surface2);color:var(--muted);border:1px solid var(--border)}
        .codigo-badge{background:rgba(79,142,247,0.1);color:var(--accent);border:1px solid rgba(79,142,247,0.2);padding:3px 8px;border-radius:6px;font-size:11px;font-weight:700;font-family:monospace}
        .ects-badge{background:rgba(124,58,237,0.1);color:#a78bfa;border:1px solid rgba(124,58,237,0.2);padding:3px 8px;border-radius:6px;font-size:11px;font-weight:700}
        .resultado{font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px}
        .aprovado{color:#34d399}.reprovado{color:#f87171}.pendente{color:var(--muted)}
        .stats{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:24px}
        .stat{background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:12px 18px;text-align:center}
        .stat-num{font-family:'Syne',sans-serif;font-size:24px;font-weight:800;display:block}
        .stat-label{font-size:12px;color:var(--muted);margin-top:2px}
        .empty{color:var(--muted);text-align:center;padding:40px 0;font-size:14px}
        @keyframes fadeUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
        @media(max-width:700px){nav{padding:14px 20px}main{padding:30px 20px}.nav-links{display:none}}
    </style>
</head>
<body>
<div class="orb orb-1"></div>
<div class="orb orb-2"></div>
<?php include 'navbar.php'; ?>
<main>
    <div class="page-header">
        <div class="page-tag">📊 Avaliação</div>
        <h1>As Minhas Notas</h1>
        <p>Consulta as tuas avaliações por disciplina, ano letivo e época.</p>
    </div>

    <?php if ($matricula): ?>
        <div class="curso-badge">🎓 <?= htmlspecialchars($matricula['nome_curso']) ?></div>
    <?php endif; ?>

    <?php if (empty($notas)): ?>
    <div class="box">
        <p class="empty">
            <?php if (!$matricula): ?>
                Ainda não estás matriculado em nenhum curso aprovado.
            <?php else: ?>
                Ainda não há notas lançadas para as tuas disciplinas.
            <?php endif; ?>
        </p>
    </div>
    <?php else: ?>

    <?php
    // Estatísticas globais
    $all_notas = array_merge(...array_values($notas));
    $com_nota  = array_filter($all_notas, fn($r) => $r['nota'] !== null);
    $aprovadas = array_filter($com_nota, fn($r) => $r['nota'] >= 10);
    $media_geral = count($com_nota) > 0 ? array_sum(array_column($com_nota, 'nota')) / count($com_nota) : null;
    ?>

    <div class="stats">
        <div class="stat">
            <span class="stat-num"><?= count($all_notas) ?></span>
            <div class="stat-label">Disciplinas</div>
        </div>
        <div class="stat">
            <span class="stat-num" style="color:#34d399"><?= count($aprovadas) ?></span>
            <div class="stat-label">Aprovadas</div>
        </div>
        <div class="stat">
            <span class="stat-num" style="color:#f87171"><?= count($com_nota) - count($aprovadas) ?></span>
            <div class="stat-label">Reprovadas</div>
        </div>
        <?php if ($media_geral !== null): ?>
        <div class="stat">
            <span class="stat-num" style="color:#4f8ef7"><?= number_format($media_geral, 1) ?></span>
            <div class="stat-label">Média Geral</div>
        </div>
        <?php endif; ?>
    </div>

    <div class="box">
        <?php foreach ($notas as $grupo_label => $grupo_rows):
            // Extrair época do label para o badge
            preg_match('/Época (\w+)/', $grupo_label, $m);
            $epoca_nome = $m[1] ?? '';
            $ano_letivo = explode(' —', $grupo_label)[0];
        ?>
        <div class="grupo-header">
            <span>📅 <?= htmlspecialchars($ano_letivo) ?></span>
            <?php if ($epoca_nome): ?>
            <span class="epoca-badge epoca-<?= $epoca_nome ?>"><?= $epoca_nome ?></span>
            <?php endif; ?>
        </div>
        <table>
            <tr><th>Código</th><th>Disciplina</th><th>ECTS</th><th>Nota</th><th>Resultado</th><th>Data</th></tr>
            <?php foreach ($grupo_rows as $row):
                $nota = $row['nota'];
                $nc = $nota === null ? 'nota-vazia' : ($nota >= 10 ? 'nota-pos' : 'nota-neg');
                $resultado = $nota === null ? ['txt'=>'Pendente','cls'=>'pendente'] : ($nota >= 10 ? ['txt'=>'Aprovado','cls'=>'aprovado'] : ['txt'=>'Reprovado','cls'=>'reprovado']);
            ?>
            <tr>
                <td><?= $row['codigo'] ? '<span class="codigo-badge">'.htmlspecialchars($row['codigo']).'</span>' : '<span style="color:var(--muted)">—</span>' ?></td>
                <td style="font-weight:500"><?= htmlspecialchars($row['nome_disc']) ?></td>
                <td><?= $row['ects'] ? '<span class="ects-badge">'.$row['ects'].'</span>' : '<span style="color:var(--muted)">—</span>' ?></td>
                <td><span class="nota-badge <?= $nc ?>"><?= $nota !== null ? number_format($nota, 1) : '—' ?></span></td>
                <td><span class="resultado <?= $resultado['cls'] ?>"><?= $resultado['txt'] ?></span></td>
                <td style="font-size:12px;color:var(--muted)"><?= $row['data_registo'] ? date('d/m/Y', strtotime($row['data_registo'])) : '—' ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</main>
</body>
</html>
