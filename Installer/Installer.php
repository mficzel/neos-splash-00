<?php
namespace Neos\Installer;

use Neos\Installer\ConsoleCommands\InstallInteractive;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;

use Composer\Script\Event;

require(__DIR__ . '/../InstallerVendor/autoload.php');

/**
 * Class Installer
 * @package Neos\Installer
 */
final class Installer
{
    public static function postCreateProject(Event $event)
    {
        try
        {
            $installer = new InstallInteractive( 'install:interactive' );
            $installer->setComposer( $event->getComposer() );

            $app = new Application( 'Neos Installer', '3.2' );
            $app->add( $installer );
            $app->find( 'install:interactive' )->run( new ArgvInput( [] ), new ConsoleOutput());
        }
        catch ( \Throwable $e )
        {
            echo get_class( $e ) . ' thrown with message: ' . $e->getMessage() . PHP_EOL;
            echo $e->getTraceAsString() . PHP_EOL;

            exit(1);
        }
    }
}
