<?php

namespace RoyGoldman\ComposerInstallersDiscovery;

use Composer\Installer\LibraryInstaller;
use Composer\Package\PackageInterface;
use Composer\Package\Package;
use Composer\Repository\RepositoryInterface;

/**
 * Implement custom installer to search dependencies for installer locations.
 */
class Installer extends LibraryInstaller {

  /**
   * Cache of installer path locations.
   *
   * @var array
   */
  protected $installerLocations;

  /**
   * Explored package meta data.
   *
   * @var array
   */
  protected $packageInstallers;

  protected $packageCache = [];

  /**
   * {@inheritdoc}
   */
  public function getInstallPath(PackageInterface $package) {
    $type = $package->getType();
    // Load mapping from discovered installer locations.
    if (is_array($this->installerLocations) && array_key_exists($type, $this->installerLocations)) {
      return $this->installerLocations[$type];
    }
    // If there is no installer for the type, use the default vendor path.
    else {
      /*
       * This should only ever be triggered if for some reason the discovery was
       * never completed or the method is called with a package which isn't of abs
       * supported type. In which case we want to fall back to the defaults.
       */
      return parent::getInstallPath($package);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function supports($packageType) {

    // Discover the installer paths once.
    if (!isset( $this->installerLocations)) {
      if ($this->composer->getPackage()) {
        /*
         * Generate the installer mapping for the root project.
         *
         * The discovered installers list will include any installer paths
         * defined in the root project. If dependencies define any additional
         * paths, they will supplement the list the root's defined paths.
         */
        $this->installerLocations = $this->discoverInstallers();
      }
    }
    return isset($this->installerLocations) && array_key_exists($packageType, $this->installerLocations);
  }

  /**
   * Helper function to scan for installer definitions in dependencies.
   *
   * @param string $dir
   *   Installation directory of the project's composer.json.
   *
   * @return array
   *   Installer mappings keyed by type, with paths as values.
   */
  protected function discoverInstallers() {
    $localRepo = $this->composer->getRepositoryManager()->getLocalRepository();

    $package = $this->composer->getPackage();

    return $this->discoverPackageInstallers($package, $localRepo);
  }

  /**
   * Recursively discover available installer paths in installers.
   *
   * @param \Composer\Package\PackageInterface $package
   *   Package to discover tree starting from.
   * @param \Composer\Repository\RepositoryInterface $localRepo
   *   Local Package repository.
   *
   * @return array
   *   Installer mappings keyed by type, with paths as values.
   */
  protected function discoverPackageInstallers(PackageInterface $package, RepositoryInterface $localRepo) {
    $package_name = $package->getName();
    if (!isset($this->package_cache[$package_name])) {

      // Initialize the package_cache entry to prevent loops.
      $this->package_cache[$package_name] = [];
      $installer_paths = &$this->package_cache[$package_name];

      // Calculate the installer paths for this package.
      $package_extra = $package->getExtra();
      if (isset($package_extra) && isset($package_extra['installer-paths'])) {
        foreach ($package_extra['installer-paths'] as $path => $values) {
          foreach ($values as $value) {
            if (strpos($value, 'type:') === 0) {
              $installer_paths[substr($value, 5)] = $path;
            }
          }
        }
      }

      $requires = $package->getRequires();
      foreach ($requires as $requirement) {
        $required_package = $localRepo->findPackage($requirement->getTarget(), $requirement->getConstraint());
        if ($required_package) {
          $installer_paths += $this->discoverPackageInstallers($required_package, $localRepo);
        }
      }
    }

    return $this->package_cache[$package_name];
  }

  /**
   * Reset discovered installers.
   *
   * When a new package is installed we need to reset the cached of discovered
   * installers so they the mapping is rebuilt.
   */
  public function clearCache() {
    $this->package_cache = [];
  }

}
