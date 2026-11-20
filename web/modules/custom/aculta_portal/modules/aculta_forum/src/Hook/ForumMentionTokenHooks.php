<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Hook;

use Drupal\aculta_forum\Service\ForumIdentityTokens;
use Drupal\comment\CommentInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Stores @mentions as account-id tokens and renders them with current names.
 */
final class ForumMentionTokenHooks {

  /**
   * Creates the hooks.
   */
  public function __construct(
    private readonly ForumIdentityTokens $tokens,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Converts known @handles in a Forum comment into {uid:N} tokens before saving.
   */
  #[Hook('comment_presave')]
  public function presave(CommentInterface $comment): void {
    if ($comment->bundle() !== 'aculta_discussion_post' || !$comment->hasField('comment_body') || $comment->get('comment_body')->isEmpty()) {
      return;
    }
    $item = $comment->get('comment_body')->first();
    if ($item === NULL) {
      return;
    }
    $rawValue = $item->get('value')->getValue();
    $rawFormat = $item->get('format')->getValue();
    $value = is_string($rawValue) ? $rawValue : '';
    $format = is_string($rawFormat) ? $rawFormat : '';
    if ($value === '' || !str_contains($value, '@')) {
      return;
    }
    $updated = $this->tokens->tokenizeMentions(
      $value,
      fn(string $handle): int => $this->uidForName($handle),
    );
    if ($updated !== $value) {
      $comment->set('comment_body', ['value' => $updated, 'format' => $format !== '' ? $format : 'basic_html']);
    }
  }

  /**
   * Replaces tokens with the current display names when a comment is shown.
   *
   * @param array<string, mixed> $build
   *   Comment render array.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The rendered comment.
   */
  #[Hook('comment_view')]
  public function view(array &$build, EntityInterface $entity): void {
    $body = $build['comment_body'] ?? NULL;
    if (!is_array($body)) {
      return;
    }
    foreach ($body as $key => $child) {
      if (!is_array($child)) {
        continue;
      }
      $rendered = $child;
      foreach (['#text', '#markup'] as $prop) {
        $value = $rendered[$prop] ?? NULL;
        if (is_string($value) && str_contains($value, '{uid:')) {
          $rendered[$prop] = $this->tokens->render($value, fn(int $uid): ?string => $this->nameOf($uid));
        }
      }
      $body[$key] = $rendered;
    }
    $build['comment_body'] = $body;
  }

  /**
   * Active account id for a lowercase handle, or 0.
   */
  private function uidForName(string $handle): int {
    $ids = $this->entityTypeManager->getStorage('user')->getQuery()
      ->accessCheck(FALSE)
      ->condition('name', $handle)
      ->condition('status', 1)
      ->range(0, 1)
      ->execute();
    return $ids === [] ? 0 : (int) reset($ids);
  }

  /**
   * Current display name of an account, or NULL when it does not exist.
   */
  private function nameOf(int $uid): ?string {
    $user = $this->entityTypeManager->getStorage('user')->load($uid);
    return $user === NULL ? NULL : (string) $user->getDisplayName();
  }

}
