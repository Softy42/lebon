<?php
declare(strict_types=1);
require __DIR__ . '/../prive/formulaire-lib.php';
$count = 0;
function check(bool $condition, string $label): void {
    global $count;
    if (!$condition) { throw new RuntimeException('ÉCHEC : ' . $label); }
    $count++;
}
function rejected(callable $action, int $status): void {
    try { $action(); } catch (FormError $error) { check($error->status === $status, 'statut du refus'); return; }
    throw new RuntimeException('ÉCHEC : requête acceptée à tort');
}
$valid = ['name' => 'Élodie Martin', 'email' => 'elodie@example.org', 'telephone' => '+33 (0)6 12 34 56 78',
    'subject' => 'visite', 'message' => "Bonjour,\nJe souhaite visiter.", 'maisons' => ['1', 'savigneux']];
check(validated_message($valid)['maisons'] === ['1', 'savigneux'], 'accents, téléphone, plusieurs maisons');
foreach ([['email', "a@example.org\r\nBcc: b@example.org"], ['name', ['array']], ['subject', 'unknown'],
    ['maisons', []], ['maisons', ['99']], ['maisons', [1]], ['message', str_repeat('é', 5001)],
    ['email', 'pas-un-email'], ['telephone', 'abcdef123456'], ['message', "a\0b"]] as [$key, $value]) {
    rejected(fn() => validated_message(array_replace($valid, [$key => $value])), 422);
}
check(validated_message(array_replace($valid, ['subject' => 'autre']))['maisons'] === [], 'maisons hors visite ignorées');
check(mail_recipients('contact@so-online.fr') === ['contact@so-online.fr'], 'configuration de test compatible');
$productionRecipients = ['info@maison-melina.fr', 'contact@so-online.fr', 'christine.thevenon2@wanadoo.fr'];
check(mail_recipients($productionRecipients) === $productionRecipients, 'trois destinataires de production');
check(count(mail_recipients(['contact@so-online.fr', 'CONTACT@SO-ONLINE.FR'])) === 1, 'doublons destinataires supprimés');
foreach ([null, [], ['pas-un-email'], ['contact@so-online.fr', "a@example.org\r\nBcc: b@example.org"],
    ['contact@so-online.fr', ['array']], ['to' => 'contact@so-online.fr']] as $invalidRecipients) {
    try { mail_recipients($invalidRecipients); }
    catch (RuntimeException $error) {
        check($error->getMessage() === 'smtp_configuration', 'destinataires invalides refusés');
        continue;
    }
    throw new RuntimeException('ÉCHEC : destinataires invalides acceptés');
}
$dir = sys_get_temp_dir() . '/melina-tests-' . bin2hex(random_bytes(8));
mkdir($dir, 0700);
try {
    $key = str_repeat('a', 64);
    rate_limit($dir, $key, [['ip', '192.0.2.1', 3600, 1]]);
    rejected(fn() => rate_limit($dir, $key, [['ip', '192.0.2.1', 3600, 1]]), 429);
    rate_limit($dir, $key, [['ip', '192.0.2.2', 3600, 1]]);
    check(!str_contains(file_get_contents($dir . '/limites.json'), '192.0.2.'), 'aucune IP en clair');
    file_put_contents($dir . '/limites.json', json_encode(['old' => ['until' => time() - 1, 'count' => 100]]));
    rate_limit($dir, $key, [['ip', '192.0.2.1', 3600, 1]]);
    check(!isset(json_decode(file_get_contents($dir . '/limites.json'), true)['old']), 'purge des limites expirées');
} finally { unlink($dir . '/limites.json'); rmdir($dir); }
echo $count . " vérifications réussies. Aucun email ni appel réseau effectué.\n";
