<?php

declare(strict_types=1);

namespace Drupal\aculta_game;

use Drupal\aculta_user\Badge\BadgeCatalog;
use Drupal\aculta_user\Badge\BadgeService;
use Drupal\aculta_user\PublicProfilePolicy;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
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
  ) {}

  /** Preferências ausentes significam exibição pública, sem destaques. */
  public function preferences(int $uid): array {
    $stored = $this->userData->get('aculta_user', $uid, 'game_achievement_display');
    return is_array($stored) ? $stored + ['hide_all' => FALSE, 'items' => []] : ['hide_all' => FALSE, 'items' => []];
  }

  /** API interna; o chamador deve autorizar a pessoa que altera as escolhas. */
  public function savePreferences(int $uid, array $preferences): void {
    if ($uid <= 0) {
      throw new \InvalidArgumentException('Preferências exigem uma conta.');
    }
    $items = [];
    foreach (BadgeCatalog::all() as $id => $definition) {
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
    $catalog = BadgeCatalog::all();
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
        $items[] = ['#plain_text' => $row['highlight'] ? $this->t('@conquista — em destaque', ['@conquista' => $row['label']]) : $this->t($row['label'])];
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

  private function cache(int $uid): CacheableMetadata {
    return (new CacheableMetadata())->addCacheTags([
      'user:' . $uid,
      'aculta_game:display:' . $uid,
      ...$this->entities->getDefinition('aculta_badge_award')->getListCacheTags(),
    ])->addCacheContexts(['languages:language_interface']);
  }

}
