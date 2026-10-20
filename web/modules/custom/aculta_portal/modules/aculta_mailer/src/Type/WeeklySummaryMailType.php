<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Type;

use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Resumo semanal dos envios para o administrador (modelo do WP Mail SMTP, relatório por e-mail).
 */
final class WeeklySummaryMailType implements MailTypeInterface {

  public const ID = 'aculta_weekly_summary';

  public function __construct(
    private readonly TranslationInterface $translation,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function id(): string {
    return self::ID;
  }

  /**
   * {@inheritdoc}
   */
  public function variables(): array {
    return [
      'periodo' => [
        'label' => (string) $this->translation->translate('Período'),
        'sample' => '13/10/2026 a 20/10/2026',
      ],
      'total' => [
        'label' => (string) $this->translation->translate('Total de envios'),
        'sample' => '120',
      ],
      'enviados' => [
        'label' => (string) $this->translation->translate('Aceitos pelo sistema de e-mail'),
        'sample' => '117',
      ],
      'falhas' => [
        'label' => (string) $this->translation->translate('Falhas'),
        'sample' => '3',
      ],
      'rebotes' => [
        'label' => (string) $this->translation->translate('Devolvidos (bounce)'),
        'sample' => '1',
      ],
      'anterior' => [
        'label' => (string) $this->translation->translate('Total da semana anterior'),
        'sample' => '98',
      ],
      'motivos' => [
        'label' => (string) $this->translation->translate('Motivos de falha'),
        'sample' => 'smtp_554: 3',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultSubject(): string {
    return (string) $this->translation->translate('Resumo semanal de envios de e-mail do ACULTA');
  }

  /**
   * {@inheritdoc}
   */
  public function defaultBody(): string {
    $partes = [
      (string) $this->translation->translate('Período: {{ periodo }}.'),
      (string) $this->translation->translate('Total de envios: {{ total }} (semana anterior: {{ anterior }}).') . "\n" . (string) $this->translation->translate('Aceitos pelo sistema de e-mail: {{ enviados }}.') . "\n" . (string) $this->translation->translate('Falhas: {{ falhas }}. Devolvidos: {{ rebotes }}.'),
      (string) $this->translation->translate("Motivos de falha:\n{{ motivos }}"),
      (string) $this->translation->translate('"Aceito" significa aceito pelo sistema de e-mail, não entregue na caixa. A entrega depende dos provedores e dos relatórios de devolução.'),
    ];
    return implode("\n\n", $partes);
  }

}
