<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Policy;

use Drupal\aculta_mailer\Log\MailLogRecorder;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Cota diária por transporte, contada no próprio registro de envios (janela de 24 horas).
 *
 * Configurada em `aculta_mailer.policy:quota` (ex.: `brevo: 200`). Sem valor para o transporte, não há limite.
 * Usa só mensagens que o transporte aceitou (`sent`, `delivered`), que são as que consomem a cota do provedor.
 */
final class TransportQuota {

  /**
   * Janela da cota, em segundos.
   */
  public const WINDOW = 86400;

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly EntityTypeManagerInterface $entityTypes,
    private readonly TimeInterface $time,
  ) {}

  /**
   * Indica se o transporte já atingiu a cota da janela.
   */
  public function exhausted(string $transport): bool {
    $cap = $this->configFactory->get('aculta_mailer.policy')->get('quota.' . $transport);
    if (!is_int($cap) || $cap <= 0) {
      return FALSE;
    }
    $storage = $this->entityTypes->getStorage(MailLogRecorder::ENTITY_TYPE);
    $used = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('transport', $transport)
      ->condition('status', ['sent', 'delivered'], 'IN')
      ->condition('created', $this->time->getRequestTime() - self::WINDOW, '>=')
      ->count()
      ->execute();
    return (int) $used >= $cap;
  }

}
