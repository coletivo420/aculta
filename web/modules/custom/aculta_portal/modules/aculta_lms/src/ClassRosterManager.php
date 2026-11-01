<?php

declare(strict_types=1);

namespace Drupal\aculta_lms;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupRelationshipInterface;
use Drupal\lms\Entity\Bundle\CourseInterface;
use Drupal\lms_classes\ClassHelper;
use Drupal\aculta_user\Identity\AuthorCardBuilder;
use Drupal\user\UserInterface;

/**
 * Builds the optional, privacy-limited roster on a learner's course page.
 */
class ClassRosterManager {

  use StringTranslationTrait;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    TranslationInterface $stringTranslation,
    private readonly AuthorCardBuilder $cardBuilder,
  ) {
    $this->stringTranslation = $stringTranslation;
  }

  /**
   * Shows classmates only when the learner's own class has enabled the list.
   *
   * @return array<string, mixed>
   *   Render array with its per-user access and membership cache metadata.
   */
  public function build(CourseInterface $course, AccountInterface $viewer): array {
    $cacheability = (new CacheableMetadata())
      ->addCacheableDependency($course)
      ->setCacheContexts(['user', 'user.group_permissions'])
      ->setCacheTags([
        'group.classes:' . $course->id(),
        'group_relationship_list:plugin:lms_classes:group:' . $course->id(),
      ]);
    $classLists = [];

    foreach (ClassHelper::getClasses($course) as $class) {
      if (!$class instanceof GroupInterface || $class->bundle() !== 'lms_class') {
        continue;
      }
      $cacheability->addCacheableDependency($class);
      $cacheability->addCacheTags(['group_relationship_list:plugin:group_membership:group:' . $class->id()]);
      if (!$class->hasField('field_roster_visible') || !(bool) $class->get('field_roster_visible')->value) {
        continue;
      }

      $members = [];
      $viewerIsStudent = FALSE;
      foreach ($this->membershipsForClass($class) as $membership) {
        $cacheability->addCacheableDependency($membership);
        $student = $membership->getEntity();
        if (!$student instanceof UserInterface) {
          continue;
        }
        $cacheability->addCacheableDependency($student);
        if (!$student->isActive()) {
          continue;
        }
        if ((string) $student->id() === (string) $viewer->id()) {
          $viewerIsStudent = TRUE;
        }
        $members[] = $student;
      }

      if (!$viewerIsStudent) {
        continue;
      }
      usort($members, static fn (UserInterface $a, UserInterface $b): int => strnatcasecmp((string) $a->getDisplayName(), (string) $b->getDisplayName()));
      // Quem tem perfil público usa o cartão com apelido. Quem não tem continua
      // na lista com o nome de exibição e o avatar do acervo (decisão de
      // 2026-10-27), em vez de virar texto simples.
      $items = [];
      foreach ($members as $member) {
        $items[] = $this->cardBuilder->build($member, 'profile_compact', [], NULL, 'sm')
          ?? $this->cardBuilder->buildFallback($member, 'sm');
      }
      $classLists[] = [
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          '#value' => $class->label(),
        ],
        'members' => [
          '#theme' => 'item_list',
          '#title' => $this->t('Participantes da turma'),
          '#items' => $items,
          '#empty' => $this->t('Ainda não há outros participantes nesta turma.'),
        ],
      ];
    }

    $build = $classLists === [] ? [] : [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'lms-class-roster'],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $this->t('Participantes'),
      ],
      'classes' => $classLists,
    ];
    $cacheability->applyTo($build);
    return $build;
  }

  /**
   * Loads only membership relationships carrying the student class role.
   *
   * @return \Drupal\group\Entity\GroupRelationshipInterface[]
   *   Student memberships for this class.
   */
  private function membershipsForClass(GroupInterface $class): array {
    $storage = $this->entityTypeManager->getStorage('group_relationship');
    $memberships = $storage->loadByProperties([
      'gid' => $class->id(),
      'plugin_id' => 'group_membership',
    ]);
    return array_values(array_filter($memberships, static function ($membership): bool {
      if (!$membership instanceof GroupRelationshipInterface || !$membership->hasField('group_roles')) {
        return FALSE;
      }
      $roles = array_column($membership->get('group_roles')->getValue(), 'target_id');
      return in_array('lms_class-member', $roles, TRUE);
    }));
  }

}
