<?php

declare(strict_types=1);

namespace PLUS\GrumPHPConfig\Composer;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\InstalledVersions;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\ScriptEvents;
use Composer\Semver\VersionParser;
use PLUS\GrumPHPConfig\VersionUtility;
use Symfony\Component\Yaml\Yaml;

final class Plugin implements PluginInterface, EventSubscriberInterface
{
    private Composer $composer;

    private IOInterface $io;

    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->composer = $composer;
        $this->io = $io;
    }

    /**
     * {@inheritdoc}
     */
    public function deactivate(Composer $composer, IOInterface $io): void
    {
    }

    /**
     * {@inheritdoc}
     */
    public function uninstall(Composer $composer, IOInterface $io): void
    {
    }

    /**
     * @return array<string, string|array{0:string, 1:int}>
     */
    public static function getSubscribedEvents(): array
    {
        $priority = 1000;
        // priority higher than phpro/grumphp so it dose not ask if you want to create a grumphp.yml,
        // as we do that for you
        return [
            ScriptEvents::POST_UPDATE_CMD => ['heavyProcessing', $priority],
            ScriptEvents::POST_INSTALL_CMD => ['heavyProcessing', $priority],

            ScriptEvents::POST_AUTOLOAD_DUMP => 'simpleProcessing',
        ];
    }

    public function heavyProcessing(): void
    {
        $this->removeOldConfigPath();
        $this->installTypo3Dependencies();
        $this->createGrumphpConfig();
        $this->createPhpStanConfig();

        $this->simpleProcessing();
    }

    public function simpleProcessing(): void
    {
        $this->createRectorConfig();
    }

    private function removeOldConfigPath(): void
    {
        $rootPackage = $this->composer->getPackage();
        $extra = $rootPackage->getExtra();
        $configSource = $this->composer->getConfig()->getConfigSource();

        $configDefaultPath = $extra['grumphp']['config-default-path'] ?? '';
        if (in_array($configDefaultPath, ['grumphp.yml', 'vendor/pluswerk/grumphp-config/grumphp.yml'])) {
            unset($extra['grumphp']['config-default-path']);
            $configSource->removeProperty('extra.grumphp.config-default-path');
            $this->message('removed extra.grumphp.config-default-path', 'yellow');
            if (empty($extra['grumphp'])) {
                unset($extra['grumphp']);
                $configSource->removeProperty('extra.grumphp');
                $this->message('removed extra.grumphp', 'yellow');
            }
        }

        if (isset($extra['pluswerk/grumphp-config'])) {
            unset($extra['pluswerk/grumphp-config']);
            $configSource->removeProperty('extra.pluswerk/grumphp-config');
            $this->message('removed extra.pluswerk/grumphp-config', 'yellow');
        }

        $rootPackage->setExtra($extra);
    }

    private function installTypo3Dependencies(): void
    {
        if (!InstalledVersions::isInstalled('typo3/cms-core')) {
            return;
        }

        $typo3RelatedPackages = VersionUtility::getRequire('typo3');

        $changed = false;
        foreach ($typo3RelatedPackages as $package => $version) {
            if (!InstalledVersions::isInstalled($package) || !InstalledVersions::satisfies(new VersionParser(), $package, $version)) {
                $this->composer->getConfig()->getConfigSource()->addLink('require-dev', $package, $version);
                $this->message(sprintf('installing %s in version %s for pluswerk/grumphp-config', $package, $version), 'yellow');
                $changed = true;
            }
        }

        if ($changed) {
            passthru('composer update -W --no-scripts ' . implode(' ', array_keys($typo3RelatedPackages)));
        }
    }

    private function createRectorConfig(): void
    {
        if (!file_exists(getcwd() . '/rector.php')) {
            copy(dirname(__DIR__, 2) . '/rector.php', getcwd() . '/rector.php');
            $this->message('rector.php file created', 'yellow');
        }
    }

    private function createGrumphpConfig(): void
    {
        $grumphpPath = getcwd() . '/grumphp.yml';
        $grumphpTemplatePath = dirname(__DIR__, 2) . '/grumphp.yml';
        if (!file_exists($grumphpPath)) {
            $defaultImport = [
                'imports' => [
                    ['resource' => 'vendor/pluswerk/grumphp-config/grumphp.yml'],
                ],
            ];
            file_put_contents($grumphpPath, Yaml::dump($defaultImport, 2, 2));
            $this->message('grumphp.yml file created', 'yellow');
        }

        $data = Yaml::parseFile($grumphpPath);
        assert(is_array($data));
        $data['parameters'] ??= [];
        assert(is_array($data['parameters']));

        if (($data['imports'][0]['resource'] ?? '') !== 'vendor/pluswerk/grumphp-config/grumphp.yml') {
            return;
        }

        $changed = false;

        $templateData = Yaml::parseFile($grumphpTemplatePath);
        assert(is_array($templateData));
        $templateData['parameters'] ??= [];
        assert(is_array($templateData['parameters']));
        foreach ($templateData['parameters'] as $key => $value) {
            if (!str_starts_with((string)$key, 'convention.')) {
                continue;
            }

            if (array_key_exists((string)$key, $data['parameters'])) {
                continue;
            }

            $data['parameters'][$key] = $value;
            $changed = true;
        }

        if ($changed) {
            file_put_contents($grumphpPath, Yaml::dump($data, 2, 2));
            $this->message('added some default conventions to grumphp.yml', 'yellow');
        }
    }

    private function createPhpStanConfig(): void
    {
        $fileNames = ['phpstan.neon', 'phpstan-baseline.neon'];
        foreach ($fileNames as $fileName) {
            if (!file_exists(getcwd() . '/' . $fileName)) {
                copy(dirname(__DIR__, 2) . '/' . $fileName, getcwd() . '/' . $fileName);
                $this->message($fileName . ' file created', 'yellow');
            }
        }
    }

    // HELPER:

    private function message(string $message, ?string $color = null): void
    {
        $colorStart = '';
        $colorEnd = '';
        if ($color) {
            $colorStart = '<fg=' . $color . '>';
            $colorEnd = '</fg=' . $color . '>';
        }

        $this->io->write('pluswerk/grumphp-config: ' . $colorStart . $message . $colorEnd);
    }
}
