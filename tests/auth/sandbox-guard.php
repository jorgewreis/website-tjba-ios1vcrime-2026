<?php
declare(strict_types=1);

// Fail closed before any config or PDO load. APP_ENV, hostname and the
// .isolated-runner marker are not proof: only a short-lived detached Ed25519
// signature over the versioned sandbox predicate payload is accepted.

function sandbox_attestation_reject(): void
{
    throw new RuntimeException('sandbox descartável não comprovado');
}

if (!function_exists('sodium_crypto_sign_verify_detached')
    || !defined('SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES')
    || !defined('SODIUM_CRYPTO_SIGN_BYTES')) {
    sandbox_attestation_reject();
}

$sandboxPublicKey = require __DIR__ . '/sandbox-attestation-public.php';
if (!is_string($sandboxPublicKey) || strlen($sandboxPublicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
    sandbox_attestation_reject();
}

$sandboxPayloadRaw = getenv('AUTH_TEST_ATTESTATION_PAYLOAD');
$sandboxSignatureRaw = getenv('AUTH_TEST_ATTESTATION_SIGNATURE');
if (!is_string($sandboxPayloadRaw) || $sandboxPayloadRaw === '' || !is_string($sandboxSignatureRaw) || $sandboxSignatureRaw === '') {
    sandbox_attestation_reject();
}
$sandboxPayload = base64_decode($sandboxPayloadRaw, true);
$sandboxSignature = base64_decode($sandboxSignatureRaw, true);
if (!is_string($sandboxPayload) || $sandboxPayload === ''
    || !is_string($sandboxSignature) || strlen($sandboxSignature) !== SODIUM_CRYPTO_SIGN_BYTES) {
    sandbox_attestation_reject();
}

try {
    $sandboxSignatureValid = sodium_crypto_sign_verify_detached($sandboxSignature, $sandboxPayload, $sandboxPublicKey);
} catch (Throwable $sandboxAttestationError) {
    sandbox_attestation_reject();
}
if ($sandboxSignatureValid !== true) {
    sandbox_attestation_reject();
}

if (str_contains($sandboxPayload, "\r")) {
    sandbox_attestation_reject();
}
$sandboxLines = explode("\n", $sandboxPayload);
$sandboxExpectedKeys = [
    'v', 'iat', 'exp', 'run_id', 'network_internal', 'network_id',
    'mysql_id', 'app_id', 'client_id', 'mailpit_id', 'browser_id',
    'binds', 'ports', 'privileged', 'publish_all_ports', 'mysql_tmpfs',
];
if (count($sandboxLines) !== count($sandboxExpectedKeys)) {
    sandbox_attestation_reject();
}
$sandboxClaims = [];
foreach ($sandboxLines as $sandboxIndex => $sandboxLine) {
    $sandboxEq = strpos($sandboxLine, '=');
    if ($sandboxEq === false) {
        sandbox_attestation_reject();
    }
    $sandboxKey = substr($sandboxLine, 0, $sandboxEq);
    $sandboxValue = substr($sandboxLine, $sandboxEq + 1);
    if ($sandboxKey !== $sandboxExpectedKeys[$sandboxIndex] || $sandboxValue === '') {
        sandbox_attestation_reject();
    }
    $sandboxClaims[$sandboxKey] = $sandboxValue;
}

$sandboxDecimal = '/^(0|[1-9][0-9]{0,17})$/';
$sandboxId64 = '/^[a-f0-9]{64}$/';
if ($sandboxClaims['v'] !== '1'
    || preg_match($sandboxDecimal, $sandboxClaims['iat']) !== 1
    || preg_match($sandboxDecimal, $sandboxClaims['exp']) !== 1
    || preg_match('/^[a-f0-9]{32}$/', $sandboxClaims['run_id']) !== 1
    || $sandboxClaims['network_internal'] !== 'true'
    || preg_match($sandboxId64, $sandboxClaims['network_id']) !== 1
    || preg_match($sandboxId64, $sandboxClaims['mysql_id']) !== 1
    || preg_match($sandboxId64, $sandboxClaims['app_id']) !== 1
    || preg_match($sandboxId64, $sandboxClaims['client_id']) !== 1
    || preg_match($sandboxId64, $sandboxClaims['mailpit_id']) !== 1
    || ($sandboxClaims['browser_id'] !== 'none' && preg_match($sandboxId64, $sandboxClaims['browser_id']) !== 1)
    || $sandboxClaims['binds'] !== '0'
    || $sandboxClaims['ports'] !== '0'
    || $sandboxClaims['privileged'] !== 'false'
    || $sandboxClaims['publish_all_ports'] !== 'false'
    || $sandboxClaims['mysql_tmpfs'] !== 'true') {
    sandbox_attestation_reject();
}

$sandboxIat = (int) $sandboxClaims['iat'];
$sandboxExp = (int) $sandboxClaims['exp'];
$sandboxNow = time();
if ($sandboxExp !== $sandboxIat + 600 || $sandboxIat > $sandboxNow + 30 || $sandboxNow >= $sandboxExp) {
    sandbox_attestation_reject();
}

if (getenv('APP_ENV') !== 'testing') {
    sandbox_attestation_reject();
}

$sandboxCgroup = '';
foreach (['/proc/self/cgroup', '/proc/1/cgroup'] as $sandboxCgroupPath) {
    if (!is_file($sandboxCgroupPath)) {
        continue;
    }
    $sandboxCgroupText = file_get_contents($sandboxCgroupPath);
    if (is_string($sandboxCgroupText)) {
        $sandboxCgroup .= $sandboxCgroupText . "\n";
    }
}
if (preg_match('/(?:docker[-\/]|\/containers\/)([a-f0-9]{64})/', $sandboxCgroup, $sandboxCgroupMatch) !== 1
    && preg_match('/docker-([a-f0-9]{64})\.scope/', $sandboxCgroup, $sandboxCgroupMatch) !== 1) {
    sandbox_attestation_reject();
}
$sandboxContainerId = $sandboxCgroupMatch[1];
$sandboxAllowedIds = [$sandboxClaims['app_id'], $sandboxClaims['client_id']];
if ($sandboxClaims['browser_id'] !== 'none') {
    $sandboxAllowedIds[] = $sandboxClaims['browser_id'];
}
if (!in_array($sandboxContainerId, $sandboxAllowedIds, true)) {
    sandbox_attestation_reject();
}
