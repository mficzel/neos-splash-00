<?php
namespace Neos\Splash\Service;

use Neos\Splash\InstallerException;
use Neos\Utility\Arrays;
use Composer\Composer;
use Composer\Repository\RepositoryManager;
use Composer\Package\PackageInterface;
use Composer\Downloader\DownloadManager;

class PackageService
{

    /**
     * @param Composer $composer
     * @param $packageKey
     * @param $versionConstraint
     * @param $path
     * @throws InstallerException
     */
    public static function downloadPackageWithComposer(Composer $composer, $packageKey, $versionConstraint, $path)
    {
        /**
         * @var RepositoryManager $repositoryManager
         */
        $repositoryManager = $composer->getRepositoryManager();

        /**
         * PackageInterface
         */
        $package = $repositoryManager->findPackage($packageKey, $versionConstraint);
        if ($package) {
            /**
             * @var DownloadManager $downloadManager
             */
            $downloadManager = $composer->getDownloadManager();
            $downloadManager->download($package, $path, false);
        } else {
            throw new InstallerException(sprintf('package %s was not found or could not satisfy version constraint %s', $packageKey, $versionConstraint));
        }
    }

    /**
     * @param $path
     * @param $newPackageName
     * @param $newPackageKey
     * @param $newPackageNamespace
     * @throws \Exception
     */
    public static function alterPackageNamespace($path, $newPackageName, $newPackageKey, $newPackageNamespace)
    {
        // read composer json
        $packageJson = JsonFileService::readFile($path . DIRECTORY_SEPARATOR . 'composer.json');

        // determine old name
        $oldPackageName = Arrays::getValueByPath($packageJson, 'name');
        if (!$oldPackageName) {
            throw new \Exception(sprintf('No composer package-name found in path %s', $path));
        }

        // determine old namespace
        $oldPackageNamespace = null;
        if ($psr4namespaces = Arrays::getValueByPath($packageJson, 'autoload.psr-4')) {
            $namespaces = array_keys($psr4namespaces);
            $oldPackageNamespace = trim($namespaces[0], '\\');
        } elseif ($psr0namespaces = Arrays::getValueByPath($packageJson, 'autoload.psr-0')) {
            $namespaces = array_keys($psr0namespaces);
            $oldPackageNamespace = trim($namespaces[0], '\\');
        }

        // determine or guess package-key
        $oldPackageKey = Arrays::getValueByPath($packageJson, 'extra.neos.package-key') ?: str_replace('\\', '.', $oldPackageNamespace);

        if (!$oldPackageKey) {
            throw new \Exception(sprintf('No composer package-key found in path %s', $path));
        }

        $replacements = [
            $oldPackageKey => $newPackageKey,
            $oldPackageKey => $newPackageKey
        ];

        if ($oldPackageNamespace) {
            $replacements[$oldPackageNamespace] = $newPackageNamespace;
            $replacements[str_replace('\\', '\\\\', $oldPackageNamespace)] = str_replace('\\', '\\\\', $newPackageNamespace);
        }

        StringReplacementService::replaceRecursively($replacements, $path);

        JsonFileService::modifyFile(
            $path . DIRECTORY_SEPARATOR . 'composer.json',
            [
                'name' => $newPackageName,
                'extra' => [
                    'neos' => [
                        'package-key' => $newPackageKey
                    ]
                ]
            ]
        );
    }
}
