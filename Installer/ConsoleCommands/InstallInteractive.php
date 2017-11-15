<?php
namespace Neos\Installer\ConsoleCommands;

use Composer\Downloader\Downloader;
use Composer\Downloader\DownloadManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Composer\Composer;
use Composer\Repository\RepositoryManager;

/**
 * Class InstallInteractive
 * @package Neos\Installer\ConsoleCommands
 */
final class InstallInteractive extends Command
{
    protected $flowAppPackageTemplates = ['Neos.Demo', 'Flowpack.Fusion.BP'];

    protected $flowExtraPackages = ['Flowpack.ElasticSearch.ContentRepositoryAdaptor', 'Sitegeist.Monocle'];

    protected $neosSitePackageTemplates = ['Neos.Demo', 'Flowpack.Fusion.BP'];

    protected $neosExtraPackages = ['Flowpack.ElasticSearch.ContentRepositoryAdaptor', 'Sitegeist.Monocle'];

    /**
     * @var Composer
     **/
    protected $composer;

    /**
     * @param Composer $composer
     */
    public function setComposer(Composer $composer)
    {
        $this->composer = $composer;
    }

    protected function execute( InputInterface $input, OutputInterface $output )
    {
        $style = new SymfonyStyle( $input, $output );

        $style->title( 'Welcome to the Neos installer.' );
        $style->section( 'Please answer the following questions.' );

        $projectType = $style->choice('Select the project-type', ['neos', 'flow'], 'neos');

        $vendorNamespace = $style->ask( 'What is your vendor namespace?', 'MyVendor' );
        $projectName = $style->ask( 'What is your project namespace?', 'MyProject' );

        // main package
        if ($projectType == 'neos') {
            $packageTemplate = $style->choice('Select the project-type', $this->neosSitePackageTemplates, $this->neosSitePackageTemplates[0]);
        } else {
            $packageTemplate = $style->choice('Select the project-type', $this->flowAppPackageTemplates, $this->flowAppPackageTemplates[0]);
        }

        // extra packages
        $extraPackages = [];
        $installMorePackages  = true;
        while ( $installMorePackages ) {
            $installMorePackages = $style->confirm( 'Do you want to install extra packages?', false );
            if ($installMorePackages) {
                if ($projectType == 'neos') {
                    $availablePackages = $this->neosExtraPackages;
                } else {
                    $availablePackages = $this->flowExtraPackages;
                }
                $extraPackages[] = $style->choice('Select the package you want to add', $availablePackages);
            }
        }

        $style->section( 'Summary:' );

        $style->table(
            [],
            [
                ['Project Type', $projectType],
                ['Your namespace', $vendorNamespace . '\\' . $projectName],
                ['Your package template', $packageTemplate],
                [
                    'Additional packages',
                    (!empty($extraPackages) ? join( ', ', array_slice( $extraPackages, 1 ) ) : 'none'),
                ]
            ]
        );

        $installNow = $style->choice(
            'All settings correct?',
            ['Yes', 'Change settings', 'Cancel installation'],
            'Yes'
        );

        switch ( $installNow )
        {
            case 'Yes':
            {
                $style->text(sprintf( 'Creating your %s-distribution now.', $projectType));

                $packageKey = $vendorNamespace . '.' . $projectName;
                $composerName = strtolower($vendorNamespace) . '/' . strtolower(str_replace('.', '-', $projectName));
                $localPackagePath = './localPackages';

                $this->createMainPackage($projectType, $composerName, $packageKey, $localPackagePath, $packageTemplate, __DIR__ . '/../..' );
                $this->buildComposerJson($projectType, $composerName, $packageKey, $localPackagePath, $extraPackages, __DIR__ . '/../..' );

                @unlink( __DIR__ . '/../../composer.lock' );
                @unlink( __DIR__ . '/../../LICENSE' );
                @unlink( __DIR__ . '/../../README.md' );

                $this->installComponents();
                $this->commitSuicide();

                $style->success( 'Your distribution was prepared successfully.' );
                $style->text( '' );
                $style->text( 'For local development you still have to:' );
                $style->text( '' );
                $style->text( '1. Add database credentials to Configuration/Development/Settings.yaml' );
                $style->text( '2. Migrate databse "./flow doctrine:migrate"' );
                $style->text( '3. Migrate databse "./flow site:import --package-key ' . $packageKey . ' "' );
                $style->text( '4. Start the Webserver "./flow server:run"' );

                break;
            }
            case 'Change settings':
            {
                $command = $this->getApplication()->find( 'install:interactive' );

                return $command->execute( $input, $output );

                break;
            }
            case 'Cancel installation':
            {
                $style->error( 'Installation canceled.' );

                return 9;
            }
        }

        return 0;
    }

    private function createMainPackage($projectType, $composerName, $packageKey, $localPackagePath, $templatePackageKey, $baseDir)
    {
        $jsonTemplateFile = implode(DIRECTORY_SEPARATOR , [$baseDir, 'Resources', 'Private', 'PackageTemplates']) . DIRECTORY_SEPARATOR . $projectType . '.json';
        $templatePackages = json_decode(file_get_contents($jsonTemplateFile), true);

        /**
         * @var RepositoryManager $repositoryManager
         */
        $repositoryManager = $this->composer->getRepositoryManager();

        if ($templatePackages && array_key_exists($templatePackageKey, $templatePackages)  ) {
            $templatePackageInfo = $templatePackages[$templatePackageKey];

            /**
             * PackageInterface
             */
            $package = $repositoryManager->findPackage($templatePackageInfo['composerName'], $templatePackageInfo['composerConstraint']);

            /**
             * @var DownloadManager $downloadManager
             */
            $downloadManager = $this->composer->getDownloadManager();
            $downloadManager->download( $package, $localPackagePath . DIRECTORY_SEPARATOR . $packageKey  , false);

            $sourceNamespace = str_replace(['.','-'], '\\', $templatePackageKey);
            $targetNamespace = str_replace(['.','-'], '\\', $packageKey);
            // adjust namespaces
            $this->replaceValuesInFiles(
                [
                    $templatePackageKey => $packageKey,
                    $templatePackageInfo['composerName'] => $composerName,
                    $sourceNamespace => $targetNamespace,
                    str_replace('\\', '\\\\', $sourceNamespace) => str_replace('\\', '\\\\', $targetNamespace)
                ],
                $localPackagePath . DIRECTORY_SEPARATOR . $packageKey
            );

        }
    }

    private function buildComposerJson($projectType, $composerName, $packageKey, $localPackagePath, array $packages, $baseDir )
    {
        $jsonTemplateFile = implode(DIRECTORY_SEPARATOR , [$baseDir, 'Resources', 'Private', 'Composer']) . DIRECTORY_SEPARATOR . $projectType . '.json';
        $json = json_decode(file_get_contents($jsonTemplateFile), true);

        $json['name'] = $composerName . '-distribution';
        $json['require'][$composerName] = 'dev-master';
        $json['repositories'][] = [
            'type' => 'path',
            'url' => $localPackagePath . DIRECTORY_SEPARATOR . '*'
        ];

        foreach ( $packages as $package)
        {
            $json['require'][ $package ] = '*';
        }

        file_put_contents(
            $baseDir . DIRECTORY_SEPARATOR . 'composer.json',
            json_encode( $json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES )
        );
    }

    private function replaceValuesInFiles( array $replacements, string $baseDir )
    {
        $dir      = new \RecursiveDirectoryIterator( $baseDir, \FilesystemIterator::SKIP_DOTS );
        $iterator = new \RecursiveIteratorIterator( $dir );

        /** @var \SplFileInfo $item */
        foreach ( $iterator as $item )
        {
            if ( !$item->isFile() )
            {
                continue;
            }

            $content = file_get_contents( $item->getRealPath() );
            $content = str_replace( array_keys( $replacements ), array_values( $replacements ), $content );
            file_put_contents( $item->getRealPath(), $content );
        }
    }

    private function installComponents()
    {
        $composerCommand = escapeshellcmd( $_SERVER['argv'][0] );
        $targetDir       = escapeshellarg( realpath( __DIR__ . '/../..' ) );

        $command = 'cd ' . $targetDir . ' && ' . $composerCommand . ' update';
        shell_exec( $command );
    }

    private function commitSuicide()
    {
        $installerDir = escapeshellarg( realpath( __DIR__ . '/../' ) );
        $vendorDir = escapeshellarg( realpath( __DIR__ . '/../../temp-vendor' ) );
        shell_exec( 'rm -rf ' . $installerDir );
        shell_exec( 'rm -rf ' . $vendorDir );
    }
}
