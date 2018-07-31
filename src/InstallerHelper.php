<?php

namespace RoyGoldman\ComposerInstallersDiscovery;

use Composer\Installers\BaseInstaller as DefaultBaseInstaller;

/**
 * Create an installer base to use with composer installers.
 *
 * We want to override the default installer in order to block the locations
 * block from the installer, so that we should use the default path.
 *
 * Derived from OomphInc\ComposerInstallersExtender\InstallerHelper.
 */
class BaseInstaller extends DefaultBaseInstaller {

  function getLocations() {
    /*
     * Caller will check the first returned element for key FALSE. We return
     * false to single the installer to use the default path.
     */
    return array( FALSE => FALSE );
  }

}
