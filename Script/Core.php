<?php
/**
 * @package    Packlink
 * @author     Packlink Shipping S.L.
 * @copyright  2019 Packlink
 */

namespace Packlink\Script;

class Core
{
    public static function postComposer()
    {
        self::removeDirectory(__DIR__ . '/../PacklinkPro/IntegrationCore');
        self::fixAndCopyDirectory('src', 'IntegrationCore');
        self::fixAndCopyDirectory('tests', 'IntegrationCore/Tests');
        self::copyResources();
    }

    private static function fixAndCopyDirectory($from, $to)
    {
        self::copyDirectory(__DIR__ . '/../vendor/packlink/integration-core/' . $from, __DIR__ . '/../tmp');
        self::renameNamespaces(__DIR__ . '/../tmp');
        self::copyDirectory(__DIR__ . '/../tmp', __DIR__ . '/../PacklinkPro/' . $to);
        self::removeDirectory(__DIR__ . '/../tmp');
    }

    private static function copyDirectory($src, $dst)
    {
        $dir = opendir($src);
        @mkdir($dst);
        while (false !== ($file = readdir($dir))) {
            if (($file !== '.') && ($file !== '..')) {
                if (is_dir($src . '/' . $file)) {
                    self::copyDirectory($src . '/' . $file, $dst . '/' . $file);
                } else {
                    copy($src . '/' . $file, $dst . '/' . $file);
                }
            }
        }

        closedir($dir);
    }

    private static function renameNamespaces($directory)
    {
        $iterator = new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new \RecursiveIteratorIterator($iterator, \RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($files as $file) {
            if (!$file->isDir()) {
                $fileToChange = file_get_contents($file->getRealPath());
                $fileToChange = str_replace(
                    "Packlink\\",
                    "Packlink\\PacklinkPro\\IntegrationCore\\",
                    $fileToChange
                );
                file_put_contents(
                    $file->getRealPath(),
                    str_replace(
                        "Logeecom\\",
                        "Packlink\\PacklinkPro\\IntegrationCore\\",
                        $fileToChange
                    )
                );
            }
        }
    }

    private static function removeDirectory($directory)
    {
        if (!file_exists($directory)) {
            return;
        }

        $iterator = new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new \RecursiveIteratorIterator($iterator, \RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }

        rmdir($directory);
    }

    /**
     * Copies resource files to module resources directory.
     */
    private static function copyResources()
    {
        self::copyDirectory(
            __DIR__ . '/../vendor/packlink/integration-core/src/BusinessLogic/Resources/js',
            __DIR__ . '/../PacklinkPro/view/adminhtml/web/packlink/js'
        );
        self::copyDirectory(
            __DIR__ . '/../vendor/packlink/integration-core/src/BusinessLogic/Resources/css',
            __DIR__ . '/../PacklinkPro/view/adminhtml/web/packlink/css'
        );
        self::copyDirectory(
            __DIR__ . '/../vendor/packlink/integration-core/src/BusinessLogic/Resources/lang',
            __DIR__ . '/../PacklinkPro/view/adminhtml/web/packlink/lang'
        );
        self::copyDirectory(
            __DIR__ . '/../vendor/packlink/integration-core/src/BusinessLogic/Resources/templates',
            __DIR__ . '/../PacklinkPro/view/adminhtml/web/packlink/templates'
        );
        self::copyDirectory(
            __DIR__ . '/../vendor/packlink/integration-core/src/BusinessLogic/Resources/images',
            __DIR__ . '/../PacklinkPro/view/adminhtml/web/packlink/images'
        );
        self::copyDirectory(
            __DIR__ . '/../vendor/packlink/integration-core/src/BusinessLogic/Resources/js',
            __DIR__ . '/../PacklinkPro/view/frontend/web/packlink/js'
        );
        self::copyDirectory(
            __DIR__ . '/../vendor/packlink/integration-core/src/BusinessLogic/Resources/LocationPicker',
            __DIR__ . '/../PacklinkPro/view/frontend/web/packlink/location'
        );
        self::copyDirectory(
            __DIR__ . '/../vendor/packlink/integration-core/src/BusinessLogic/Resources/images/carriers',
            __DIR__ . '/../PacklinkPro/view/frontend/web/packlink/images/carriers'
        );
    }
}
