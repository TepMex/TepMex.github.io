<?php

declare(strict_types=1);

function bry_smtp_read($socket): string
{
    $data = '';
    while (($line = fgets($socket, 515)) !== false) {
        $data .= $line;
        if (isset($line[3]) && $line[3] === ' ') {
            break;
        }
    }

    return $data;
}

function bry_smtp_expect(string $response, array $codes): bool
{
    $code = (int) substr($response, 0, 3);

    return in_array($code, $codes, true);
}

function bry_smtp_cmd($socket, string $command, array $okCodes): bool
{
    fwrite($socket, $command . "\r\n");

    return bry_smtp_expect(bry_smtp_read($socket), $okCodes);
}

function bry_smtp_enable_crypto($socket, string $host): bool
{
    $crypto = stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
    if ($crypto) {
        return true;
    }

    return (bool) stream_socket_enable_crypto(
        $socket,
        true,
        STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT
    );
}

function bry_send_mail_smtp(array $mailConfig, string $to, string $subject, string $body, array $headers): bool
{
    $host = trim((string) ($mailConfig['smtp_host'] ?? ''));
    if ($host === '') {
        return false;
    }

    $port = (int) ($mailConfig['smtp_port'] ?? 587);
    $user = (string) ($mailConfig['smtp_user'] ?? '');
    $pass = (string) ($mailConfig['smtp_pass'] ?? '');
    $secure = strtolower(trim((string) ($mailConfig['smtp_secure'] ?? 'tls')));
    $from = (string) ($mailConfig['from'] ?? $user);
    $timeout = 15;

    $remote = $secure === 'ssl'
        ? 'ssl://' . $host . ':' . $port
        : 'tcp://' . $host . ':' . $port;

    $socket = @stream_socket_client($remote, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT);
    if ($socket === false) {
        error_log('BRY SMTP connect failed: ' . $errstr . ' (' . $errno . ')');

        return false;
    }

    stream_set_timeout($socket, $timeout);

    if (!bry_smtp_expect(bry_smtp_read($socket), [220])) {
        fclose($socket);

        return false;
    }

    $ehloHost = 'bry-school.ru';
    if (!bry_smtp_cmd($socket, 'EHLO ' . $ehloHost, [250])) {
        fclose($socket);

        return false;
    }

    if ($secure === 'tls') {
        if (!bry_smtp_cmd($socket, 'STARTTLS', [220])) {
            fclose($socket);

            return false;
        }
        if (!bry_smtp_enable_crypto($socket, $host)) {
            error_log('BRY SMTP STARTTLS failed');
            fclose($socket);

            return false;
        }
        if (!bry_smtp_cmd($socket, 'EHLO ' . $ehloHost, [250])) {
            fclose($socket);

            return false;
        }
    }

    if ($user !== '' && $pass !== '') {
        if (!bry_smtp_cmd($socket, 'AUTH LOGIN', [334])
            || !bry_smtp_cmd($socket, base64_encode($user), [334])
            || !bry_smtp_cmd($socket, base64_encode($pass), [235])) {
            error_log('BRY SMTP authentication failed');
            fclose($socket);

            return false;
        }
    }

    if (!bry_smtp_cmd($socket, 'MAIL FROM:<' . $from . '>', [250])
        || !bry_smtp_cmd($socket, 'RCPT TO:<' . $to . '>', [250, 251])) {
        fclose($socket);

        return false;
    }

    if (!bry_smtp_cmd($socket, 'DATA', [354])) {
        fclose($socket);

        return false;
    }

    $headerLines = array_merge($headers, [
        'To: ' . $to,
        'Subject: ' . $subject,
    ]);
    $message = implode("\r\n", $headerLines) . "\r\n\r\n" . str_replace("\n.", "\n..", $body);
    fwrite($socket, $message . "\r\n.\r\n");

    if (!bry_smtp_expect(bry_smtp_read($socket), [250])) {
        fclose($socket);

        return false;
    }

    bry_smtp_cmd($socket, 'QUIT', [221]);
    fclose($socket);

    return true;
}
