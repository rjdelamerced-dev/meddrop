<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('meddrop_session');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    ini_set('session.use_strict_mode', '1');
    session_start();
}
date_default_timezone_set('Asia/Manila');

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

function e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function redirect(string $url): void { header('Location: ' . $url); exit; }
function csrf_token(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function csrf_check(): void
{
    $token = $_POST['_csrf'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) { http_response_code(419); exit('Your session expired. Go back and refresh the page.'); }
}
function current_user(): ?array { return $_SESSION['user'] ?? null; }
function require_role(string ...$roles): array
{
    $user = current_user();
    if (!$user) redirect('?page=login');
    if (!in_array($user['role'], $roles, true)) { http_response_code(403); exit('You do not have permission to access this page.'); }
    return $user;
}
function flash(string $type, string $message): void { $_SESSION['flash'] = compact('type', 'message'); }
function take_flash(): ?array { $flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $flash; }
function money(float|string $amount): string { return '₱' . number_format((float)$amount, 2); }
function distance_km(float $lat1,float $lng1,float $lat2,float $lng2): float
{
    $radians=static fn(float $degrees): float=>$degrees*M_PI/180;
    $dLat=$radians($lat2-$lat1);$dLng=$radians($lng2-$lng1);
    $a=sin($dLat/2)**2+cos($radians($lat1))*cos($radians($lat2))*sin($dLng/2)**2;
    return 6371*2*atan2(sqrt($a),sqrt(1-$a));
}
function avatar_url(?string $photo): string
{
    if(!$photo || basename($photo)!==$photo || !preg_match('/^[a-zA-Z0-9_-]+\.(?:jpg|jpeg|png|webp|svg)$/i',$photo) || !is_file(AVATAR_DIR.'/'.$photo)) return '';
    return 'uploads/avatars/'.rawurlencode($photo);
}
function avatar_html(string $name,?string $photo,string $class='avatar'): string
{
    $src=avatar_url($photo);
    if($src!=='') return '<img class="'.e($class).' avatar-photo" src="'.e($src).'" alt="'.e($name).' profile photo">';
    $parts=preg_split('/\s+/u',trim($name),-1,PREG_SPLIT_NO_EMPTY) ?: [];
    $initials=strtoupper(mb_substr($parts[0]??'?',0,1).(count($parts)>1?mb_substr(end($parts),0,1):''));
    return '<span class="'.e($class).' avatar-fallback" role="img" aria-label="'.e($name).' avatar"><svg viewBox="0 0 40 40" aria-hidden="true"><circle cx="20" cy="20" r="20"/><text x="20" y="21">'.e($initials).'</text></svg></span>';
}
function upload_document(string $field, array $allowedMime, string $prefix): ?string
{
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) return null;
    $file = $_FILES[$field];
    if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > MAX_UPLOAD_BYTES || $file['size'] < 1) throw new RuntimeException('File upload failed or exceeds the 5 MB limit.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'application/pdf' => 'pdf'];
    if (!isset($extensions[$mime]) || !in_array($mime, $allowedMime, true)) throw new RuntimeException('Please upload a valid JPG, PNG, or PDF file.');
    if (!is_dir(UPLOAD_DIR) && !mkdir(UPLOAD_DIR, 0750, true) && !is_dir(UPLOAD_DIR)) throw new RuntimeException('Upload storage is unavailable.');
    $name = $prefix . '_' . bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
    if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . '/' . $name)) throw new RuntimeException('Could not save the uploaded file.');
    return $name;
}
function upload_avatar(string $field,int $userId): ?string
{
    if(!isset($_FILES[$field])||$_FILES[$field]['error']===UPLOAD_ERR_NO_FILE)return null;
    $file=$_FILES[$field];
    if($file['error']!==UPLOAD_ERR_OK||$file['size']<1||$file['size']>MAX_AVATAR_BYTES)throw new RuntimeException('Choose an image smaller than 2 MB.');
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $extensions=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    if(!isset($extensions[$mime])||!@getimagesize($file['tmp_name']))throw new RuntimeException('Profile photos must be JPG, PNG, or WebP images.');
    if(!is_dir(AVATAR_DIR)&&!mkdir(AVATAR_DIR,0750,true)&&!is_dir(AVATAR_DIR))throw new RuntimeException('Avatar storage is unavailable.');
    $name='user'.$userId.'_'.bin2hex(random_bytes(16)).'.'.$extensions[$mime];
    if(!move_uploaded_file($file['tmp_name'],AVATAR_DIR.'/'.$name))throw new RuntimeException('Could not save the profile photo.');
    return $name;
}
function document_url(?string $file): string { return $file ? 'document.php?file=' . rawurlencode($file) : ''; }
function order_status_label(string $status): string
{
    return ['pending_review'=>'Pending Pharmacist Review','approved'=>'Approved & Preparing','out_for_delivery'=>'Out for Delivery','delivered'=>'Delivered','rejected'=>'Rejected'][$status] ?? ucfirst($status);
}

