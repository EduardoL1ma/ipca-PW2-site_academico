<?php
require_once 'auth.php';
require_once 'config.php';

$isAdmin       = ($_SESSION['grupo'] == 1);
$isAluno       = ($_SESSION['grupo'] == 2);
$isFuncionario = ($_SESSION['grupo'] == 3);
$isGestor      = ($_SESSION['grupo'] == 4);
$podeGerir     = ($isAdmin || $isGestor);

if (!$isAdmin && !$isFuncionario) { header("Location: index.php"); exit; }

$login = $_SESSION['login'];
$msg = ''; $msg_type = '';

if (isset($_GET['del'])) {
    $stmt = $pdo->prepare("DELETE FROM matriculas WHERE ID=?");
    $stmt->execute([intval($_GET['del'])]);
    $msg = "✅ Matrícula removida!"; $msg_type = 'ok';
}

if (isset($_POST['add'])) {
    $aluno  = trim($_POST['login'] ?? '');
    $curso  = intval($_POST['curso'] ?? 0);
    if (empty($aluno) || !$curso) { $msg = "⚠️ Preenche todos os campos."; $msg_type = 'err'; }
    else {
        $check = $pdo->prepare("SELECT ID FROM matriculas WHERE login=? AND curso=?");
        $check->execute([$aluno, $curso]);
        if ($check->fetch()) { $msg = "⚠️ Esta matrícula já existe."; $msg_type = 'err'; }
        else {
            $stmt = $pdo->prepare("INSERT INTO matriculas (login, curso, estado, data_pedido) VALUES (?,?,'aprovada',NOW())");
            $stmt->execute([$aluno, $curso]);
            $msg = "✅ Matrícula adicionada!"; $msg_type = 'ok';
        }
    }
}

$alunos     = $pdo->query("SELECT login FROM users WHERE grupo=2 ORDER BY login")->fetchAll();
$cursos     = $pdo->query("SELECT * FROM cursos WHERE ativo=1 ORDER BY Nome")->fetchAll();
$matriculas = $pdo->query("
    SELECT m.*, c.Nome AS nome_curso, f.nome_completo
    FROM matriculas m
    JOIN cursos c ON m.curso=c.ID
    LEFT JOIN ficha_aluno f ON m.login=f.login
    ORDER BY m.login, c.Nome
")->fetchAll();

$estado_cores = [
    'pendente'  => ['cor'=>'#f59e0b','label'=>'Pendente'],
    'aprovada'  => ['cor'=>'#10b981','label'=>'Aprovada'],
    'rejeitada' => ['cor'=>'#ef4444','label'=>'Rejeitada'],
];
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8"><title>IPCA — Matrículas</title>
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
        .btn-del{background:rgba(239,68,68,0.15);color:#f87171;border:1px solid rgba(239,68,68,0.3);font-size:12px;padding:6px 12px;text-decoration:none;border-radius:7px}
        table{width:100%;border-collapse:collapse}
        th{padding:10px 16px;text-align:left;font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:1px;border-bottom:1px solid var(--border)}
        td{padding:14px 16px;font-size:14px;border-bottom:1px solid var(--border);vertical-align:middle}
        tr:last-child td{border-bottom:none}
        tr:hover td{background:rgba(255,255,255,0.02)}
        .badge{display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;background:var(--surface2);border:1px solid var(--border);border-radius:8px;font-size:12px;font-weight:700;color:var(--muted)}
        .estado-badge{display:inline-flex;align-items:center;gap:6px;padding:5px 12px;border-radius:20px;font-size:12px;font-weight:700;text-transform:uppercase}
        .msg{padding:14px 18px;border-radius:10px;margin-bottom:20px;font-size:14px}
        .msg-ok{background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.2);color:#34d399}
        .msg-err{background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.2);color:#f87171}
        @keyframes fadeUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
        @media(max-width:700px){nav{padding:14px 20px}main{padding:30px 20px}.nav-links{display:none}.form-row{flex-direction:column}}
    </style>
</head>
<body>
<div class="orb orb-1"></div><div class="orb orb-2"></div>
<?php include 'navbar.php'; ?>
<main>
    <div class="page-header">
        <div class="page-tag">🎓 Gestão</div>
        <h1>Matrículas</h1>
        <p>Gere as matrículas diretas dos alunos nos cursos.</p>
    </div>
    <?php if ($msg): ?><div class="msg msg-<?= $msg_type ?>"><?= $msg ?></div><?php endif; ?>
    <div class="box">
        <div class="box-title">Nova Matrícula</div>
        <form method="POST" novalidate>
            <div class="form-row">
                <div class="form-group"><label>Aluno</label>
                    <select name="login" required><option value="">-- Seleciona --</option>
                    <?php foreach ($alunos as $a): ?><option value="<?= htmlspecialchars($a['login']) ?>"><?= htmlspecialchars($a['login']) ?></option><?php endforeach; ?>
                    </select></div>
                <div class="form-group"><label>Curso</label>
                    <select name="curso" required><option value="">-- Seleciona --</option>
                    <?php foreach ($cursos as $c): ?><option value="<?= $c['ID'] ?>"><?= htmlspecialchars($c['Nome']) ?></option><?php endforeach; ?>
                    </select></div>
                <button type="submit" name="add" class="btn btn-primary">➕ Matricular</button>
            </div>
        </form>
    </div>
    <div class="box">
        <div class="box-title">Todas as Matrículas (<?= count($matriculas) ?>)</div>
        <?php if (empty($matriculas)): ?>
            <p style="color:var(--muted);text-align:center;padding:20px 0">Nenhuma matrícula registada.</p>
        <?php else: ?>
        <table>
            <tr><th>ID</th><th>Aluno</th><th>Nome</th><th>Curso</th><th>Estado</th><th>Ações</th></tr>
            <?php foreach ($matriculas as $row):
                $ec = $estado_cores[$row['estado']] ?? ['cor'=>'#6b7a99','label'=>$row['estado']];
            ?>
            <tr>
                <td><span class="badge"><?= $row['ID'] ?></span></td>
                <td><?= htmlspecialchars($row['login']) ?></td>
                <td style="color:var(--muted)"><?= htmlspecialchars($row['nome_completo'] ?? '—') ?></td>
                <td><?= htmlspecialchars($row['nome_curso']) ?></td>
                <td><span class="estado-badge" style="background:<?= $ec['cor'] ?>22;color:<?= $ec['cor'] ?>;border:1px solid <?= $ec['cor'] ?>44">● <?= $ec['label'] ?></span></td>
                <td><a href="?del=<?= $row['ID'] ?>" class="btn-del" onclick="return confirm('Remover matrícula?')">🗑️</a></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php endif; ?>
    </div>
</main>
</body>
</html>
