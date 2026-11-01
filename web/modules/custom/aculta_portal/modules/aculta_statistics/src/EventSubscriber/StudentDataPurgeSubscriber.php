<?php

declare(strict_types=1);

namespace Drupal\aculta_statistics\EventSubscriber;

use Drupal\aculta_statistics\Storage\AggregateStorageInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Apaga os dados por aluno quando a conta é excluída ou o aceite é revogado.
 *
 * Atende o direito de eliminação (LGPD art. 18, VI) para `lms.student.status`. Os nomes dos eventos são texto:
 * este módulo não depende de `aculta_user`.
 */
final class StudentDataPurgeSubscriber implements EventSubscriberInterface {

  private const ACCOUNT_DELETED = 'aculta_user.account_deleted';
  private const CONSENT_REVOKED = 'aculta_user.consent_revoked';
  private const METRIC = 'lms.student.status';

  public function __construct(
    private readonly AggregateStorageInterface $storage,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      self::ACCOUNT_DELETED => 'onAccountDeleted',
      self::CONSENT_REVOKED => 'onConsentRevoked',
    ];
  }

  /**
   * Conta excluída: apaga todas as linhas por aluno dela.
   *
   * @param object $event
   *   Evento com `uid`.
   */
  public function onAccountDeleted(object $event): void {
    $this->storage->purgeStudent(self::METRIC, (int) $event->uid);
  }

  /**
   * Revogação: apaga as linhas por aluno da conta, em todos os dias.
   *
   * @param object $event
   *   Evento com `uid` e `agreementType`.
   */
  public function onConsentRevoked(object $event): void {
    $this->storage->purgeStudent(self::METRIC, (int) $event->uid);
  }

}
