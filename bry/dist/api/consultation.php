<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

try {
    $config = bry_load_config();
} catch (Throwable $e) {
    error_log('BRY config error: ' . $e->getMessage());
    bry_json_response(['ok' => false, 'error' => 'Сервис временно недоступен.'], 503);
}

$security = $config['security'] ?? [];
bry_handle_cors($security);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($method !== 'POST') {
    bry_json_response(['ok' => false, 'error' => 'Метод не поддерживается'], 405);
}

$raw = file_get_contents('php://input') ?: '';
$data = json_decode($raw, true);
if (!is_array($data)) {
    bry_json_response(['ok' => false, 'error' => 'Некорректный запрос'], 400);
}

$honeypotField = $security['honeypot_field'] ?? 'website';
$validated = bry_validate_submission($data, $honeypotField);
if (!$validated['ok']) {
    bry_json_response(['ok' => false, 'error' => $validated['error']], $validated['status']);
}

$rateLimitSeconds = (int) ($security['rate_limit_seconds'] ?? 60);
$ip = bry_client_ip();
if (!bry_check_rate_limit($ip, $rateLimitSeconds)) {
    bry_json_response(
        ['ok' => false, 'error' => 'Подождите минуту перед повторной отправкой.'],
        429
    );
}

$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
if (mb_strlen($userAgent) > 255) {
    $userAgent = mb_substr($userAgent, 0, 255);
}

try {
    $pdo = bry_pdo($config['db']);
    $stmt = $pdo->prepare(
        'INSERT INTO consultation_submissions (name, phone, preferred_time, ip_address, user_agent)
         VALUES (:name, :phone, :preferred_time, :ip_address, :user_agent)'
    );
    $stmt->execute([
        ':name' => $validated['name'],
        ':phone' => $validated['phone'],
        ':preferred_time' => $validated['time'],
        ':ip_address' => $ip,
        ':user_agent' => $userAgent !== '' ? $userAgent : null,
    ]);
} catch (Throwable $e) {
    error_log('BRY DB error: ' . $e->getMessage());
    bry_json_response(
        ['ok' => false, 'error' => 'Не удалось сохранить заявку. Попробуйте позже.'],
        500
    );
}

$timestamp = (new DateTimeImmutable('now', new DateTimeZone('Europe/Moscow')))->format('d.m.Y H:i');
$mailLines = [
    'Новая заявка на консультацию',
    '',
    'Имя: ' . $validated['name'],
    'Телефон: ' . $validated['phone'],
];
if ($validated['time_label'] !== null) {
    $mailLines[] = 'Удобное время: ' . $validated['time_label'];
}
$mailLines[] = 'Дата: ' . $timestamp;
$mailLines[] = '';
$mailLines[] = 'IP: ' . $ip;
$mailBody = implode("\n", $mailLines);

$mailConfig = $config['mail'] ?? [];
if (!bry_send_mail($mailConfig, $mailBody)) {
    error_log('BRY mail error: failed to send notification for submission from ' . $ip);
}

bry_json_response(['ok' => true]);
