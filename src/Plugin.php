<?php

namespace RoyGoldman\ComposerInstallersDiscovery;

use Composer\Composer;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\EventDispatcher\EventSubscriberInterface;

/**
 * Implement Composer Plugin to initialize installer configuration.
 */
class Plugin implements PluginInterface, EventSubscriberInterface {

  /**
   * @var \RoyGoldman\ComposerInstallersDiscovery\InstallEventHandler
   */
  protected $handler;

  /**
   * {@inheritdoc}
   */
  public function activate(Composer $composer, IOInterface $io) {
    $installer_instance = new Installer($io, $composer);
    $composer->getInstallationManager()->addInstaller($installer_instance);

    $this->handler = new InstallEventHandler($installer_instance);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      PackageEvents::POST_PACKAGE_INSTALL => 'onPostPackageEvent',
      PackageEvents::POST_PACKAGE_UPDATE => 'onPostPackageEvent',
      PackageEvents::POST_PACKAGE_UNINSTALL => 'onPostPackageEvent',
    ];
  }

  /**
   * Post package event behaviour.
   *
   * @param \Composer\Installer\PackageEvent $event
   */
  public function onPostPackageEvent(PackageEvent $event) {
    $this->handler->onPostPackageEvent($event);
  }

}
