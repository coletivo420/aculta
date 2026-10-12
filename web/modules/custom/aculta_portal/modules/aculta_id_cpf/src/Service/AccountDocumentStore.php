<?php

declare(strict_types=1);

namespace Drupal\aculta_id_cpf\Service;

use Drupal\aculta_portal\Account\AccountDocumentInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\profile\Entity\ProfileInterface;
use Drupal\user\UserInterface;

/**
 * Grava o CPF ou CNPJ da pessoa, cifrado, no perfil participante (campo field_documento).
 *
 * O campo é privado do perfil (profile_private) e não entra em formulários, exibições nem Views do
 * Portal. Gravar é uma única vez: depois de salvo, o valor não muda pela interface.
 */
final class AccountDocumentStore implements AccountDocumentInterface {

  private const FIELD = 'field_documento';

  public function __construct(
    private readonly EntityTypeManagerInterface $entities,
    private readonly DocumentValidator $validator,
    private readonly DocumentFormatter $formatter,
    private readonly DocumentCipher $cipher,
  ) {}

  public function isAvailable(): bool {
    return $this->cipher->isAvailable();
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
    if ($profile === NULL || $profile->get(self::FIELD)->isEmpty()) {
      return NULL;
    }
    $plain = $this->cipher->decrypt((string) $profile->get(self::FIELD)->value);
    return $this->formatter->partial($plain, $this->validator->detectType($plain));
  }

  public function save(UserInterface $account, string $canonical): void {
    if (!$this->isAvailable()) {
      throw new \RuntimeException('Document storage is unavailable.');
    }
    // Revalida no servidor: o valor do formulário nunca é confiável sozinho.
    $value = $this->validator->canonicalize($canonical, $this->validator->detectType($canonical));
    $profile = $this->participantProfile($account) ?? $this->createParticipantProfile($account);
    if (!$profile->get(self::FIELD)->isEmpty()) {
      throw new \LogicException('Document already saved.');
    }
    $profile->set(self::FIELD, $this->cipher->encrypt($value));
    $profile->save();
  }

  private function participantProfile(UserInterface $account): ?ProfileInterface {
    return $this->entities->getStorage('profile')->loadByUser($account, 'participante');
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
