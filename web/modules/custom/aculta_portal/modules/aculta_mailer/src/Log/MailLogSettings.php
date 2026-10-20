<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Log;

use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Configuração do registro de envios (`aculta_mailer.log`): ligado e retenção. Sem valor gravado, vale o padrão atual.
 */
final class MailLogSettings {

  public const CONFIG = 'aculta_mailer.log';

  /**
   * Retenção padrão, em dias (comportamento anterior à tela).
   */
  public const DEFAULT_RETENTION_DAYS = 90;

  /**
   * Prazos permitidos, em dias, para a tela de retenção.
   */
  public const RETENTION_OPTIONS = [30, 90, 180, 365];

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Registro ligado. Sem valor gravado, ligado (padrão).
   */
  public function enabled(): bool {
    return $this->configFactory->get(self::CONFIG)->get('enabled') !== FALSE;
  }

  /**
   * Prazo de retenção, em dias. Valor fora da lista permitida vale o padrão.
   */
  public function retentionDays(): int {
    $days = (int) $this->configFactory->get(self::CONFIG)->get('retention_days');
    return in_array($days, self::RETENTION_OPTIONS, TRUE) ? $days : self::DEFAULT_RETENTION_DAYS;
  }

}
