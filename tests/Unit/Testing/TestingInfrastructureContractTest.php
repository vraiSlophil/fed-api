<?php

function testingRepositoryRoot(): string
{
    return dirname(__DIR__, 3);
}

function testingRepositoryPath(string $path): string
{
    return testingRepositoryRoot().'/'.$path;
}

function testingRepositoryFileContents(string $path): string
{
    $contents = file_get_contents(testingRepositoryPath($path));

    if ($contents === false) {
        throw new RuntimeException("Unable to read repository file [{$path}].");
    }

    return $contents;
}

function testingRepositoryPhpFiles(array $directories): array
{
    $paths = [];

    foreach ($directories as $directory) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                testingRepositoryPath($directory),
                FilesystemIterator::SKIP_DOTS
            )
        );

        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo || ! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $paths[] = $file->getPathname();
        }
    }

    sort($paths);

    return $paths;
}

it('uses the canonical composer test wrapper', function () {
    $composer = json_decode(
        testingRepositoryFileContents('composer.json'),
        true,
        512,
        JSON_THROW_ON_ERROR
    );

    expect($composer['scripts']['test'])
        ->toBe(['sh ./scripts/test.sh'])
        ->and(testingRepositoryFileContents('scripts/test.sh'))
        ->toContain('php artisan config:clear --ansi')
        ->toContain('exec php artisan test "$@"');
});

it('keeps postgres as the committed normal test backend', function () {
    $phpUnit = testingRepositoryFileContents('phpunit.xml');
    $testingEnvironment = testingRepositoryFileContents('.env.testing');
    $exampleEnvironment = testingRepositoryFileContents('.env.example');

    expect($phpUnit)
        ->toContain('failOnWarning="true"')
        ->toContain('failOnRisky="true"')
        ->toContain('failOnEmptyTestSuite="true"')
        ->not->toContain('DB_CONNECTION" value="sqlite"')
        ->not->toContain('DB_DATABASE" value=":memory:"');

    expect($testingEnvironment)
        ->toContain('APP_ENV=testing')
        ->toContain('DB_CONNECTION=pgsql')
        ->toContain('DB_DATABASE=fed_test');

    expect($exampleEnvironment)->not->toContain('DB_TEST_DATABASE=');
});

it('keeps backend CI aligned with composer test and without broad seed steps', function () {
    $backendWorkflow = testingRepositoryFileContents('.github/workflows/tests-backend.yml');
    $ciWorkflow = testingRepositoryFileContents('.github/workflows/ci.yml');

    expect($backendWorkflow)
        ->toContain('POSTGRES_DB: fed_test')
        ->toContain('run: composer test')
        ->not->toContain('db:seed')
        ->not->toContain('seed:');

    expect($ciWorkflow)
        ->toContain('uses: ./.github/workflows/tests-backend.yml')
        ->not->toContain('seed: ${{');
});

it('forbids implicit test seeding and lingering debug helpers in repository code', function () {
    $debugOffenders = [];
    $seedingOffenders = [];
    $currentFile = realpath(__FILE__);

    foreach (testingRepositoryPhpFiles(['app', 'tests']) as $path) {
        if (realpath($path) === $currentFile) {
            continue;
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("Unable to read repository file [{$path}].");
        }

        $relativePath = str_replace(testingRepositoryRoot().'/', '', $path);

        if (preg_match('/\b(?:dd|dump|ray)\s*\(/', $contents) === 1) {
            $debugOffenders[] = $relativePath;
        }

        if (str_starts_with($relativePath, 'tests/')
            && preg_match('/\$this->seed\(\s*\)|protected\s+\$seed\s*=\s*true|protected\s+\$seeder\s*=/', $contents) === 1) {
            $seedingOffenders[] = $relativePath;
        }
    }

    expect($debugOffenders)->toBeEmpty()
        ->and($seedingOffenders)->toBeEmpty();
});
