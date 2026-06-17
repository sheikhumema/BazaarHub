<?php

function app_base_url(): string
{
    $configured = app_setting('APP_URL', '');
    if ($configured !== '') {
        return rtrim($configured, '/');
    }

    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/';
    $dir = str_replace('\\', '/', rtrim(dirname($scriptName), '/\\'));
    if ($dir === '.' || $dir === '\\' || $dir === '/') {
        $dir = '';
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    return rtrim($scheme . '://' . $host . $dir, '/');
}

function app_url(string $path): string
{
    return app_base_url() . '/' . ltrim($path, '/');
}

function app_setting(string $key, string $default = ''): string
{
    static $fileConfig = null;

    if ($fileConfig === null) {
        $fileConfig = [];
        $configPath = __DIR__ . '/app_config.php';
        if (is_file($configPath)) {
            $loaded = require $configPath;
            if (is_array($loaded)) {
                $fileConfig = $loaded;
            }
        }
    }

    if (array_key_exists($key, $fileConfig) && $fileConfig[$key] !== '') {
        return (string) $fileConfig[$key];
    }

    $value = getenv($key);
    if ($value === false || $value === '') {
        return $default;
    }

    return $value;
}

function send_app_email(string $to, string $subject, string $htmlBody, string $textBody = ''): bool
{
    $fromAddress = app_setting('MAIL_FROM_ADDRESS', 'no-reply@bazaarhub.local');
    $fromName = app_setting('MAIL_FROM_NAME', 'BazaarHub');
    $smtpHost = app_setting('SMTP_HOST', '');
    $smtpPort = (int) app_setting('SMTP_PORT', '587');
    $smtpUsername = app_setting('SMTP_USERNAME', '');
    $smtpPassword = app_setting('SMTP_PASSWORD', '');
    $smtpEncryption = strtolower(app_setting('SMTP_ENCRYPTION', 'tls'));

    if ($textBody === '') {
        $textBody = trim(html_entity_decode(strip_tags($htmlBody), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    if ($smtpHost === '') {
        throw new RuntimeException('SMTP is not configured. Set SMTP_HOST, SMTP_PORT, SMTP_USERNAME, and SMTP_PASSWORD in app_config.php.');
    }

    return send_app_email_via_smtp(
        $smtpHost,
        $smtpPort,
        $smtpEncryption,
        $smtpUsername,
        $smtpPassword,
        $fromAddress,
        $fromName,
        $to,
        $subject,
        $htmlBody,
        $textBody
    );
}

function send_app_email_via_smtp(
    string $host,
    int $port,
    string $encryption,
    string $username,
    string $password,
    string $fromAddress,
    string $fromName,
    string $to,
    string $subject,
    string $htmlBody,
    string $textBody
): bool {
    $remote = ($encryption === 'ssl' ? 'ssl://' : '') . $host;
    $socket = @stream_socket_client($remote . ':' . $port, $errno, $errstr, 20, STREAM_CLIENT_CONNECT);
    if (!$socket) {
        throw new RuntimeException('SMTP connection failed: ' . $errstr);
    }

    stream_set_timeout($socket, 20);

    $read = function () use ($socket): string {
        $data = '';
        while (($line = fgets($socket, 515)) !== false) {
            $data .= $line;
            if (strlen($line) < 4 || $line[3] !== '-') {
                break;
            }
        }
        return $data;
    };

    $write = function (string $command) use ($socket): void {
        fwrite($socket, $command . "\r\n");
    };

    $expect = function (string $prefix) use ($read): string {
        $response = $read();
        if (!preg_match('/^' . preg_quote($prefix, '/') . '\d{2}/m', $response)) {
            throw new RuntimeException(trim($response) ?: 'Unexpected SMTP response.');
        }
        return $response;
    };

    $expect('2');
    $write('EHLO localhost');
    $ehlo = $read();

    if ($encryption === 'tls') {
        if (stripos($ehlo, 'STARTTLS') === false) {
            throw new RuntimeException('SMTP server does not support STARTTLS.');
        }
        $write('STARTTLS');
        $response = $read();
        if (strpos($response, '220') !== 0) {
            throw new RuntimeException(trim($response) ?: 'STARTTLS failed.');
        }
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new RuntimeException('Unable to establish TLS with SMTP server.');
        }
        $write('EHLO localhost');
        $read();
    }

    if ($username !== '') {
        if (stripos($ehlo, 'AUTH') === false && $encryption !== 'tls') {
            // Some servers advertise AUTH only after TLS; if not using TLS, continue and let the server reject.
        }
        $write('AUTH LOGIN');
        $response = $read();
        if (strpos($response, '334') !== 0) {
            throw new RuntimeException(trim($response) ?: 'SMTP AUTH LOGIN failed.');
        }
        $write(base64_encode($username));
        $response = $read();
        if (strpos($response, '334') !== 0) {
            throw new RuntimeException(trim($response) ?: 'SMTP username rejected.');
        }
        $write(base64_encode($password));
        $response = $read();
        if (strpos($response, '235') !== 0) {
            throw new RuntimeException(trim($response) ?: 'SMTP password rejected.');
        }
    }

    $write('MAIL FROM:<' . $fromAddress . '>');
    $response = $read();
    if (strpos($response, '250') !== 0) {
        throw new RuntimeException(trim($response) ?: 'MAIL FROM rejected.');
    }

    $write('RCPT TO:<' . $to . '>');
    $response = $read();
    if (strpos($response, '250') !== 0 && strpos($response, '251') !== 0) {
        throw new RuntimeException(trim($response) ?: 'RCPT TO rejected.');
    }

    $write('DATA');
    $response = $read();
    if (strpos($response, '354') !== 0) {
        throw new RuntimeException(trim($response) ?: 'DATA rejected.');
    }

    $boundary = 'bazaarhub_' . bin2hex(random_bytes(8));
    $headers = [
        'From: ' . $fromName . ' <' . $fromAddress . '>',
        'To: <' . $to . '>',
        'Subject: ' . $subject,
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
    ];

    $message = implode("\r\n", $headers) . "\r\n\r\n";
    $message .= '--' . $boundary . "\r\n";
    $message .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
    $message .= $textBody . "\r\n\r\n";
    $message .= '--' . $boundary . "\r\n";
    $message .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
    $message .= $htmlBody . "\r\n\r\n";
    $message .= '--' . $boundary . "--\r\n";

    foreach (preg_split("/\r\n|\n|\r/", $message) as $line) {
        if ($line !== '' && $line[0] === '.') {
            $line = '.' . $line;
        }
        fwrite($socket, $line . "\r\n");
    }

    fwrite($socket, ".\r\n");

    $response = $read();
    if (strpos($response, '250') !== 0) {
        throw new RuntimeException(trim($response) ?: 'Message delivery failed.');
    }

    $write('QUIT');
    fclose($socket);
    return true;
}

function google_oauth_config(): array
{
    return [
        'client_id' => app_setting('GOOGLE_CLIENT_ID'),
        'client_secret' => app_setting('GOOGLE_CLIENT_SECRET'),
        'redirect_uri' => app_setting('GOOGLE_REDIRECT_URI', app_url('google-callback.php')),
    ];
}

function google_oauth_ready(): bool
{
    $config = google_oauth_config();
    return $config['client_id'] !== '' && $config['client_secret'] !== '' && $config['redirect_uri'] !== '';
}

function google_oauth_authorize_url(string $state): string
{
    $config = google_oauth_config();

    $params = [
        'client_id' => $config['client_id'],
        'redirect_uri' => $config['redirect_uri'],
        'response_type' => 'code',
        'scope' => 'openid email profile',
        'access_type' => 'offline',
        'prompt' => 'select_account',
        'state' => $state,
    ];

    return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
}

function http_request_json(string $url, string $method = 'GET', array $fields = [], array $headers = []): array
{
    $body = '';
    $method = strtoupper($method);

    if ($method !== 'GET' && $fields) {
        $body = http_build_query($fields, '', '&', PHP_QUERY_RFC3986);
    }

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        $curlHeaders = $headers;

        if ($method === 'GET') {
            curl_setopt($ch, CURLOPT_HTTPGET, true);
        } else {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            $curlHeaders[] = 'Content-Length: ' . strlen($body);
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_HTTPHEADER => $curlHeaders,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_FOLLOWLOCATION => true,
        ]);

        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException($error ?: 'HTTP request failed.');
        }

        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        return [
            'status' => $status,
            'headers' => [],
            'body' => substr($response, $headerSize),
        ];
    }

    $contextHeaders = $headers;
    if ($method !== 'GET') {
        $contextHeaders[] = 'Content-Type: application/x-www-form-urlencoded';
        $contextHeaders[] = 'Content-Length: ' . strlen($body);
    }

    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", $contextHeaders),
            'content' => $body,
            'ignore_errors' => true,
            'timeout' => 20,
        ],
    ]);

    $responseBody = file_get_contents($url, false, $context);
    if ($responseBody === false) {
        throw new RuntimeException('HTTP request failed.');
    }

    $status = 0;
    $responseHeaders = $http_response_header ?? [];
    if (!empty($responseHeaders[0]) && preg_match('/\s(\d{3})\s/', $responseHeaders[0], $matches)) {
        $status = (int) $matches[1];
    }

    return [
        'status' => $status,
        'headers' => $responseHeaders,
        'body' => $responseBody,
    ];
}

function google_oauth_exchange_code(string $code): array
{
    $config = google_oauth_config();
    $response = http_request_json(
        'https://oauth2.googleapis.com/token',
        'POST',
        [
            'code' => $code,
            'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret'],
            'redirect_uri' => $config['redirect_uri'],
            'grant_type' => 'authorization_code',
        ]
    );

    $data = json_decode($response['body'], true);
    if (!is_array($data) || ($response['status'] < 200 || $response['status'] >= 300)) {
        $message = is_array($data) && !empty($data['error_description']) ? $data['error_description'] : 'Unable to exchange Google authorization code.';
        throw new RuntimeException($message);
    }

    return $data;
}

function google_oauth_fetch_userinfo(string $accessToken): array
{
    $response = http_request_json(
        'https://www.googleapis.com/oauth2/v2/userinfo',
        'GET',
        [],
        ['Authorization: Bearer ' . $accessToken]
    );

    $data = json_decode($response['body'], true);
    if (!is_array($data) || ($response['status'] < 200 || $response['status'] >= 300)) {
        $message = is_array($data) && !empty($data['error']['message']) ? $data['error']['message'] : 'Unable to fetch Google account details.';
        throw new RuntimeException($message);
    }

    return $data;
}
