<?php
namespace Neos\Splash\Service;

class StringReplacementService
{
    /**
     * Replace strings in a directory
     *
     * @param array $replacements key=>value pairs
     * @param $baseDirectory
     */
    public static function replaceRecursively(array $replacements, $baseDirectory)
    {
        $dir      = new \RecursiveDirectoryIterator($baseDirectory, \FilesystemIterator::SKIP_DOTS);
        $iterator = new \RecursiveIteratorIterator($dir);

        /** @var \SplFileInfo $item */
        foreach ($iterator as $item) {
            if (!$item->isFile()) {
                continue;
            }

            $content = file_get_contents($item->getRealPath());
            $content = str_replace(array_keys($replacements), array_values($replacements), $content);
            file_put_contents($item->getRealPath(), $content);
        }
    }
}
