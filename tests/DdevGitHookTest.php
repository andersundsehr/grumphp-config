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
 * PATH contains only these fakes, `git`, `cat` and `sed`, so a real `ddev` on the computer cannot change the result.
 *
 * The fakes do not start a container. For the commands with the paths of a linked worktree in the container,
 * assertPathsInTheContainerExist() replaces /var/www/html with the main worktree, as DDEV mounts it, and checks the
 * paths there.
 */
#[CoversNothing]
final class DdevGitHookTest extends TestCase
{
    /**
     * Logs one line for each call: the name, the exported COMMIT_MSG_FILE and the arguments. The ASCII unit separator
     * separates the fields, because the paths in the tests contain spaces and brackets.
     */
    private const FAKE_COMMAND = <<<'SH'
        #!/bin/sh
        cat > /dev/null
        {
          printf '%s\037%s' "${0##*/}" "$COMMIT_MSG_FILE"
          for arg in "$@"; do printf '\037%s' "$arg"; done
          printf '\n'
        } >> "$HOOK_CALLS"
        SH;

    private const SEPARATOR = "\037";

    private const DDEV = 'ddev';

    private const WITHOUT_DDEV = 'php';

    private const CONTAINER_ROOT = '/var/www/html';

    private const COMMIT_MESSAGE = '[TASK] Change a file';

    private const DDEV_EXEC = ['ddev', 'exec', 'FORCE_COLOR=1', 'php'];

    private static string $renderDir;

    /**
     * @var array<string, string> The hooks directory for each directory of GrumPHP in the git repository.
     */
    private static array $hooksDirs = [];

    private string $dir;

    private string $project;

    public static function setUpBeforeClass(): void
    {
        self::$renderDir = self::createTempDir();
        self::$hooksDirs = [];
    }

    public static function tearDownAfterClass(): void
    {
        self::removeDir(self::$renderDir);
    }

    protected function setUp(): void
    {
        $this->dir = self::createTempDir();
        mkdir($this->dir . '/bin');
        mkdir($this->dir . '/home');
        $this->linkCommand('git');
        $this->linkCommand('cat');
        $this->linkCommand('sed');
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
        yield 'name with a backslash' => ['back\tslash'];
    }

    #[Test]
    #[DataProvider('projectNames')]
    public function mainWorktreeRunsGrumphpInTheDefaultDirectoryOfTheDdevContainer(string $projectName): void
    {
        $this->project = $this->dir . '/' . $projectName;
        $this->createProject();

        $this->commitIn($this->project);

        $this->assertHooksStartGrumphpWith(self::DDEV_EXEC, '.git/COMMIT_EDITMSG');
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function worktreesInsideTheProject(): iterable
    {
        yield 'worktree of Claude Code' => ['project', '.claude/worktrees/feature', 'feature'];
        yield 'worktree in the project root' => ['project', 'feature', 'feature'];
        yield 'project name with a space and glob characters' => ['my project [1] *', 'feature', 'feature'];
        yield 'project name with a backslash' => ['back\tslash', 'feature', 'feature'];
        yield 'worktree name with all permitted special characters' => ['project', 'a_b.c+d@e,f-g', 'a_b.c+d@e,f-g'];
    }

    #[Test]
    #[DataProvider('worktreesInsideTheProject')]
    public function worktreeInsideTheProjectRunsGrumphpWithTheWorktreeInTheDdevContainer(
        string $projectName,
        string $worktreePath,
        string $gitDirName,
    ): void {
        $this->project = $this->dir . '/' . $projectName;
        $this->createProject();
        $worktree = $this->addWorktree($this->project . '/' . $worktreePath);

        $this->commitIn($worktree);

        $containerDir = self::CONTAINER_ROOT . '/' . $worktreePath;
        $containerGitDir = self::CONTAINER_ROOT . '/.git/worktrees/' . $gitDirName;
        $this->assertHooksStartGrumphpWith(
            [
                'ddev',
                'exec',
                '--dir',
                $containerDir,
                'GIT_DIR=' . $containerGitDir,
                'GIT_WORK_TREE=' . $containerDir,
                'GRUMPHP_GIT_REPOSITORY_DIR=' . $containerGitDir,
                'FORCE_COLOR=1',
                'php',
            ],
            $containerGitDir . '/COMMIT_EDITMSG',
        );
        $this->assertPathsInTheContainerExist($worktree, $worktree);
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
        $this->assertPathsInTheContainerExist($worktree, $worktree);
    }

    /**
     * GrumPHP in a subdirectory of the git repository: the hook changes into this directory before it starts GrumPHP.
     */
    #[Test]
    public function worktreeWithGrumphpInASubdirectoryRunsGrumphpInThisSubdirectoryOfTheWorktree(): void
    {
        $this->createProject(grumphpDir: 'app');
        $worktree = $this->addWorktree($this->project . '/.claude/worktrees/feature');

        $this->commitIn($worktree);

        $this->assertHooksStartGrumphpWith(
            [
                'ddev',
                'exec',
                '--dir',
                '/var/www/html/.claude/worktrees/feature/app',
                'GIT_DIR=/var/www/html/.git/worktrees/feature',
                'GIT_WORK_TREE=/var/www/html/.claude/worktrees/feature',
                'GRUMPHP_GIT_REPOSITORY_DIR=/var/www/html/.git/worktrees/feature',
                'FORCE_COLOR=1',
                'php',
            ],
            '/var/www/html/.git/worktrees/feature/COMMIT_EDITMSG',
        );
        $this->assertPathsInTheContainerExist($worktree, $worktree . '/app');
    }

    #[Test]
    public function mainWorktreeWithGrumphpInASubdirectoryRunsGrumphpInTheDefaultDirectoryOfTheDdevContainer(): void
    {
        $this->createProject(grumphpDir: 'app');

        $this->commitIn($this->project);

        $this->assertHooksStartGrumphpWith(self::DDEV_EXEC, '.git/COMMIT_EDITMSG');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function pathsThatBashChanges(): iterable
    {
        yield 'space in the worktree' => ['.claude/worktrees/my feature', ''];
        yield 'tab in the worktree' => [".claude/worktrees/my\tfeature", ''];
        yield '* in the worktree' => ['feature*', ''];
        yield '? in the worktree' => ['feature?', ''];
        yield '[ in the worktree' => ['feature[1]', ''];
        yield 'backslash in the worktree' => ['back\cslash', ''];
        yield '$ in the worktree' => ['feature$x', ''];
        yield 'quote in the worktree' => ["feature'x", ''];
        yield '~ in the worktree' => ['feature~1', ''];
        yield '; in the worktree' => ['feature;x', ''];
        yield 'space in the directory of GrumPHP' => ['feature', 'my app'];
        yield 'backslash in the directory of GrumPHP' => ['feature', 'app\cdir'];
    }

    /**
     * The hook splits $DDEV_EXEC into words, and bash in the container reads the words again. Then the worktree is
     * not in the arguments, and ddev exec runs GrumPHP in the main worktree, as before.
     */
    #[Test]
    #[DataProvider('pathsThatBashChanges')]
    public function worktreeWithCharactersThatBashChangesRunsGrumphpInTheDefaultDirectoryOfTheDdevContainer(
        string $worktreePath,
        string $grumphpDir,
    ): void {
        $this->createProject(grumphpDir: $grumphpDir);
        $worktree = $this->addWorktree($this->project . '/' . $worktreePath);

        $this->commitIn($worktree);

        $this->assertHooksStartGrumphpWith(self::DDEV_EXEC, $this->gitDirOf($worktree) . '/COMMIT_EDITMSG');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function worktreesOutsideTheProject(): iterable
    {
        yield 'other directory' => ['project', 'outside'];
        yield 'directory with the project name as prefix' => ['project', 'project-feature'];
        yield 'project name with a backslash' => ['back\tslash', 'outside'];
    }

    /**
     * The DDEV container does not contain a worktree outside the project. The command is the same as before.
     */
    #[Test]
    #[DataProvider('worktreesOutsideTheProject')]
    public function worktreeOutsideTheProjectRunsGrumphpInTheDefaultDirectoryOfTheDdevContainer(
        string $projectName,
        string $worktreePath,
    ): void {
        $this->project = $this->dir . '/' . $projectName;
        $this->createProject();
        $worktree = $this->addWorktree($this->dir . '/' . $worktreePath);

        $this->commitIn($worktree);

        $this->assertHooksStartGrumphpWith(
            self::DDEV_EXEC,
            $this->project . '/.git/worktrees/' . $worktreePath . '/COMMIT_EDITMSG',
        );
    }

    /**
     * The main worktree is no DDEV project, e.g. on an old branch.
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
            self::DDEV_EXEC,
            $this->project . '/.git/worktrees/feature/COMMIT_EDITMSG',
        );
    }

    /**
     * The git dir is in the home directory, so the hook looks for the DDEV project in the home directory. There, ~/.ddev
     * is the global configuration of DDEV and no project.
     */
    #[Test]
    public function mainWorktreeWithItsGitDirInTheHomeDirectoryRunsGrumphpInTheDefaultDirectoryOfTheDdevContainer(): void
    {
        mkdir($this->dir . '/home/.ddev');
        file_put_contents($this->dir . '/home/.ddev/global_config.yaml', "instrumentation_opt_in: false\n");
        mkdir($this->dir . '/home/www');
        $this->project = $this->dir . '/home/www/project';
        $this->createProject(separateGitDir: $this->dir . '/home/project.git');

        $this->commitIn($this->project);

        $this->assertHooksStartGrumphpWith(self::DDEV_EXEC, $this->dir . '/home/project.git/COMMIT_EDITMSG');
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

    private function createProject(bool $withDdev = true, string $grumphpDir = '', ?string $separateGitDir = null): void
    {
        mkdir($this->project);
        $initArguments = $separateGitDir === null ? ['init', '-q'] : ['init', '-q', '--separate-git-dir=' . $separateGitDir];
        $this->git($initArguments, $this->project);
        file_put_contents($this->project . '/README.md', "# Test\n");
        if ($withDdev) {
            mkdir($this->project . '/.ddev');
            file_put_contents($this->project . '/.ddev/config.yaml', "name: test\n");
        }

        if ($grumphpDir !== '') {
            mkdir($this->project . '/' . $grumphpDir);
            file_put_contents($this->project . '/' . $grumphpDir . '/composer.json', "{}\n");
        }

        $this->git(['add', '-A'], $this->project);
        $this->git(['commit', '-q', '-m', '[TASK] Initial commit'], $this->project);
        $hooksDir = $this->gitDirOf($this->project) . '/hooks';
        foreach (['pre-commit', 'commit-msg'] as $hook) {
            copy($this->hooksDir($grumphpDir) . '/' . $hook, $hooksDir . '/' . $hook);
            chmod($hooksDir . '/' . $hook, 0755);
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
        $this->git(['commit', '-q', '-m', self::COMMIT_MESSAGE], $checkout, $env);
    }

    private function gitDirOf(string $checkout): string
    {
        return trim($this->git(['rev-parse', '--absolute-git-dir'], $checkout));
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
        self::assertSame('', $this->call('git:pre-commit')['commitMessageFile'], 'The pre-commit hook has no commit message.');
    }

    /**
     * Replaces /var/www/html with the main worktree, as DDEV mounts it, and checks the paths that the hooks give.
     */
    private function assertPathsInTheContainerExist(string $worktree, string $grumphpDir): void
    {
        $arguments = $this->call('git:commit-msg')['arguments'];
        self::assertSame('--dir', $arguments[1], 'ddev exec has no --dir: ' . implode(' ', $arguments));
        $hostDir = $this->onTheHost($arguments[2]);
        $hostGitDir = $this->onTheHost($this->valueOf('GIT_DIR', $arguments));
        $hostWorkTree = $this->onTheHost($this->valueOf('GIT_WORK_TREE', $arguments));

        self::assertSame(realpath($grumphpDir), realpath($hostDir), '--dir is not the directory of GrumPHP.');
        self::assertSame(realpath($worktree), realpath($hostWorkTree), 'GIT_WORK_TREE is not the worktree.');
        self::assertSame($this->gitDirOf($worktree), realpath($hostGitDir), 'GIT_DIR is not the git dir of the worktree.');
        self::assertSame($hostGitDir, $this->onTheHost($this->valueOf('GRUMPHP_GIT_REPOSITORY_DIR', $arguments)));
        $gitArguments = ['--git-dir=' . $hostGitDir, '--work-tree=' . $hostWorkTree, 'status', '--porcelain'];
        $status = $this->git($gitArguments, $hostWorkTree);
        self::assertSame('', $status, 'git does not see the commit of the worktree with GIT_DIR and GIT_WORK_TREE.');
        $commitMessage = (string)file_get_contents($this->onTheHost($this->lastArgumentOf('git:commit-msg')));
        self::assertStringStartsWith(self::COMMIT_MESSAGE, $commitMessage);
    }

    private function onTheHost(string $containerPath): string
    {
        self::assertStringStartsWith(self::CONTAINER_ROOT . '/', $containerPath);
        return $this->project . substr($containerPath, strlen(self::CONTAINER_ROOT));
    }

    /**
     * @param list<string> $arguments
     */
    private function valueOf(string $variable, array $arguments): string
    {
        foreach ($arguments as $argument) {
            if (str_starts_with($argument, $variable . '=')) {
                return substr($argument, strlen($variable) + 1);
            }
        }

        throw new RuntimeException('The arguments do not set ' . $variable . ': ' . implode(' ', $arguments), 1764180001);
    }

    /**
     * @return array<string, list<string>> The command up to `php` for each GrumPHP command that a hook started.
     */
    private function grumphpStarts(): array
    {
        $starts = [];
        foreach ($this->calls() as $call) {
            $starts[$this->findGrumphpCommand($call['arguments'])] = $this->commandUpToPhp($call['name'], $call['arguments']);
        }

        return $starts;
    }

    private function lastArgumentOf(string $grumphpCommand): string
    {
        $arguments = $this->call($grumphpCommand)['arguments'];
        return (string)end($arguments);
    }

    /**
     * @return array{name: string, arguments: list<string>, commitMessageFile: string}
     */
    private function call(string $grumphpCommand): array
    {
        foreach ($this->calls() as $call) {
            if ($this->findGrumphpCommand($call['arguments']) === $grumphpCommand) {
                return $call;
            }
        }

        throw new RuntimeException('No hook started GrumPHP with ' . $grumphpCommand, 1764180002);
    }

    /**
     * @return list<array{name: string, arguments: list<string>, commitMessageFile: string}> The calls of the fakes.
     */
    private function calls(): array
    {
        $log = $this->dir . '/calls.log';
        self::assertFileExists($log, 'No hook started GrumPHP.');
        $calls = [];
        foreach (file($log, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            $fields = explode(self::SEPARATOR, $line);
            self::assertGreaterThanOrEqual(2, count($fields), 'Unexpected line in the log: ' . $line);
            $calls[] = ['name' => $fields[0], 'arguments' => array_slice($fields, 2), 'commitMessageFile' => $fields[1]];
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

        throw new RuntimeException('The call has no GrumPHP command: ' . implode(' ', $arguments), 1764180003);
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
    private function git(array $arguments, string $cwd, array $env = []): string
    {
        return self::runProcess(['git', ...$arguments], $cwd, $env + [
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
     * GrumPHP creates the hooks once for each directory of GrumPHP: the root of the repository or a subdirectory.
     */
    private function hooksDir(string $grumphpDir): string
    {
        if (isset(self::$hooksDirs[$grumphpDir])) {
            return self::$hooksDirs[$grumphpDir];
        }

        $repository = self::$renderDir . '/' . count(self::$hooksDirs);
        mkdir($repository);
        self::runProcess(['git', 'init', '-q', $repository], self::$renderDir, self::realEnv());
        $projectDir = $grumphpDir === '' ? $repository : $repository . '/' . $grumphpDir;
        if ($grumphpDir !== '') {
            mkdir($projectDir);
        }

        file_put_contents($projectDir . '/grumphp.yml', sprintf("imports:\n  - { resource: '%s/../grumphp.yml' }\n", __DIR__));
        self::runProcess(
            [PHP_BINARY, __DIR__ . '/../vendor/bin/grumphp', 'git:init', '--config=' . $projectDir . '/grumphp.yml'],
            $projectDir,
            self::realEnv() + ['GRUMPHP_BIN_DIR' => __DIR__ . '/../vendor/bin'],
        );
        self::$hooksDirs[$grumphpDir] = $repository . '/.git/hooks';
        return self::$hooksDirs[$grumphpDir];
    }

    /**
     * @return array<string, string> The environment of the test without the variables that change git, GrumPHP or
     *                               the hooks. git sets GIT_DIR, for example, if PHPUnit runs in a git hook. With
     *                               GIT_DIR, `git init` changes the repository of GIT_DIR instead of a new one.
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
        return realpath($dir) ?: throw new RuntimeException('Could not create ' . $dir, 1764180004);
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
            throw new RuntimeException('Could not start ' . implode(' ', $command), 1764180005);
        }

        fclose($pipes[0]);
        // The commands write only a few lines, so reading stdout first cannot block on a full stderr pipe.
        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
        if ($exitCode !== 0) {
            $message = sprintf("%s failed with exit code %d:\n%s", implode(' ', $command), $exitCode, $output);
            throw new RuntimeException($message, 1764180006);
        }

        return $output;
    }
}
