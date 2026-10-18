<?php

declare(strict_types=1);

namespace Drupal\aculta_lms_h5p\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\lms\Entity\Bundle\CourseInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Builds H5P entry points within the existing ACULTA LMS contexts. */
final class H5PDashboardController implements ContainerInjectionInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AccountProxyInterface $currentUser,
    private readonly DomainPurposeManager $domainPurposeManager,
    private readonly TranslationInterface $translation,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('aculta_portal.domain_purpose'),
      $container->get('string_translation'),
    );
  }

  /** Administrative library and configuration hub. */
  public function administrator(): array {
    $items = [
      Link::createFromRoute($this->translation->translate('Configurações H5P'), 'aculta_lms_h5p.admin.config.system.h5p')->toRenderable(),
      Link::createFromRoute($this->translation->translate('Bibliotecas H5P'), 'aculta_lms_h5p.admin.content.h5p')->toRenderable(),
    ];
    return [
      '#theme' => 'item_list',
      '#title' => $this->translation->translate('Administração de conteúdo interativo'),
      '#items' => $items,
      '#cache' => ['contexts' => ['user.permissions']],
    ];
  }

  /** Student view of interactive content in courses they can take. */
  public function student(): array {
    return $this->courseList(FALSE);
  }

  /** Educator view of courses they can update. */
  public function educator(): array {
    return $this->courseList(TRUE);
  }

  /**
   * Builds only links and fields authorized by the existing Group/LMS access.
   */
  private function courseList(bool $educator): array {
    $storage = $this->entityTypeManager->getStorage('group');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'lms_course')
      ->sort('label')
      ->execute();
    $cacheability = (new CacheableMetadata())
      ->addCacheContexts(['user', 'user.permissions', 'user.group_permissions'])
      ->addCacheTags(['group_list']);
    $items = [];

    foreach ($storage->loadMultiple($ids) as $entity) {
      if (!$entity instanceof CourseInterface) {
        continue;
      }
      $access = $educator
        ? $entity->access('update', $this->currentUser, TRUE)
        : $entity->takeAccess($this->currentUser);
      $cacheability->addCacheableDependency($entity)->addCacheableDependency($access);
      if (!$access->isAllowed()) {
        continue;
      }

      if (!$educator && (!$entity->hasField('field_h5p_activities') || $entity->get('field_h5p_activities')->isEmpty())) {
        continue;
      }

      $courseUrl = $this->domainPurposeManager->routeUrl('courses', 'entity.group.canonical', ['group' => $entity->id()]);
      if ($courseUrl === NULL) {
        continue;
      }
      $courseRouteAccess = $courseUrl->access($this->currentUser, TRUE);
      $cacheability->addCacheableDependency($courseRouteAccess);
      if (!$courseRouteAccess->isAllowed()) {
        continue;
      }

      $courseBuild = [
        '#type' => 'container',
        '#attributes' => ['class' => ['aculta-lms-h5p-course']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h2',
          '#value' => (string) $entity->label(),
        ],
        'course_link' => Link::fromTextAndUrl(
          $educator ? $this->translation->translate('Abrir sala do curso') : $this->translation->translate('Acessar curso'),
          $courseUrl,
        )->toRenderable(),
      ];

      if (!$educator) {
        $courseBuild['activities'] = $entity->get('field_h5p_activities')->view(['label' => 'hidden']);
      }

      $items[] = $courseBuild;
    }

    $build = [
      '#type' => 'container',
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h1',
        '#value' => $educator ? $this->translation->translate('Atividades interativas dos meus cursos') : $this->translation->translate('Atividades interativas'),
      ],
      'courses' => $items === []
        ? ['#plain_text' => $educator
          ? $this->translation->translate('Você ainda não administra cursos com atividades H5P.')
          : $this->translation->translate('Você ainda não tem acesso a cursos com atividades H5P.')]
        : ['#theme' => 'item_list', '#items' => $items],
      '#cache' => [],
    ];
    if ($educator) {
      $build['editor_notice'] = [
        '#plain_text' => $this->translation->translate('Para criar ou editar atividades H5P, abra a sala do curso e use a aba Editar. O acesso segue as permissões da equipe do curso.'),
        '#weight' => 10,
      ];
    }
    $cacheability->applyTo($build);
    return $build;
  }

}
