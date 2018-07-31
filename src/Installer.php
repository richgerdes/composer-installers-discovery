<?php

namespace RoyGoldman\ComposerInstallersDiscovery;

use Composer\Installer\LibraryInstaller;
use Composer\Installers\Installer as ComposerInstaller;
use Composer\Package\PackageInterface;
use Composer\Package\Package;

use JMS\Composer\DependencyAnalyzer;
use JMS\Composer\Graph\PackageNode;

/**
 * Implement custom installer to search dependencies for installer locations.
 */
class Installer extends ComposerInstaller {

  /**
   * Cache of installer path locations.
   *
   * @var array
   */
  protected $packageTypes;

  /**
   * Explored package meta data.
   *
   * @var array
   */
  protected $packageInstallers;

  public function getInstallPath( PackageInterface $package ) {
    $installer = new BaseInstaller($package, $this->composer, $this->io);
    $path = $installer->getInstallPath($package, $package->getType());
    // If the path is false, use the default installer path instead.
    return $path !== false ? $path : LibraryInstaller::getInstallPath( $package );
  }

  public function supports( $packageType ) {
    // grab the package types once
    if ( !isset( $this->packageTypes ) ) {
      $this->packageTypes = false;
      if ( $this->composer->getPackage() ) {
        $this->packageTypes = $this->discoverDependencyInstallers($this->composer->getPackage())
      }
    }
    return is_array( $this->packageTypes ) && in_array( $packageType, $this->packageTypes );
  }

  /**
   * Search a package and any dependencies for available installer metadata.
   */
  protected function _discoverPackageInstallers(PackageInterface $package) {
    $localRepo = $this->composer->getRepositoryManager()->getLocalRepository();
    $localPackages = $localRepo->getCanonicalPackages();


    $packageTypes = array();
    $extra = $package->getExtra();
    if ( !empty( $extra['installer-types'] ) ) {
      $localTypes = (array) $extra['installer-types'];
    }

    foreach ($package->getRequires() as $requirement) {
      $localTypes .= $this->discoverPackageInstallers
    }


    $this->packageInstallers[$package->getName()] = $packageTypes;

  }

  protected function discoverDependencyInstallers($dir) {
    static $installer_mapping = NULL;
    if ($installer_mapping === NULL) {
      $analyzer = new DependencyAnalyzer();
      $dependencyGraph = $analyzer->analyze($dir);
      $root = $dependencyGraph->getRootPackage();
      $installer_mapping = $this->discoverPackageInstallers($root);
    }
    return $installer_mapping;
  }

  protected function discoverPackageInstallers(PackageNode $package) {
    static $package_cache = [];

    $package_name = $package->getName();
    if (!isset($package_cache[$package_name])) {

      $package_cache[$package_name] = [];
      $installer_paths = &$package_cache[$package_name];

      $package_info = $package->getData();
      if (isset($package_info['extra']) && isset($package_info['extra']['installer-paths'])) {
        foreach ($package_info['extra']['installer-paths'] as $path => $values) {
          foreach ($values as $value) {
            if (strpos($value, 'type:') === 0) {
              $installer_paths[substr($value, 5)] = $path;
            }
          }
        }
      }

      $edges = $package->getOutEdges();
      foreach ($edges as $edge) {
        $dependency_package = $edge->getDestPackage();
        $dependency_installers = $this->discoverPackageInstallers($dependency_package);
        $installer_paths += $dependency_installers;
      }
    }

    return $package_cache[$package_name];
  }

}