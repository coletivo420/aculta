<?php

declare(strict_types=1);

// Adaptado de OpenCulturas 3.1.4 (GPL-2.0-or-later), profile/modules/custom/openculturas_map/src/Plugin/Field/FieldFormatter/SmartDateUpcomingFormatter.php

namespace Drupal\aculta_events\Plugin\Field\FieldFormatter;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Mostra só as datas que ainda não terminaram, em horário de Brasília.
 *
 * Diferença em relação ao OpenCulturas: não depende de cron de invalidação. O cache do
 * elemento expira no fim da próxima data (correção de T5 na auditoria), e o formato é
 * dd/mm/aaaa HH:mm, em 24 horas.
 */
#[FieldFormatter(
  id: 'aculta_daterange_upcoming',
  label: new TranslatableMarkup('Datas futuras (ACULTA)'),
  field_types: ['daterange'],
)]
final class DateRangeUpcomingFormatter extends FormatterBase {

  /**
   * Fuso do site (D9).
   */
  private const FUSO = 'America/Sao_Paulo';

  /**
   * Formato brasileiro com horário de 24 horas.
   */
  private const FORMATO = 'd/m/Y H:i';

  /**
   * Construtor.
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    $label,
    $view_mode,
    array $third_party_settings,
    private readonly TimeInterface $tempo,
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('datetime.time'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $fuso = new \DateTimeZone(self::FUSO);
    $utc = new \DateTimeZone('UTC');
    $agora = $this->tempo->getCurrentTime();
    $elements = [];
    $validade = NULL;

    foreach ($items as $delta => $item) {
      $valores = $item->getValue();
      $inicio = (new \DateTimeImmutable((string) $valores['value'], $utc))->setTimezone($fuso);
      $fim = (new \DateTimeImmutable((string) $valores['end_value'], $utc))->setTimezone($fuso);

      // Data que já terminou não aparece.
      if ($fim->getTimestamp() < $agora) {
        continue;
      }

      $elements[$delta] = [
        '#markup' => $this->texto($inicio, $fim),
      ];
      $validade = $validade === NULL ? $fim->getTimestamp() - $agora : min($validade, $fim->getTimestamp() - $agora);
    }

    // Quando a próxima data terminar, o conjunto muda: o cache acompanha esse instante.
    if ($validade !== NULL) {
      $elements['#cache'] = ['max-age' => $validade];
    }

    return $elements;
  }

  /**
   * Texto da data: "dd/mm/aaaa HH:mm – HH:mm" no mesmo dia, ou dois instantes completos.
   */
  private function texto(\DateTimeImmutable $inicio, \DateTimeImmutable $fim): string {
    if ($inicio->format('Y-m-d') === $fim->format('Y-m-d')) {
      return $inicio->format(self::FORMATO) . ' – ' . $fim->format('H:i');
    }
    return $inicio->format(self::FORMATO) . ' – ' . $fim->format(self::FORMATO);
  }

}
