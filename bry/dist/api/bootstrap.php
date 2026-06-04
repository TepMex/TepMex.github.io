<?php

declare(strict_types=1);

const BRY_PROJECT_ROOT = __DIR__ . '/../..';

const BRY_TIME_LABELS = [
    'morning' => 'Утро (9:00–12:00)',
    'day' => 'День (12:00–17:00)',
    'evening' => 'Вечер (17:00–20:00)',
];

function bry_load_config(): array
{
    $localPath = BRY_PROJECT_ROOT . '/private/config.local.php';
    if (is_readable($localPath)) {
        $config = require $localPath;
        if (!is_array($config)) {
            throw new RuntimeException('config.local.php must return an array');
        }
        return $config;
    }

    $dbHost = getenv('DB_HOST');
    if ($dbHost !== false && $dbHost !== '') {
        return [
            'db' => [
                'host' => $dbHost,
                'port' => (int) (getenv('DB_PORT') ?: 3306),
                'name' => getenv('DB_NAME') ?: '',
                'user' => getenv('DB_USER') ?: '',
                'pass' => getenv('DB_PASS') ?: '',
                'charset' => getenv('DB_CHARSET') ?: 'utf8mb4',
            ],
            'mail' => [
                'to' => getenv('MAIL_TO') ?: 'mihawww@yandex.ru',
                'from' => getenv('MAIL_FROM') ?: 'noreply@bry-school.ru',
                'from_name' => getenv('MAIL_FROM_NAME') ?: 'BRY School — заявки',
                'subject' => getenv('MAIL_SUBJECT') ?: 'Новая заявка на консультацию',
            ],
            'security' => [
                'allowed_origins' => array_filter(array_map('trim', explode(',', getenv('ALLOWED_ORIGINS') ?: ''))),
                'rate_limit_seconds' => (int) (getenv('RATE_LIMIT_SECONDS') ?: 60),
                'honeypot_field' => getenv('HONEYPOT_FIELD') ?: 'website',
            ],
        ];
    }

    throw new RuntimeException(
        'Configuration not found. Copy private/config.example.php to private/config.local.php'
    );
}

function bry_json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function bry_pdo(array $dbConfig): PDO
{
    $host = $dbConfig['host'];
    $port = (int) ($dbConfig['port'] ?? 3306);
    $name = $dbConfig['name'];
    $charset = $dbConfig['charset'] ?? 'utf8mb4';

    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $name, $charset);

    return new PDO($dsn, $dbConfig['user'], $dbConfig['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

function bry_client_ip(): string
{
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($parts[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function bry_check_rate_limit(string $ip, int $seconds): bool
{
    $dir = BRY_PROJECT_ROOT . '/private/rate-limit';
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
        return true;
    }

    $safeIp = preg_replace('/[^a-fA-F0-9.:]/', '', $ip) ?: 'unknown';
    $file = $dir . '/' . hash('sha256', $safeIp) . '.txt';

    $now = time();
    if (is_readable($file)) {
        $last = (int) trim((string) file_get_contents($file));
        if ($now - $last < $seconds) {
            return false;
        }
    }

    file_put_contents($file, (string) $now, LOCK_EX);
    return true;
}

function bry_handle_cors(array $security): void
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $allowed = $security['allowed_origins'] ?? [];

    if ($origin !== '' && in_array($origin, $allowed, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
        header('Access-Control-Max-Age: 86400');
    }
}

function bry_send_mail(array $mailConfig, string $body): bool
{
    require_once __DIR__ . '/smtp.php';

    $to = $mailConfig['to'] ?? '';
    $from = $mailConfig['from'] ?? '';
    $fromName = $mailConfig['from_name'] ?? 'BRY School';
    $subject = $mailConfig['subject'] ?? 'Новая заявка';
    $useSmtp = trim((string) ($mailConfig['smtp_host'] ?? '')) !== '';

    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $encodedFromName = '=?UTF-8?B?' . base64_encode($fromName) . '?=';

    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        'From: ' . $encodedFromName . ' <' . $from . '>',
        'Reply-To: ' . $from,
        'X-Mailer: PHP/' . PHP_VERSION,
    ];

    if ($useSmtp) {
        return bry_send_mail_smtp($mailConfig, $to, $encodedSubject, $body, $headers);
    }

    return mail($to, $encodedSubject, $body, implode("\r\n", $headers));
}

function bry_validate_submission(array $data, string $honeypotField): array
{
    $honeypot = trim((string) ($data[$honeypotField] ?? ''));
    if ($honeypot !== '') {
        return ['ok' => false, 'error' => 'Не удалось отправить заявку. Попробуйте позже.', 'status' => 400];
    }

    $name = trim((string) ($data['name'] ?? ''));
    $phone = trim((string) ($data['phone'] ?? ''));
    $time = trim((string) ($data['time'] ?? ''));

    if ($name === '' || mb_strlen($name) > 100) {
        return ['ok' => false, 'error' => 'Укажите ваше имя', 'status' => 422];
    }

    if ($phone === '' || !preg_match('/^[\d\s+()-]{10,}$/', $phone) || mb_strlen($phone) > 32) {
        return ['ok' => false, 'error' => 'Укажите корректный номер телефона', 'status' => 422];
    }

    $timeLabel = null;
    if ($time !== '') {
        if (!array_key_exists($time, BRY_TIME_LABELS)) {
            return ['ok' => false, 'error' => 'Выберите удобное время', 'status' => 422];
        }
        $timeLabel = BRY_TIME_LABELS[$time];
    }

    return [
        'ok' => true,
        'name' => $name,
        'phone' => $phone,
        'time' => $time,
        'time_label' => $timeLabel,
    ];
}
