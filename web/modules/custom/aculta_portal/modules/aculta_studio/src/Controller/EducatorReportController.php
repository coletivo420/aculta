<?php

declare(strict_types=1);

namespace Drupal\aculta_studio\Controller;

use Drupal\aculta_cgu\Scope\ReportTargetScope;
use Drupal\aculta_cgu\Service\CguReportQueue;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Fila de denúncias de discussões LMS no workspace Ganjalab Educador.
 *
 * A fila e a decisão são do CGU. Este controller só monta a tela do educador com
 * componentes SDC. Quem vê um alvo é decidido por ReportTargetScope, pelo mesmo
 * Group que protege a moderação pedagógica; nada aqui concede permissão.
 */
final class EducatorReportController {

  use StringTranslationTrait;

  /**
   * Permissão que habilita a fila do educador (escopo por Group, não global).
   */
  public const PERMISSION = 'moderar denuncias lms';

  public function __construct(
    private readonly CguReportQueue $reportQueue,
    private readonly ReportTargetScope $targetScope,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly DateFormatterInterface $dateFormatter,
    private readonly AccountInterface $account,
  ) {}

  /**
   * Lista os conteúdos LMS denunciados que esta educadora pode moderar.
   *
   * @return array<string, mixed>
   *   Render array da fila.
   */
  public function queue(): array {
    if (!$this->account->hasPermission(self::PERMISSION)) {
      throw new AccessDeniedHttpException();
    }
    $page = $this->reportQueue->getQueueItems($this->account, 'lms', 'pendente', 0, 50);

    $items = [];
    foreach ($page['items'] as $group) {
      $target = $this->loadTarget($group['target_type'], $group['target_id']);
      if ($target === NULL) {
        continue;
      }
      $items[] = [
        '#component' => 'aculta420_v2:studio-report-item',
        '#props' => [
          'label' => (string) $target->label(),
          'total' => $group['total'],
          'last_at' => $group['ultima'] > 0 ? $this->dateFormatter->format($group['ultima'], 'short') : '',
          'url' => Url::fromRoute('aculta_studio.educator_report_decide', [
            'target_type' => $group['target_type'],
            'target_id' => $group['target_id'],
          ])->toString(),
        ],
      ];
    }

    return [
      '#type' => 'container',
      '#attributes' => [
        'data-aculta-studio' => 'educator-report-queue',
        'data-portal-ui' => 'studio-course-educator',
      ],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h1',
        '#value' => $this->t('Denúncias das discussões dos meus cursos'),
      ],
      'items' => $items !== []
        ? $items
        : [
          '#component' => 'aculta420_v2:studio-report-empty',
          '#props' => ['message' => (string) $this->t('Nenhuma denúncia pendente nos seus cursos.')],
        ],
      '#cache' => ['contexts' => ['user', 'user.permissions'], 'max-age' => 0],
    ];
  }

  /**
   * Carrega o alvo denunciado, se ainda existir e a conta puder moderá-lo.
   */
  private function loadTarget(string $targetType, int $targetId): ?EntityInterface {
    if (!$this->entityTypeManager->hasDefinition($targetType)) {
      return NULL;
    }
    $target = $this->entityTypeManager->getStorage($targetType)->load($targetId);
    if ($target === NULL || !$this->targetScope->decide('lms', $target, $this->account)) {
      return NULL;
    }
    return $target;
  }

}
