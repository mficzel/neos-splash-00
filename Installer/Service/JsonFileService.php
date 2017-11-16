<?php
namespace Neos\Splash\Service;

use Neos\Utility\Arrays;

class JsonFileService
{

    /**
     * @param string $filename
     * @return array
     */
    public static function readFile($filename) {
        $json = json_decode(file_get_contents($filename), true);
        return $json;
    }

    /**
     * @param string $filename
     * @return array
     */
    public static function writeFile($filename, $json) {
        file_put_contents(
            $filename,
            json_encode( $json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES )
        );
    }

    /**
     * @param string $filename
     * @param array $delta
     */
    public static function modifyFile($filename, array $delta) {
        $json = self::readFile($filename);
        $json = Arrays::arrayMergeRecursiveOverrule($json, $delta);
        self::writeFile($filename, $json);
    }

}