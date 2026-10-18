<?php

declare(strict_types=1);

namespace Drupal\aculta_lms_h5p\Access;

use Drupal\aculta_lms_h5p\H5PEditor\H5PEditorDrupalStorage;
use Drupal\Core\Session\AccountInterface;

/** Centralizes the local H5P library and restricted-type policy. */
final class H5PLibraryAccessManager {

  public function __construct(
    private readonly H5PEditorDrupalStorage $editorStorage,
  ) {}

  /** Whether this installed library version is usable by the account. */
  public function canUse(string $machineName, int $majorVersion, int $minorVersion, AccountInterface $account): bool {
    $libraries = $this->editorStorage->getLibraries([(object) [
      'name' => $machineName,
      'majorVersion' => $majorVersion,
      'minorVersion' => $minorVersion,
    ]]);
    if ($libraries === []) {
      return FALSE;
    }

    return empty($libraries[0]->restricted)
      || $account->hasPermission('create restricted h5p content types');
  }

}
