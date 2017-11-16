<?php
namespace Neos\Splash\ConsoleCommands;

use Composer\Downloader\Downloader;
use Composer\Downloader\DownloadManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Composer\Composer;
use Composer\Repository\RepositoryManager;

use Neos\Splash\Service\PackageService;
use Neos\Splash\Service\JsonFileService;
use Neos\Utility\Arrays;

/**
 * Class InstallInteractive
 * @package Neos\Splash\ConsoleCommands
 */
final class InstallInteractive extends Command
{
    const LOCAL_PACKAGE_PATH = 'DistributionPackages';

    const BASE_DIRECTORY = __DIR__ . '/../..';

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

        $projectType = $style->choice('Select the project-type', ['Neos', 'Flow'], 'Neos');

        $vendorNamespace = $style->ask( 'What is your vendor namespace?', 'MyVendor' );
        $projectName = $style->ask( 'What is your project namespace?', 'MyProject' );

        // main package
        $mainPackages = JsonFileService::readFile(implode(DIRECTORY_SEPARATOR ,[self::BASE_DIRECTORY, 'InstallerResources', 'Scaffold', $projectType, 'AvailablePackages' , 'main.json'] ));
        $mainPackageKeys = array_keys($mainPackages);
        $mainPackageTemplateKey = $style->choice('Select the main package template ', $mainPackageKeys, $mainPackageKeys[0]);

        // extra packages
        $requiredExtraPackageKeys= [];
        $extraPackages = JsonFileService::readFile(implode(DIRECTORY_SEPARATOR ,[self::BASE_DIRECTORY, 'InstallerResources', 'Scaffold', $projectType, 'AvailablePackages' , 'extra.json'] ));
        $extraPackageKeys = array_keys($extraPackages);
        $installExtraPackages  = true;
        while ( $installExtraPackages ) {
            $installExtraPackages = $style->confirm( 'Do you want to install extra packages?', false );
            if ($installExtraPackages) {
                $requiredExtraPackageKeys[] = $style->choice('Select the package you want to add', $extraPackageKeys);
            }
        }

        // confirm
        $style->section( 'Summary:' );
        $style->table(
            [],
            [
                ['Project Type', $projectType],
                ['Your namespace', $vendorNamespace . '\\' . $projectName],
                ['Your package template', $mainPackageTemplateKey],
                [
                    'Additional packages',
                    (!empty($requiredExtraPackageKeys) ? implode(', ', $requiredExtraPackageKeys) : 'none'),
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

                // build distribution skeleton
                $this->removeFilesFromDistributionRoot();
                $this->copyDistributionSkeleton($projectType);
                $this->createMainPackage( $composerName, $packageKey, $mainPackages[$mainPackageTemplateKey]['name'], $mainPackages[$mainPackageTemplateKey]['version'] );
                $this->adjustMainComposerJson( $projectType, $composerName, $requiredExtraPackageKeys, $extraPackages);

                // install
                $this->installComponents();
                $this->removeInstaller();

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

    /**
     * Remove all files from distribution root
     */
    private function removeFilesFromDistributionRoot()
    {
        foreach (new \DirectoryIterator(self::BASE_DIRECTORY) as $fileInfo) {
            if ($fileInfo->isFile()) {
                @unlink($fileInfo->getPathname());
            }
        }
    }

    /**
     * Copy all files from distribution skeletons to the main directory
     *
     * @param string $projectType
     */
    private function copyDistributionSkeleton($projectType)
    {
        $sourceDirectory = implode(DIRECTORY_SEPARATOR, [self::BASE_DIRECTORY, 'InstallerResources', 'Scaffold', $projectType]);
        foreach (new \DirectoryIterator($sourceDirectory) as $fileInfo) {
            if($fileInfo->isFile()) {
                @copy($fileInfo->getPathname(), self::BASE_DIRECTORY . DIRECTORY_SEPARATOR . $fileInfo->getFilename() );
            }
        }
    }

    /**
     * Fetch main package-template and transfer to the project and vendor namespace
     *
     * @param string $composerName
     * @param string $packageKey
     * @param string $templatePackageName
     * @param string $templatePackageVersion

     */
    private function createMainPackage($composerName, $packageKey, $templatePackageName, $templatePackageVersion)
    {
        PackageService::downloadPackageWithComposer(
            $this->composer,
            $templatePackageName,
            $templatePackageVersion,
            self::BASE_DIRECTORY . DIRECTORY_SEPARATOR . self::LOCAL_PACKAGE_PATH . DIRECTORY_SEPARATOR . $packageKey
        );

        PackageService::alterPackageNamespace(
            self::BASE_DIRECTORY . DIRECTORY_SEPARATOR . self::LOCAL_PACKAGE_PATH . DIRECTORY_SEPARATOR . $packageKey,
            $composerName,
            $packageKey,
            str_replace('.', '\\', $packageKey)
        );
    }

    /**
     * Create the composer.json for the distribution by using the template for the given projectType
     *
     * @param string $projectType
     * @param string $composerName
     * @param array  $requiredPackageKeys
     * @param array  $requiredPackageKeys
     */
    private function adjustMainComposerJson($projectType, $composerName, array $requiredPackageKeys, array $packageInfos )
    {
        $composerJsonDelta = [
            'name' =>  $composerName . '-distribution',
            'require' => [
                $composerName => 'dev-master'
            ],
            'repositories' => [
                [
                    'type' => 'path',
                    'url' => './' . self::LOCAL_PACKAGE_PATH . DIRECTORY_SEPARATOR . '*'
                ]
            ]
        ];

        foreach ($requiredPackageKeys as $packageKey)
        {
            if (array_key_exists($packageKey, $packageInfos)) {
                $info = $packageInfos[$packageKey];
                $composerJsonDelta['require'][$info['name']] = $info['version'];
            }
        }

        JsonFileService::modifyFile(self::BASE_DIRECTORY . DIRECTORY_SEPARATOR .  'composer.json', $composerJsonDelta);
    }

    /**
     * Install the new dependencies
     */
    private function installComponents()
    {
        $composerCommand = escapeshellcmd( $_SERVER['argv'][0] );
        $command = 'cd ' . self::BASE_DIRECTORY . ' && ' . $composerCommand . ' update';
        shell_exec($command);
    }

    /**
     * Remove the installer directories from the folder
     */
    private function removeInstaller()
    {
        foreach (['InstallerVendor', 'InstallerResources', 'Installer'] as $folderToRemove ) {
            $path = self::BASE_DIRECTORY . DIRECTORY_SEPARATOR . $folderToRemove;
            shell_exec( 'rm -rf ' . escapeshellarg(realpath($path)));
        }
    }
}
