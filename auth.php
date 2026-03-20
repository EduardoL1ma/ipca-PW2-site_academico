<?php
session_start();

// Impedir cache
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Expiração de sessão: 30 minutos
$tempo_expiracao = 30 * 60;
if (isset($_SESSION['ultimo_acesso']) && (time() - $_SESSION['ultimo_acesso']) > $tempo_expiracao) {
    session_unset();
    session_destroy();
    header("Location: login.php?expirou=1");
    exit;
}

if (!isset($_SESSION['login'])) {
    header("Location: login.php");
    exit;
}

$_SESSION['ultimo_acesso'] = time();
