<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Hook;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\aculta_lms\AttemptArchiveManager;
use Drupal\aculta_lms\Entity\AttemptArchive;
use Drupal\group\Access\GroupAccessResult;
use Drupal\lms\Entity\AnswerInterface;
use Drupal\lms\Entity\CourseStatusInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Captures deleted LMS answers and renders protected attempt history. */
final class AttemptArchiveHooks {

  public function __construct(
    #[Autowire(service: 'aculta_lms.attempt_archive')]
    private readonly AttemptArchiveManager $attemptArchiveManager,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly DateFormatterInterface $dateFormatter,
    private readonly TranslationInterface $translation,
  ) {}

  #[Hook('entity_predelete')]
  public function entityPredelete(EntityInterface $entity): void {
    if ($entity instanceof AnswerInterface) {
      $this->attemptArchiveManager->archiveAnswer($entity);
    }
  }

  #[Hook('entity_postdelete')]
  public function entityPostdelete(EntityInterface $entity): void {
    if ($entity instanceof CourseStatusInterface) {
      $this->attemptArchiveManager->purgeByCourseStatus($entity);
      return;
    }
    if ($entity instanceof GroupInterface && $entity->bundle() === 'lms_course') {
      $this->attemptArchiveManager->purgeByGroup($entity);
      return;
    }
    if ($entity instanceof UserInterface) {
      $this->attemptArchiveManager->purgeByUser($entity);
    }
  }

  #[Hook('entity_presave')]
  public function entityPresave(EntityInterface $entity): void {
    if ($entity instanceof AnswerInterface) {
      $this->attemptArchiveManager->archivePreviousAnswer($entity);
    }
  }

  #[Hook('entity_access')]
  public function attemptArchiveAccess(EntityInterface $entity, string $operation, AccountInterface $account): AccessResult {
    if (!$entity instanceof AttemptArchive) {
      return AccessResult::neutral();
    }

    if ($operation === 'view' || $operation === 'view label') {
      if ($account->hasPermission('administer lms')) {
        return AccessResult::allowed()->cachePerPermissions()->addCacheableDependency($entity);
      }
      $course = $this->entityTypeManager->getStorage('group')->load($entity->get('group_id')->value);
      if (!$course instanceof GroupInterface) {
        return AccessResult::forbidden()->addCacheableDependency($entity);
      }
      $permissions = (string) $account->id() === (string) $entity->get('uid')->value
        ? ['take course']
        : ['grade students'];
      return GroupAccessResult::allowedIfHasGroupPermissions($course, $account, $permissions)
        ->addCacheableDependency($entity)
        ->addCacheableDependency($course);
    }

    return AccessResult::forbidden()->addCacheableDependency($entity);
  }

  #[Hook('entity_create_access')]
  public function attemptArchiveCreateAccess(AccountInterface $account, array $context, ?string $entityBundle): AccessResult {
    if (($context['entity_type_id'] ?? NULL) !== 'aculta_lms_attempt_archive') {
      return AccessResult::neutral();
    }
    return AccessResult::allowedIfHasPermission($account, 'administer lms')
      ->cachePerPermissions();
  }

  #[Hook('lms_answer_details_alter')]
  public function addPreviousAttempts(array &$build, AnswerInterface $answer, CacheableMetadata $cacheability): void {
    $storage = $this->entityTypeManager->getStorage('aculta_lms_attempt_archive');
    $cacheability->addCacheTags($storage->getEntityType()->getListCacheTags());
    $cacheability->addCacheContexts(['user', 'user.group_permissions']);
    $records = $this->attemptArchiveManager->loadPreviousAttempts($answer, $cacheability);
    if ($records === []) {
      return;
    }

    $rows = [];
    foreach ($records as $record) {
      $cacheability->addCacheableDependency($record);
      $event_label = match ($record->get('event_type')->value) {
        'answer_changed' => $this->translation->translate('Resposta substituída'),
        'evaluation_changed' => $this->translation->translate('Nota reavaliada'),
        default => $this->translation->translate('Tentativa reiniciada'),
      };
      $rows[] = [
        ['data' => ['#plain_text' => $this->dateFormatter->format((int) $record->get('answered')->value, 'short')]],
        ['data' => ['#plain_text' => $event_label]],
        ['data' => ['#plain_text' => $record->get('evaluated')->value
          ? $this->translation->translate('Avaliada: @score de @maximum pontos', [
            '@score' => $record->get('score')->value,
            '@maximum' => $record->get('max_score')->value,
          ])
          : $this->translation->translate('Aguardando avaliação do educador')]],
        ['data' => ['#plain_text' => $this->answerText($record->get('answer_data')->value)]],
      ];
      $comments = Json::decode((string) $record->get('answer_comments')->value) ?: [];
      foreach ($comments as $comment) {
        $author_type = ($comment['author_type'] ?? '') === 'student' ? 'estudante' : 'educador';
        $rows[] = [
          ['data' => ['#plain_text' => $this->dateFormatter->format((int) ($comment['created'] ?? 0), 'short')]],
          ['data' => ['#plain_text' => $this->translation->translate('Comentário de @author (@type)', [
            '@author' => $comment['author'] ?? $this->translation->translate('educador'),
            '@type' => $this->translation->translate($author_type),
          ])]],
          ['data' => ['#plain_text' => '']],
          ['data' => ['#plain_text' => \strip_tags((string) ($comment['body'] ?? ''))]],
        ];
      }
    }

    $build['previous_attempts'] = [
      '#type' => 'details',
      '#title' => $this->translation->translate('Histórico de respostas e avaliações'),
      '#open' => FALSE,
      'history' => [
        '#theme' => 'table',
        '#header' => [
          $this->translation->translate('Data'),
          $this->translation->translate('Registro'),
          $this->translation->translate('Avaliação'),
          $this->translation->translate('Resposta'),
        ],
        '#rows' => $rows,
      ],
    ];
  }

  private function answerText(string $json): string {
    $data = Json::decode($json);
    $answer = $data['answer'] ?? $data;
    if (\is_string($answer)) {
      return \strip_tags($answer);
    }
    if (!\is_array($answer)) {
      return (string) $answer;
    }

    $parts = [];
    foreach ($answer as $item) {
      if (\is_array($item)) {
        $item = $item['value'] ?? $item;
      }
      if (\is_scalar($item) && (string) $item !== '') {
        $parts[] = \strip_tags((string) $item);
      }
    }
    return \implode(' / ', $parts);
  }

}
