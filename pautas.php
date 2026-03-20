<?php
require_once 'auth.php';
require_once 'config.php';

$isAdmin       = ($_SESSION['grupo'] == 1);
$isAluno       = ($_SESSION['grupo'] == 2);
$isFuncionario = ($_SESSION['grupo'] == 3);
$isGestor      = ($_SESSION['grupo'] == 4);
$podeGerir     = ($isAdmin || $isFuncionario);

if (!$podeGerir) { header("Location: index.php"); exit; }

$login = $_SESSION['login'];
$msg = ''; $msg_type = '';

// Criar pauta
if (isset($_POST['criar_pauta'])) {
    $curso_id   = intval($_POST['curso_id']);
    $disc_id    = intval($_POST['disc_id']);
    $ano_letivo = trim($_POST['ano_letivo'] ?? '');
    $epoca      = trim($_POST['epoca'] ?? '');

    if (!$curso_id || !$disc_id || !$ano_letivo || !$epoca) {
        $msg = "⚠️ Preenche todos os campos."; $msg_type = 'err';
    } else {
        $check = $pdo->prepare("SELECT ID FROM pautas_header WHERE curso=? AND disciplina=? AND ano_letivo=? AND epoca=?");
        $check->execute([$curso_id, $disc_id, $ano_letivo, $epoca]);
        if ($check->fetch()) {
            $msg = "⚠️ Já existe uma pauta para esta combinação!"; $msg_type = 'err';
        } else {
            $stmt = $pdo->prepare("INSERT INTO pautas_header (curso, disciplina, ano_letivo, epoca, criado_por) VALUES (?,?,?,?,?)");
            $stmt->execute([$curso_id, $disc_id, $ano_letivo, $epoca, $login]);
            $pauta_id = $pdo->lastInsertId();

            $alunos = $pdo->prepare("SELECT login FROM matriculas WHERE curso=? AND estado='aprovada'");
            $alunos->execute([$curso_id]);
            $lista = $alunos->fetchAll();

            foreach ($lista as $a) {
                $ins = $pdo->prepare("INSERT INTO pautas (pauta_id, curso, disciplina, login_aluno, ano_letivo, epoca) VALUES (?,?,?,?,?,?)");
                $ins->execute([$pauta_id, $curso_id, $disc_id, $a['login'], $ano_letivo, $epoca]);
            }
            $msg = "✅ Pauta criada com ".count($lista)." aluno(s)!"; $msg_type = 'ok';
        }
    }
}

// Guardar nota
if (isset($_POST['guardar_nota'])) {
    $pauta_linha_id = intval($_POST['pauta_linha_id']);
    $nota = trim($_POST['nota'] ?? '');
    $nota_val = ($nota === '') ? null : floatval(str_replace(',', '.', $nota));

    if ($nota_val !== null && ($nota_val < 0 || $nota_val > 20)) {
        $msg = "⚠️ A nota tem de ser entre 0 e 20."; $msg_type = 'err';
    } else {
        $stmt = $pdo->prepare("UPDATE pautas SET nota=?, data_registo=NOW() WHERE ID=?");
        $stmt->execute([$nota_val, $pauta_linha_id]);
        $msg = "✅ Nota guardada!"; $msg_type = 'ok';
    }
}

$cursos      = $pdo->query("SELECT * FROM cursos WHERE ativo=1 ORDER BY Nome")->fetchAll();
$disciplinas = $pdo->query("SELECT * FROM disciplinas WHERE ativo=1 ORDER BY Nome_disc")->fetchAll();
$filtro_pauta_id = intval($_GET['pauta'] ?? 0);

$pautas_list = $pdo->query("
    SELECT ph.*, c.Nome AS nome_curso, d.Nome_disc AS nome_disc
    FROM pautas_header ph
    JOIN cursos c ON ph.curso=c.ID
    JOIN disciplinas d ON ph.disciplina=d.ID
    ORDER BY ph.data_criacao DESC
")->fetchAll();

$pauta_info = null;
$pauta_linhas = [];
if ($filtro_pauta_id) {
    $stmt = $pdo->prepare("SELECT ph.*, c.Nome AS nome_curso, d.Nome_disc AS nome_disc FROM pautas_header ph JOIN cursos c ON ph.curso=c.ID JOIN disciplinas d ON ph.disciplina=d.ID WHERE ph.ID=?");
    $stmt->execute([$filtro_pauta_id]);
    $pauta_info = $stmt->fetch();
    if ($pauta_info) {
        $stmt2 = $pdo->prepare("SELECT p.*, f.nome_completo FROM pautas p LEFT JOIN ficha_aluno f ON p.login_aluno=f.login WHERE p.pauta_id=? ORDER BY f.nome_completo, p.login_aluno");
        $stmt2->execute([$filtro_pauta_id]);
        $pauta_linhas = $stmt2->fetchAll();
    }
}

$epocas = ['Normal','Recurso','Especial'];
$anos_letivos = ['2024/2025','2025/2026','2026/2027'];
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8"><title>IPCA — Pautas</title>
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
        main{position:relative;z-index:1;max-width:1000px;margin:0 auto;padding:50px 40px}
        .page-header{margin-bottom:36px;animation:fadeUp 0.4s ease both}
        .page-tag{display:inline-flex;align-items:center;gap:6px;background:rgba(79,142,247,0.1);border:1px solid rgba(79,142,247,0.2);color:var(--accent);font-size:11px;font-weight:600;letter-spacing:1px;text-transform:uppercase;padding:4px 12px;border-radius:20px;margin-bottom:14px}
        .page-header h1{font-family:'Syne',sans-serif;font-size:32px;font-weight:800;letter-spacing:-1px;margin-bottom:6px}
        .page-header p{color:var(--muted);font-size:14px}
        .layout{display:grid;grid-template-columns:340px 1fr;gap:24px;align-items:start}
        .box{background:var(--surface);border:1px solid var(--border);border-radius:20px;padding:28px;margin-bottom:24px;animation:fadeUp 0.4s 0.1s ease both}
        .box-title{font-family:'Syne',sans-serif;font-size:13px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:20px}
        .form-group{display:flex;flex-direction:column;gap:6px;margin-bottom:14px}
        .form-group label{font-size:12px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:0.5px}
        .form-group select,.form-group input{padding:10px 14px;background:var(--surface2);border:1px solid var(--border);border-radius:10px;color:var(--text);font-size:14px;font-family:'DM Sans',sans-serif;width:100%}
        .form-group select option{background:var(--surface2)}
        .btn{padding:11px 20px;border:none;border-radius:10px;font-size:14px;font-weight:600;font-family:'DM Sans',sans-serif;cursor:pointer;width:100%}
        .btn-primary{background:linear-gradient(135deg,var(--accent),#3b6fd4);color:white}
        .pauta-item{background:var(--surface2);border:1px solid var(--border);border-radius:12px;padding:16px 18px;margin-bottom:10px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;cursor:pointer;transition:border-color 0.2s;text-decoration:none;color:var(--text)}
        .pauta-item:hover{border-color:rgba(79,142,247,0.4)}
        .pauta-item.ativa{border-color:var(--accent);background:rgba(79,142,247,0.05)}
        .pauta-nome{font-family:'Syne',sans-serif;font-size:14px;font-weight:700;margin-bottom:3px}
        .pauta-sub{font-size:12px;color:var(--muted)}
        .epoca-badge{font-size:11px;font-weight:700;padding:3px 10px;border-radius:20px;text-transform:uppercase;letter-spacing:0.5px}
        .epoca-Normal{background:rgba(79,142,247,0.15);color:#4f8ef7;border:1px solid rgba(79,142,247,0.3)}
        .epoca-Recurso{background:rgba(245,158,11,0.15);color:#fbbf24;border:1px solid rgba(245,158,11,0.3)}
        .epoca-Especial{background:rgba(124,58,237,0.15);color:#a78bfa;border:1px solid rgba(124,58,237,0.3)}
        .pauta-header-info{display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:20px;padding-bottom:16px;border-bottom:1px solid var(--border)}
        .pauta-header-info h2{font-family:'Syne',sans-serif;font-size:18px;font-weight:800;letter-spacing:-0.5px;flex:1}
        .pauta-stats{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:16px}
        .stat-pill{background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:8px 14px;font-size:13px;color:var(--muted)}
        .stat-pill strong{color:var(--text);font-family:'Syne',sans-serif;font-size:16px;display:block}
        table{width:100%;border-collapse:collapse}
        th{padding:10px 14px;text-align:left;font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:1px;border-bottom:1px solid var(--border)}
        td{padding:12px 14px;font-size:14px;border-bottom:1px solid var(--border);vertical-align:middle}
        tr:last-child td{border-bottom:none}
        tr:hover td{background:rgba(255,255,255,0.02)}
        .nota-input{width:80px;padding:7px 10px;background:var(--bg);border:1px solid var(--border);border-radius:7px;color:var(--text);font-size:14px;font-family:'DM Sans',sans-serif;text-align:center}
        .nota-badge{display:inline-flex;align-items:center;justify-content:center;min-width:52px;height:28px;border-radius:7px;font-size:13px;font-weight:700;padding:0 8px}
        .nota-pos{background:rgba(16,185,129,0.15);color:#34d399}
        .nota-neg{background:rgba(239,68,68,0.15);color:#f87171}
        .nota-vazia{background:var(--surface2);color:var(--muted)}
        .btn-save-nota{background:rgba(79,142,247,0.15);color:var(--accent);border:1px solid rgba(79,142,247,0.3);padding:6px 12px;border-radius:7px;font-size:12px;font-weight:600;font-family:'DM Sans',sans-serif;cursor:pointer}
        .msg{padding:14px 18px;border-radius:10px;margin-bottom:20px;font-size:14px}
        .msg-ok{background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.2);color:#34d399}
        .msg-err{background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.2);color:#f87171}
        .empty{color:var(--muted);text-align:center;padding:24px 0;font-size:14px}
        .back-link{display:inline-flex;align-items:center;gap:6px;color:var(--muted);text-decoration:none;font-size:13px;margin-bottom:16px;transition:color 0.2s}
        .back-link:hover{color:var(--text)}
        @keyframes fadeUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
        @media(max-width:800px){.layout{grid-template-columns:1fr}nav{padding:14px 20px}main{padding:30px 20px}.nav-links{display:none}}
    </style>
</head>
<body>
<div class="orb orb-1"></div><div class="orb orb-2"></div>
<?php include 'navbar.php'; ?>
<main>
    <div class="page-header">
        <div class="page-tag">📊 Avaliação</div>
        <h1>Pautas de Avaliação</h1>
        <p>Cria pautas por UC, ano letivo e época. Regista e edita notas dos alunos.</p>
    </div>
    <?php if ($msg): ?><div class="msg msg-<?= $msg_type ?>"><?= $msg ?></div><?php endif; ?>

    <?php if ($filtro_pauta_id && $pauta_info): ?>
    <a href="pautas.php" class="back-link">← Voltar às pautas</a>
    <div class="box">
        <div class="pauta-header-info">
            <div>
                <h2><?= htmlspecialchars($pauta_info['nome_disc']) ?></h2>
                <p style="font-size:13px;color:var(--muted);margin-top:4px"><?= htmlspecialchars($pauta_info['nome_curso']) ?> · <?= htmlspecialchars($pauta_info['ano_letivo']) ?></p>
            </div>
            <span class="epoca-badge epoca-<?= $pauta_info['epoca'] ?>"><?= htmlspecialchars($pauta_info['epoca']) ?></span>
        </div>
        <?php if (!empty($pauta_linhas)):
            $com_nota   = array_filter($pauta_linhas, fn($r) => $r['nota'] !== null);
            $aprovados  = array_filter($com_nota, fn($r) => $r['nota'] >= 10);
            $reprovados = array_filter($com_nota, fn($r) => $r['nota'] < 10);
            $media      = count($com_nota) > 0 ? array_sum(array_column($com_nota,'nota'))/count($com_nota) : null;
        ?>
        <div class="pauta-stats">
            <div class="stat-pill"><strong><?= count($pauta_linhas) ?></strong>Alunos</div>
            <div class="stat-pill"><strong><?= count($com_nota) ?></strong>Com nota</div>
            <div class="stat-pill" style="border-color:rgba(16,185,129,0.3)"><strong style="color:#34d399"><?= count($aprovados) ?></strong>Aprovados</div>
            <div class="stat-pill" style="border-color:rgba(239,68,68,0.3)"><strong style="color:#f87171"><?= count($reprovados) ?></strong>Reprovados</div>
            <?php if ($media !== null): ?><div class="stat-pill" style="border-color:rgba(79,142,247,0.3)"><strong style="color:#4f8ef7"><?= number_format($media,1) ?></strong>Média</div><?php endif; ?>
        </div>
        <table>
            <tr><th>#</th><th>Aluno</th><th>Nome</th><th>Nota (0-20)</th><th>Registado</th><th>Ação</th></tr>
            <?php foreach ($pauta_linhas as $i => $row):
                $nota = $row['nota'];
                $nc = $nota===null ? 'nota-vazia' : ($nota>=10 ? 'nota-pos' : 'nota-neg');
            ?>
            <tr>
                <td style="color:var(--muted)"><?= $i+1 ?></td>
                <td style="font-family:'Syne',sans-serif;font-weight:600"><?= htmlspecialchars($row['login_aluno']) ?></td>
                <td style="color:var(--muted)"><?= htmlspecialchars($row['nome_completo']??'—') ?></td>
                <td><span class="nota-badge <?= $nc ?>"><?= $nota===null ? '—' : number_format($nota,1) ?></span></td>
                <td style="font-size:12px;color:var(--muted)"><?= $row['data_registo'] ? date('d/m/Y H:i',strtotime($row['data_registo'])) : '—' ?></td>
                <td>
                    <form method="POST" style="display:flex;gap:8px;align-items:center">
                        <input type="hidden" name="pauta_linha_id" value="<?= $row['ID'] ?>">
                        <input type="number" name="nota" class="nota-input" min="0" max="20" step="0.1"
                               placeholder="0-20" value="<?= $nota!==null ? number_format($nota,1) : '' ?>">
                        <button type="submit" name="guardar_nota" class="btn-save-nota">💾</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php else: ?><p class="empty">Nenhum aluno elegível nesta pauta.</p><?php endif; ?>
    </div>

    <?php else: ?>
    <div class="layout">
        <div>
            <div class="box">
                <div class="box-title">Criar Nova Pauta</div>
                <form method="POST" novalidate>
                    <div class="form-group"><label>Curso</label>
                        <select name="curso_id" required><option value="">-- Seleciona --</option>
                        <?php foreach ($cursos as $c): ?><option value="<?= $c['ID'] ?>"><?= htmlspecialchars($c['Nome']) ?></option><?php endforeach; ?>
                        </select></div>
                    <div class="form-group"><label>Disciplina (UC)</label>
                        <select name="disc_id" required><option value="">-- Seleciona --</option>
                        <?php foreach ($disciplinas as $d): ?><option value="<?= $d['ID'] ?>"><?= htmlspecialchars($d['Nome_disc']) ?></option><?php endforeach; ?>
                        </select></div>
                    <div class="form-group"><label>Ano Letivo</label>
                        <select name="ano_letivo" required>
                        <?php foreach ($anos_letivos as $al): ?><option value="<?= $al ?>" <?= $al=='2025/2026'?'selected':'' ?>><?= $al ?></option><?php endforeach; ?>
                        </select></div>
                    <div class="form-group"><label>Época</label>
                        <select name="epoca" required>
                        <?php foreach ($epocas as $ep): ?><option value="<?= $ep ?>"><?= $ep ?></option><?php endforeach; ?>
                        </select></div>
                    <button type="submit" name="criar_pauta" class="btn btn-primary">📋 Criar Pauta</button>
                </form>
            </div>
        </div>
        <div>
            <div class="box">
                <div class="box-title">Pautas Criadas (<?= count($pautas_list) ?>)</div>
                <?php if (empty($pautas_list)): ?>
                    <p class="empty">Ainda não foram criadas pautas.</p>
                <?php else: ?>
                <?php foreach ($pautas_list as $p): ?>
                <a href="pautas.php?pauta=<?= $p['ID'] ?>" class="pauta-item <?= $filtro_pauta_id==$p['ID']?'ativa':'' ?>">
                    <div>
                        <div class="pauta-nome"><?= htmlspecialchars($p['nome_disc']) ?></div>
                        <div class="pauta-sub"><?= htmlspecialchars($p['nome_curso']) ?> · <?= htmlspecialchars($p['ano_letivo']) ?></div>
                        <div class="pauta-sub" style="margin-top:2px">Criada por <?= htmlspecialchars($p['criado_por']) ?> em <?= date('d/m/Y',strtotime($p['data_criacao'])) ?></div>
                    </div>
                    <span class="epoca-badge epoca-<?= $p['epoca'] ?>"><?= htmlspecialchars($p['epoca']) ?></span>
                </a>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</main>
</body>
</html>
