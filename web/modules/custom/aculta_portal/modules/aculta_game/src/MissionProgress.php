<?php

declare(strict_types=1);

namespace Drupal\aculta_game;

use Drupal\aculta_user\Badge\BadgeCatalog;
use Drupal\aculta_user\Badge\BadgeService;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Missões unitárias sobre fatos históricos, sem saldo ou storage de progresso.
 */
final class MissionProgress {

  use StringTranslationTrait;

  private const CRITERIA = [
    'comunidade' => BadgeCatalog::PRIMEIRO_COMENTARIO,
    'conhecimento' => BadgeCatalog::PRIMEIRA_EDICAO_WIKI,
    'aprendizagem' => BadgeCatalog::PRIMEIRO_CURSO,
  ];

  public function __construct(
    private readonly ConfigFactoryInterface $configs,
    private readonly BadgeService $badges,
    private readonly EntityTypeManagerInterface $entities,
    private readonly DateFormatterInterface $dateFormatter,
  ) {}

  /**
   * Catálogo fechado: configuração não cria regras executáveis arbitrárias.
   */
  public function definitions(bool $onlyEnabled = TRUE): array {
    $definitions = [];
    $configured = $this->configs->get('aculta_game.missions')->get('missions') ?? [];
    foreach (self::CRITERIA as $id => $badge) {
      $definition = $configured[$id] ?? [];
      if (($definition['badge'] ?? NULL) !== $badge || ($definition['criterion_version'] ?? NULL) !== 1
        || ($onlyEnabled && empty($definition['enabled']))) {
        continue;
      }
      $definitions[$id] = $definition;
    }
    return $definitions;
  }

  /**
   * Progresso privado: zero ou um marco registrado, sem reavaliar o passado.
   */
  public function build(int $uid): array {
    $config = $this->configs->get('aculta_game.missions');
    $cache = (new CacheableMetadata())->addCacheableDependency($config)
      ->addCacheTags(['user:' . $uid, ...$this->entities->getDefinition('aculta_badge_award')->getListCacheTags()])
      ->addCacheContexts(['user', 'languages:language_interface']);
    $build = ['#type' => 'details', '#title' => $this->t('Missões'), '#open' => TRUE];
    if (!$this->badges->isEligible($uid)) {
      $build['message'] = ['#plain_text' => $this->t('As missões estão disponíveis para contas participantes ativas.')];
      $cache->applyTo($build);
      return $build;
    }
    // Data de obtenção exibida no tooltip; só concessões ativas contam.
    $active = [];
    foreach ($this->badges->activeFor($uid) as $award) {
      $active[(string) $award->get('badge')->value] = (int) $award->get('concedido_em')->value;
      $cache->addCacheableDependency($award);
    }
    foreach ($this->definitions() as $id => $definition) {
      $done = isset($active[$definition['badge']]);
      $data = $done ? $this->dateFormatter->format($active[$definition['badge']], 'custom', 'd/m/Y') : '';
      // Mission labels and descriptions are maintained as configuration text.
      // phpcs:ignore Drupal.Semantics.FunctionT.NotLiteralString
      $label = $this->t($definition['label']);
      // phpcs:ignore Drupal.Semantics.FunctionT.NotLiteralString
      $criterion = $this->t($definition['description']);
      $build[$id] = [
        '#type' => 'details',
        '#title' => $label,
        '#open' => TRUE,
        // O tooltip informa a data apenas para conquistas obtidas.
        '#attributes' => $done ? [
          'title' => (string) $this->t('Obtida em @data', ['@data' => $data]),
          'data-aculta-game' => 'obtida',
        ] : [],
        'criterion' => ['#plain_text' => $criterion],
        'progress' => [
          '#plain_text' => $done
            ? $this->t('Concluída em @data — 1 de 1 marco registrado.', ['@data' => $data])
            : $this->t('A realizar — 0 de 1 marco registrado.'),
        ],
      ];
    }
    if (count($this->definitions()) === 0) {
      $build['message'] = ['#plain_text' => $this->t('Não há missões disponíveis no momento.')];
    }
    $cache->applyTo($build);
    return $build;
  }

}
