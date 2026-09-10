<?php
/**
 * Shared image-upload helper for FoodFusion.
 *
 * Security notes (worth citing in the reflection):
 *  - The file type is decided by inspecting the image itself with getimagesize(),
 *    NOT by trusting $_FILES['type'] or the file extension, both of which the
 *    browser sends and an attacker can forge.
 *  - The stored filename is generated with random_bytes(), so a user can never
 *    control the path, overwrite another file, or upload something like
 *    "shell.php" and have it executed.
 *  - Only a fixed whitelist of real image formats is accepted.
 */

define('UPLOAD_DIR_FS',  __DIR__ . '/../assets/uploads');
define('UPLOAD_DIR_WEB', 'assets/uploads');
define('UPLOAD_MAX_BYTES', 3 * 1024 * 1024); // 3 MB

/**
 * Handle one uploaded image.
 *
 * @param  array|null $file  An entry from $_FILES, e.g. $_FILES['image_file'].
 * @param  string|null $error Set to a user-facing message when the upload fails.
 * @return string|null Web-relative path to store in the database, or null.
 */
function handle_image_upload($file, &$error = null)
{
    $error = null;

    // Nothing uploaded — this is not an error, the field is optional.
    if (empty($file) || !isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
        $error = 'That image is too large. Please choose a file under 3 MB.';
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'That image could not be uploaded. Please try again.';
        return null;
    }

    if ($file['size'] > UPLOAD_MAX_BYTES) {
        $error = 'That image is too large. Please choose a file under 3 MB.';
        return null;
    }

    // Confirms the file really is an image, and tells us its true type.
    $info = @getimagesize($file['tmp_name']);
    if ($info === false) {
        $error = 'That file does not appear to be a valid image.';
        return null;
    }

    $allowed = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG  => 'png',
        IMAGETYPE_GIF  => 'gif',
        IMAGETYPE_WEBP => 'webp',
    ];

    if (!isset($allowed[$info[2]])) {
        $error = 'Please upload a JPG, PNG, GIF or WEBP image.';
        return null;
    }

    if (!is_dir(UPLOAD_DIR_FS) && !@mkdir(UPLOAD_DIR_FS, 0755, true)) {
        $error = 'The server could not save the image. Please try again later.';
        return null;
    }

    // Random name: the user never controls the stored filename.
    $filename = bin2hex(random_bytes(16)) . '.' . $allowed[$info[2]];
    $target   = UPLOAD_DIR_FS . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $target)) {
        $error = 'The server could not save the image. Please try again.';
        return null;
    }

    @chmod($target, 0644);

    return UPLOAD_DIR_WEB . '/' . $filename;
}

/**
 * Resolve a stored image reference for display.
 * Accepts both uploaded paths and full external URLs, so posts created
 * before uploads existed still render correctly.
 */
function image_src($stored)
{
    $stored = trim((string) $stored);
    if ($stored === '') {
        return null;
    }
    if (preg_match('#^(https?:)?//#i', $stored)) {
        return $stored;
    }
    return $stored;
}

/**
 * Extract a YouTube video ID from the common URL shapes, so a real
 * thumbnail can be shown instead of a flat colour panel.
 * Returns null for anything that isn't a recognisable YouTube link.
 */
function youtube_id($url)
{
    $url = trim((string) $url);
    if ($url === '' || $url === '#') {
        return null;
    }
    $patterns = [
        '#youtube\.com/watch\?(?:.*&)?v=([A-Za-z0-9_-]{11})#i',
        '#youtu\.be/([A-Za-z0-9_-]{11})#i',
        '#youtube\.com/embed/([A-Za-z0-9_-]{11})#i',
        '#youtube\.com/shorts/([A-Za-z0-9_-]{11})#i',
    ];
    foreach ($patterns as $re) {
        if (preg_match($re, $url, $m)) {
            return $m[1];
        }
    }
    return null;
}

/**
 * Thumbnail URL for a video link, or null when one can't be derived.
 */
function video_thumbnail($url)
{
    $id = youtube_id($url);
    return $id ? "https://img.youtube.com/vi/$id/hqdefault.jpg" : null;
}

/**
 * mb_strlen fallback.
 * The mbstring extension is enabled in most PHP builds (including XAMPP),
 * but if it is switched off, every form that length-checks a title would
 * fatal. This keeps the site working either way.
 */
if (!function_exists('mb_strlen')) {
    function mb_strlen($string, $encoding = null) {
        return strlen(utf8_decode((string) $string));
    }
}