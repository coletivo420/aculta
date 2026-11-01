<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Controller;

use Drupal\aculta_forum\Entity\ForumPost;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionableStorageInterface;
use Drupal\Core\Link;
use Drupal\Core\Mail\MailFormatHelper;
use Drupal\Core\Url;
use Drupal\diff\DiffEntityComparison;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Compares two authorized revisions of one Forum post.
 */
final class ForumPostRevisionComparisonController extends ControllerBase {

  public function __construct(
    private readonly EntityTypeManagerInterface $forumEntityTypeManager,
    private readonly DiffEntityComparison $entityComparison,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('diff.entity_comparison'),
    );
  }

  /**
   * Builds a read-only subject/body comparison for two post revisions.
   */
  public function compare(ForumPost $aculta_forum_post, string $left_revision, string $right_revision): array {
    $storage = $this->forumEntityTypeManager->getStorage('aculta_forum_post');
    if (!$storage instanceof RevisionableStorageInterface) {
      throw new NotFoundHttpException();
    }

    $left = $storage->loadRevision($left_revision);
    $right = $storage->loadRevision($right_revision);
    if (!$left instanceof ForumPost
      || !$right instanceof ForumPost
      || (string) $left->id() !== (string) $aculta_forum_post->id()
      || (string) $right->id() !== (string) $aculta_forum_post->id()
      || (string) $left->getRevisionId() !== $left_revision
      || (string) $right->getRevisionId() !== $right_revision
      || (string) $left->getRevisionId() === (string) $right->getRevisionId()) {
      throw new NotFoundHttpException();
    }

    $fields = $this->entityComparison->compareRevisions($left, $right);
    $labels = [
      'subject' => $this->t('Assunto'),
      'body' => $this->t('Texto'),
    ];
    $rows = [];
    foreach ($labels as $field_name => $label) {
      $key = $aculta_forum_post->id() . ':aculta_forum_post.' . $field_name;
      if (!isset($fields[$key])) {
        continue;
      }

      $field = $fields[$key];
      $field['#data']['#left'] = MailFormatHelper::htmlToText((string) ($field['#data']['#left'] ?? ''));
      $field['#data']['#right'] = MailFormatHelper::htmlToText((string) ($field['#data']['#right'] ?? ''));
      if ($field['#data']['#left'] === $field['#data']['#right']) {
        continue;
      }

      $this->entityComparison->processStateLine($field);
      $field_rows = $this->entityComparison->getRows(
        $field['#data']['#left'],
        $field['#data']['#right'],
      );
      $rows[] = [
        [
          'data' => ['#plain_text' => $label],
          'colspan' => 4,
          'class' => ['field-name'],
        ],
      ];
      foreach ($field_rows as $field_row) {
        $rows[] = $field_row;
      }
    }

    $build = [
      '#title' => $this->t('Comparando revisão @left com @right', [
        '@left' => $left_revision,
        '@right' => $right_revision,
      ]),
      'description' => [
        '#plain_text' => $this->t('A comparação mostra somente as diferenças no assunto e no texto da publicação.'),
      ],
      'comparison' => [
        '#type' => 'table',
        '#prefix' => '<div class="diff-responsive-table-wrapper">',
        '#suffix' => '</div>',
        '#header' => [
          $this->t('Alteração'),
          $this->t('Revisão anterior'),
          $this->t('Alteração'),
          $this->t('Revisão comparada'),
        ],
        '#rows' => $rows,
        '#empty' => $this->t('Não há diferenças no assunto ou no texto entre estas revisões.'),
        '#attributes' => ['class' => ['diff', 'diff-responsive-table']],
      ],
      'back' => Link::fromTextAndUrl(
        $this->t('Voltar ao histórico de revisões'),
        Url::fromRoute('aculta_forum.post_revision_history', [
          'aculta_forum_post' => $aculta_forum_post->id(),
        ]),
      )->toRenderable(),
      '#attached' => [
        'library' => [
          'diff/diff.general',
          'diff/diff.double_column',
          'diff/diff.colors',
        ],
      ],
    ];

    CacheableMetadata::createFromObject($aculta_forum_post)
      ->addCacheableDependency($left)
      ->addCacheableDependency($right)
      ->addCacheContexts(['domain', 'user', 'user.permissions', 'languages:language_content'])
      ->addCacheTags(['aculta_forum_post_list', 'config:diff.plugins', 'config:diff.settings', 'config:system.diff'])
      ->applyTo($build);
    return $build;
  }

}
