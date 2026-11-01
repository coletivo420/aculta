<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Controller;

use Drupal\Core\Template\Attribute;
use Drupal\aculta_portal\Render\PortalLinkElement;
use Drupal\aculta_portal\Presentation\AccountCoursePresenter;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Presents the current user's LMS memberships inside ACCOUNT. */
final class AccountCoursesController implements ContainerInjectionInterface {

  public function __construct(
    private readonly AccountCoursePresenter $presenter,
    private readonly AccountProxyInterface $currentAccount,
    private readonly TranslationInterface $translation,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('aculta_portal.presentation.account_course'),
      $container->get('current_user'),
      $container->get('string_translation'),
    );
  }

  public function page(): array {
    $view = $this->presenter->present($this->currentAccount);
    $items = $view['items'];
    $build = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'account-courses'],
      '#cache' => [
        'contexts' => ['user', 'user.permissions'],
        'max-age' => 0,
      ],
    ];

    if ($items === []) {
      $build['empty'] = [
        '#type' => 'container',
        'message' => ['#plain_text' => $view['empty']['message']],
      ];
      if ($view['empty']['action'] !== NULL) {
        $build['empty']['link'] = PortalLinkElement::build($view['empty']['action']['label'], $view['empty']['action']['url'], ['data-portal-ui' => 'button-primary']);
      }
      return $build;
    }

    foreach ($items as $delta => $course) {
      $card = [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'account-course'],
        '#cache' => ['tags' => $course['cache_tags']],
        'title' => ['#theme' => 'aculta_portal_element', '#tag' => 'h3', '#value' => $course['title']],
      ];
      if ($course['description'] !== []) {
        $card['description'] = $course['description'];
      }
      $card['meta'] = [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'account-course-meta'],
        'status' => [
          '#plain_text' => (string) $this->translation->translate(
            'Status: @status',
            ['@status' => $course['status']['label']],
          ),
        ],
      ];
      if ($course['score_label'] !== NULL) {
        $card['meta']['score'] = ['#plain_text' => $course['score_label']];
      }
      if ($course['action'] !== NULL) {
        $card['action'] = PortalLinkElement::build($course['action']['label'], $course['action']['url'], ['data-portal-ui' => 'button-primary']);
      }
      $build['course_' . $delta] = $card;
    }

    return $build;
  }

}
