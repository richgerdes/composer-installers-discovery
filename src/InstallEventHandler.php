<?php

namespace RoyGoldman\ComposerInstallersDiscovery;

use Composer\Installer\PackageEvent;
use Composer\DependencyResolver\Operation\UpdateOperation;
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
    // Get the effected package from the Event.
    $installed_package = NULL;
    $operation = $event->getOperation();
    if ($operation instanceof UpdateOperation) {
      $installed_package = $operation->getTargetPackage();
    }
    else {
      $installed_package = $operation->getPackage();
    }
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
