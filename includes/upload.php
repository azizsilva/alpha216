<?php

function mk_save_proof_upload($file, $abs_upload_root, $rel_upload_root) {
    if (!is_array($file) || !isset($file['error'])) return [null, 'Invalid upload.'];
    if ((int)$file['error'] === UPLOAD_ERR_NO_FILE) return [null, 'Proof is required.'];
    if ((int)$file['error'] !== UPLOAD_ERR_OK) return [null, 'Upload failed.'];

    $tmp = (string)($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) return [null, 'Upload failed.'];

    $size = (int)($file['size'] ?? 0);
    if ($size <= 0) return [null, 'Invalid file.'];
    if ($size > 4 * 1024 * 1024) return [null, 'File too large (max 4MB).'];

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmp) ?: '';
    $allowed = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp'
    ];
    if (!isset($allowed[$mime])) return [null, 'Only PNG/JPG/WEBP allowed.'];

    $ext = $allowed[$mime];
    $name = bin2hex(random_bytes(16)) . '.' . $ext;

    $abs_upload_root = rtrim((string)$abs_upload_root, "\\/") . DIRECTORY_SEPARATOR;
    $rel_upload_root = rtrim((string)$rel_upload_root, "/") . '/';

    if (!is_dir($abs_upload_root)) {
        if (!mkdir($abs_upload_root, 0775, true)) return [null, 'Upload directory not writable.'];
    }

    $dest = $abs_upload_root . $name;
    if (!move_uploaded_file($tmp, $dest)) return [null, 'Upload failed.'];

    return [$rel_upload_root . $name, ''];
}

