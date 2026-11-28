<?php
declare(strict_types=1);

/** @param list<string> $allowedIds */
function sandbox_attestation_target_matches_cgroup(
    string $cgroup,
    string $targetId,
    array $allowedIds,
    string $expectedNonceHash,
    string $noncePath
): bool
{
    if (preg_match('/^[a-f0-9]{64}$/', $targetId) !== 1
        || preg_match('/^[a-f0-9]{64}$/', $expectedNonceHash) !== 1
        || !in_array($targetId, $allowedIds, true)) {
        return false;
    }

    $nonce = @file_get_contents($noncePath);
    if (!is_string($nonce) || !hash_equals($expectedNonceHash, hash('sha256', $nonce))) {
        return false;
    }

    if (preg_match('/(?:docker[-\/]|\/containers\/)([a-f0-9]{64})/', $cgroup, $match) === 1
        || preg_match('/docker-([a-f0-9]{64})\.scope/', $cgroup, $match) === 1) {
        return hash_equals($targetId, $match[1]);
    }

    // Private cgroup v2 exposes 0::/; the signed target is scoped by docker exec.
    return preg_match('/(?:^|\n)0::\/(?:\n|$)/', $cgroup) === 1;
}

function sandbox_attestation_mount_namespace_matches(string $expected, string $actual): bool
{
    return preg_match('/^mnt:\[[0-9]+\]$/', $expected) === 1
        && preg_match('/^mnt:\[[0-9]+\]$/', $actual) === 1
        && hash_equals($expected, $actual);
}
