<?php

function handle_attachment_upload(array $file): array
{

    if (!isset($file['error']) || is_array($file['error'])) {
        return ['success' => false, 'error' => 'Invalid upload.'];
    }

    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            return ['success' => false, 'error' => 'No file was uploaded.'];
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return ['success' => false, 'error' => 'File is too large.'];
        default:
            return ['success' => false, 'error' => 'Upload failed. Please try again.'];
    }

    if (!is_uploaded_file($file['tmp_name'])) {
        return ['success' => false, 'error' => 'Invalid upload.'];
    }

    $sizeBytes = $file['size'];

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $realMime = $finfo->file($file['tmp_name']);

    $isImage = in_array($realMime, ALLOWED_IMAGE_MIME, true);
    $isVideo = in_array($realMime, ALLOWED_VIDEO_MIME, true);

    if (!$isImage && !$isVideo) {
        return ['success' => false, 'error' => 'Unsupported file type.'];
    }

    if ($isImage && $sizeBytes > MAX_IMAGE_SIZE_BYTES) {
        return ['success' => false, 'error' => 'Image is too large (max ' . round(MAX_IMAGE_SIZE_BYTES / 1024 / 1024) . 'MB).'];
    }
    if ($isVideo && $sizeBytes > MAX_VIDEO_SIZE_BYTES) {
        return ['success' => false, 'error' => 'Video is too large (max ' . round(MAX_VIDEO_SIZE_BYTES / 1024 / 1024) . 'MB).'];
    }

    $mimeToExt = [
        'image/jpeg'      => 'jpg',
        'image/png'       => 'png',
        'image/gif'       => 'gif',
        'image/webp'      => 'webp',
        'video/mp4'       => 'mp4',
        'video/webm'      => 'webm',
        'video/quicktime' => 'mov',
    ];
    $extension = $mimeToExt[$realMime] ?? null;

    if ($extension === null) {
        return ['success' => false, 'error' => 'Unsupported file type.'];
    }

    $allowedExt = $isImage ? ALLOWED_IMAGE_EXT : ALLOWED_VIDEO_EXT;
    if (!in_array($extension, $allowedExt, true)) {
        return ['success' => false, 'error' => 'Unsupported file type.'];
    }

    $randomName = bin2hex(random_bytes(16)) . '.' . $extension;
    $destination = UPLOAD_DIR . $randomName;

    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return ['success' => false, 'error' => 'Failed to save file. Please try again.'];
    }

    chmod($destination, 0644);

    return [
        'success'  => true,
        'filename' => $randomName,
        'type'     => $isImage ? 'image' : 'video',
        'mime'     => $realMime,
    ];
}
