<?php

namespace RoyGoldman\ComposerInstallersDiscovery;

use Composer\Installer\LibraryInstaller;
use Composer\Package\Link;
use Composer\Package\PackageInterface;
use Composer\Package\Package;
use Composer\Repository\RepositoryManager;

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
  protected $packageInstallers = [];

  /**
   * {@inheritdoc}
   */
  public function getInstallPath(PackageInterface $package) {
    $type = $package->getType();
    $full_name = $package->getPrettyName();
    if (strpos($full_name, '/') !== false) {
      list($vendor, $name) = explode('/', $full_name);
    } else {
      $name = $full_name;
      $vendor = '';
    }
    $path_vars = [
      'type' => $type,
      'name' => $name,
      'vendor' => $vendor,
    ];
    $extra = $package->getExtra();
    if (!empty($extra['installer-name'])) {
        $path_vars['name'] = $extra['installer-name'];
    }

    // Check for installer path in root package.
    if ($this->composer->getPackage()) {
      $root_extra = $this->composer->getPackage()->getExtra();
      if (!empty($root_extra['installer-paths'])) {
        $path = $this->mapCustomInstallPaths($root_extra['installer-paths'], $full_name, $type, $vendor);
        if ($path !== false) {
          // If project defines a path, install the package there.
          return $this->templatePath($path, $path_vars);
        }
      }
    }

    // Load mapping from discovered installer locations.
    if (is_array($this->installerLocations) && array_key_exists($type, $this->installerLocations)) {
      $path = $this->installerLocations[$type];
      return $this->templatePath($path, $path_vars);
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
   * Replace variables in a path
   *
   * @param  string $path
   * @param  array  $path_vars
   * @return string
   */
  protected function templatePath($path, array $path_vars = []) {
    if (strpos($path, '{') !== false) {
      extract($path_vars);
      preg_match_all('@\{\$([A-Za-z0-9_]*)\}@i', $path, $matches);
      if (!empty($matches[1])) {
        foreach ($matches[1] as $var) {
          $path = str_replace('{$' . $var . '}', $$var, $path);
        }
      }
    }

    return $path;
  }

  /**
   * {@inheritdoc}
   */
  public function supports($package_type) {

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
    return isset($this->installerLocations) && array_key_exists($package_type, $this->installerLocations);
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
    $repo_manager = $this->composer->getRepositoryManager();

    $package = $this->composer->getPackage();

    return $this->discoverPackageInstallers($package, $repo_manager);
  }

  /**
   * Recursively discover available installer paths in installers.
   *
   * @param \Composer\Package\PackageInterface $package
   *   Package to discover tree starting from.
   * @param \Composer\Repository\RepositoryManager $repo_manager
   *   Local Package repository.
   *
   * @return array
   *   Installer mappings keyed by type, with paths as values.
   */
  protected function discoverPackageInstallers(PackageInterface $package, RepositoryManager $repo_manager) {
    $package_name = $package->getName();
    if (!isset($this->packageInstallers[$package_name])) {

      // Initialize the package cache entry to prevent loops.
      $this->packageInstallers[$package_name] = [];
      $installer_paths = &$this->packageInstallers[$package_name];

      // Calculate the installer paths for this package.
      $package_extra = $package->getExtra();
      if (isset($package_extra) && isset($package_extra['installer-paths'])) {
        foreach ($package_extra['installer-paths'] as $path => $values) {
          foreach ($values as $value) {
            $type = explode(':', $value, 2);
            if (count($type) >= 2 && $type[0] === 'type') {
              $installer_paths[$type[1]] = $path;
            }
          }
        }
      }

      $requires = $package->getRequires();
      foreach ($requires as $requirement) {
        if (static::isSystemRequirement($requirement)) {
          continue;
        }
        $required_package = $repo_manager->findPackage($requirement->getTarget(), $requirement->getConstraint());
        if ($required_package) {
          $installer_paths += $this->discoverPackageInstallers($required_package, $repo_manager);
        }
      }
    }

    return $this->packageInstallers[$package_name];
  }

  /**
   * Checks if the required package doesn't have a vendor.
   *
   * Packages without vendor definitions should only ever be php or a php
   * extension.
   *
   * @param \Composer\Package\Link $requirement
   *   Required package reference.
   *
   * @return bool
   *   True if the package is a php or an extension. False otherwise.
   */
  public static function isSystemRequirement(Link $requirement) {
    $package_name = $requirement->getTarget();
    if (strpos($package_name, '/') === FALSE) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Reset discovered installers.
   *
   * When a new package is installed we need to reset the cached of discovered
   * installers so they the mapping is rebuilt.
   */
  public function clearCache() {
    $this->packageInstallers = [];
    $this->installerLocations = NULL;
  }

  /**
   * Search through a passed paths array for a custom install path.
   *
   * @param array $paths
   *   List of path templates, where keys are paths and values or package names.
   * @param string $name
   *   Package name, without vendor prefix.
   * @param string $type
   *   Package Type.
   * @param string $vendor
   *   Vendor name.
   * @return string
   *   Package install path if available, otherwise false.
   */
  protected function mapCustomInstallPaths(array $paths, $name, $type, $vendor = NULL) {
    static $package_map = NULL;
    if ($package_map === NULL) {
      $package_map = [];
      foreach ($paths as $path => $names) {
        foreach ($names as $name) {
          if (!isset($package_map[$name])) {
            $package_map[$name] = $path;
          }
        }
      }
    }

    $keys = [
      $name,
      'type:' . $type,
      'vendor:' . $vendor,
    ];
    foreach ($keys as $key) {
      if (isset($package_map[$key])) {
        return $package_map[$key];
      }
    }

    return false;
  }

}
