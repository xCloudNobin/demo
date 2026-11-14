#!/usr/bin/env php
<?php
/**
 * Give Doctrine a numeric Postgres serverVersion without printing secrets.
 * Managed DATABASE_URL may contain "serverVersion=" with an empty value, which
 * overrides config/packages/doctrine.yaml and crashes schema:update.
 */
function has_numeric_sv(string $u): bool
{
    return (bool) preg_match('/serverVersion=\d+/', $u);
}

function with_sv(string $u): string
{
    if (has_numeric_sv($u)) {
        return $u;
    }
    $n = 0;
    $u = preg_replace('/serverVersion=[^&]*/', 'serverVersion=16', $u, 1, $n);
    if (!$n) {
        $u .= (str_contains($u, '?') ? '&' : '?').'serverVersion=16&charset=utf8';
    }

    return $u;
}

$envUrl = getenv('DATABASE_URL') ?: '';
fwrite(STDERR, 'DATABASE_URL_in_process='.($envUrl !== '' ? 'yes' : 'no').' process_has_numeric_sv='.(has_numeric_sv($envUrl) ? 'yes' : 'no')."\n");

if (is_file('.env')) {
    $c = file_get_contents('.env');
    fwrite(STDERR, 'dotenv_has_DATABASE_URL='.(preg_match('/^DATABASE_URL=/m', $c) ? 'yes' : 'no').' dotenv_has_numeric_sv='.(preg_match('/^DATABASE_URL=.*serverVersion=\d+/m', $c) ? 'yes' : 'no')."\n");
    $c2 = preg_replace_callback('/^DATABASE_URL=(.*)$/m', function ($m) {
        $v = trim($m[1], "\"'");
        if ($v === '') {
            return $m[0];
        }

        return 'DATABASE_URL="'.with_sv($v).'"';
    }, $c, 1);
    if (is_string($c2) && $c2 !== $c) {
        file_put_contents('.env', $c2);
        fwrite(STDERR, "dotenv_updated=yes\n");
    }
}

$yaml = is_file('config/packages/doctrine.yaml') ? file_get_contents('config/packages/doctrine.yaml') : '';
fwrite(STDERR, 'doctrine_yaml_server_version='.(preg_match("/server_version:\\s*'?[0-9]/", $yaml) ? 'yes' : 'no')."\n");

$export = getenv('XCLOUD_EXPORT_FILE') ?: '';
if ($export !== '' && $envUrl !== '') {
    $fixed = with_sv($envUrl);
    if ($fixed !== $envUrl) {
        file_put_contents($export, 'export DATABASE_URL='.escapeshellarg($fixed).PHP_EOL);
        fwrite(STDERR, "process_export_written=yes\n");
    }
}
