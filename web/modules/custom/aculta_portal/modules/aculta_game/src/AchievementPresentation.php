<?php

declare(strict_types=1);

namespace Drupal\aculta_game;

use Drupal\aculta_user\Badge\BadgeService;
use Drupal\aculta_user\Entity\BadgeAward;
use Drupal\aculta_user\PublicProfilePolicy;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Template\Attribute;
use Drupal\file\FileInterface;
use Drupal\profile\Entity\ProfileInterface;
use Drupal\user\UserDataInterface;
use Drupal\user\UserInterface;
use Drupal\aculta_portal\Profile\PerfilDoPapel;

/**
 * Representação de conquistas, sem publicar evidências ou dados de origem.
 */
final class AchievementPresentation {

  use StringTranslationTrait;

  public function __construct(
    private readonly EntityTypeManagerInterface $entities,
    private readonly BadgeService $badges,
    private readonly UserDataInterface $userData,
    private readonly CacheTagsInvalidatorInterface $cacheInvalidator,
    private readonly ModuleExtensionList $modules,
  ) {}

  /**
   * Preferências ausentes significam exibição pública, sem destaques.
   *
   * @return array{hide_all: bool, items: array<string, array{hidden: bool, highlight: bool, weight: int}>, email_notifications: bool}
   */
  public function preferences(int $uid): array {
    $stored = $this->userData->get('aculta_user', $uid, 'game_achievement_display');
    $defaults = [
      'hide_all' => FALSE,
      'items' => [],
      'email_notifications' => TRUE,
    ];
    if (!is_array($stored)) {
      return $defaults;
    }
    $rawItems = isset($stored['items']) && is_array($stored['items']) ? $stored['items'] : [];
    $items = [];
    foreach ($rawItems as $k => $item) {
      if (is_array($item)) {
        $items[(string) $k] = [
          'hidden' => !empty($item['hidden']),
          'highlight' => !empty($item['highlight']),
          'weight' => isset($item['weight']) && is_numeric($item['weight']) ? (int) $item['weight'] : 0,
        ];
      }
    }
    return [
      'hide_all' => !empty($stored['hide_all']),
      'items' => $items,
      'email_notifications' => !array_key_exists('email_notifications', $stored) || !empty($stored['email_notifications']),
    ];
  }

  /**
   * API interna; o chamador deve autorizar a pessoa que altera as escolhas.
   *
   * @param array<string, mixed> $preferences
   */
  public function savePreferences(int $uid, array $preferences): void {
    if ($uid <= 0) {
      throw new \InvalidArgumentException('Preferências exigem uma conta.');
    }
    $items = [];
    $rawItems = isset($preferences['items']) && is_array($preferences['items']) ? $preferences['items'] : [];
    /** @var array<string, mixed> $definitions */
    $definitions = $this->badges->definitions();
    foreach ($definitions as $id => $definition) {
      $idStr = (string) $id;
      $item = isset($rawItems[$idStr]) && is_array($rawItems[$idStr]) ? $rawItems[$idStr] : [];
      $weight = isset($item['weight']) && is_numeric($item['weight']) ? (int) $item['weight'] : 0;
      $items[$idStr] = [
        'hidden' => !empty($item['hidden']),
        'highlight' => !empty($item['highlight']),
        'weight' => max(-50, min(50, $weight)),
      ];
    }
    // User owns privacy: uninstalling optional Game must not erase choices.
    $this->userData->set('aculta_user', $uid, 'game_achievement_display', [
      'hide_all' => !empty($preferences['hide_all']),
      'items' => $items,
      'email_notifications' => !array_key_exists('email_notifications', $preferences) || !empty($preferences['email_notifications']),
    ]);
    $this->cacheInvalidator->invalidateTags(['aculta_game:display:' . $uid]);
  }

  /**
   * Lista própria: não depende das preferências públicas.
   *
   * @return array<string, mixed>
   */
  public function own(int $uid): array {
    $build = $this->listing($uid, FALSE);
    if (!isset($build['#cache']) || !is_array($build['#cache'])) {
      $build['#cache'] = [];
    }
    if (!isset($build['#cache']['contexts']) || !is_array($build['#cache']['contexts'])) {
      $build['#cache']['contexts'] = [];
    }
    $build['#cache']['contexts'][] = 'user';
    return $build;
  }

  /**
   * Perfil e futuras caixas devem usar esta mesma projeção pública.
   *
   * @return array<string, mixed>
   */
  public function publicFor(ProfileInterface $profile): array {
    $uid = (int) $profile->getOwnerId();
    $cache = $this->cache($uid)->addCacheableDependency($profile);
    $owner = $profile->getOwner();
    $cache->addCacheableDependency($owner);
    /** @var array<string> $roles */
    $roles = $owner->getRoles();
    $public = !$profile->hasField('field_perfil_publico') || $profile->get('field_perfil_publico')->isEmpty()
      || (bool) $profile->get('field_perfil_publico')->value;
    $identified = PerfilDoPapel::ehTipoDePapel($profile) && $profile->hasField('field_nickname') && !$profile->get('field_nickname')->isEmpty();
    if (!$identified || !$this->badges->isEligible($uid) || (!$public && !PublicProfilePolicy::isObrigatorioPublico($roles))) {
      $build = [];
      $cache->applyTo($build);
      /** @var array<string, mixed> $build */
      return $build;
    }
    $build = $this->listing($uid, TRUE);
    $cache->applyTo($build);
    /** @var array<string, mixed> $build */
    return $build;
  }

  /**
   * Build the own or public achievement listing.
   *
   * @return array<string, mixed>
   */
  private function listing(int $uid, bool $public): array {
    $preferences = $this->preferences($uid);
    $cache = $this->cache($uid);
    $build = [];
    if ($public && !empty($preferences['hide_all'])) {
      $cache->applyTo($build);
      /** @var array<string, mixed> $build */
      return $build;
    }
    /** @var array<string, array{label: string|\Drupal\Core\StringTranslation\TranslatableMarkup, description?: string, triggers?: array<int|string, string>, enabled?: bool, image_uuid?: string, image?: string}> $catalog */
    $catalog = $this->badges->definitions();
    $rows = [];
    foreach ($this->badges->activeFor($uid) as $award) {
      $cache->addCacheableDependency($award);
      $badgeValue = $award->get('badge')->value;
      $id = is_scalar($badgeValue) ? (string) $badgeValue : '';
      $choice = $preferences['items'][$id] ?? [];
      if (!isset($catalog[$id]) || ($public && !empty($choice['hidden']))) {
        continue;
      }
      // A projeção não inclui IDs de curso/pedido, atores ou dados pessoais.
      $rows[$id] = [
        'id' => $id,
        'label' => (string) $catalog[$id]['label'],
        'highlight' => !empty($choice['highlight']),
        'weight' => (int) ($choice['weight'] ?? 0),
      ];
    }
    uasort($rows, static fn (array $a, array $b): int =>
      ((int) $b['highlight'] <=> (int) $a['highlight']) ?: ($a['weight'] <=> $b['weight']) ?: strcmp($a['label'], $b['label']));
    if ($public && $rows !== []) {
      $items = [];
      foreach ($rows as $row) {
        $definition = $catalog[$row['id']];
        $item = [
          '#type' => 'container',
          '#attributes' => ['data-portal-ui' => 'profile-achievement'],
        ];
        $image = $this->imageForDefinition($definition, TRUE, (string) $row['id']);
        if ($image !== []) {
          $item['image'] = [
            '#type' => 'container',
            '#attributes' => ['data-portal-ui' => 'profile-achievement-image'],
            'asset' => $image,
          ];
        }
        $content = [
          '#type' => 'container',
          '#attributes' => ['data-portal-ui' => 'profile-achievement-content'],
          'label' => [
            '#theme' => 'aculta_portal_element',
            '#tag' => 'h4',
            '#value' => (string) $definition['label'],
            '#element_attributes' => new Attribute(['data-portal-ui' => 'profile-achievement-name']),
          ],
        ];
        if (!empty($row['highlight'])) {
          $content['highlight'] = [
            '#type' => 'container',
            '#attributes' => ['data-portal-ui' => 'profile-achievement-highlight'],
            'text' => ['#plain_text' => $this->t('Em destaque')],
          ];
        }
        $desc = isset($definition['description']) ? trim((string) $definition['description']) : '';
        if ($desc !== '') {
          $content['description'] = [
            '#theme' => 'aculta_portal_element',
            '#tag' => 'p',
            '#value' => $desc,
            '#element_attributes' => new Attribute(['data-portal-ui' => 'profile-achievement-description']),
          ];
        }
        $item['content'] = $content;
        $items[] = $item;
      }
      $build = [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'profile-achievements'],
        'title' => [
          '#theme' => 'aculta_portal_element',
          '#tag' => 'h3',
          '#value' => $this->t('Conquistas'),
          '#element_attributes' => new Attribute(['data-portal-ui' => 'profile-achievements-title']),
        ],
        'items' => [
          '#type' => 'container',
          '#attributes' => ['data-portal-ui' => 'profile-achievements-list'],
          'entries' => $items,
        ],
      ];
    }
    elseif ($rows !== [] || !$public) {
      $items = [];
      foreach ($rows as $row) {
        $items[] = [
          '#type' => 'container',
          'image' => $this->imageForDefinition($catalog[$row['id']]),
          // ItemList interpreta filhos sem #type como sublistas.
          'label' => [
            '#type' => 'container',
            'text' => [
              '#plain_text' => $row['highlight']
                ? $this->t('@conquista — em destaque', ['@conquista' => $row['label']])
                : (string) $row['label'],
            ],
          ],
        ];
      }
      $build = [
        '#theme' => 'item_list',
        '#title' => $this->t('Conquistas'),
        '#items' => $items,
        '#empty' => $this->t('Você ainda não tem conquistas.'),
      ];
    }
    $cache->applyTo($build);
    /** @var array<string, mixed> $build */
    return $build;
  }

  /**
   * IDs concedidos ativos para formar a ficha única de cada conquista.
   *
   * @return array<string, bool>
   */
  public function earnedIds(int $uid): array {
    $ids = [];
    foreach ($this->badges->activeFor($uid) as $award) {
      $badgeValue = $award->get('badge')->value;
      $id = is_scalar($badgeValue) ? (string) $badgeValue : '';
      if ($id !== '') {
        $ids[$id] = TRUE;
      }
    }
    return $ids;
  }

  /**
   * Imagem pública atual, com fallback dos arquivos históricos importados.
   *
   * @param array<mixed> $definition
   *
   * @return array<string, mixed>
   */
  public function imageForDefinition(array $definition, bool $useFallback = FALSE, string $achievementId = ''): array {
    $label = isset($definition['label']) && is_scalar($definition['label']) ? (string) $definition['label'] : '';
    if (!empty($definition['image_uuid'])) {
      $files = $this->entities->getStorage('file')->loadByProperties(['uuid' => $definition['image_uuid']]);
      $file = $files ? reset($files) : NULL;
      if ($file instanceof FileInterface) {
        $fileUri = $file->getFileUri();
        $mimeType = (string) $file->getMimeType();
        if ($fileUri !== NULL && str_starts_with($fileUri, 'public://') && str_starts_with($mimeType, 'image/')) {
          return [
            '#theme' => 'image',
            '#uri' => $fileUri,
            '#alt' => $label,
            '#width' => 96,
            '#cache' => ['tags' => $file->getCacheTags()],
          ];
        }
      }
    }
    $legacyKey = isset($definition['image']) && is_scalar($definition['image']) ? (string) $definition['image'] : '';
    $legacy = [
      'fundadore' => 'fundadore.png',
      'associado' => 'associado-antiproibicionista.png',
      'apoiador' => 'apoiadore.png',
    ][$legacyKey] ?? NULL;
    if ($legacy) {
      return [
        '#theme' => 'image',
        '#uri' => $this->modules->getPath('aculta_game') . '/assets/conquistas/' . $legacy,
        '#width' => 96,
        '#alt' => $label,
      ];
    }
    $illustrations = [
      'primeiro_comentario_forum' => 'primeiro-comentario-forum.png',
      'primeira_edicao_wiki' => 'primeira-edicao-wiki.png',
      'primeiro_curso' => 'primeiro-curso.png',
      'perfil_basico' => 'perfil-basico.png',
    ];
    if (isset($illustrations[$achievementId])) {
      return [
        '#theme' => 'image',
        '#uri' => $this->modules->getPath('aculta_game') . '/assets/conquistas/' . $illustrations[$achievementId],
        '#width' => 96,
        '#height' => 96,
        '#alt' => $label,
      ];
    }
    return $useFallback ? [
      '#theme' => 'image',
      '#uri' => $this->modules->getPath('aculta_game') . '/assets/conquistas/conquista-padrao.png',
      '#width' => 96,
      '#height' => 96,
      '#alt' => (string) $this->t('Símbolo ilustrativo da conquista @label', ['@label' => $label]),
      '#cache' => ['contexts' => ['languages:language_interface']],
    ] : [];
  }

  /**
   * Return the configured achievement definitions.
   *
   * @return array<string, array{label: string|\Drupal\Core\StringTranslation\TranslatableMarkup, description?: string, triggers?: array<int|string, string>, enabled?: bool, image_uuid?: string, image?: string}>
   */
  public function definitions(): array {
    /** @var array<string, array{label: string|\Drupal\Core\StringTranslation\TranslatableMarkup, description?: string, triggers?: array<int|string, string>, enabled?: bool, image_uuid?: string, image?: string}> $definitions */
    $definitions = $this->badges->definitions();
    return $definitions;
  }

  /**
   * Build cache metadata for one account achievement listing.
   */
  private function cache(int $uid): CacheableMetadata {
    return (new CacheableMetadata())->addCacheTags([
      'user:' . $uid,
      'aculta_game:display:' . $uid,
      'config:aculta_user.achievement_definitions',
      ...$this->entities->getDefinition('aculta_badge_award')->getListCacheTags(),
    ])->addCacheContexts(['languages:language_interface']);
  }

}
