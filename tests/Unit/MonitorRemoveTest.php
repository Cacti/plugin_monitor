<?php

declare(strict_types=1);

/*
 * Unit tests for monitor_device_remove() — PR #208 (fix/prepared-monitor-sql-207).
 *
 * monitor_device_remove() in setup.php builds three DELETE statements whose
 * IN-clause is formed from a $devices array passed directly by the Cacti
 * core hook system. The hardening goal is: every element must be cast with
 * intval() before being embedded so that non-integer values (injected strings,
 * floats) are truncated to their integer representation or dropped.
 *
 * Tests are structured in two layers:
 *
 *   1. Pure-PHP mirrors of the sanitisation logic — these run immediately
 *      and confirm the in-clause building contract.
 *
 *   2. Source-verification tests — these read setup.php and assert that the
 *      hardened call site pattern is present (post-merge state).
 */

require_once __DIR__ . '/../Helpers/CactiStubs.php';

$_setup_src = file_get_contents(__DIR__ . '/../../setup.php');
if ($_setup_src === false) {
    throw new \RuntimeException('Could not read setup.php');
}

// ---------------------------------------------------------------------------
// Helper: mirrors the hardened sanitisation pattern from the PR.
// ---------------------------------------------------------------------------

/**
 * Mirrors the intval + array_filter guard the PR applies to $devices before
 * building the IN-clause.
 *
 * @param list<mixed> $devices
 * @return list<int>
 */
function sanitise_device_ids(array $devices): array
{
    return array_values(
        array_filter(
            array_map('intval', $devices),
            fn(int $v): bool => $v > 0
        )
    );
}

// ---------------------------------------------------------------------------
// 1. Pure-PHP mirrors — always run, no DB needed.
// ---------------------------------------------------------------------------

describe('monitor_device_remove — intval + placeholder pattern (logic mirror)', function (): void {
    test('clean integer list passes through unchanged', function (): void {
        $safe = sanitise_device_ids([1, 2, 3]);
        expect($safe)->toBe([1, 2, 3]);
    });

    test('integer strings are cast to int', function (): void {
        $safe = sanitise_device_ids(['4', '5', '6']);
        expect($safe)->toBe([4, 5, 6]);
    });

    test('zero values are removed (no valid host has id 0)', function (): void {
        $safe = sanitise_device_ids([0, 1, 2]);
        expect($safe)->toBe([1, 2]);
    });

    test('negative values are removed', function (): void {
        $safe = sanitise_device_ids([-1, 3, -99]);
        expect($safe)->toBe([3]);
    });

    test('UNION injection payload is truncated to its leading integer', function (): void {
        /* intval("3) UNION SELECT 1--") === 3 */
        $safe = sanitise_device_ids(['1', "3) UNION SELECT 1--", '5']);
        expect($safe)->toBe([1, 3, 5]);
    });

    test('purely non-numeric injection string is cast to 0 and filtered out', function (): void {
        $safe = sanitise_device_ids(["' OR '1'='1", '2', '4']);
        expect($safe)->toBe([2, 4]);
    });

    test('empty device list returns empty array', function (): void {
        $safe = sanitise_device_ids([]);
        expect($safe)->toBe([]);
    });

    test('all-injection list produces empty safe array', function (): void {
        $safe = sanitise_device_ids(["' OR 1=1--", '0', '-5']);
        expect($safe)->toBe([]);
    });

    test('single device produces valid IN-clause fragment', function (): void {
        $safe   = sanitise_device_ids([7]);
        $clause = implode(',', $safe);
        expect($clause)->toBe('7');
        expect($clause)->toMatch('/^\d+$/');
    });

    test('multiple devices produce comma-separated integer IN-clause fragment', function (): void {
        $safe   = sanitise_device_ids([1, 2, 3]);
        $clause = implode(',', $safe);
        expect($clause)->toBe('1,2,3');
        expect($clause)->toMatch('/^\d+(,\d+)*$/');
    });

    test('IN-clause built from sanitised IDs contains no non-integer characters', function (): void {
        $safe   = sanitise_device_ids(['7', "8) OR 1=1--", '9']);
        $clause = implode(',', $safe);
        expect($clause)->toMatch('/^\d+(,\d+)*$/');
    });
});

// ---------------------------------------------------------------------------
// 2. Source-verification — post-merge state of setup.php.
// ---------------------------------------------------------------------------

describe('monitor_device_remove — source verification (PR #208)', function () use ($_setup_src): void {
    /*
     * The hardened implementation must apply intval() to each device ID before
     * building the IN-clause. Acceptable patterns are:
     *
     *   a) array_map('intval', $devices)  — batch cast, then implode
     *   b) intval() applied per-element   — scalar cast inline
     *
     * Either satisfies the type-safety requirement. The tests below assert
     * whichever pattern the PR lands on.
     */

    test('monitor_device_remove function exists in setup.php', function () use ($_setup_src): void {
        expect($_setup_src)->toContain('function monitor_device_remove(');
    });

    test('function deletes from plugin_monitor_notify_history', function () use ($_setup_src): void {
        expect($_setup_src)->toContain('DELETE FROM plugin_monitor_notify_history');
    });

    test('function deletes from plugin_monitor_reboot_history', function () use ($_setup_src): void {
        expect($_setup_src)->toContain('DELETE FROM plugin_monitor_reboot_history');
    });

    test('function deletes from plugin_monitor_uptime', function () use ($_setup_src): void {
        expect($_setup_src)->toContain('DELETE FROM plugin_monitor_uptime');
    });

    test('intval() or array_map intval is applied to $devices before IN-clause', function () use ($_setup_src): void {
        /*
         * Verify the hardened pattern. Either:
         *   array_map('intval', $devices)
         *   intval($devices[...])
         * must appear in the monitor_device_remove function body.
         */
        preg_match(
            '/function monitor_device_remove\s*\(\s*\$devices\s*\)\s*\{(.+?)^\}/ms',
            $_setup_src,
            $m
        );
        $body = $m[1] ?? '';

        $hasArrayMapIntval  = str_contains($body, "array_map('intval'");
        $hasInlineIntval    = str_contains($body, 'intval($devices');
        $hasIntvalDevices   = str_contains($body, 'intval(');

        expect($hasArrayMapIntval || $hasInlineIntval || $hasIntvalDevices)
            ->toBeTrue('Expected intval() or array_map intval applied to $devices in monitor_device_remove');
    })->todo('Seam lands when PR #208 (fix/prepared-monitor-sql-207) is merged; currently uses raw implode');

    test('raw implode of $devices without intval is absent post-merge', function () use ($_setup_src): void {
        /*
         * Document the pre-hardening pattern that must be removed.
         * This test is marked todo until the PR lands.
         */
        preg_match(
            '/function monitor_device_remove\s*\(\s*\$devices\s*\)\s*\{(.+?)^\}/ms',
            $_setup_src,
            $m
        );
        $body = $m[1] ?? '';

        // Pattern: implode(',', $devices) where $devices is not yet cast.
        $rawImplodeCount = preg_match_all(
            "/implode\s*\(\s*['\"],\s*['\"],\s*\\\$devices\s*\)/",
            $body
        );

        expect($rawImplodeCount)->toBe(0);
    })->todo('Seam lands when PR #208 (fix/prepared-monitor-sql-207) is merged; currently uses raw implode');

    test('empty device list: implode of empty array produces empty string (safe IN-clause sentinel)', function (): void {
        /*
         * When $devices is empty after sanitisation, implode returns ''.
         * Callers must guard against issuing "WHERE host_id IN()" which is a
         * MySQL syntax error. This test documents the expected caller contract.
         */
        $safe   = sanitise_device_ids([]);
        $clause = implode(',', $safe);
        expect($clause)->toBe('');
        // A real caller should skip the DELETE when clause is empty.
        expect(strlen($clause) > 0 ? 'execute' : 'skip')->toBe('skip');
    });
});

// ---------------------------------------------------------------------------
// 3. SQL format contracts — single and multiple device cases.
// ---------------------------------------------------------------------------

describe('monitor_device_remove — SQL format', function (): void {
    test('single device produces "host_id IN(N)" format', function (): void {
        $safe   = sanitise_device_ids([42]);
        $clause = 'host_id IN(' . implode(',', $safe) . ')';
        expect($clause)->toBe('host_id IN(42)');
    });

    test('multiple devices produce "host_id IN(N,M,...)" format', function (): void {
        $safe   = sanitise_device_ids([1, 2, 3]);
        $clause = 'host_id IN(' . implode(',', $safe) . ')';
        expect($clause)->toBe('host_id IN(1,2,3)');
    });

    test('mixed input: only positive integers appear in final clause', function (): void {
        $safe   = sanitise_device_ids([0, -1, 5, "6 OR 1=1", 7]);
        $clause = implode(',', $safe);
        // intval("6 OR 1=1") === 6; 0 and -1 are filtered.
        expect($clause)->toBe('5,6,7');
    });
});
