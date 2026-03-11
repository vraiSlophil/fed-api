<?php

use Symfony\Component\Process\Process;

it('forces sqlite docs generation environment variables', function () {
    $tempRoot = sys_get_temp_dir().'/fed-api-docs-script-'.bin2hex(random_bytes(8));
    $binDirectory = $tempRoot.'/bin';
    $logFile = $tempRoot.'/commands.log';

    mkdir($binDirectory, 0777, true);

    $stub = <<<'SH'
#!/usr/bin/env sh
{
    printf 'command=%s\n' "$0"
    printf 'args=%s\n' "$*"
    printf 'DB_CONNECTION=%s\n' "${DB_CONNECTION:-}"
    printf 'DB_DATABASE=%s\n' "${DB_DATABASE:-}"
    printf 'GENERATING_API_DOCS=%s\n' "${GENERATING_API_DOCS:-}"
} >> "${DOCS_SCRIPT_LOG}"
exit 0
SH;

    file_put_contents($binDirectory.'/php', $stub);
    chmod($binDirectory.'/php', 0755);
    $process = new Process(
        ['sh', './scripts/generate-api-docs.sh'],
        base_path(),
        [
            'PATH' => $binDirectory.':'.(getenv('PATH') ?: ''),
            'DOCS_SCRIPT_LOG' => $logFile,
            'DB_CONNECTION' => 'pgsql',
            'DB_DATABASE' => '/tmp/dev-postgres.db',
        ],
    );

    $process->run();

    if (! $process->isSuccessful()) {
        throw new RuntimeException($process->getErrorOutput().$process->getOutput());
    }

    $log = file_get_contents($logFile);

    expect($log)
        ->toContain('DB_CONNECTION=sqlite')
        ->toContain('DB_DATABASE=/tmp/fed-api-docs.sqlite')
        ->toContain('GENERATING_API_DOCS=true');
});

it('preserves an explicit docs base url while forcing sqlite docs generation variables', function () {
    $tempRoot = sys_get_temp_dir().'/fed-api-docs-script-'.bin2hex(random_bytes(8));
    $binDirectory = $tempRoot.'/bin';
    $logFile = $tempRoot.'/commands.log';

    mkdir($binDirectory, 0777, true);

    $stub = <<<'SH'
#!/usr/bin/env sh
{
    printf 'DOCS_API_BASE_URL=%s\n' "${DOCS_API_BASE_URL:-}"
    printf 'DB_CONNECTION=%s\n' "${DB_CONNECTION:-}"
    printf 'DB_DATABASE=%s\n' "${DB_DATABASE:-}"
} >> "${DOCS_SCRIPT_LOG}"
exit 0
SH;

    file_put_contents($binDirectory.'/php', $stub);
    chmod($binDirectory.'/php', 0755);
    $process = new Process(
        ['sh', './scripts/generate-api-docs.sh'],
        base_path(),
        [
            'PATH' => $binDirectory.':'.(getenv('PATH') ?: ''),
            'DOCS_SCRIPT_LOG' => $logFile,
            'DOCS_API_BASE_URL' => 'https://docs.example.test',
            'DB_CONNECTION' => 'pgsql',
            'DB_DATABASE' => '/tmp/dev-postgres.db',
        ],
    );

    $process->run();

    if (! $process->isSuccessful()) {
        throw new RuntimeException($process->getErrorOutput().$process->getOutput());
    }

    $log = file_get_contents($logFile);

    expect($log)
        ->toContain('DOCS_API_BASE_URL=https://docs.example.test')
        ->toContain('DB_CONNECTION=sqlite')
        ->toContain('DB_DATABASE=/tmp/fed-api-docs.sqlite');
});
