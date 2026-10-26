<?php

declare(strict_types=1);

namespace Drupal\aculta_id_cpf\Service;

use Drupal\aculta_portal\Account\AccountDocumentInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Database\IntegrityConstraintViolationException;
use Drupal\profile\Entity\ProfileInterface;
use Drupal\user\UserInterface;

/**
 * Grava o CPF ou CNPJ cifrado no perfil participante.
 *
 * O fingerprint HMAC no campo `field_documento_indice` recusa duplicidades. Um
 * índice único no armazenamento fecha a concorrência entre gravações. O campo
 * cifrado é privado e não aparece em formulários, exibições ou Views do Portal.
 * A gravação pela interface é única; o titular não pode trocar o documento.
 */
final class AccountDocumentStore implements AccountDocumentInterface {

  private const FIELD = 'field_documento';

  private const INDEX_FIELD = 'field_documento_indice';

  /**
   * Cached field definitions for the participant profile.
   *
   * @var array<string, mixed>|null
   */
  private ?array $profileFields = NULL;

  public function __construct(
    private readonly EntityTypeManagerInterface $entities,
    private readonly EntityFieldManagerInterface $fieldManager,
    private readonly DocumentValidator $validator,
    private readonly DocumentFormatter $formatter,
    private readonly DocumentCipher $cipher,
    private readonly DocumentFingerprint $fingerprint,
  ) {}

  /**
   * Checks whether the encrypted document field can be read.
   */
  public function isAvailable(): bool {
    return isset($this->profileFields()[self::FIELD]) && $this->cipher->isAvailable();
  }

  /**
   * Checks whether storage and both cryptographic keys are available.
   */
  public function canSave(): bool {
    $fields = $this->profileFields();
    return isset($fields[self::FIELD], $fields[self::INDEX_FIELD])
      && $this->cipher->isAvailable()
      && $this->fingerprint->isAvailable();
  }

  /**
   * Canonicalizes a CPF or CNPJ value without exposing validation details.
   *
   * @param string $input
   *   Submitted document value.
   *
   * @return string|null
   *   Canonical document or NULL when invalid.
   */
  public function canonicalize(string $input): ?string {
    try {
      return $this->validator->canonicalize($input, $this->validator->detectType($input));
    }
    catch (\InvalidArgumentException) {
      return NULL;
    }
  }

  /**
   * Returns the account's masked document, if one is stored.
   *
   * @param \Drupal\user\UserInterface $account
   *   Account whose document is loaded.
   *
   * @return string|null
   *   Masked document value or NULL when none is stored.
   */
  public function savedDocument(UserInterface $account): ?string {
    $profile = $this->participantProfile($account);
    if ($profile === NULL) {
      return NULL;
    }
    if (!$profile->hasField(self::FIELD)) {
      throw new \RuntimeException('Document field is unavailable.');
    }
    if ($profile->get(self::FIELD)->isEmpty()) {
      return NULL;
    }
    $plain = $this->cipher->decrypt((string) $profile->get(self::FIELD)->value);
    return $this->formatter->partial($plain, $this->validator->detectType($plain));
  }

  /**
   * Saves a valid document once and atomically reserves its fingerprint.
   *
   * @param \Drupal\user\UserInterface $account
   *   Account that owns the participant profile.
   * @param string $canonical
   *   Canonical document value.
   *
   * @throws \LogicException
   *   When a document is already saved or registered to another account.
   * @throws \RuntimeException
   *   When storage or cryptographic keys are unavailable.
   */
  public function save(UserInterface $account, string $canonical): void {
    if (!$this->canSave()) {
      throw new \RuntimeException('Document storage is unavailable.');
    }
    // Revalida no servidor: o valor do formulário nunca é confiável sozinho.
    $type = $this->validator->detectType($canonical);
    $value = $this->validator->canonicalize($canonical, $type);
    $index = $this->fingerprint->fingerprint($value, $type);

    // Os campos foram verificados por canSave() no bundle participante.
    $profile = $this->participantProfile($account) ?? $this->createParticipantProfile($account);
    if (!$profile->get(self::FIELD)->isEmpty()) {
      throw new \LogicException('Document already saved.');
    }
    // A mesma mensagem evita revelar se outra conta já possui o documento.
    if ($this->indexInUseByOther($index, (int) $account->id())) {
      throw new \LogicException('Document already registered.');
    }
    $profile->set(self::FIELD, $this->cipher->encrypt($value));
    $profile->set(self::INDEX_FIELD, $index);
    try {
      $profile->save();
    }
    catch (EntityStorageException $exception) {
      if ($this->isDocumentIndexConflict($exception)) {
        // The unique database key closes the race between the preflight query
        // and the save without revealing which account owns the document.
        throw new \LogicException('Document already registered.', 0, $exception);
      }
      throw $exception;
    }
  }

  /**
   * Preenche o índice de um documento gravado antes do campo existir.
   *
   * @return string
   *   `empty`, `conflict` ou `indexed`, conforme o resultado.
   *
   * @throws \RuntimeException
   *   Quando a cifra ou o fingerprint não está disponível.
   */
  public function indexStoredDocument(ProfileInterface $profile): string {
    if (!$profile->hasField(self::FIELD)) {
      throw new \RuntimeException('Document storage is unavailable.');
    }
    if ($profile->get(self::FIELD)->isEmpty()) {
      return 'empty';
    }
    if (!$profile->hasField(self::INDEX_FIELD) || !$this->canSave()) {
      throw new \RuntimeException('Document storage is unavailable.');
    }
    $plain = $this->cipher->decrypt((string) $profile->get(self::FIELD)->value);
    $type = $this->validator->detectType($plain);
    $index = $this->fingerprint->fingerprint($plain, $type);
    if ($this->indexInUseByOther($index, (int) $profile->getOwnerId())) {
      return 'conflict';
    }
    $profile->set(self::INDEX_FIELD, $index);
    $profile->save();
    return 'indexed';
  }

  /**
   * Returns the participant profile's field definitions, cached per request.
   *
   * @return array<string, mixed>
   *   Field definitions keyed by machine name.
   */
  private function profileFields(): array {
    return $this->profileFields ??= $this->fieldManager->getFieldDefinitions('profile', 'associado');
  }

  /**
   * Checks whether another account already uses the fingerprint.
   */
  private function indexInUseByOther(string $index, int $uid): bool {
    $ids = $this->entities->getStorage('profile')->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'associado')
      ->condition(self::INDEX_FIELD, $index)
      ->condition('uid', $uid, '<>')
      ->range(0, 1)
      ->execute();
    return $ids !== [];
  }

  /**
   * Determines whether an entity storage exception is the fingerprint conflict.
   */
  private function isDocumentIndexConflict(\Throwable $exception): bool {
    do {
      if ($exception instanceof IntegrityConstraintViolationException
        && str_contains($exception->getMessage(), 'aculta_id_cpf_documento_unique')) {
        return TRUE;
      }
      $exception = $exception->getPrevious();
    } while ($exception !== NULL);
    return FALSE;
  }

  /**
   * Loads the account's participant profile, including unpublished profiles.
   *
   * Uses ProfileStorage ordering without filtering status.
   *
   * @param \Drupal\user\UserInterface $account
   *   The account whose participant profile is loaded.
   *
   * @return \Drupal\profile\Entity\ProfileInterface|null
   *   The account profile when present.
   */
  private function participantProfile(UserInterface $account): ?ProfileInterface {
    $storage = $this->entities->getStorage('profile');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('uid', $account->id())
      ->condition('type', 'associado')
      ->sort('is_default', 'DESC')
      ->sort('profile_id', 'DESC')
      ->range(0, 1)
      ->execute();
    return $ids ? $storage->load(reset($ids)) : NULL;
  }

  /**
   * Creates a default participant profile for an account.
   *
   * @param \Drupal\user\UserInterface $account
   *   The account that owns the new profile.
   *
   * @return \Drupal\profile\Entity\ProfileInterface
   *   The unsaved participant profile.
   */
  private function createParticipantProfile(UserInterface $account): ProfileInterface {
    return $this->entities->getStorage('profile')->create([
      'type' => 'associado',
      'uid' => $account->id(),
      'status' => TRUE,
      'is_default' => TRUE,
    ]);
  }

}
