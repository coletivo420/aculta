<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Service;

use Drupal\aculta_editor\Service\FluentEmojiIconAdapterInterface;
use Drupal\aculta_forum\Access\DiscussionReactionEndpointAccess;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\user\EntityOwnerInterface;

/**
 * Builds the discussion reaction bar render array.
 */
class DiscussionReactionBuilder {

  use StringTranslationTrait;

  public function __construct(
    protected readonly DiscussionReactionManager $reactionManager,
    protected readonly DiscussionReactionEndpointAccess $reactionEndpointAccess,
    protected readonly CsrfTokenGenerator $csrfToken,
    protected readonly ?FluentEmojiIconAdapterInterface $emojiAdapter = NULL,
  ) {}

  /**
   * Builds the render array for the reaction bar of a comment or forum post.
   */
  public function build(EntityInterface $entity, AccountInterface $account): array {
    $entityType = $entity->getEntityTypeId();
    $entityId = (int) $entity->id();
    $uid = $account->isAuthenticated() ? (int) $account->id() : NULL;
    $authorUid = $entity instanceof EntityOwnerInterface ? (int) $entity->getOwnerId() : 0;
    $isOwnContent = ($uid !== NULL && $uid > 0 && $authorUid > 0 && $uid === $authorUid);

    $reactionsData = $this->reactionManager->loadReactions($entityType, $entityId, $uid);
    $access = $this->reactionEndpointAccess->access($entityType, (string) $entityId, $account);
    $canReact = $access->isAllowed();

    $reactUrl = Url::fromRoute('aculta_forum.reaction_react', [
      'entity_type' => $entityType,
      'entity_id' => $entityId,
    ])->toString();

    $pickerUrl = Url::fromRoute('aculta_forum.reaction_picker', [
      'entity_type' => $entityType,
      'entity_id' => $entityId,
    ])->toString();

    $csrfToken = $account->isAuthenticated()
      ? $this->csrfToken->get("aculta_forum:reaction:{$entityType}:{$entityId}")
      : '';

    $allowedReactions = $this->reactionManager->getAllowedReactions();
    $defaultReaction = $this->reactionManager->getDefaultReaction();

    $targetId = "discussion-reactions-{$entityType}-{$entityId}";

    $build = [
      '#type' => 'component',
      '#component' => 'aculta420_v2:discussion-reaction-bar',
      '#props' => [
        'target_id' => $targetId,
        'entity_type' => $entityType,
        'entity_id' => (string) $entityId,
        'reactions' => $reactionsData['reactions'],
        'total_count' => $reactionsData['total_count'],
        'user_reaction' => $reactionsData['user_reaction'],
        'allowed_reactions' => $allowedReactions,
        'default_reaction' => $defaultReaction,
        'can_react' => $canReact,
        'is_own_content' => $isOwnContent,
        'react_url' => $reactUrl,
        'picker_url' => $pickerUrl,
        'csrf_token' => $csrfToken,
      ],
      '#attached' => [
        'library' => [
          'core/drupal.htmx',
        ],
      ],
      '#cache' => [
        'contexts' => ['user', 'user.permissions'],
        'tags' => array_merge($entity->getCacheTags(), [
          "aculta_discussion_reactions:{$entityType}:{$entityId}",
          'config:aculta_forum.settings',
        ]),
      ],
    ];
    $cacheMetadata = CacheableMetadata::createFromRenderArray($build);
    $cacheMetadata->addCacheableDependency($access);
    $cacheMetadata->applyTo($build);

    return $build;
  }

  /**
   * Builds the render array for the extra reactions picker popover.
   */
  public function buildPicker(EntityInterface $entity, AccountInterface $account): array {
    $entityType = $entity->getEntityTypeId();
    $entityId = (int) $entity->id();
    $uid = $account->isAuthenticated() ? (int) $account->id() : NULL;

    $reactionsData = $this->reactionManager->loadReactions($entityType, $entityId, $uid);
    $access = $this->reactionEndpointAccess->access($entityType, (string) $entityId, $account);
    $canReact = $access->isAllowed();

    $reactUrl = Url::fromRoute('aculta_forum.reaction_react', [
      'entity_type' => $entityType,
      'entity_id' => $entityId,
    ])->toString();

    $csrfToken = $account->isAuthenticated()
      ? $this->csrfToken->get("aculta_forum:reaction:{$entityType}:{$entityId}")
      : '';

    $allowedReactions = $this->reactionManager->getAllowedReactions();
    $targetId = "discussion-reactions-{$entityType}-{$entityId}";

    $categoryLabels = [
      'cultivo' => (string) $this->t('Cultivo e Botânica'),
      'vibe' => (string) $this->t('Expressões e Comunidade'),
      'saude' => (string) $this->t('Ciência e Saúde'),
      'direitos' => (string) $this->t('Direitos e Sociedade'),
    ];

    $emojiCategories = [];
    if ($this->emojiAdapter !== NULL) {
      $rawCategories = $this->emojiAdapter->getCategories();
      foreach ($rawCategories as $catKey => $items) {
        $filteredItems = [];
        foreach ($allowedReactions as $reactionKey => $reactionInfo) {
          $emojiIcon = $reactionInfo['icon'];
          // Se o ícone do emoji existir nesta categoria do catálogo.
          if (isset($items[$emojiIcon])) {
            $catItem = $items[$emojiIcon];
            $filteredItems[$reactionKey] = [
              'id' => $reactionKey,
              'name' => $reactionInfo['label'],
              'icon' => $emojiIcon,
              'keywords' => array_merge([$reactionInfo['label']], $catItem['keywords'] ?? []),
            ];
          }
        }
        if (!empty($filteredItems)) {
          $emojiCategories[$catKey] = [
            'label' => $categoryLabels[$catKey] ?? ucfirst($catKey),
            'items' => $filteredItems,
          ];
        }
      }
    }

    if (empty($emojiCategories)) {
      $emojiCategories['geral'] = [
        'label' => (string) $this->t('Reações'),
        'items' => array_map(static fn(array $item): array => [
          'id' => $item['id'],
          'name' => $item['label'],
          'keywords' => [$item['label']],
        ], $allowedReactions),
      ];
    }

    return [
      '#theme' => 'discussion_reaction_picker',
      '#target_id' => $targetId,
      '#entity_type' => $entityType,
      '#entity_id' => (string) $entityId,
      '#allowed_reactions' => $allowedReactions,
      '#emoji_categories' => $emojiCategories,
      '#user_reaction' => $reactionsData['user_reaction'],
      '#can_react' => $canReact,
      '#react_url' => $reactUrl,
      '#csrf_token' => $csrfToken,
    ];
  }

}
