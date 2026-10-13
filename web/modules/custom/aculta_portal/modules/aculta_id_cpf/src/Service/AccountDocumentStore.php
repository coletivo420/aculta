<?php

declare(strict_types=1);

namespace Drupal\aculta_id_cpf\Service;

use Drupal\aculta_portal\Account\AccountDocumentInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\profile\Entity\ProfileInterface;
use Drupal\user\UserInterface;

/**
 * Grava o CPF ou CNPJ da pessoa, cifrado, no perfil participante (campo field_documento).
 *
 * Junto do valor cifrado grava o fingerprint HMAC (field_documento_indice), usado para recusar o mesmo
 * documento em outra conta. Essa checagem não é transacional: sob gravações simultâneas, duas contas podem
 * passar pela verificação ao mesmo tempo. A garantia forte exige índice único, ainda não decidido (ver README).
 * O campo é privado do perfil (profile_private) e não entra em formulários, exibições nem Views do Portal.
 * Gravar é uma única vez: depois de salvo, o valor não muda pela interface.
 */
final class AccountDocumentStore implements AccountDocumentInterface {

  private const FIELD = 'field_documento';

  private const INDEX_FIELD = 'field_documento_indice';

  /**
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

  public function isAvailable(): bool {
    return isset($this->profileFields()[self::FIELD]) && $this->cipher->isAvailable();
  }

  public function canSave(): bool {
    $fields = $this->profileFields();
    return isset($fields[self::FIELD], $fields[self::INDEX_FIELD])
      && $this->cipher->isAvailable()
      && $this->fingerprint->isAvailable();
  }

  public function canonicalize(string $input): ?string {
    try {
      return $this->validator->canonicalize($input, $this->validator->detectType($input));
    }
    catch (\InvalidArgumentException) {
      return NULL;
    }
  }

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

  public function save(UserInterface $account, string $canonical): void {
    if (!$this->canSave()) {
      throw new \RuntimeException('Document storage is unavailable.');
    }
    // Revalida no servidor: o valor do formulário nunca é confiável sozinho.
    $type = $this->validator->detectType($canonical);
    $value = $this->validator->canonicalize($canonical, $type);
    $index = $this->fingerprint->fingerprint($value, $type);

    // Os campos já foram verificados por canSave(): o perfil é do bundle participante, então os campos existem.
    $profile = $this->participantProfile($account) ?? $this->createParticipantProfile($account);
    if (!$profile->get(self::FIELD)->isEmpty()) {
      throw new \LogicException('Document already saved.');
    }
    // Mensagem idêntica para "já cadastrado em outra conta" e para falha: não revela quem tem o documento.
    if ($this->indexInUseByOther($index, (int) $account->id())) {
      throw new \LogicException('Document already registered.');
    }
    $profile->set(self::FIELD, $this->cipher->encrypt($value));
    $profile->set(self::INDEX_FIELD, $index);
    $profile->save();
  }

  /**
   * Preenche o índice de um documento gravado antes do campo de índice existir (atualização do módulo).
   *
   * @return string
   *   'empty' quando não há documento, 'conflict' quando outro perfil já tem o mesmo documento, ou 'indexed'.
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
   * Definições de campo do perfil participante, lidas uma vez por instância (o serviço é por requisição).
   *
   * @return array<string, mixed>
   */
  private function profileFields(): array {
    return $this->profileFields ??= $this->fieldManager->getFieldDefinitions('profile', 'participante');
  }

  private function indexInUseByOther(string $index, int $uid): bool {
    $ids = $this->entities->getStorage('profile')->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'participante')
      ->condition(self::INDEX_FIELD, $index)
      ->condition('uid', $uid, '<>')
      ->range(0, 1)
      ->execute();
    return $ids !== [];
  }

  /**
   * Perfil participante do titular, publicado ou não. Mesma ordem de ProfileStorage::loadByUser, sem o filtro de status.
   */
  private function participantProfile(UserInterface $account): ?ProfileInterface {
    $storage = $this->entities->getStorage('profile');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('uid', $account->id())
      ->condition('type', 'participante')
      ->sort('is_default', 'DESC')
      ->sort('profile_id', 'DESC')
      ->range(0, 1)
      ->execute();
    return $ids ? $storage->load(reset($ids)) : NULL;
  }

  private function createParticipantProfile(UserInterface $account): ProfileInterface {
    return $this->entities->getStorage('profile')->create([
      'type' => 'participante',
      'uid' => $account->id(),
      'status' => TRUE,
      'is_default' => TRUE,
    ]);
  }

}
