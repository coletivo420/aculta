<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Event;

/**
 * Um consentimento foi revogado no ledger do aculta_user.
 *
 * Consumidores apagam os dados que dependiam dele (por exemplo, a série por aluno do aculta_statistics).
 * Carrega só a conta e o tipo de consentimento.
 */
final readonly class ConsentRevokedEvent {

  public const NAME = 'aculta_user.consent_revoked';

  public function __construct(
    public int $uid,
    public string $agreementType,
  ) {}

}
