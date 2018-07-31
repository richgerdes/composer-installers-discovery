<?php

namespace RoyGoldman\ComposerInstallersDiscovery;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;

/**
 * Implement Composer Plugin to initialize installer configuration.
 */
class Plugin implements PluginInterface {

  /**
   * {@inheirtdoc}
   */
  public function activate( Composer $composer, IOInterface $io ) {
    $installer = new Installer( $io, $composer );
    $composer->getInstallationManager()->addInstaller($installer);
  }

}
