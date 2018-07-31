<?php

namespace RoyGoldman\ComposerInstallersDiscovery;

use Composer\Installer\LibraryInstaller;
use Composer\Package\PackageInterface;
use Composer\Package\Package;

use JMS\Composer\DependencyAnalyzer;
use JMS\Composer\Graph\PackageNode;

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

  /**
   * {@inheirtdoc}
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
   * {@inheirtdoc}
   */
  public function supports($packageType) {
    // Discover the installer paths once.
    if (!isset( $this->installerLocations)) {
      $this->installerLocations = false;
      if ($this->composer->getPackage()) {
        $manager = $this->composer->getInstallationManager();
        $project_root = $manager->getInstallPath($this->composer->getPackage());
        /*
         * Generate the installer mapping for the root project.
         *
         * The discovered installers list will include any installer paths
         * defined in the root project. If dependencies define any additional
         * paths, they will supplement the list the root's defined paths.
         */
        $this->installerLocations = $this->discoverInstallers($project_root);
      }
    }
    return is_array($this->installerLocations) && array_key_exists($packageType, $this->installerLocations);
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
  protected function discoverInstallers($dir) {
    $analyzer = new DependencyAnalyzer();
    $dependencyGraph = $analyzer->analyze($dir);
    $root = $dependencyGraph->getRootPackage();
    return $this->discoverPackageInstallers($root);
  }

  /**
   * Recursively discover available installer paths in installers.
   *
   * @param \JMS\Composer\Graph\PackageNode\ $package
   *   Package to discover tree starting from.
   *
   * @return array
   *   Installer mappings keyed by type, with paths as values.
   */
  protected function discoverPackageInstallers(PackageNode $package) {
    static $package_cache = [];

    // Only discover dependencies for a package once.
    $package_name = $package->getName();
    if (!isset($package_cache[$package_name])) {

      // Initialize the package_cache entry to prevent loops.
      $package_cache[$package_name] = [];
      $installer_paths = &$package_cache[$package_name];

      // Calculate the installer paths for this package.
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

      // For each dependency, supplement missing installer paths.
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