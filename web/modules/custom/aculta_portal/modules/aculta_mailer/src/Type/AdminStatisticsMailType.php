<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Type;

use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Resumo das estatísticas da plataforma para o administrador (pedidos, matrículas, Wiki etc.).
 *
 * Os indicadores vêm do `aculta_statistics` por `StatisticsLinesProviderInterface`. Sem o
 * módulo, o e-mail informa que as estatísticas não estão disponíveis.
 */
final class AdminStatisticsMailType implements MailTypeInterface {

  public const ID = 'aculta_admin_statistics';

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
      'estatisticas' => [
        'label' => (string) $this->translation->translate('Indicadores da plataforma'),
        'sample' => "Pedidos pagos: 12\nReceita bruta (BRL): 1.234,56",
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultSubject(): string {
    return (string) $this->translation->translate('Estatísticas da plataforma ACULTA');
  }

  /**
   * {@inheritdoc}
   */
  public function defaultBody(): string {
    $partes = [
      (string) $this->translation->translate('Período: {{ periodo }}.'),
      (string) $this->translation->translate("Indicadores:\n{{ estatisticas }}"),
      (string) $this->translation->translate('Os valores são agregados e não identificam pessoas. Receita e reembolsos aparecem por moeda, em centavos convertidos.'),
    ];
    return implode("\n\n", $partes);
  }

}
