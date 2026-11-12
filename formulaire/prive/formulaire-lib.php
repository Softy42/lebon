<?php
declare(strict_types=1);

final class FormError extends RuntimeException
{
    public function __construct(public int $status, string $message) { parent::__construct($message); }
}

function field(array $data, string $key, int $min, int $max, bool $multiline = false): string
{
    $value = $data[$key] ?? '';
    if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) {
        throw new FormError(422, 'Un champ du formulaire est invalide.');
    }
    $value = trim($value);
    $pattern = $multiline ? '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u' : '/[\x00-\x1F\x7F]/u';
    if (mb_strlen($value, 'UTF-8') < $min || mb_strlen($value, 'UTF-8') > $max || preg_match($pattern, $value)) {
        throw new FormError(422, 'Vérifiez les champs du formulaire et leur longueur.');
    }
    return $value;
}

function validated_message(array $data): array
{
    $out = [
        'name' => field($data, 'name', 2, 120),
        'email' => field($data, 'email', 3, 254),
        'telephone' => field($data, 'telephone', 6, 40),
        'subject' => field($data, 'subject', 1, 30),
        'message' => field($data, 'message', 1, 5000, true),
    ];
    if (!filter_var($out['email'], FILTER_VALIDATE_EMAIL)
        || !preg_match('/\A[+0-9().\s-]+\z/u', $out['telephone'])
        || preg_match_all('/[0-9]/', $out['telephone']) < 6
        || !isset(subjects()[$out['subject']])) {
        throw new FormError(422, 'Vérifiez l’adresse email, le téléphone et le sujet.');
    }
    $houses = $data['maisons'] ?? [];
    if (!is_array($houses) || !array_is_list($houses) || count($houses) > count(houses())) {
        throw new FormError(422, 'La sélection des maisons est invalide.');
    }
    foreach ($houses as $house) {
        if (!is_string($house) || !isset(houses()[$house])) {
            throw new FormError(422, 'La sélection des maisons est invalide.');
        }
    }
    $out['maisons'] = $out['subject'] === 'visite' ? array_values(array_unique($houses)) : [];
    if ($out['subject'] === 'visite' && !$out['maisons']) {
        throw new FormError(422, 'Veuillez sélectionner au moins une maison.');
    }
    return $out;
}

function subjects(): array
{
    return ['information' => 'Demande d’informations', 'visite' => 'Demande de visite',
        'projet' => 'Projet de maison partagée', 'emploi' => 'Demande d’emploi', 'autre' => 'Autre'];
}

function houses(): array
{
    return ['1' => 'Feurs', '2' => 'Saint-Chamond', '3' => 'Saint-Didier-en-Velay',
        '4' => 'Saint-Just-Saint-Rambert', '5' => 'Châteauroux', '6' => 'Montrond-les-Bains',
        'savigneux' => 'Maison EDMOND de Savigneux'];
}

// Fichier verrouillé : limites partagées par tous les processus PHP et toutes les sessions.
// Les adresses réseau sont hachées avec une clé privée, jamais conservées en clair.
function rate_limit(string $dir, string $key, array $limits): void
{
    $f = fopen($dir . '/limites.json', 'c+');
    if ($f === false || !flock($f, LOCK_EX)) { throw new RuntimeException('rate_lock'); }
    try {
        $raw = stream_get_contents($f, 2000001);
        if ($raw === false || strlen($raw) > 2000000) { throw new RuntimeException('rate_size'); }
        $state = $raw === '' ? [] : json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($state)) { throw new RuntimeException('rate_data'); }
        $now = time();
        foreach ($state as $id => $entry) {
            if (!is_array($entry) || ($entry['until'] ?? 0) <= $now) { unset($state[$id]); }
        }
        foreach ($limits as [$scope, $identifier, $window, $max]) {
            $id = hash_hmac('sha256', $scope . ':' . $identifier, $key);
            $entry = $state[$id] ?? ['until' => $now + $window, 'count' => 0];
            if ($entry['count'] >= $max) { throw new FormError(429, 'Trop de tentatives. Veuillez réessayer plus tard.'); }
        }
        foreach ($limits as [$scope, $identifier, $window, $max]) {
            $id = hash_hmac('sha256', $scope . ':' . $identifier, $key);
            $state[$id] ??= ['until' => $now + $window, 'count' => 0];
            $state[$id]['count']++;
        }
        $encoded = json_encode($state, JSON_THROW_ON_ERROR);
        if (strlen($encoded) > 1900000) { throw new FormError(503, 'Service temporairement indisponible.'); }
        rewind($f);
        if (!ftruncate($f, 0) || fwrite($f, $encoded) !== strlen($encoded) || !fflush($f)) {
            throw new RuntimeException('rate_write');
        }
    } finally { flock($f, LOCK_UN); fclose($f); }
}

function verify_recaptcha(string $secret, string $token, string $hostname): void
{
    $ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query([
        'secret' => $secret, 'response' => $token,
    ]), CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 12,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2]);
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if (!is_string($body) || $status !== 200) { throw new FormError(503, 'La vérification anti-spam est momentanément indisponible.'); }
    $result = json_decode($body, true);
    if (!is_array($result) || ($result['success'] ?? false) !== true
        || ($result['hostname'] ?? '') !== $hostname) {
        throw new FormError(422, 'La vérification anti-spam a expiré ou a échoué. Veuillez la renouveler.');
    }
}

function mail_recipients(mixed $value): array
{
    // Conserve la compatibilité avec l’adresse unique utilisée en préproduction.
    $addresses = is_string($value) ? [$value] : $value;
    if (!is_array($addresses) || !array_is_list($addresses) || !$addresses || count($addresses) > 10) {
        throw new RuntimeException('smtp_configuration');
    }
    $recipients = [];
    foreach ($addresses as $address) {
        if (!is_string($address) || !filter_var($address, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('smtp_configuration');
        }
        $recipients[strtolower($address)] = $address;
    }
    return array_values($recipients);
}

function send_message(array $config, array $data): void
{
    foreach (['Exception', 'PHPMailer', 'SMTP'] as $class) {
        require_once __DIR__ . '/lib/PHPMailer/' . $class . '.php';
    }
    $smtp = $config['smtp'];
    foreach (['host', 'username', 'password', 'from'] as $key) {
        if (!is_string($smtp[$key] ?? null) || $smtp[$key] === '') { throw new RuntimeException('smtp_configuration'); }
    }
    $recipients = mail_recipients($smtp['to'] ?? null);
    if (!(($smtp['encryption'] === 'smtps' && $smtp['port'] === 465)
        || ($smtp['encryption'] === 'starttls' && $smtp['port'] === 587))) { throw new RuntimeException('smtp_tls'); }
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $smtp['host'];
    $mail->Port = $smtp['port'];
    $mail->SMTPAuth = true;
    $mail->Username = $smtp['username'];
    $mail->Password = $smtp['password'];
    $mail->SMTPSecure = $smtp['encryption'] === 'smtps' ? 'ssl' : 'tls';
    $mail->SMTPOptions = ['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'allow_self_signed' => false]];
    $mail->Timeout = 15;
    $mail->CharSet = 'UTF-8';
    $mail->setFrom($smtp['from'], 'Maison Mélina');
    foreach ($recipients as $recipient) { $mail->addAddress($recipient); }
    $mail->addReplyTo($data['email']);
    $mail->Subject = 'Contact Site Maison Mélina de: ' . $data['name'];
    $labels = ['name' => 'Nom', 'email' => 'Email', 'telephone' => 'Téléphone', 'subject' => 'Sujet',
        'maisons' => 'Maisons', 'message' => 'Message'];
    $values = $data;
    $values['subject'] = subjects()[$data['subject']];
    $values['maisons'] = implode(', ', array_map(fn($id) => houses()[$id], $data['maisons']));
    $html = '<h1 style="background:#b42c2d;color:white;padding:16px">Contact Site Maison Mélina</h1><table>';
    $text = '';
    foreach ($labels as $key => $label) {
        $text .= $label . ': ' . $values[$key] . "\n\n";
        $html .= '<tr><th style="text-align:left;padding-bottom:16px">' . $label . '</th><td style="padding-bottom:16px">'
            . nl2br(htmlspecialchars($values[$key], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) . '</td></tr>';
    }
    $mail->isHTML(true);
    $mail->Body = $html . '</table>';
    $mail->AltBody = $text;
    $mail->send();
}
