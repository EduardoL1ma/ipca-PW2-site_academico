<?php
require_once 'auth.php';
require_once 'config.php';

$isAdmin   = ($_SESSION['grupo'] == 1);
$isGestor  = ($_SESSION['grupo'] == 4);
$isAluno   = ($_SESSION['grupo'] == 2);
$isFuncionario = ($_SESSION['grupo'] == 3);
$podeGerir = ($isAdmin || $isGestor);

$msg = ''; $msg_type = '';

if ($podeGerir && isset($_GET['del'])) {
    $stmt = $pdo->prepare("UPDATE cursos SET ativo=0 WHERE ID=?");
    $stmt->execute([intval($_GET['del'])]);
    $msg = "✅ Curso desativado!"; $msg_type = 'ok';
}
if ($podeGerir && isset($_GET['reativar'])) {
    $stmt = $pdo->prepare("UPDATE cursos SET ativo=1 WHERE ID=?");
    $stmt->execute([intval($_GET['reativar'])]);
    $msg = "✅ Curso reativado!"; $msg_type = 'ok';
}
if ($podeGerir && isset($_POST['add'])) {
    $nome = trim($_POST['nome'] ?? '');
    if (strlen($nome) < 3) { $msg = "⚠️ O nome deve ter pelo menos 3 caracteres."; $msg_type = 'err'; }
    else {
        $stmt = $pdo->prepare("INSERT INTO cursos (Nome) VALUES (?)");
        $stmt->execute([$nome]);
        $msg = "✅ Curso adicionado!"; $msg_type = 'ok';
    }
}
if ($podeGerir && isset($_POST['edit'])) {
    $id = intval($_POST['id']); $nome = trim($_POST['nome'] ?? '');
    if (strlen($nome) < 3) { $msg = "⚠️ O nome deve ter pelo menos 3 caracteres."; $msg_type = 'err'; }
    else {
        $stmt = $pdo->prepare("UPDATE cursos SET Nome=? WHERE ID=?");
        $stmt->execute([$nome, $id]);
        $msg = "✅ Curso atualizado!"; $msg_type = 'ok';
    }
}

$cursos_ativos   = $pdo->query("SELECT * FROM cursos WHERE ativo=1 ORDER BY Nome")->fetchAll();
$cursos_inativos = $pdo->query("SELECT * FROM cursos WHERE ativo=0 ORDER BY Nome")->fetchAll();
$curso_edit = null;
if ($podeGerir && isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM cursos WHERE ID=?");
    $stmt->execute([intval($_GET['edit'])]);
    $curso_edit = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8"><title>IPCA — Cursos</title>
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
        .form-group input{padding:11px 14px;background:var(--surface2);border:1px solid var(--border);border-radius:10px;color:var(--text);font-size:14px;font-family:'DM Sans',sans-serif}
        .form-group input:focus{outline:none;border-color:rgba(79,142,247,0.5)}
        .btn{padding:11px 20px;border:none;border-radius:10px;font-size:14px;font-weight:600;font-family:'DM Sans',sans-serif;cursor:pointer;white-space:nowrap}
        .btn-primary{background:linear-gradient(135deg,var(--accent),#3b6fd4);color:white}
        .btn-save{background:linear-gradient(135deg,#10b981,#059669);color:white;font-size:12px;padding:7px 14px}
        .btn-edit{background:rgba(245,158,11,0.15);color:#fbbf24;border:1px solid rgba(245,158,11,0.3);font-size:12px;padding:7px 14px;text-decoration:none;border-radius:8px}
        .btn-del{background:rgba(239,68,68,0.15);color:#f87171;border:1px solid rgba(239,68,68,0.3);font-size:12px;padding:7px 14px;text-decoration:none;border-radius:8px}
        .btn-reativar{background:rgba(16,185,129,0.15);color:#34d399;border:1px solid rgba(16,185,129,0.3);font-size:12px;padding:7px 14px;text-decoration:none;border-radius:8px}
        table{width:100%;border-collapse:collapse}
        th{padding:10px 16px;text-align:left;font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:1px;border-bottom:1px solid var(--border)}
        td{padding:14px 16px;font-size:14px;border-bottom:1px solid var(--border);vertical-align:middle}
        tr:last-child td{border-bottom:none}
        tr:hover td{background:rgba(255,255,255,0.02)}
        .badge{display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;background:var(--surface2);border:1px solid var(--border);border-radius:8px;font-size:12px;font-weight:700;color:var(--muted)}
        .actions{display:flex;gap:8px;align-items:center}
        .status-ativo{color:#34d399;font-size:12px;font-weight:600}
        .status-inativo{color:#6b7a99;font-size:12px;font-weight:600}
        .msg{padding:14px 18px;border-radius:10px;margin-bottom:20px;font-size:14px}
        .msg-ok{background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.2);color:#34d399}
        .msg-err{background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.2);color:#f87171}
        .divider{border:none;border-top:1px solid var(--border);margin:24px 0}
        .inativo-row td{opacity:0.5}
        @keyframes fadeUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
        @media(max-width:700px){nav{padding:14px 20px}main{padding:30px 20px}.nav-links{display:none}}
    </style>
</head>
<body>
<div class="orb orb-1"></div><div class="orb orb-2"></div>
<?php include 'navbar.php'; ?>
<main>
    <div class="page-header">
        <div class="page-tag">📚 Gestão</div>
        <h1>Cursos</h1>
        <p><?= $podeGerir ? 'Cria, edita e gere os cursos. A desativação mantém o histórico.' : 'Consulta os cursos disponíveis.' ?></p>
    </div>
    <?php if ($msg): ?><div class="msg msg-<?= $msg_type ?>"><?= $msg ?></div><?php endif; ?>
    <?php if ($podeGerir): ?>
    <div class="box">
        <div class="box-title">Adicionar Curso</div>
        <form method="POST" novalidate>
            <div class="form-row">
                <div class="form-group">
                    <label>Nome do Curso *</label>
                    <input type="text" name="nome" required minlength="3" maxlength="200" placeholder="Ex: Engenharia Informática">
                </div>
                <button type="submit" name="add" class="btn btn-primary">➕ Adicionar</button>
            </div>
        </form>
    </div>
    <?php endif; ?>
    <div class="box">
        <div class="box-title">Cursos Ativos (<?= count($cursos_ativos) ?>)</div>
        <?php if (empty($cursos_ativos)): ?>
            <p style="color:var(--muted);text-align:center;padding:20px 0">Nenhum curso ativo.</p>
        <?php else: ?>
        <table>
            <tr><th>ID</th><th>Nome</th><th>Estado</th><?php if ($podeGerir): ?><th>Ações</th><?php endif; ?></tr>
            <?php foreach ($cursos_ativos as $row): ?>
            <tr>
                <td><span class="badge"><?= $row['ID'] ?></span></td>
                <td><?= htmlspecialchars($row['Nome']) ?></td>
                <td><span class="status-ativo">● Ativo</span></td>
                <?php if ($podeGerir): ?>
                <td><div class="actions">
                    <a href="?edit=<?= $row['ID'] ?>" class="btn-edit">✏️ Editar</a>
                    <a href="?del=<?= $row['ID'] ?>" class="btn-del" onclick="return confirm('Desativar curso?')">⏸ Desativar</a>
                </div></td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php if ($curso_edit): ?>
        <hr class="divider">
        <p style="font-family:'Syne',sans-serif;font-size:15px;font-weight:700;margin-bottom:16px">✏️ Editar Curso</p>
        <form method="POST" novalidate>
            <input type="hidden" name="id" value="<?= $curso_edit['ID'] ?>">
            <div class="form-row">
                <div class="form-group">
                    <label>Nome</label>
                    <input type="text" name="nome" value="<?= htmlspecialchars($curso_edit['Nome']) ?>" required minlength="3">
                </div>
                <button type="submit" name="edit" class="btn btn-save">💾 Guardar</button>
                <a href="cursos.php" style="color:var(--muted);font-size:13px;padding:11px 0">Cancelar</a>
            </div>
        </form>
        <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php if ($podeGerir && !empty($cursos_inativos)): ?>
    <div class="box">
        <div class="box-title">Cursos Inativos (<?= count($cursos_inativos) ?>)</div>
        <table>
            <tr><th>ID</th><th>Nome</th><th>Estado</th><th>Ações</th></tr>
            <?php foreach ($cursos_inativos as $row): ?>
            <tr class="inativo-row">
                <td><span class="badge"><?= $row['ID'] ?></span></td>
                <td><?= htmlspecialchars($row['Nome']) ?></td>
                <td><span class="status-inativo">● Inativo</span></td>
                <td><a href="?reativar=<?= $row['ID'] ?>" class="btn-reativar">▶ Reativar</a></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php endif; ?>
</main>
</body>
</html>
