<?php
// validacao.php — funções de validação partilhadas

// Foto: formatos aceites e limite de tamanho
define('FOTO_MAX_SIZE',  2 * 1024 * 1024); // 2MB
define('FOTO_FORMATOS',  ['image/jpeg', 'image/png', 'image/webp']);
define('FOTO_EXTENSOES', ['jpg', 'jpeg', 'png', 'webp']);

function validarFoto(array $file, array &$erros): bool {
    if ($file['error'] === UPLOAD_ERR_NO_FILE) return true; // opcional

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $erros[] = "Erro no upload da fotografia (código {$file['error']}).";
        return false;
    }

    // Verificar tamanho
    if ($file['size'] > FOTO_MAX_SIZE) {
        $erros[] = "A fotografia não pode exceder 2MB (enviaste " . round($file['size']/1024/1024, 1) . "MB).";
        return false;
    }

    // Verificar tipo MIME real (não confiar no nome)
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']) ?: '';
    if (!in_array($mime, FOTO_FORMATOS)) {
        $erros[] = "Formato não aceite. Usa JPG, PNG ou WEBP.";
        return false;
    }

    // Verificar extensão
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, FOTO_EXTENSOES)) {
        $erros[] = "Extensão inválida. Usa .jpg, .png ou .webp.";
        return false;
    }

    return true;
}

function guardarFoto(array $file, string $dir, array &$erros): ?string {
    if ($file['error'] === UPLOAD_ERR_NO_FILE) return '';

    if (!validarFoto($file, $erros)) return null;

    if (!is_dir($dir)) @mkdir($dir, 0755, true);

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    $ext   = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$mime] ?? 'jpg';
    $nome  = 'foto_' . bin2hex(random_bytes(8)) . '_' . time() . '.' . $ext;
    $dest  = $dir . DIRECTORY_SEPARATOR . $nome;

    if (!@move_uploaded_file($file['tmp_name'], $dest)) {
        $erros[] = "Falha ao guardar a fotografia. Verifica permissões da pasta FOTOS.";
        return null;
    }
    return $nome;
}

function validarNIF(string $nif): bool {
    return preg_match('/^\d{9}$/', $nif) === 1;
}

function validarTelefone(string $tel): bool {
    return preg_match('/^[0-9+\s\-]{9,15}$/', $tel) === 1;
}

function validarEmail(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validarData(string $data): bool {
    $d = DateTime::createFromFormat('Y-m-d', $data);
    return $d && $d->format('Y-m-d') === $data;
}

function sanitizar(string $val): string {
    return htmlspecialchars(trim($val), ENT_QUOTES, 'UTF-8');
}
