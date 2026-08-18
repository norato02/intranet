<?php
// ============================================================
// HELPER DE UPLOAD DE IMAGENS — Intranet v2.1
// Suporte: JPG, PNG, GIF, WEBP, BMP, TIFF
// Corrige: paths, GD load, fallback seguro
// ============================================================

// Tipos MIME aceitos → extensão de saída
const IMG_ALLOWED = [
    'image/jpeg'   => 'jpg',
    'image/jpg'    => 'jpg',
    'image/pjpeg'  => 'jpg',
    'image/png'    => 'png',
    'image/gif'    => 'gif',
    'image/webp'   => 'webp',
    'image/bmp'    => 'jpg',
    'image/x-bmp'  => 'jpg',
    'image/x-ms-bmp' => 'jpg',
    'image/tiff'   => 'jpg',
    'image/x-tiff' => 'jpg',
];

const IMG_MAX_BYTES  = 15 * 1024 * 1024; // 15 MB
const IMG_MAX_W      = 1920;
const IMG_MAX_H      = 1080;
const IMG_QUALITY    = 85;
const THUMB_W        = 640;
const THUMB_H        = 360;

/**
 * Faz upload, valida, redimensiona e gera thumbnail.
 *
 * @param  array  $file       Entrada de $_FILES['campo']
 * @param  string $subfolder  'posts' | 'modules' | 'avatars'
 * @return array  ['success', 'filename' (relativo a uploads/), 'message']
 */
function uploadImage(array $file, string $subfolder = 'posts'): array
{
    // 1. Verificar erro do PHP
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        $msgs = [
            UPLOAD_ERR_INI_SIZE   => 'Arquivo excede o limite do servidor (upload_max_filesize).',
            UPLOAD_ERR_FORM_SIZE  => 'Arquivo excede o limite do formulário.',
            UPLOAD_ERR_PARTIAL    => 'Upload incompleto. Tente novamente.',
            UPLOAD_ERR_NO_FILE    => 'Nenhum arquivo enviado.',
            UPLOAD_ERR_NO_TMP_DIR => 'Pasta temporária não encontrada.',
            UPLOAD_ERR_CANT_WRITE => 'Falha ao gravar no disco.',
        ];
        $code = $file['error'] ?? -1;
        return ['success' => false, 'message' => $msgs[$code] ?? "Erro de upload (código $code)."];
    }

    // 2. Verificar tamanho
    if ($file['size'] > IMG_MAX_BYTES) {
        $mb = round(IMG_MAX_BYTES / 1024 / 1024);
        return ['success' => false, 'message' => "Arquivo muito grande. Máximo: {$mb}MB."];
    }

    // 3. Detectar MIME real (não confiar em $_FILES['type'])
    if (!function_exists('finfo_open')) {
        // Fallback: usar getimagesize
        $info = @getimagesize($file['tmp_name']);
        if (!$info) return ['success' => false, 'message' => 'Arquivo não é uma imagem válida.'];
        $mime = $info['mime'];
    } else {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']);
    }

    if (!array_key_exists($mime, IMG_ALLOWED)) {
        return ['success' => false, 'message' => "Formato não aceito ($mime). Use JPG, PNG, GIF, WEBP ou BMP."];
    }

    // 4. Confirmar que é imagem real
    $imgInfo = @getimagesize($file['tmp_name']);
    if (!$imgInfo) {
        return ['success' => false, 'message' => 'O arquivo não é uma imagem válida.'];
    }

    // 5. Definir caminhos
    $ext       = IMG_ALLOWED[$mime];
    $base      = uniqid('img_', true);
    $filename  = $base . '.' . $ext;
    $thumbName = $base . '_thumb.' . $ext;

    // UPLOAD_DIR já aponta para raiz/uploads/ (corrigido no config.php)
    $dir = rtrim(UPLOAD_DIR, '/') . '/' . $subfolder . '/';
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true)) {
            return ['success' => false, 'message' => "Não foi possível criar a pasta de upload: $dir"];
        }
    }

    $destPath  = $dir . $filename;
    $thumbPath = $dir . $thumbName;

    // 6. Processar (redimensionar) ou copiar direto
    $processed = imgProcess($file['tmp_name'], $mime, $destPath, IMG_MAX_W, IMG_MAX_H, IMG_QUALITY, false);
    if (!$processed) {
        // GD não disponível ou falhou: mover o arquivo bruto
        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            return ['success' => false, 'message' => 'Falha ao salvar a imagem no servidor.'];
        }
    }

    // 7. Gerar thumbnail (falha silenciosamente)
    if (file_exists($destPath)) {
        imgProcess($destPath, $mime, $thumbPath, THUMB_W, THUMB_H, 80, true);
    }

    return [
        'success'  => true,
        'filename' => $subfolder . '/' . $filename,   // relativo a uploads/
        'thumb'    => $subfolder . '/' . $thumbName,
        'message'  => 'Imagem enviada com sucesso.',
    ];
}

/**
 * Carrega, (re)dimensiona e salva uma imagem usando GD.
 * Retorna false se GD não estiver disponível ou a imagem não puder ser carregada.
 */
function imgProcess(string $src, string $mime, string $dest, int $maxW, int $maxH, int $quality = 85, bool $crop = false): bool
{
    if (!extension_loaded('gd')) return false;

    // Carregar conforme o tipo
    $img = match(true) {
        in_array($mime, ['image/jpeg','image/jpg','image/pjpeg',
                          'image/bmp','image/x-bmp','image/x-ms-bmp',
                          'image/tiff','image/x-tiff'])
                        => @imagecreatefromjpeg($src)
                           ?: @imagecreatefromstring(@file_get_contents($src)),
        $mime === 'image/png'  => @imagecreatefrompng($src),
        $mime === 'image/gif'  => @imagecreatefromgif($src),
        $mime === 'image/webp' => function_exists('imagecreatefromwebp')
                                    ? @imagecreatefromwebp($src)
                                    : @imagecreatefromstring(@file_get_contents($src)),
        default                => @imagecreatefromstring(@file_get_contents($src)),
    };

    if (!$img) return false;

    $origW = imagesx($img);
    $origH = imagesy($img);

    if ($crop) {
        // Thumbnail: cortar centralizado
        $ratio = max($maxW / $origW, $maxH / $origH);
        $newW  = (int) ($origW * $ratio);
        $newH  = (int) ($origH * $ratio);
        $srcX  = (int) (($origW - $maxW / $ratio) / 2);
        $srcY  = (int) (($origH - $maxH / $ratio) / 2);
        $srcCW = (int) ($maxW / $ratio);
        $srcCH = (int) ($maxH / $ratio);

        $canvas = imagecreatetruecolor($maxW, $maxH);
        imgSetBackground($canvas, $mime);
        imagecopyresampled($canvas, $img, 0, 0, $srcX, $srcY, $maxW, $maxH, $srcCW, $srcCH);
    } else {
        // Redimensionar proporcional (só se necessário)
        if ($origW <= $maxW && $origH <= $maxH) {
            // PNG/GIF/WEBP: mesmo sem redimensionar, preservar transparência
            if (in_array($mime, ['image/png','image/gif','image/webp'])) {
                imagealphablending($img, false);
                imagesavealpha($img, true);
            }
            $canvas = $img;
        } else {
            $ratio  = min($maxW / $origW, $maxH / $origH);
            $newW   = max(1, (int) ($origW * $ratio));
            $newH   = max(1, (int) ($origH * $ratio));
            $canvas = imagecreatetruecolor($newW, $newH);
            imgSetBackground($canvas, $mime);
            // Ativar leitura de alpha na imagem fonte antes de copiar
            if (in_array($mime, ['image/png','image/gif','image/webp'])) {
                imagealphablending($img, true);
            }
            imagecopyresampled($canvas, $img, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
        }
    }

    // Salvar no formato de saída
    $ext  = strtolower(pathinfo($dest, PATHINFO_EXTENSION));
    $ok   = match($ext) {
        'png'  => imagepng($canvas, $dest, max(0, min(9, (int) ((100 - $quality) / 11)))),
        'gif'  => imagegif($canvas, $dest),
        'webp' => function_exists('imagewebp') ? imagewebp($canvas, $dest, $quality) : imagejpeg($canvas, $dest, $quality),
        default => imagejpeg($canvas, $dest, $quality),
    };

    imagedestroy($img);
    if ($canvas !== $img) imagedestroy($canvas);

    return (bool) $ok;
}

/** Define fundo branco para JPG ou transparente para PNG/GIF/WEBP */
function imgSetBackground(\GdImage $canvas, string $mime): void
{
    if (in_array($mime, ['image/png','image/gif','image/webp'])) {
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefill($canvas, 0, 0, $transparent);
    } else {
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $white);
    }
}

/**
 * Remove imagem e thumbnail do disco.
 * @param string $relativePath  relativo a uploads/, ex: 'posts/img_abc.jpg'
 */
function deleteImage(string $relativePath): void
{
    if (!$relativePath) return;
    $full = rtrim(UPLOAD_DIR, '/') . '/' . ltrim($relativePath, '/');
    if (file_exists($full)) @unlink($full);
    // thumbnail: mesmo nome + _thumb
    $pi    = pathinfo($full);
    $thumb = $pi['dirname'] . '/' . $pi['filename'] . '_thumb.' . ($pi['extension'] ?? 'jpg');
    if (file_exists($thumb)) @unlink($thumb);
}

/**
 * Retorna a URL pública de uma imagem.
 * @param string $relativePath  relativo a uploads/
 * @param bool   $thumb         retornar thumbnail se disponível
 */
function imageUrl(string $relativePath, bool $thumb = false): string
{
    if (!$relativePath) return '';
    $base = rtrim(UPLOAD_URL, '/');
    if ($thumb) {
        $pi    = pathinfo($relativePath);
        $tRel  = $pi['dirname'] . '/' . $pi['filename'] . '_thumb.' . ($pi['extension'] ?? 'jpg');
        $tFull = rtrim(UPLOAD_DIR, '/') . '/' . ltrim($tRel, '/');
        if (file_exists($tFull)) return $base . '/' . ltrim($tRel, '/');
    }
    return $base . '/' . ltrim($relativePath, '/');
}
