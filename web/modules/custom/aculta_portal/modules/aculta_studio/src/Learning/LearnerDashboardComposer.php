<?php

declare(strict_types=1);

namespace Drupal\aculta_studio\Learning;

use Drupal\aculta_lms\LearningDashboardManager;
use Drupal\aculta_lms\Entity\CourseCertificate;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;

/**
 * Owns the learner dashboard render composition; LMS owns authorized data.
 */
final class LearnerDashboardComposer {

  use StringTranslationTrait;

  public function __construct(
    private readonly LearningDashboardManager $dashboards,
    private readonly AccountProxyInterface $currentUser,
    private readonly DateFormatterInterface $dateFormatter,
    TranslationInterface $stringTranslation,
  ) {
    $this->stringTranslation = $stringTranslation;
  }

  public function build(): array {
    $data = $this->dashboards->studentDashboard($this->currentUser);
    $build = [
      '#type' => 'container',
      'context_header' => [
        '#type' => 'component',
        '#component' => 'aculta420:context-header',
        '#props' => [
          'eyebrow' => (string) $this->t('GanjaLab420'),
          'title' => (string) $this->t('Meus cursos'),
          'subtitle' => (string) $this->t('Acompanhe suas aulas e certificados.'),
        ],
        '#weight' => -100,
      ],
      '#cache' => ['max-age' => 0],
    ];
    $course_items = [];
    foreach ($data['courses'] as $course) {
      $item = [
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          '#plain_text' => (string) $course['label'],
        ],
        'progress' => [
          '#type' => 'container',
          'label' => [
            '#plain_text' => $this->t('@completed de @total aulas concluídas (@percent%)', [
              '@completed' => $course['completed_lessons'],
              '@total' => $course['total_lessons'],
              '@percent' => $course['progress_percent'],
            ]),
          ],
          'bar' => [
            '#type' => 'html_tag',
            '#tag' => 'progress',
            '#value' => '',
            '#attributes' => [
              'max' => 100,
              'value' => $course['progress_percent'],
              'aria-label' => $this->t('Progresso do curso'),
            ],
          ],
        ],
      ];
      if ($course['pending_evaluation']) {
        $item['pending'] = ['#plain_text' => $this->t('Avaliação pendente')];
      }
      if ($course['url'] instanceof Url) {
        $item['link'] = Link::fromTextAndUrl($this->t('Acessar curso'), $course['url'])->toRenderable();
      }
      $course_items[] = [
        '#type' => 'component',
        '#component' => 'aculta420:card',
        '#slots' => [
          'header' => $item['title'],
          'content' => [
            'progress' => $item['progress'],
            'pending' => $item['pending'] ?? [],
          ],
          'footer' => $item['link'] ?? [],
        ],
      ];
    }
    $build['courses'] = $course_items === []
      ? [
        '#type' => 'component',
        '#component' => 'aculta420:empty-state',
        '#props' => ['compact' => TRUE],
        '#slots' => ['message' => ['#plain_text' => $this->t('Você ainda não está matriculado em nenhum curso.')]],
      ]
      : [
        '#type' => 'component',
        '#component' => 'aculta420:content-grid',
        '#props' => ['columns' => 2],
        '#slots' => ['items' => $course_items],
      ];

    $build['certificates_title'] = ['#type' => 'html_tag', '#tag' => 'h2', '#value' => $this->t('Meus certificados')];
    $certificate_items = [];
    foreach ($data['certificates'] as $certificate) {
      if (!$certificate instanceof CourseCertificate) {
        continue;
      }
      $valid = $certificate->get('state')->value === 'active';
      $certificate_data = [
        'course' => ['#plain_text' => (string) $certificate->get('course_title')->value],
        'state' => ['#plain_text' => $valid ? $this->t('Válido') : $this->t('Revogado')],
        'issued' => [
          '#plain_text' => $this->t('Emitido em @date', [
            '@date' => $this->dateFormatter->format((int) $certificate->get('issued')->value, 'short'),
          ]),
        ],
        'verify' => Link::fromTextAndUrl($this->t('Ver certificado'), Url::fromRoute('aculta_lms.certificate_verify', [
          'codigo' => $certificate->get('verification_code')->value,
        ]))->toRenderable(),
      ];
      $certificate_items[] = [
        '#type' => 'component',
        '#component' => 'aculta420:card',
        '#slots' => [
          'header' => $certificate_data['course'],
          'content' => [
            'state' => $certificate_data['state'],
            'issued' => $certificate_data['issued'],
          ],
          'footer' => $certificate_data['verify'],
        ],
      ];
    }
    $build['certificates'] = $certificate_items === []
      ? [
        '#type' => 'component',
        '#component' => 'aculta420:empty-state',
        '#props' => ['compact' => TRUE],
        '#slots' => ['message' => ['#plain_text' => $this->t('Nenhum certificado emitido ainda.')]],
      ]
      : [
        '#type' => 'component',
        '#component' => 'aculta420:content-grid',
        '#props' => ['columns' => 2],
        '#slots' => ['items' => $certificate_items],
      ];
    CacheableMetadata::createFromRenderArray($build)
      ->addCacheableDependency($data['cache'])
      ->applyTo($build);
    return $build;
  }

}
