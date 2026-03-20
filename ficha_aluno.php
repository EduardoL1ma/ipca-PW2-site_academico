<?php
require_once 'auth.php';
require_once 'config.php';
require_once 'validacao.php';

$isAdmin       = ($_SESSION['grupo'] == 1);
$isAluno       = ($_SESSION['grupo'] == 2);
$isFuncionario = ($_SESSION['grupo'] == 3);
$isGestor      = ($_SESSION['grupo'] == 4);
$podeGerir     = ($isAdmin || $isGestor);

if (!$isAluno && !$podeGerir) { header("Location: index.php"); exit; }

$login    = $_SESSION['login'];
$msg      = ''; $msg_type = '';
$erros    = [];
$fotosDir = __DIR__ . DIRECTORY_SEPARATOR . "FOTOS";

// --- GUARDAR FICHA (Aluno) ---
if ($isAluno && isset($_POST['guardar'])) {
    $nome       = trim($_POST['nome_completo'] ?? '');
    $nasc       = trim($_POST['data_nascimento'] ?? '');
    $morada     = trim($_POST['morada'] ?? '');
    $tel        = trim($_POST['telefone'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $nif        = trim($_POST['nif'] ?? '');
    $curso_pret = intval($_POST['curso_pretendido'] ?? 0);

    // Validação servidor
    if (empty($nome) || strlen($nome) < 3)       $erros[] = "Nome completo obrigatório (mín. 3 caracteres).";
    if (!validarData($nasc))                      $erros[] = "Data de nascimento inválida.";
    if (empty($morada))                           $erros[] = "Morada obrigatória.";
    if (!validarTelefone($tel))                   $erros[] = "Telefone inválido (9-15 dígitos).";
    if (!validarEmail($email))                    $erros[] = "Email inválido.";
    if (!validarNIF($nif))                        $erros[] = "NIF inválido (deve ter 9 dígitos).";
    if ($curso_pret <= 0)                         $erros[] = "Seleciona um curso pretendido.";

    // Validar foto
    $foto_nome = '';
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE) {
        $foto_nome = guardarFoto($_FILES['foto'], $fotosDir, $erros);
        if ($foto_nome === null) $foto_nome = '';
    }

    if (empty($erros)) {
        $check = $pdo->prepare("SELECT login, foto FROM ficha_aluno WHERE login = ?");
        $check->execute([$login]);
        $existente = $check->fetch();

        if ($existente) {
            $foto_final = ($foto_nome !== '') ? $foto_nome : $existente['foto'];
            $stmt = $pdo->prepare("UPDATE ficha_aluno SET nome_completo=?, data_nascimento=?, morada=?, telefone=?, email=?, nif=?, curso_pretendido=?, foto=?, estado='rascunho' WHERE login=?");
            $stmt->execute([$nome, $nasc, $morada, $tel, $email, $nif, $curso_pret, $foto_final, $login]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO ficha_aluno (login, nome_completo, data_nascimento, morada, telefone, email, nif, curso_pretendido, foto, estado) VALUES (?,?,?,?,?,?,?,?,?,'rascunho')");
            $stmt->execute([$login, $nome, $nasc, $morada, $tel, $email, $nif, $curso_pret, $foto_nome]);
        }
        $msg = "✅ Ficha guardada com sucesso!"; $msg_type = 'ok';
    } else {
        $msg = implode('<br>', $erros); $msg_type = 'err';
    }
}

// --- SUBMETER FICHA (Aluno) ---
if ($isAluno && isset($_POST['submeter'])) {
    // Verificar se ficha está preenchida
    $check = $pdo->prepare("SELECT estado FROM ficha_aluno WHERE login=?");
    $check->execute([$login]);
    $f = $check->fetch();

    if (!$f) {
        $msg = "⚠️ Preenche e guarda a ficha antes de submeter."; $msg_type = 'err';
    } elseif ($f['estado'] !== 'rascunho') {
        $msg = "⚠️ A ficha já foi submetida."; $msg_type = 'err';
    } else {
        $stmt = $pdo->prepare("UPDATE ficha_aluno SET estado='submetida', data_submissao=NOW() WHERE login=?");
        $stmt->execute([$login]);
        $msg = "✅ Ficha submetida! Aguarda validação do Gestor Pedagógico."; $msg_type = 'ok';
    }
}

// --- VALIDAR (Gestor/Admin) ---
if ($podeGerir && isset($_POST['validar'])) {
    $aluno_login = trim($_POST['aluno_login'] ?? '');
    $obs         = trim($_POST['observacoes'] ?? '');
    if (empty($aluno_login)) { $msg = "⚠️ Erro: aluno não identificado."; $msg_type = 'err'; }
    else {
        $stmt = $pdo->prepare("UPDATE ficha_aluno SET estado='validada', observacoes=?, decidido_por=?, data_decisao=NOW() WHERE login=? AND estado='submetida'");
        $stmt->execute([$obs, $login, $aluno_login]);
        $msg = $stmt->rowCount() > 0 ? "✅ Ficha aprovada!" : "⚠️ Ficha já foi processada.";
        $msg_type = $stmt->rowCount() > 0 ? 'ok' : 'err';
    }
}

// --- REJEITAR (Gestor/Admin) ---
if ($podeGerir && isset($_POST['rejeitar'])) {
    $aluno_login = trim($_POST['aluno_login'] ?? '');
    $obs         = trim($_POST['observacoes'] ?? '');
    if (empty($aluno_login)) { $msg = "⚠️ Erro: aluno não identificado."; $msg_type = 'err'; }
    elseif (empty($obs))     { $msg = "⚠️ A rejeição requer justificação obrigatória."; $msg_type = 'err'; }
    else {
        $stmt = $pdo->prepare("UPDATE ficha_aluno SET estado='rejeitada', observacoes=?, decidido_por=?, data_decisao=NOW() WHERE login=? AND estado='submetida'");
        $stmt->execute([$obs, $login, $aluno_login]);
        $msg = $stmt->rowCount() > 0 ? "❌ Ficha rejeitada." : "⚠️ Ficha já foi processada.";
        $msg_type = 'err';
    }
}

$cursos = $pdo->query("SELECT * FROM cursos WHERE ativo=1 ORDER BY Nome")->fetchAll();

if ($isAluno) {
    $stmt = $pdo->prepare("SELECT * FROM ficha_aluno WHERE login=?");
    $stmt->execute([$login]);
    $ficha = $stmt->fetch();
} else {
    $stmt = $pdo->query("SELECT f.*, u.login FROM ficha_aluno f JOIN users u ON f.login=u.login ORDER BY FIELD(f.estado,'submetida','rascunho','rejeitada','validada'), f.data_submissao DESC");
    $fichas = $stmt->fetchAll();
}

$estado_cores = [
    'rascunho'  => ['cor'=>'#6b7a99','label'=>'Rascunho'],
    'submetida' => ['cor'=>'#f59e0b','label'=>'Submetida'],
    'validada'  => ['cor'=>'#10b981','label'=>'Aprovada'],
    'rejeitada' => ['cor'=>'#ef4444','label'=>'Rejeitada'],
];
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>IPCA — Ficha de Aluno</title>
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
        .nav-brand{font-family:'Syne',sans-serif;font-weight:800;font-size:20px;letter-spacing:-0.5px}
        .nav-brand span{color:var(--accent)}
        .nav-links{display:flex;gap:6px}
        .nav-links a{color:var(--muted);text-decoration:none;font-size:14px;font-weight:500;padding:7px 14px;border-radius:8px;transition:all 0.2s}
        .nav-links a:hover,.nav-links a.active{color:var(--text);background:var(--surface2)}
        .nav-right{display:flex;align-items:center;gap:10px}
        .nav-user{display:flex;align-items:center;gap:8px;background:var(--surface2);padding:7px 14px;border-radius:20px;font-size:13px;color:var(--muted);border:1px solid var(--border)}
        .nav-user strong{color:var(--text)}
        .nav-avatar{width:28px;height:28px;background:linear-gradient(135deg,var(--accent),var(--accent2));border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:white}
        .btn-logout{background:rgba(239,68,68,0.1);color:#f87171;border:1px solid rgba(239,68,68,0.2);padding:7px 16px;border-radius:8px;font-size:13px;text-decoration:none;font-weight:500}
        main{position:relative;z-index:1;max-width:860px;margin:0 auto;padding:50px 40px}
        .page-header{margin-bottom:36px;animation:fadeUp 0.4s ease both}
        .page-tag{display:inline-flex;align-items:center;gap:6px;background:rgba(79,142,247,0.1);border:1px solid rgba(79,142,247,0.2);color:var(--accent);font-size:11px;font-weight:600;letter-spacing:1px;text-transform:uppercase;padding:4px 12px;border-radius:20px;margin-bottom:14px}
        .page-header h1{font-family:'Syne',sans-serif;font-size:32px;font-weight:800;letter-spacing:-1px;margin-bottom:6px}
        .page-header p{color:var(--muted);font-size:14px}
        .box{background:var(--surface);border:1px solid var(--border);border-radius:20px;padding:32px;margin-bottom:24px;animation:fadeUp 0.4s 0.1s ease both}
        .box-title{font-family:'Syne',sans-serif;font-size:13px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:24px}
        .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
        .form-group{display:flex;flex-direction:column;gap:6px}
        .form-group.full{grid-column:1/-1}
        .form-group label{font-size:12px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:0.5px}
        .form-group input,.form-group select{padding:11px 14px;background:var(--surface2);border:1px solid var(--border);border-radius:10px;color:var(--text);font-size:14px;font-family:'DM Sans',sans-serif;transition:border-color 0.2s}
        .form-group input:focus,.form-group select:focus{outline:none;border-color:rgba(79,142,247,0.5)}
        .form-group select option{background:var(--surface2)}
        .form-group .hint{font-size:11px;color:var(--muted);margin-top:3px}
        .foto-upload{border:2px dashed var(--border);border-radius:14px;padding:24px;text-align:center;cursor:pointer;transition:border-color 0.2s;position:relative}
        .foto-upload:hover{border-color:rgba(79,142,247,0.4)}
        .foto-upload input{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%}
        .foto-preview{width:100px;height:100px;border-radius:50%;object-fit:cover;border:3px solid var(--accent);margin:0 auto 12px;display:block}
        .foto-placeholder{width:80px;height:80px;border-radius:50%;background:var(--surface2);border:2px dashed var(--border);margin:0 auto 12px;display:flex;align-items:center;justify-content:center;font-size:28px}
        .foto-info{font-size:12px;color:var(--muted);margin-top:6px}
        .btn-row{display:flex;gap:12px;margin-top:24px;flex-wrap:wrap}
        .btn{padding:11px 20px;border:none;border-radius:10px;font-size:14px;font-weight:600;font-family:'DM Sans',sans-serif;cursor:pointer;transition:opacity 0.2s,transform 0.2s}
        .btn:hover{opacity:0.9;transform:translateY(-1px)}
        .btn-primary{background:linear-gradient(135deg,var(--accent),#3b6fd4);color:white}
        .btn-success{background:linear-gradient(135deg,#10b981,#059669);color:white}
        .btn-danger{background:rgba(239,68,68,0.15);color:#f87171;border:1px solid rgba(239,68,68,0.3)}
        .estado-badge{display:inline-flex;align-items:center;gap:6px;padding:5px 12px;border-radius:20px;font-size:12px;font-weight:700;letter-spacing:0.5px;text-transform:uppercase}
        .msg{padding:14px 18px;border-radius:10px;margin-bottom:20px;font-size:14px;line-height:1.6;animation:fadeUp 0.3s ease both}
        .msg-ok{background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.2);color:#34d399}
        .msg-err{background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.2);color:#f87171}
        .ficha-card{background:var(--surface2);border:1px solid var(--border);border-radius:14px;padding:24px;margin-bottom:16px}
        .ficha-top{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:16px}
        .ficha-id{display:flex;align-items:center;gap:12px}
        .ficha-avatar{width:52px;height:52px;border-radius:50%;object-fit:cover;border:2px solid var(--border)}
        .ficha-avatar-placeholder{width:52px;height:52px;border-radius:50%;background:var(--surface);border:2px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:700;color:var(--muted);font-family:'Syne',sans-serif}
        .ficha-login{font-family:'Syne',sans-serif;font-weight:700;font-size:16px}
        .ficha-nome{color:var(--muted);font-size:13px;margin-top:2px}
        .ficha-details{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;margin-bottom:16px}
        .ficha-detail{background:var(--surface);border:1px solid var(--border);border-radius:8px;padding:10px 14px}
        .ficha-detail-label{font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:3px}
        .ficha-detail-value{font-size:13px;color:var(--text)}
        .ficha-actions{border-top:1px solid var(--border);padding-top:16px}
        .obs-textarea{width:100%;padding:10px 14px;background:var(--bg);border:1px solid var(--border);border-radius:10px;color:var(--text);font-size:13px;font-family:'DM Sans',sans-serif;resize:vertical;min-height:70px;margin-bottom:10px}
        .obs-textarea:focus{outline:none;border-color:rgba(79,142,247,0.5)}
        .ficha-btns{display:flex;gap:10px}
        .obs-nota{font-size:11px;color:#f87171;margin-bottom:6px}
        @keyframes fadeUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
        @media(max-width:600px){.form-grid{grid-template-columns:1fr}nav{padding:14px 20px}main{padding:30px 20px}.nav-links{display:none}}
    </style>
</head>
<body>
<div class="orb orb-1"></div>
<div class="orb orb-2"></div>
<?php include 'navbar.php'; ?>
<main>
    <?php if ($isAluno): ?>
    <div class="page-header">
        <div class="page-tag">👤 O Meu Perfil</div>
        <h1>A Minha Ficha</h1>
        <p>Preenche os teus dados, adiciona uma foto e submete para validação.</p>
    </div>

    <?php if ($msg): ?><div class="msg msg-<?= $msg_type ?>"><?= $msg ?></div><?php endif; ?>

    <?php if ($ficha): ?>
    <div style="margin-bottom:20px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
        <?php $ec = $estado_cores[$ficha['estado']] ?? ['cor'=>'#6b7a99','label'=>$ficha['estado']]; ?>
        <span class="estado-badge" style="background:<?= $ec['cor'] ?>22;color:<?= $ec['cor'] ?>;border:1px solid <?= $ec['cor'] ?>44">● <?= $ec['label'] ?></span>
        <?php if ($ficha['data_submissao']): ?>
            <span style="font-size:13px;color:var(--muted)">Submetida em <?= date('d/m/Y H:i', strtotime($ficha['data_submissao'])) ?></span>
        <?php endif; ?>
        <?php if ($ficha['observacoes']): ?>
            <div style="width:100%;background:rgba(245,158,11,0.1);border:1px solid rgba(245,158,11,0.2);border-radius:10px;padding:12px 16px;font-size:13px;color:#fbbf24;margin-top:4px;">
                💬 <strong>Observações do Gestor:</strong> <?= htmlspecialchars($ficha['observacoes']) ?>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php $podeEditar = !$ficha || in_array($ficha['estado'], ['rascunho','rejeitada']); ?>

    <div class="box">
        <div class="box-title">Dados Pessoais e Contacto</div>
        <form method="POST" enctype="multipart/form-data" novalidate>
            <div class="form-grid">
                <div class="form-group full">
                    <label>Fotografia <span style="color:var(--muted);font-weight:400">(JPG, PNG ou WEBP · máx. 2MB)</span></label>
                    <div class="foto-upload">
                        <?php if ($ficha && $ficha['foto'] && file_exists($fotosDir.DIRECTORY_SEPARATOR.$ficha['foto'])): ?>
                            <img src="FOTOS/<?= htmlspecialchars($ficha['foto']) ?>" class="foto-preview" id="fotoPreview">
                        <?php else: ?>
                            <div class="foto-placeholder" id="fotoPlaceholder">👤</div>
                            <img src="" class="foto-preview" id="fotoPreview" style="display:none">
                        <?php endif; ?>
                        <p style="font-size:13px;color:var(--muted)">Clica para <?= ($ficha && $ficha['foto']) ? 'alterar' : 'adicionar' ?> foto</p>
                        <p class="foto-info">Formatos aceites: JPG, PNG, WEBP · Tamanho máximo: 2MB</p>
                        <input type="file" name="foto" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                               id="fotoInput" <?= !$podeEditar ? 'disabled' : '' ?> onchange="previewFoto(this)">
                    </div>
                </div>
                <div class="form-group full">
                    <label>Nome Completo *</label>
                    <input type="text" name="nome_completo" required minlength="3" maxlength="100"
                           placeholder="O teu nome completo"
                           value="<?= htmlspecialchars($ficha['nome_completo'] ?? '') ?>"
                           <?= !$podeEditar ? 'readonly' : '' ?>>
                </div>
                <div class="form-group">
                    <label>Data de Nascimento *</label>
                    <input type="date" name="data_nascimento" required
                           value="<?= htmlspecialchars($ficha['data_nascimento'] ?? '') ?>"
                           <?= !$podeEditar ? 'readonly' : '' ?>>
                </div>
                <div class="form-group">
                    <label>NIF *</label>
                    <input type="text" name="nif" required pattern="\d{9}" maxlength="9" placeholder="123456789"
                           value="<?= htmlspecialchars($ficha['nif'] ?? '') ?>"
                           <?= !$podeEditar ? 'readonly' : '' ?>>
                    <span class="hint">9 dígitos sem espaços</span>
                </div>
                <div class="form-group full">
                    <label>Morada *</label>
                    <input type="text" name="morada" required maxlength="200" placeholder="Rua, nº, código postal, cidade"
                           value="<?= htmlspecialchars($ficha['morada'] ?? '') ?>"
                           <?= !$podeEditar ? 'readonly' : '' ?>>
                </div>
                <div class="form-group">
                    <label>Telefone *</label>
                    <input type="tel" name="telefone" required placeholder="9XXXXXXXX"
                           value="<?= htmlspecialchars($ficha['telefone'] ?? '') ?>"
                           <?= !$podeEditar ? 'readonly' : '' ?>>
                </div>
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" required maxlength="100" placeholder="email@exemplo.pt"
                           value="<?= htmlspecialchars($ficha['email'] ?? '') ?>"
                           <?= !$podeEditar ? 'readonly' : '' ?>>
                </div>
                <div class="form-group full">
                    <label>Curso Pretendido *</label>
                    <select name="curso_pretendido" required <?= !$podeEditar ? 'disabled' : '' ?>>
                        <option value="">-- Seleciona um curso --</option>
                        <?php foreach ($cursos as $c): ?>
                        <option value="<?= $c['ID'] ?>" <?= ($ficha['curso_pretendido'] ?? '') == $c['ID'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['Nome']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <?php if ($podeEditar): ?>
            <div class="btn-row">
                <button type="submit" name="guardar" class="btn btn-primary">💾 Guardar Rascunho</button>
                <?php if ($ficha && $ficha['estado'] === 'rascunho'): ?>
                    <button type="submit" name="submeter" class="btn btn-success">📤 Submeter para Validação</button>
                <?php endif; ?>
            </div>
            <?php else: ?>
                <p style="color:var(--muted);font-size:13px;margin-top:20px;">
                    <?= $ficha['estado']==='submetida' ? '⏳ Aguarda validação do Gestor Pedagógico.' : '✅ Ficha aprovada — não pode ser editada.' ?>
                </p>
            <?php endif; ?>
        </form>
    </div>

    <?php else: ?>
    <!-- GESTOR / ADMIN -->
    <div class="page-header">
        <div class="page-tag">👤 Validação</div>
        <h1>Fichas de Alunos</h1>
        <p>Consulta, aprova ou rejeita as fichas. A rejeição requer justificação obrigatória.</p>
    </div>

    <?php if ($msg): ?><div class="msg msg-<?= $msg_type ?>"><?= $msg ?></div><?php endif; ?>

    <div class="box">
        <div class="box-title">Fichas (<?= count($fichas) ?>)</div>
        <?php if (empty($fichas)): ?>
            <p style="color:var(--muted);text-align:center;padding:20px 0;">Nenhuma ficha submetida ainda.</p>
        <?php else: ?>
        <?php foreach ($fichas as $f):
            $ec = $estado_cores[$f['estado']] ?? ['cor'=>'#6b7a99','label'=>$f['estado']];
            $foto_path = $fotosDir . DIRECTORY_SEPARATOR . ($f['foto'] ?? '');
            $tem_foto  = !empty($f['foto']) && file_exists($foto_path);
            $curso_nome = '—';
            if ($f['curso_pretendido']) {
                $cr = $pdo->prepare("SELECT Nome FROM cursos WHERE ID=?");
                $cr->execute([$f['curso_pretendido']]);
                $cn = $cr->fetch();
                if ($cn) $curso_nome = $cn['Nome'];
            }
        ?>
        <div class="ficha-card">
            <div class="ficha-top">
                <div class="ficha-id">
                    <?php if ($tem_foto): ?>
                        <img src="FOTOS/<?= htmlspecialchars($f['foto']) ?>" class="ficha-avatar">
                    <?php else: ?>
                        <div class="ficha-avatar-placeholder"><?= strtoupper(substr($f['login'],0,1)) ?></div>
                    <?php endif; ?>
                    <div>
                        <div class="ficha-login"><?= htmlspecialchars($f['login']) ?></div>
                        <div class="ficha-nome"><?= htmlspecialchars($f['nome_completo'] ?? '—') ?></div>
                    </div>
                </div>
                <span class="estado-badge" style="background:<?= $ec['cor'] ?>22;color:<?= $ec['cor'] ?>;border:1px solid <?= $ec['cor'] ?>44">● <?= $ec['label'] ?></span>
            </div>
            <div class="ficha-details">
                <div class="ficha-detail"><div class="ficha-detail-label">📅 Nascimento</div><div class="ficha-detail-value"><?= $f['data_nascimento'] ? date('d/m/Y',strtotime($f['data_nascimento'])) : '—' ?></div></div>
                <div class="ficha-detail"><div class="ficha-detail-label">📋 NIF</div><div class="ficha-detail-value"><?= htmlspecialchars($f['nif']??'—') ?></div></div>
                <div class="ficha-detail"><div class="ficha-detail-label">📞 Telefone</div><div class="ficha-detail-value"><?= htmlspecialchars($f['telefone']??'—') ?></div></div>
                <div class="ficha-detail"><div class="ficha-detail-label">✉️ Email</div><div class="ficha-detail-value"><?= htmlspecialchars($f['email']??'—') ?></div></div>
                <div class="ficha-detail"><div class="ficha-detail-label">🏠 Morada</div><div class="ficha-detail-value"><?= htmlspecialchars($f['morada']??'—') ?></div></div>
                <div class="ficha-detail"><div class="ficha-detail-label">🎓 Curso Pretendido</div><div class="ficha-detail-value"><?= htmlspecialchars($curso_nome) ?></div></div>
                <?php if ($f['data_submissao']): ?>
                <div class="ficha-detail"><div class="ficha-detail-label">📤 Submetida em</div><div class="ficha-detail-value"><?= date('d/m/Y H:i',strtotime($f['data_submissao'])) ?></div></div>
                <?php endif; ?>
                <?php if ($f['decidido_por']): ?>
                <div class="ficha-detail"><div class="ficha-detail-label">⚖️ Decisão por</div><div class="ficha-detail-value"><?= htmlspecialchars($f['decidido_por']) ?> em <?= date('d/m/Y H:i',strtotime($f['data_decisao'])) ?></div></div>
                <?php endif; ?>
            </div>
            <?php if ($f['estado']==='submetida'): ?>
            <div class="ficha-actions">
                <form method="POST">
                    <input type="hidden" name="aluno_login" value="<?= htmlspecialchars($f['login']) ?>">
                    <p class="obs-nota">* Justificação obrigatória em caso de rejeição.</p>
                    <textarea name="observacoes" class="obs-textarea" placeholder="Observações / justificação..."><?= htmlspecialchars($f['observacoes']??'') ?></textarea>
                    <div class="ficha-btns">
                        <button type="submit" name="validar" class="btn btn-success">✅ Aprovar</button>
                        <button type="submit" name="rejeitar" class="btn btn-danger">❌ Rejeitar</button>
                    </div>
                </form>
            </div>
            <?php elseif ($f['observacoes']): ?>
            <div style="border-top:1px solid var(--border);padding-top:14px;font-size:13px;color:var(--muted);">
                💬 <strong style="color:var(--text)">Observações:</strong> <?= htmlspecialchars($f['observacoes']) ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</main>
<script>
function previewFoto(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const maxSize = 2 * 1024 * 1024;
        const allowed = ['image/jpeg','image/png','image/webp'];
        if (file.size > maxSize) {
            alert('A foto não pode exceder 2MB!');
            input.value = '';
            return;
        }
        if (!allowed.includes(file.type)) {
            alert('Formato não aceite! Usa JPG, PNG ou WEBP.');
            input.value = '';
            return;
        }
        const reader = new FileReader();
        reader.onload = e => {
            const preview = document.getElementById('fotoPreview');
            const placeholder = document.getElementById('fotoPlaceholder');
            preview.src = e.target.result;
            preview.style.display = 'block';
            if (placeholder) placeholder.style.display = 'none';
        };
        reader.readAsDataURL(file);
    }
}
</script>
</body>
</html>
