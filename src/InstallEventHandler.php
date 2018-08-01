<?php

namespace RoyGoldman\ComposerInstallersDiscovery;

use Composer\Installer\PackageEvent;
use RoyGoldman\ComposerInstallersDiscovery\Plugin;

/**
 * Defines a handler for Composer package install events.
 */
class InstallEventHandler {

  /**
   * Installer Instance
   *
   * @var \RoyGoldman\ComposerInstallersDiscovery\Installer
   */
  protected $installer;

  /**
   * Create a new InstallEventHandler.
   *
   * @param \RoyGoldman\ComposerInstallersDiscovery\Installer $installer
   *   Installer instance which needs to be reset.
   */
  public function __construct(Installer $installer) {
    $this->installer = $installer;
  }

  /**
   * Event handler for to process package events.
   *
   * @param \Composer\Installer\PackageEvent $event
   *   Composer Package event for the currently installing package.
   */
  public function onPostPackageEvent(PackageEvent $event) {
    $installed_package = $event->getOperation()->getPackage();
    $package_extra = $installed_package->getExtra();

    // If the effected package has installers, reset cached installers.
    if (isset($package_extra) && isset($package_extra['installer-paths'])) {
      $composer = $event->getComposer();
      $composer->getInstallationManager()->removeInstaller($this->installer);
      $composer->getInstallationManager()->addInstaller($this->installer);
      $this->installer->clearCache();
    }
  }

}
