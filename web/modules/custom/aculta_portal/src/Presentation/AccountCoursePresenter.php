<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Presentation;

use Drupal\aculta_learning_contracts\Learning\AccountCourseSummary;
use Drupal\aculta_portal\AccountCoursesManager;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Prepares account course view-models from provider-neutral course data.
 *
 * User-facing labels and tones belong to the Portal presenter; provider access
 * and course progress remain behind the neutral learning contract.
 */
final class AccountCoursePresenter {

  public function __construct(
    private readonly AccountCoursesManager $courses,
    private readonly TranslationInterface $translation,
  ) {}

  /**
   * Builds the view-model for the current account's course section.
   *
   * @return array{
   *   items: array<int, array{
   *     id: string,
   *     title: string,
   *     description: array,
   *     status: array{code: string, label: string, tone: string},
   *     score_label: string|null,
   *     action: array{label: string, url: \Drupal\Core\Url, kind: string}|null,
   *     cache_tags: string[]
   *   }>,
   *   empty: array{
   *     message: string,
   *     action: array{label: string, url: \Drupal\Core\Url, kind: string}|null
   *   }
   *   }
   */
  public function present(AccountInterface $account): array {
    $items = [];

    foreach ($this->courses->getCourses($account) as $course) {
      $statusCode = (string) ($course['status'] ?? AccountCourseSummary::STATUS_NOT_STARTED);
      $score = $course['score'];
      $url = $course['url'];

      $items[] = [
        'id' => (string) $course['id'],
        'title' => (string) $course['label'],
        'description' => $course['description'],
        'status' => $this->statusPresentation($statusCode),
        'score_label' => $score !== NULL
          ? (string) $this->translation->translate('Resultado: @score%', ['@score' => $score])
          : NULL,
        'action' => $url !== NULL ? [
          'label' => (string) $this->translation->translate(
            $course['finished'] ? 'Ver resultado' : 'Acessar curso'
          ),
          'url' => $url,
          'kind' => 'primary',
        ] : NULL,
        'cache_tags' => $course['cache_tags'],
      ];
    }

    $catalog = $this->courses->catalogUrl();

    return [
      'items' => $items,
      'empty' => [
        'message' => (string) $this->translation->translate('Você ainda não está participando de nenhum curso.'),
        'action' => $catalog !== NULL ? [
          'label' => (string) $this->translation->translate('Ver cursos disponíveis'),
          'url' => $catalog,
          'kind' => 'primary',
        ] : NULL,
      ],
    ];
  }

  /**
   * Maps the shared learning status vocabulary to account presentation.
   *
   * @return array{code: string, label: string, tone: string}
   *   Código, rótulo e tom visual do status.
   */
  private function statusPresentation(string $status): array {
    return match ($status) {
      AccountCourseSummary::STATUS_IN_PROGRESS => [
        'code' => $status,
        'label' => (string) $this->translation->translate('Em andamento'),
        'tone' => 'info',
      ],
      AccountCourseSummary::STATUS_COMPLETED => [
        'code' => $status,
        'label' => (string) $this->translation->translate('Concluído'),
        'tone' => 'success',
      ],
      AccountCourseSummary::STATUS_FAILED => [
        'code' => $status,
        'label' => (string) $this->translation->translate('Não aprovado'),
        'tone' => 'danger',
      ],
      AccountCourseSummary::STATUS_AWAITING_EVALUATION => [
        'code' => $status,
        'label' => (string) $this->translation->translate('Aguardando avaliação'),
        'tone' => 'warning',
      ],
      default => [
        'code' => $status,
        'label' => (string) $this->translation->translate('Não iniciado'),
        'tone' => 'neutral',
      ],
    };
  }

}
