<?php
declare(strict_types=1);
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');

function reply(int $status, array $body): never
{
    http_response_code($status);
    if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

try {
    if (PHP_VERSION_ID < 80300) { throw new RuntimeException('php_version'); }
    foreach (['curl', 'mbstring', 'openssl', 'session'] as $extension) {
        if (!extension_loaded($extension)) { throw new RuntimeException('php_extension_' . $extension); }
    }
    // Racines imbriquées : placer les secrets hors de www et clickandbuilds.
    $privateParent = dirname(__DIR__, 2);
    if (in_array(basename($privateParent), ['www', 'clickandbuilds'], true)) {
        $privateParent = dirname($privateParent);
    }
    $private = realpath(getenv('MELINA_FORM_PRIVATE_DIR') ?: $privateParent
        . '/melina-form-private-' . basename(dirname(__DIR__)));
    $web = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
    if (!$private || !$web || $private === $web || str_starts_with($private, $web . DIRECTORY_SEPARATOR)) {
        throw new RuntimeException('private_location');
    }
    if (!is_readable($private . '/formulaire-lib.php')) { throw new RuntimeException('library_unreadable'); }
    if (!is_readable($private . '/config.php')) { throw new RuntimeException('config_unreadable'); }
    require $private . '/formulaire-lib.php';
    try { $config = require $private . '/config.php'; }
    catch (ParseError $error) { throw new RuntimeException('config_syntax'); }
    if (!is_array($config) || !in_array($config['mode'] ?? '', ['simulation', 'smtp'], true)
        || !preg_match('/\A[a-f0-9]{64}\z/', $config['app_key'] ?? '')
        || empty($config['recaptcha_secret']) || empty($config['recaptcha_site_key'])) {
        throw new RuntimeException('configuration');
    }
    $origin = $config['origin'] ?? '';
    $url = parse_url($origin);
    if (!$url || ($url['scheme'] ?? '') !== 'https' || empty($url['host'])
        || isset($url['path']) || isset($url['query']) || isset($url['fragment']) || isset($url['user'])) {
        throw new RuntimeException('origin_configuration');
    }
    // Ne pas faire confiance à X-Forwarded-For / X-Forwarded-Proto fournis par le client.
    if (($_SERVER['HTTPS'] ?? '') !== 'on' && ($_SERVER['HTTPS'] ?? '') !== '1') {
        throw new FormError(403, 'Une connexion HTTPS est nécessaire.');
    }
    if (strtolower($_SERVER['HTTP_HOST'] ?? '') !== strtolower(substr($origin, 8))) {
        throw new FormError(403, 'Ce domaine n’est pas autorisé.');
    }
    $method = $_SERVER['REQUEST_METHOD'] ?? '';
    if (!in_array($method, ['GET', 'POST'], true)) {
        header('Allow: GET, POST');
        throw new FormError(405, 'Méthode non autorisée.');
    }
    if (($_SERVER['HTTP_X_MELINA_FORM'] ?? '') !== '1'
        || (isset($_SERVER['HTTP_ORIGIN']) && $_SERVER['HTTP_ORIGIN'] !== $origin)
        || ($method === 'POST' && ($_SERVER['HTTP_ORIGIN'] ?? '') !== $origin)
        || (isset($_SERVER['HTTP_SEC_FETCH_SITE']) && !in_array($_SERVER['HTTP_SEC_FETCH_SITE'], ['same-origin', 'none'], true))) {
        throw new FormError(403, 'Requête non autorisée.');
    }
    umask(0077);
    $storage = $private . '/donnees';
    if (!is_dir($storage) && !@mkdir($storage, 0700) && !is_dir($storage)) { throw new RuntimeException('storage'); }
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!filter_var($ip, FILTER_VALIDATE_IP)) { throw new RuntimeException('ip'); }
    rate_limit($storage, $config['app_key'], $method === 'GET'
        ? [['init-ip', $ip, 3600, 60], ['init-global', 'all', 3600, 3000]]
        : [['post-ip', $ip, 3600, 10], ['post-global', 'all', 3600, 100]]);
    $sessions = $storage . '/sessions';
    if (!is_dir($sessions) && !@mkdir($sessions, 0700) && !is_dir($sessions)) { throw new RuntimeException('session_storage'); }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.gc_maxlifetime', '3600');
    session_save_path($sessions);
    session_name('__Host-MelinaForm');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Strict']);
    if (!session_start()) { throw new RuntimeException('session'); }
    if ($method === 'GET') {
        $tickets = $_SESSION['tickets'] ?? [];
        $tickets = array_filter($tickets, fn($time) => $time > time() - 3600);
        $tickets = array_slice($tickets, -4, null, true);
        $token = bin2hex(random_bytes(32));
        $tickets[$token] = time();
        $_SESSION['tickets'] = $tickets;
        reply(200, ['ok' => true, 'token' => $token, 'siteKey' => $config['recaptcha_site_key'], 'mode' => $config['mode']]);
    }
    if (strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])) !== 'application/json') {
        throw new FormError(415, 'Format de requête non autorisé.');
    }
    if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 24000) { throw new FormError(413, 'Le message est trop volumineux.'); }
    $raw = file_get_contents('php://input', false, null, 0, 24001);
    if ($raw === false || strlen($raw) > 24000) { throw new FormError(413, 'Le message est trop volumineux.'); }
    try { $data = json_decode($raw, true, 16, JSON_THROW_ON_ERROR); }
    catch (JsonException) { throw new FormError(400, 'Le formulaire est illisible.'); }
    if (!is_array($data) || array_is_list($data)) { throw new FormError(400, 'Le formulaire est illisible.'); }
    $token = $data['token'] ?? '';
    $issued = is_string($token) ? ($_SESSION['tickets'][$token] ?? null) : null;
    if (!$issued || time() - $issued > 3600) { throw new FormError(403, 'Le formulaire a expiré. Veuillez réessayer.'); }
    unset($_SESSION['tickets'][$token]); // ticket à usage unique, même si le contrôle suivant échoue.
    if (time() - $issued < 3 || ($data['website'] ?? null) !== '') {
        throw new FormError(422, 'La vérification anti-spam a échoué. Veuillez réessayer.');
    }
    $message = validated_message($data);
    $captcha = field($data, 'captcha', 1, 4096);
    verify_recaptcha($config['recaptcha_secret'], $captcha, $url['host']);
    rate_limit($storage, $config['app_key'], [['email', strtolower($message['email']), 86400, 5]]);
    if ($config['mode'] === 'simulation') {
        reply(200, ['ok' => true, 'simulation' => true, 'message' => 'Test réussi : formulaire vérifié, aucun email envoyé.']);
    }
    send_message($config, $message);
    reply(200, ['ok' => true, 'simulation' => false, 'message' => 'Votre message a bien été envoyé.']);
} catch (Throwable $error) {
    if ($error instanceof FormError) {
        if ($error->status === 429) { header('Retry-After: 3600'); }
        reply($error->status, ['ok' => false, 'message' => $error->getMessage()]);
    }
    $reference = bin2hex(random_bytes(6));
    // Liste fermée : jamais le texte brut d’une exception, un chemin ou une valeur de configuration.
    $safeCodes = ['php_version', 'php_extension_curl', 'php_extension_mbstring', 'php_extension_openssl',
        'php_extension_session', 'private_location', 'library_unreadable', 'config_unreadable',
        'config_syntax', 'configuration', 'origin_configuration', 'storage', 'ip', 'session_storage',
        'session', 'rate_lock', 'rate_size', 'rate_data', 'rate_write', 'smtp_configuration', 'smtp_tls'];
    $code = get_class($error) === RuntimeException::class && in_array($error->getMessage(), $safeCodes, true)
        ? $error->getMessage() : 'internal';
    // Aucune donnée de formulaire ni exception SMTP (qui pourrait contenir un secret) dans les logs.
    error_log('melina-form incident=' . $reference . ' code=' . $code . ' type=' . get_class($error));
    reply(503, ['ok' => false, 'message' => 'L’envoi est temporairement indisponible. Référence : ' . $reference,
        'code' => $code]);
}
