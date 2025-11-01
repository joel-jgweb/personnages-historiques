<?php
// Helper upload : inclure ce fichier avec require_once
function handle_uploaded_file(array $file, string $destDir, array $allowedExt = ['jpg','jpeg','png','gif','pdf'], int $maxBytes = 10 * 1024 * 1024) {
    if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'reason' => 'no_file'];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'reason' => 'php_error', 'code' => $file['error']];
    }

    if (!is_uploaded_file($file['tmp_name'])) {
        return ['ok' => false, 'reason' => 'not_uploaded_file'];
    }

    if ($file['size'] > $maxBytes) {
        return ['ok' => false, 'reason' => 'too_large'];
    }

    $original = $file['name'];
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        return ['ok' => false, 'reason' => 'bad_ext', 'ext' => $ext];
    }

    // Ensure destination directory exists and is writable
    if (!is_dir($destDir)) {
        if (!mkdir($destDir, 0755, true)) {
            return ['ok' => false, 'reason' => 'mkdir_failed'];
        }
    }
    if (!is_writable($destDir)) {
        return ['ok' => false, 'reason' => 'not_writable'];
    }

    // Create safe unique filename: timestamp + random
    $safeBase = preg_replace('/[^a-z0-9_\-]/i', '_', pathinfo($original, PATHINFO_FILENAME));
    try {
        $random = bin2hex(random_bytes(4));
    } catch (Exception $e) {
        $random = uniqid();
    }
    $targetName = sprintf('%s_%s.%s', time(), $random, $ext);
    $targetPath = rtrim($destDir, '/\\') . DIRECTORY_SEPARATOR . $targetName;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['ok' => false, 'reason' => 'move_failed'];
    }

    return ['ok' => true, 'path' => $targetPath, 'filename' => $targetName, 'original' => $original];
}
?>