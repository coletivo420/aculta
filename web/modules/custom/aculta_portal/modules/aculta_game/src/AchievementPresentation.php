<?php

declare(strict_types=1);

namespace Drupal\aculta_game;

use Drupal\aculta_user\Badge\BadgeService;
use Drupal\aculta_user\PublicProfilePolicy;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\profile\Entity\ProfileInterface;
use Drupal\user\UserDataInterface;

/** Representação de conquistas, sem publicar evidências ou dados de origem. */
final class AchievementPresentation {

  use StringTranslationTrait;

  public function __construct(
    private readonly EntityTypeManagerInterface $entities,
    private readonly BadgeService $badges,
    private readonly UserDataInterface $userData,
    private readonly CacheTagsInvalidatorInterface $cacheInvalidator,
    private readonly ModuleExtensionList $modules,
  ) {}

  /** Preferências ausentes significam exibição pública, sem destaques. */
  public function preferences(int $uid): array {
    $stored = $this->userData->get('aculta_user', $uid, 'game_achievement_display');
    return is_array($stored) ? $stored + ['hide_all' => FALSE, 'items' => [], 'email_notifications' => TRUE] : ['hide_all' => FALSE, 'items' => [], 'email_notifications' => TRUE];
  }

  /** API interna; o chamador deve autorizar a pessoa que altera as escolhas. */
  public function savePreferences(int $uid, array $preferences): void {
    if ($uid <= 0) {
      throw new \InvalidArgumentException('Preferências exigem uma conta.');
    }
    $items = [];
    foreach ($this->badges->definitions() as $id => $definition) {
      $item = $preferences['items'][$id] ?? [];
      $items[$id] = [
        'hidden' => !empty($item['hidden']),
        'highlight' => !empty($item['highlight']),
        'weight' => max(-50, min(50, (int) ($item['weight'] ?? 0))),
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

  /** Lista própria: não depende das preferências públicas. */
  public function own(int $uid): array {
    $build = $this->listing($uid, FALSE);
    $build['#cache']['contexts'][] = 'user';
    return $build;
  }

  /** Perfil e futuras caixas devem usar esta mesma projeção pública. */
  public function publicFor(ProfileInterface $profile): array {
    $uid = (int) $profile->getOwnerId();
    $cache = $this->cache($uid)->addCacheableDependency($profile);
    $owner = $profile->getOwner();
    $cache->addCacheableDependency($owner);
    $public = !$profile->hasField('field_perfil_publico') || $profile->get('field_perfil_publico')->isEmpty()
      || (bool) $profile->get('field_perfil_publico')->value;
    $identified = $profile->bundle() === 'participante' && $profile->hasField('field_nickname') && !$profile->get('field_nickname')->isEmpty();
    if (!$identified || !$this->badges->isEligible($uid) || (!$public && !PublicProfilePolicy::isObrigatorioPublico($owner?->getRoles() ?? []))) {
      $build = [];
      $cache->applyTo($build);
      return $build;
    }
    $build = $this->listing($uid, TRUE);
    $cache->applyTo($build);
    return $build;
  }

  private function listing(int $uid, bool $public): array {
    $preferences = $this->preferences($uid);
    $cache = $this->cache($uid);
    $build = [];
    if ($public && !empty($preferences['hide_all'])) {
      $cache->applyTo($build);
      return $build;
    }
    $catalog = $this->badges->definitions();
    $rows = [];
    foreach ($this->badges->activeFor($uid) as $award) {
      $cache->addCacheableDependency($award);
      $id = (string) $award->get('badge')->value;
      $choice = $preferences['items'][$id] ?? [];
      if (!isset($catalog[$id]) || ($public && !empty($choice['hidden']))) {
        continue;
      }
      // A projeção não inclui IDs de curso/pedido, atores ou dados pessoais.
      $rows[$id] = [
        'id' => $id,
        'label' => $catalog[$id]['label'],
        'highlight' => !empty($choice['highlight']),
        'weight' => (int) ($choice['weight'] ?? 0),
      ];
    }
    uasort($rows, static fn (array $a, array $b): int =>
      ((int) $b['highlight'] <=> (int) $a['highlight']) ?: ($a['weight'] <=> $b['weight']) ?: strcmp($a['label'], $b['label']));
    if ($rows !== [] || !$public) {
      $items = [];
      foreach ($rows as $row) {
        $items[] = [
          '#type' => 'container',
          'image' => $this->imageForDefinition($catalog[$row['id']]),
          // ItemList interpreta filhos sem #type como sublistas.
          'label' => ['#type' => 'container', 'text' => ['#plain_text' => $row['highlight'] ? $this->t('@conquista — em destaque', ['@conquista' => $row['label']]) : (string) $row['label']]],
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
    return $build;
  }

  /** IDs concedidos ativos para formar a ficha única de cada conquista. */
  public function earnedIds(int $uid): array {
    $ids = [];
    foreach ($this->badges->activeFor($uid) as $award) {
      $ids[(string) $award->get('badge')->value] = TRUE;
    }
    return $ids;
  }

  /** Imagem pública atual, com fallback dos arquivos históricos importados. */
  public function imageForDefinition(array $definition): array {
    if (!empty($definition['image_uuid'])) {
      $files = $this->entities->getStorage('file')->loadByProperties(['uuid' => $definition['image_uuid']]);
      $file = $files ? reset($files) : NULL;
      if ($file && str_starts_with($file->getFileUri(), 'public://') && str_starts_with((string) $file->getMimeType(), 'image/')) {
        return [
          '#theme' => 'image',
          '#uri' => $file->getFileUri(),
          '#alt' => (string) ($definition['label'] ?? ''),
          '#width' => 96,
          '#cache' => ['tags' => $file->getCacheTags()],
        ];
      }
    }
    $legacy = [
      'fundadore' => 'fundadore.png',
      'associado' => 'associado-antiproibicionista.png',
      'apoiador' => 'apoiadore.png',
    ][$definition['image'] ?? ''] ?? NULL;
    return $legacy ? [
      '#theme' => 'image',
      '#uri' => $this->modules->getPath('aculta_game') . '/assets/conquistas/' . $legacy,
      '#width' => 96,
      '#alt' => (string) ($definition['label'] ?? ''),
    ] : [];
  }

  public function definitions(): array {
    return $this->badges->definitions();
  }

  private function cache(int $uid): CacheableMetadata {
    return (new CacheableMetadata())->addCacheTags([
      'user:' . $uid,
      'aculta_game:display:' . $uid,
      'config:aculta_user.achievement_definitions',
      ...$this->entities->getDefinition('aculta_badge_award')->getListCacheTags(),
    ])->addCacheContexts(['languages:language_interface']);
  }

}
