<?php
// navbar.php — incluir em todas as páginas após auth.php e config.php
$pagina_atual = basename($_SERVER['PHP_SELF']);
?>
<nav>
    <div class="nav-brand">IP<span>CA</span></div>
    <div class="nav-links">
        <a href="index.php" <?= $pagina_atual=='index.php' ? 'class="active"' : '' ?>>Dashboard</a>

        <?php if ($isAdmin || $isGestor): ?>
            <a href="cursos.php" <?= $pagina_atual=='cursos.php' ? 'class="active"' : '' ?>>Cursos</a>
            <a href="disciplinas.php" <?= $pagina_atual=='disciplinas.php' ? 'class="active"' : '' ?>>Disciplinas</a>
            <a href="plano_estudos.php" <?= $pagina_atual=='plano_estudos.php' ? 'class="active"' : '' ?>>Plano de Estudos</a>
        <?php endif; ?>

        <?php if ($isGestor || $isAdmin): ?>
            <a href="ficha_aluno.php" <?= $pagina_atual=='ficha_aluno.php' ? 'class="active"' : '' ?>>Fichas</a>
        <?php endif; ?>

        <?php if ($isFuncionario || $isAdmin): ?>
            <a href="pedido_matricula.php" <?= $pagina_atual=='pedido_matricula.php' ? 'class="active"' : '' ?>>Matrículas</a>
            <a href="pautas.php" <?= $pagina_atual=='pautas.php' ? 'class="active"' : '' ?>>Pautas</a>
        <?php endif; ?>

        <?php if ($isAluno): ?>
            <a href="cursos.php" <?= $pagina_atual=='cursos.php' ? 'class="active"' : '' ?>>Cursos</a>
            <a href="plano_estudos.php" <?= $pagina_atual=='plano_estudos.php' ? 'class="active"' : '' ?>>Plano de Estudos</a>
            <a href="ficha_aluno.php" <?= $pagina_atual=='ficha_aluno.php' ? 'class="active"' : '' ?>>A Minha Ficha</a>
            <a href="pedido_matricula.php" <?= $pagina_atual=='pedido_matricula.php' ? 'class="active"' : '' ?>>Matrícula</a>
        <?php endif; ?>

        <?php if ($isAdmin): ?>
            <a href="matriculas.php" <?= $pagina_atual=='matriculas.php' ? 'class="active"' : '' ?>>Utilizadores</a>
            <a href="qrcode.php" <?= $pagina_atual=='qrcode.php' ? 'class="active"' : '' ?>>QR Code</a>
        <?php endif; ?>
    </div>
    <div class="nav-right">
        <?php
        $perfil_cores = [1=>'#4f8ef7', 2=>'#10b981', 3=>'#f59e0b', 4=>'#a78bfa'];
        $perfil_labels = [1=>'Admin', 2=>'Aluno', 3=>'Funcionário', 4=>'Gestor'];
        $grupo = $_SESSION['grupo'];
        $cor = $perfil_cores[$grupo] ?? '#6b7a99';
        $label = $perfil_labels[$grupo] ?? 'User';
        ?>
        <span style="font-size:11px; font-weight:700; padding:4px 10px; border-radius:20px; letter-spacing:0.5px; text-transform:uppercase; background:<?= $cor ?>22; color:<?= $cor ?>; border:1px solid <?= $cor ?>44">
            <?= $label ?>
        </span>
        <div class="nav-user">
            <div class="nav-avatar"><?= strtoupper(substr($_SESSION['login'], 0, 1)) ?></div>
            <strong><?= htmlspecialchars($_SESSION['login']) ?></strong>
        </div>
        <a href="logout.php" class="btn-logout">Logout</a>
    </div>
</nav>
