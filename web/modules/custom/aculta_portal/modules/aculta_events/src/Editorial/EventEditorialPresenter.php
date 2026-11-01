<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Editorial;

use Drupal\aculta_events\Entity\Event;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Prepares authorized supporters and press quotes for Event presentation.
 *
 * Enforces brand authorization policy: sponsor logos and brand marks are
 * NEVER rendered publicly unless field_apoiadores_autorizados is strictly TRUE.
 */
class EventEditorialPresenter {

  use StringTranslationTrait;

  public function __construct(?TranslationInterface $stringTranslation = NULL) {
    if ($stringTranslation !== NULL) {
      $this->stringTranslation = $stringTranslation;
    }
  }

  /**
   * Renders the authorized supporters section.
   *
   * @param \Drupal\aculta_events\Entity\Event $event
   *   The event entity.
   *
   * @return array<string, mixed>|null
   *   Render array or NULL if not authorized or empty.
   */
  public function renderSupporters(Event $event): ?array {
    if (!$event->hasField('field_apoiadores') || $event->get('field_apoiadores')->isEmpty()) {
      return NULL;
    }

    // Strict brand authorization guard.
    $authorized = $event->hasField('field_apoiadores_autorizados')
      && (bool) $event->get('field_apoiadores_autorizados')->value;

    if (!$authorized) {
      // Do not disclose or render unauthorized partner marks.
      return NULL;
    }

    $raw = (string) $event->get('field_apoiadores')->value;
    $items = $this->parseSupportersData($raw);
    if (empty($items)) {
      return NULL;
    }

    $renderItems = [];
    foreach ($items as $item) {
      $name = htmlspecialchars($item['nome'] ?? '', ENT_QUOTES, 'UTF-8');
      $cota = htmlspecialchars($item['cota'] ?? 'Apoio', ENT_QUOTES, 'UTF-8');
      $link = !empty($item['link']) ? filter_var($item['link'], FILTER_VALIDATE_URL) : NULL;

      $itemMarkup = sprintf('<span data-portal-ui="event-supporter-tier">%s</span>: <strong data-portal-ui="event-supporter-name">%s</strong>', $cota, $name);
      if ($link) {
        $itemMarkup = sprintf('<a href="%s" target="_blank" rel="noopener noreferrer" data-portal-ui="event-supporter-link">%s</a>', htmlspecialchars($link, ENT_QUOTES, 'UTF-8'), $itemMarkup);
      }

      $renderItems[] = [
        '#markup' => $itemMarkup,
        '#wrapper_attributes' => ['data-portal-ui' => 'event-supporter-item'],
      ];
    }

    return [
      '#type' => 'container',
      '#attributes' => [
        'data-portal-ui' => 'event-supporters',
      ],
      'heading' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => $this->t('Apoio e Realização'),
        '#attributes' => ['data-portal-ui' => 'event-section-title'],
      ],
      'list' => [
        '#theme' => 'item_list',
        '#items' => $renderItems,
        '#attributes' => ['data-portal-ui' => 'event-supporters-list'],
      ],
    ];
  }

  /**
   * Renders the press quotes and clipping section.
   *
   * @param \Drupal\aculta_events\Entity\Event $event
   *   The event entity.
   *
   * @return array<string, mixed>|null
   *   Render array or NULL if empty.
   */
  public function renderPressQuotes(Event $event): ?array {
    if (!$event->hasField('field_citacoes_imprensa') || $event->get('field_citacoes_imprensa')->isEmpty()) {
      return NULL;
    }

    $raw = (string) $event->get('field_citacoes_imprensa')->value;
    $quotes = $this->parsePressQuotesData($raw);
    if (empty($quotes)) {
      return NULL;
    }

    $renderItems = [];
    foreach ($quotes as $quote) {
      $veiculo = htmlspecialchars($quote['veiculo'] ?? '', ENT_QUOTES, 'UTF-8');
      $data = htmlspecialchars($quote['data'] ?? '', ENT_QUOTES, 'UTF-8');
      $trecho = htmlspecialchars($quote['trecho'] ?? '', ENT_QUOTES, 'UTF-8');
      $link = !empty($quote['link']) ? filter_var($quote['link'], FILTER_VALIDATE_URL) : NULL;

      $sourceText = $veiculo . ($data ? ' · ' . $data : '');
      $sourceMarkup = $link
        ? sprintf('<cite><a href="%s" target="_blank" rel="noopener noreferrer">%s</a></cite>', htmlspecialchars($link, ENT_QUOTES, 'UTF-8'), $sourceText)
        : sprintf('<cite>%s</cite>', $sourceText);

      $renderItems[] = [
        '#markup' => sprintf('<blockquote data-portal-ui="event-press-quote"><p>“%s”</p><footer>%s</footer></blockquote>', $trecho, $sourceMarkup),
      ];
    }

    return [
      '#type' => 'container',
      '#attributes' => [
        'data-portal-ui' => 'event-press-quotes',
      ],
      'heading' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => $this->t('Na Imprensa'),
        '#attributes' => ['data-portal-ui' => 'event-section-title'],
      ],
      'list' => [
        '#theme' => 'item_list',
        '#items' => $renderItems,
        '#attributes' => ['data-portal-ui' => 'event-press-quotes-list'],
      ],
    ];
  }

  /**
   * Parses supporters data from JSON or line format.
   *
   * @return array<int, array{nome: string, cota?: string, link?: string}>
   *   Parsed supporter records.
   */
  protected function parseSupportersData(string $raw): array {
    $trimmed = trim($raw);
    if (str_starts_with($trimmed, '[') || str_starts_with($trimmed, '{')) {
      $decoded = json_decode($trimmed, TRUE);
      if (is_array($decoded)) {
        return isset($decoded['nome']) ? [$decoded] : $decoded;
      }
    }

    // Line fallback: "Nome | Cota | Link".
    $lines = preg_split('/\r\n|\r|\n/', $trimmed);
    $results = [];
    foreach ($lines as $line) {
      $line = trim($line);
      if ($line === '') {
        continue;
      }
      $parts = array_map('trim', explode('|', $line));
      $results[] = [
        'nome' => $parts[0] ?? '',
        'cota' => $parts[1] ?? 'Apoio',
        'link' => $parts[2] ?? NULL,
      ];
    }

    return $results;
  }

  /**
   * Parses press quotes data from JSON or line format.
   *
   * @return array<int, array{veiculo: string, trecho: string, data?: string, link?: string}>
   *   Parsed press quote records.
   */
  protected function parsePressQuotesData(string $raw): array {
    $trimmed = trim($raw);
    if (str_starts_with($trimmed, '[') || str_starts_with($trimmed, '{')) {
      $decoded = json_decode($trimmed, TRUE);
      if (is_array($decoded)) {
        return isset($decoded['veiculo']) ? [$decoded] : $decoded;
      }
    }

    // Line fallback: "Veículo | Trecho | Data | Link".
    $lines = preg_split('/\r\n|\r|\n/', $trimmed);
    $results = [];
    foreach ($lines as $line) {
      $line = trim($line);
      if ($line === '') {
        continue;
      }
      $parts = array_map('trim', explode('|', $line));
      $results[] = [
        'veiculo' => $parts[0] ?? 'Imprensa',
        'trecho' => $parts[1] ?? '',
        'data' => $parts[2] ?? '',
        'link' => $parts[3] ?? NULL,
      ];
    }

    return $results;
  }

}
