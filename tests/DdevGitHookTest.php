<?php

declare(strict_types=1);

namespace PLUS\GrumPHPConfig\Tests;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * The tests let GrumPHP create the git hooks from the shared grumphp.yml and commit in real git repositories.
 * Fake `ddev` and `php` commands write their arguments to a log, so the tests see how the hooks start GrumPHP.
 * PATH contains only these fakes, `git` and `cat`, so a real `ddev` on the computer cannot change the result.
 */
#[CoversNothing]
final class DdevGitHookTest extends TestCase
{
    private const FAKE_COMMAND = <<<'SH'
        #!/bin/sh
        cat > /dev/null
        { printf '%s' "${0##*/}"; for arg in "$@"; do printf ' [%s]' "$arg"; done; printf '\n'; } >> "$HOOK_CALLS"
        SH;

    private const DDEV = 'ddev';

    private const WITHOUT_DDEV = 'php';

    private static string $hooksDir;

    private string $dir;

    private string $project;

    public static function setUpBeforeClass(): void
    {
        $dir = self::createTempDir();
        self::runProcess(['git', 'init', '-q', $dir], $dir, self::realEnv());
        file_put_contents($dir . '/grumphp.yml', sprintf("imports:\n  - { resource: '%s/../grumphp.yml' }\n", __DIR__));
        self::runProcess(
            [PHP_BINARY, __DIR__ . '/../vendor/bin/grumphp', 'git:init', '--config=' . $dir . '/grumphp.yml'],
            $dir,
            self::realEnv() + ['GRUMPHP_BIN_DIR' => __DIR__ . '/../vendor/bin'],
        );
        self::$hooksDir = $dir . '/.git/hooks';
    }

    public static function tearDownAfterClass(): void
    {
        self::removeDir(dirname(self::$hooksDir, 2));
    }

    protected function setUp(): void
    {
        $this->dir = self::createTempDir();
        mkdir($this->dir . '/bin');
        mkdir($this->dir . '/home');
        $this->linkCommand('git');
        $this->linkCommand('cat');
        $this->addFakeCommand('ddev');
        $this->addFakeCommand('php');
        $this->project = $this->dir . '/project';
    }

    protected function tearDown(): void
    {
        self::removeDir($this->dir);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function projectNames(): iterable
    {
        yield 'simple name' => ['project'];
        yield 'name with a space and glob characters' => ['my project [1] *'];
    }

    #[Test]
    #[DataProvider('projectNames')]
    public function mainWorktreeRunsGrumphpInTheDefaultDirectoryOfTheDdevContainer(string $projectName): void
    {
        $this->project = $this->dir . '/' . $projectName;
        $this->createProject();

        $this->commitIn($this->project);

        $this->assertHooksStartGrumphpWith(['ddev', 'exec', 'FORCE_COLOR=1', 'php'], '.git/COMMIT_EDITMSG');
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function worktreesInsideTheProject(): iterable
    {
        yield 'worktree of Claude Code' => ['project', '.claude/worktrees/feature', '/var/www/html/.claude/worktrees/feature'];
        yield 'worktree in the project root' => ['project', 'feature', '/var/www/html/feature'];
        yield 'project name with a space and glob characters' => ['my project [1] *', 'feature', '/var/www/html/feature'];
    }

    #[Test]
    #[DataProvider('worktreesInsideTheProject')]
    public function worktreeInsideTheProjectRunsGrumphpWithTheWorktreeInTheDdevContainer(
        string $projectName,
        string $worktreePath,
        string $containerDir,
    ): void {
        $this->project = $this->dir . '/' . $projectName;
        $this->createProject();
        $worktree = $this->addWorktree($this->project . '/' . $worktreePath);

        $this->commitIn($worktree);

        $this->assertHooksStartGrumphpWith(
            [
                'ddev',
                'exec',
                '--dir',
                $containerDir,
                'GIT_DIR=/var/www/html/.git/worktrees/feature',
                'GIT_WORK_TREE=' . $containerDir,
                'GRUMPHP_GIT_REPOSITORY_DIR=/var/www/html/.git/worktrees/feature',
                'FORCE_COLOR=1',
                'php',
            ],
            '/var/www/html/.git/worktrees/feature/COMMIT_EDITMSG',
        );
    }

    #[Test]
    public function worktreeReachedThroughASymbolicLinkRunsGrumphpWithTheWorktreeInTheDdevContainer(): void
    {
        $this->createProject();
        symlink($this->project, $this->dir . '/link');
        $worktree = $this->addWorktree($this->dir . '/link/.claude/worktrees/feature', $this->dir . '/link');

        $this->commitIn($worktree);

        $this->assertHooksStartGrumphpWith(
            [
                'ddev',
                'exec',
                '--dir',
                '/var/www/html/.claude/worktrees/feature',
                'GIT_DIR=/var/www/html/.git/worktrees/feature',
                'GIT_WORK_TREE=/var/www/html/.claude/worktrees/feature',
                'GRUMPHP_GIT_REPOSITORY_DIR=/var/www/html/.git/worktrees/feature',
                'FORCE_COLOR=1',
                'php',
            ],
            '/var/www/html/.git/worktrees/feature/COMMIT_EDITMSG',
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function worktreesOutsideTheProject(): iterable
    {
        yield 'other directory' => ['outside'];
        yield 'directory with the project name as prefix' => ['project-feature'];
    }

    /**
     * The DDEV container does not contain a worktree outside the project. DDEV selects the project for this worktree.
     */
    #[Test]
    #[DataProvider('worktreesOutsideTheProject')]
    public function worktreeOutsideTheProjectRunsGrumphpInTheDefaultDirectoryOfTheDdevContainer(string $worktreePath): void
    {
        $this->createProject();
        $worktree = $this->addWorktree($this->dir . '/' . $worktreePath);

        $this->commitIn($worktree);

        $this->assertHooksStartGrumphpWith(
            ['ddev', 'exec', 'FORCE_COLOR=1', 'php'],
            $this->project . '/.git/worktrees/' . $worktreePath . '/COMMIT_EDITMSG',
        );
    }

    /**
     * The main worktree is no DDEV project, e.g. on an old branch. DDEV selects the project for this worktree.
     */
    #[Test]
    public function worktreeOfAMainWorktreeWithoutDdevRunsGrumphpInTheDefaultDirectoryOfTheDdevContainer(): void
    {
        $this->createProject();
        $this->git(['rm', '-q', '-r', '.ddev'], $this->project);
        $this->git(['commit', '-q', '--no-verify', '-m', '[TASK] Remove DDEV'], $this->project);
        $worktree = $this->addWorktree($this->project . '/.claude/worktrees/feature', $this->project, 'HEAD~1');

        $this->commitIn($worktree);

        $this->assertHooksStartGrumphpWith(
            ['ddev', 'exec', 'FORCE_COLOR=1', 'php'],
            $this->project . '/.git/worktrees/feature/COMMIT_EDITMSG',
        );
    }

    #[Test]
    public function worktreeInsideTheDdevContainerRunsGrumphpWithPhp(): void
    {
        $this->createProject();
        $worktree = $this->addWorktree($this->project . '/.claude/worktrees/feature');

        $this->commitIn($worktree, ['IS_DDEV_PROJECT' => 'true']);

        $this->assertHooksStartGrumphpWith(['php'], $this->project . '/.git/worktrees/feature/COMMIT_EDITMSG');
    }

    #[Test]
    public function worktreeWithoutDdevFolderRunsGrumphpWithPhp(): void
    {
        $this->createProject(withDdev: false);
        $worktree = $this->addWorktree($this->project . '/.claude/worktrees/feature');

        $this->commitIn($worktree);

        $this->assertHooksStartGrumphpWith(['php'], $this->project . '/.git/worktrees/feature/COMMIT_EDITMSG');
    }

    #[Test]
    public function worktreeWithoutDdevCommandRunsGrumphpWithPhp(): void
    {
        unlink($this->dir . '/bin/ddev');
        $this->createProject();
        $worktree = $this->addWorktree($this->project . '/.claude/worktrees/feature');

        $this->commitIn($worktree);

        $this->assertHooksStartGrumphpWith(['php'], $this->project . '/.git/worktrees/feature/COMMIT_EDITMSG');
    }

    private function createProject(bool $withDdev = true): void
    {
        mkdir($this->project);
        $this->git(['init', '-q'], $this->project);
        file_put_contents($this->project . '/README.md', "# Test\n");
        if ($withDdev) {
            mkdir($this->project . '/.ddev');
            file_put_contents($this->project . '/.ddev/config.yaml', "name: test\n");
        }

        $this->git(['add', '-A'], $this->project);
        $this->git(['commit', '-q', '-m', '[TASK] Initial commit'], $this->project);
        foreach (['pre-commit', 'commit-msg'] as $hook) {
            copy(self::$hooksDir . '/' . $hook, $this->project . '/.git/hooks/' . $hook);
            chmod($this->project . '/.git/hooks/' . $hook, 0755);
        }
    }

    private function addWorktree(string $path, ?string $cwd = null, string $commit = 'HEAD'): string
    {
        $this->git(['worktree', 'add', '-q', '-b', 'feature', $path, $commit], $cwd ?? $this->project);
        return $path;
    }

    /**
     * @param array<string, string> $env
     */
    private function commitIn(string $checkout, array $env = []): void
    {
        file_put_contents($checkout . '/change.txt', "change\n");
        $this->git(['add', 'change.txt'], $checkout);
        $this->git(['commit', '-q', '-m', '[TASK] Change a file'], $checkout, $env);
    }

    /**
     * @param list<string> $command The command that starts GrumPHP, up to `php`.
     * @param string $commitMessageFile The path of the commit message that the commit-msg hook gives to GrumPHP.
     */
    private function assertHooksStartGrumphpWith(array $command, string $commitMessageFile): void
    {
        self::assertSame(
            [
                'git:pre-commit' => $command,
                'git:commit-msg' => $command,
            ],
            $this->grumphpStarts(),
        );
        self::assertSame($commitMessageFile, $this->lastArgumentOf('git:commit-msg'));
    }

    /**
     * @return array<string, list<string>> The command up to `php` for each GrumPHP command that a hook started.
     */
    private function grumphpStarts(): array
    {
        $starts = [];
        foreach ($this->calls() as [$name, $arguments]) {
            $starts[$this->findGrumphpCommand($arguments)] = $this->commandUpToPhp($name, $arguments);
        }

        return $starts;
    }

    private function lastArgumentOf(string $grumphpCommand): string
    {
        foreach ($this->calls() as [, $arguments]) {
            if ($this->findGrumphpCommand($arguments) === $grumphpCommand) {
                return (string)end($arguments);
            }
        }

        throw new RuntimeException('No hook started GrumPHP with ' . $grumphpCommand);
    }

    /**
     * @return list<array{string, list<string>}> The name and the arguments of each call of a fake command.
     */
    private function calls(): array
    {
        $log = $this->dir . '/calls.log';
        self::assertFileExists($log, 'No hook started GrumPHP.');
        $calls = [];
        foreach (file($log, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            preg_match_all('/ \[([^\]]*)\]/', $line, $matches);
            $calls[] = [(string)strtok($line, ' '), $matches[1]];
        }

        return $calls;
    }

    /**
     * @param list<string> $arguments
     */
    private function findGrumphpCommand(array $arguments): string
    {
        foreach ($arguments as $argument) {
            if (str_starts_with($argument, 'git:')) {
                return $argument;
            }
        }

        throw new RuntimeException('The call has no GrumPHP command: ' . implode(' ', $arguments));
    }

    /**
     * @param list<string> $arguments
     * @return list<string>
     */
    private function commandUpToPhp(string $name, array $arguments): array
    {
        if ($name === self::WITHOUT_DDEV) {
            return [self::WITHOUT_DDEV];
        }

        self::assertSame(self::DDEV, $name);
        $phpPosition = array_search('php', $arguments, true);
        self::assertIsInt($phpPosition, 'ddev did not start php: ' . implode(' ', $arguments));
        return [$name, ...array_slice($arguments, 0, $phpPosition + 1)];
    }

    /**
     * @param list<string> $arguments
     * @param array<string, string> $env
     */
    private function git(array $arguments, string $cwd, array $env = []): void
    {
        self::runProcess(['git', ...$arguments], $cwd, $env + [
            'PATH' => $this->dir . '/bin',
            'PWD' => $cwd,
            'HOME' => $this->dir . '/home',
            'GIT_CONFIG_NOSYSTEM' => '1',
            'GIT_AUTHOR_NAME' => 'Test',
            'GIT_AUTHOR_EMAIL' => 'test@example.com',
            'GIT_COMMITTER_NAME' => 'Test',
            'GIT_COMMITTER_EMAIL' => 'test@example.com',
            'HOOK_CALLS' => $this->dir . '/calls.log',
        ]);
    }

    private function linkCommand(string $name): void
    {
        $path = trim(self::runProcess(['sh', '-c', 'command -v ' . $name], $this->dir, self::realEnv()));
        symlink($path, $this->dir . '/bin/' . $name);
    }

    private function addFakeCommand(string $name): void
    {
        file_put_contents($this->dir . '/bin/' . $name, self::FAKE_COMMAND . "\n");
        chmod($this->dir . '/bin/' . $name, 0755);
    }

    /**
     * @return array<string, string> The environment of the test without the variables that change git, GrumPHP or
     *                               the hooks. git sets GIT_DIR, for example, if PHPUnit runs in a git hook.
     */
    private static function realEnv(): array
    {
        $env = [];
        foreach (getenv() as $name => $value) {
            if ($name === 'IS_DDEV_PROJECT') {
                continue;
            }

            if (str_starts_with($name, 'GIT_') || str_starts_with($name, 'GRUMPHP_')) {
                continue;
            }

            $env[$name] = $value;
        }

        return $env;
    }

    /**
     * @return string The path without symbolic links, because git gives paths without them.
     */
    private static function createTempDir(): string
    {
        $dir = sys_get_temp_dir() . '/grumphp-config-test-' . bin2hex(random_bytes(8));
        mkdir($dir);
        return realpath($dir) ?: throw new RuntimeException('Could not create ' . $dir);
    }

    private static function removeDir(string $dir): void
    {
        self::runProcess(['rm', '-rf', $dir], sys_get_temp_dir(), self::realEnv());
    }

    /**
     * @param list<string> $command
     * @param array<string, string> $env
     */
    private static function runProcess(array $command, string $cwd, array $env): string
    {
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $cwd, $env);
        if (!is_resource($process)) {
            throw new RuntimeException('Could not start ' . implode(' ', $command));
        }

        fclose($pipes[0]);
        // The commands write only a few lines, so reading stdout first cannot block on a full stderr pipe.
        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
        if ($exitCode !== 0) {
            throw new RuntimeException(sprintf("%s failed with exit code %d:\n%s", implode(' ', $command), $exitCode, $output));
        }

        return $output;
    }
}
