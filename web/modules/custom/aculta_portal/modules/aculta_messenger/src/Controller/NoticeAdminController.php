<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Administrative landing and listing pages for ACULTA notices.
 */
final class NoticeAdminController extends ControllerBase {

  use StringTranslationTrait;

  public function __construct(
    #[Autowire(service: 'entity_type.manager')]
    private readonly EntityTypeManagerInterface $noticeEntityTypeManager,
    #[Autowire(service: 'date.formatter')]
    private readonly DateFormatterInterface $dateFormatter,
    #[Autowire(service: 'current_user')]
    private readonly AccountInterface $noticeAdminAccount,
  ) {}

  /** {@inheritdoc} */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('date.formatter'),
      $container->get('current_user'),
    );
  }

  /**
   * Builds the notice administration overview.
   */
  public function overview(): array {
    $items = [];
    if ($this->noticeAdminAccount->hasPermission('administer site alert')) {
      $items['temporary'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['admin-item']],
        'title' => ['#type' => 'link', '#title' => $this->t('Avisos temporários'), '#url' => Url::fromRoute('aculta_messenger.admin_temporary_notices')],
        'description' => ['#type' => 'html_tag', '#tag' => 'p', '#value' => $this->t('Gerencie alertas curtos, com prioridade e período de exibição. Cada visitante pode dispensar o aviso na própria sessão/conta.')],
      ];
    }
    if ($this->noticeAdminAccount->hasPermission('administer nodes')) {
      $items['persistent'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['admin-item']],
        'title' => ['#type' => 'link', '#title' => $this->t('Avisos persistentes'), '#url' => Url::fromRoute('aculta_messenger.admin_persistent_notices')],
        'description' => ['#type' => 'html_tag', '#tag' => 'p', '#value' => $this->t('Crie comunicados institucionais segmentados por público, com início, validade, revisão editorial e estado individual de leitura.')],
      ];
    }

    return [
      'intro' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['aculta-notice-admin-intro']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $this->t('Centralize aqui os avisos que aparecem no site. Escolha o formato conforme o tempo de validade da comunicação.'),
        ],
        'description' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $this->t('Avisos temporários podem ser agendados e dispensados por cada pessoa. Avisos persistentes são comunicados editoriais, podem usar os formatos de texto autorizados para sua conta e ficam disponíveis até a validade definida.'),
        ],
      ],
      'items' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['admin-list', 'aculta-notice-admin-list']],
        ...$items,
      ],
      '#cache' => [
        'tags' => ['node_list', 'site_alert_list'],
        'contexts' => ['user.permissions'],
      ],
    ];
  }

  /**
   * Presents Site Alert's native collection inside the ACULTA Avisos section.
   */
  public function temporary(): array {
    return [
      'intro' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['aculta-notice-admin-intro']],
        'description' => [
          '#plain_text' => $this->t('Avisos temporários usam a agenda e a severidade do Site Alert. A lista e as operações abaixo são as nativas do Drupal; os formulários de criação, edição e exclusão continuam respeitando as permissões do módulo.'),
        ],
      ],
      'collection' => $this->noticeEntityTypeManager->getListBuilder('site_alert')->render(),
      '#cache' => [
        'tags' => ['site_alert_list'],
        'contexts' => ['user.permissions'],
      ],
    ];
  }

  /**
   * Builds an access-aware table using native Node entities and operations.
   */
  public function persistent(): array {
    $query = $this->noticeEntityTypeManager->getStorage('node')->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'aculta_notice')
      ->sort('changed', 'DESC')
      ->pager(25);
    $ids = $query->execute();
    $nodes = $this->noticeEntityTypeManager->getStorage('node')->loadMultiple($ids);
    $rows = [];
    $cacheability = (new CacheableMetadata())
      ->addCacheTags(['node_list', 'config:node.type.aculta_notice'])
      ->addCacheContexts(['user.permissions', 'user.node_grants:view', 'timezone']);

    foreach ($ids as $id) {
      $node = $nodes[$id] ?? NULL;
      if (!$node instanceof NodeInterface) {
        continue;
      }
      $titleAccess = $node->access('view', NULL, TRUE);
      $updateAccess = $node->access('update', NULL, TRUE);
      $deleteAccess = $node->access('delete', NULL, TRUE);
      $cacheability
        ->addCacheableDependency($node)
        ->addCacheableDependency($titleAccess)
        ->addCacheableDependency($updateAccess)
        ->addCacheableDependency($deleteAccess);
      $operations = [];
      if ($updateAccess->isAllowed()) {
        $operations[] = Link::fromTextAndUrl($this->t('Editar'), Url::fromRoute('aculta_messenger.admin_persistent_notice_edit', ['node' => $node->id()]))->toRenderable();
      }
      if ($deleteAccess->isAllowed()) {
        $operations[] = Link::fromTextAndUrl($this->t('Excluir'), Url::fromRoute('aculta_messenger.admin_persistent_notice_delete', ['node' => $node->id()]))->toRenderable();
      }

      $audiences = [];
      foreach ($node->get('field_aculta_notice_roles')->getValue() as $item) {
        $audiences[] = $node->get('field_aculta_notice_roles')->getFieldDefinition()
          ->getFieldStorageDefinition()->getSetting('allowed_values')[$item['value']] ?? $item['value'];
      }
      $start = $this->timestamp($node->get('field_aculta_notice_start')->value);
      $end = $this->timestamp($node->get('field_aculta_notice_end')->value);
      $rows[] = [
        'title' => ['data' => $titleAccess->isAllowed()
          ? $node->toLink()->toRenderable()
          : ['#plain_text' => $node->label()]],
        'status' => ['data' => ['#plain_text' => $node->isPublished() ? $this->t('Publicado') : $this->t('Rascunho')]],
        'audience' => ['data' => ['#plain_text' => $audiences ? implode(', ', $audiences) : $this->t('Sem público definido')]],
        'validity' => ['data' => ['#plain_text' => $this->formatValidity($start, $end)]],
        'changed' => ['data' => ['#plain_text' => $this->dateFormatter->format((int) $node->getChangedTime(), 'short')]],
        'operations' => ['data' => [
          '#theme' => 'item_list',
          '#items' => $operations ?: [['#plain_text' => $this->t('Sem operações')]],
        ]],
      ];
    }

    $build = [
      'table' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Comunicado'),
          $this->t('Estado'),
          $this->t('Público'),
          $this->t('Validade'),
          $this->t('Atualizado'),
          $this->t('Operações'),
        ],
        '#rows' => $rows,
        '#empty' => $this->t('Ainda não há avisos persistentes. Crie um comunicado para apresentá-lo ao público selecionado.'),
        '#attributes' => ['class' => ['aculta-notice-admin-table']],
      ],
      'pager' => ['#type' => 'pager'],
    ];
    $cacheability->applyTo($build);
    return $build;
  }

  /** Converts a Drupal datetime value to a display timestamp. */
  private function timestamp(?string $value): ?int {
    if ($value === NULL || $value === '') {
      return NULL;
    }
    $timestamp = strtotime($value);
    return $timestamp === FALSE ? NULL : $timestamp;
  }

  /** Formats an optional start/end validity range. */
  private function formatValidity(?int $start, ?int $end): string {
    if ($start === NULL && $end === NULL) {
      return (string) $this->t('Sem prazo');
    }
    $startLabel = $start === NULL ? (string) $this->t('Imediato') : $this->dateFormatter->format($start, 'short');
    $endLabel = $end === NULL ? (string) $this->t('Sem término') : $this->dateFormatter->format($end, 'short');
    return $startLabel . ' – ' . $endLabel;
  }

}
