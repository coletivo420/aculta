<?php

declare(strict_types=1);

namespace Drupal\aculta_id_cpf\Service;

use Drupal\aculta_id_cpf\Enum\DocumentType;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\profile\Entity\ProfileInterface;
use Drupal\user\UserInterface;

/**
 * Resolves a valid CPF login identifier to its owning account name.
 *
 * The CPF and its fingerprint are never returned, logged, or stored in form
 * state beyond the submitted value already held by Form API.
 */
final class CpfLoginIdentifierResolver {

  private const INDEX_FIELD = 'field_documento_indice';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityFieldManagerInterface $entityFieldManager,
    private readonly DocumentValidator $validator,
    private readonly DocumentFingerprint $fingerprint,
  ) {}

  /**
   * Finds the account name for a saved CPF.
   *
   * @param string $identifier
   *   The login field value.
   *
   * @return string|null
   *   The owning account name, or NULL for an invalid, unknown, ambiguous, or
   *   unavailable CPF. NULL preserves the native username/email login path.
   */
  public function resolveUsername(string $identifier): ?string {
    try {
      $fields = $this->entityFieldManager->getFieldDefinitions('profile', 'associado');
      if (!isset($fields[self::INDEX_FIELD]) || !$this->fingerprint->isAvailable()) {
        return NULL;
      }
      $canonical = $this->validator->canonicalize($identifier, DocumentType::CPF);
      $index = $this->fingerprint->fingerprint($canonical, DocumentType::CPF);
      $profiles = $this->entityTypeManager->getStorage('profile');
      $ids = $profiles->getQuery()
        ->accessCheck(FALSE)
        ->condition('type', 'associado')
        ->condition(self::INDEX_FIELD, $index)
        ->range(0, 2)
        ->execute();
      if (count($ids) !== 1) {
        return NULL;
      }

      $profile = $profiles->load(reset($ids));
      if (!$profile instanceof ProfileInterface) {
        return NULL;
      }

      $account = $this->entityTypeManager->getStorage('user')->load($profile->getOwnerId());
      return $account instanceof UserInterface ? $account->getAccountName() : NULL;
    }
    catch (\Throwable) {
      // Keep ordinary username/email login available when CPF lookup is not.
      return NULL;
    }
  }

}
