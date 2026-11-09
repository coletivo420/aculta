<?php

declare(strict_types=1);

namespace Drupal\aculta_ui_library\Controller;

use Drupal\block\BlockInterface;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Plugin\Context\ContextInterface;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\Core\Plugin\Context\ContextRepositoryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Lists real theme blocks and renders an access-checked preview.
 *
 * The preview is administrative: the route requires `administer blocks`,
 * every block is checked against the current account, and the render array is
 * marked max-age 0 so it is never stored in a public or shared cache.
 */
final class UiLibraryPreviewController extends ControllerBase {

  /**
   * Theme whose blocks are catalogued.
   */
  private const THEME = 'aculta420_v2';

  public function __construct(
    private readonly ContextRepositoryInterface $contextRepository,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('context.repository'),
    );
  }

  /**
   * Lists configured blocks for the V2 theme.
   */
  public function listBlocks(): array {
    $storage = $this->entityTypeManager()->getStorage('block');
    $blocks = $storage->loadByProperties(['theme' => self::THEME]);
    ksort($blocks);

    $rows = [];
    foreach ($blocks as $block) {
      $plugin_id = (string) ($block->get('plugin') ?? '');
      $rows[] = [
        'label' => $block->label(),
        'id' => $block->id(),
        'plugin' => $plugin_id,
        'region' => (string) ($block->get('region') ?? ''),
        'status' => $block->status() ? $this->t('Ativo') : $this->t('Inativo'),
        'preview' => [
          'data' => [
            '#type' => 'link',
            '#title' => $this->t('Abrir prévia real'),
            '#url' => Url::fromRoute('aculta_ui_library.block_preview', ['block' => $block->id()]),
          ],
        ],
      ];
    }

    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['aculta-ui-library-blocks']],
      'intro' => [
        '#markup' => '<p>' . $this->t('Prévias com dados reais do domínio principal. Cada bloco é resolvido pelo plugin original, com verificação de acesso do usuário atual. Se o contexto ou o acesso não estiverem disponíveis, a prévia informa a indisponibilidade em vez de exibir conteúdo.') . '</p>',
      ],
      'warning' => [
        '#markup' => '<p><strong>' . $this->t('Esta rota existe apenas no domínio principal.') . '</strong></p>',
      ],
      'table' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Bloco'),
          $this->t('ID'),
          $this->t('Plugin'),
          $this->t('Região'),
          $this->t('Estado'),
          $this->t('Prévia'),
        ],
        '#rows' => $rows,
        '#empty' => $this->t('Nenhum bloco configurado para o tema ACULTA420 V2.'),
      ],
    ];

    $metadata = (new CacheableMetadata())
      ->addCacheContexts(['user.roles'])
      ->setCacheMaxAge(0);
    $metadata->applyTo($build);

    return $build;
  }

  /**
   * Renders one block preview with access and context checks.
   */
  public function preview(BlockInterface $block): array {
    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['aculta-ui-library-preview']],
      'header' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['aculta-ui-library-preview__header']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h2',
          '#value' => $block->label(),
        ],
        'meta' => [
          '#markup' => '<p>' . $this->t('Bloco @id · plugin @plugin · região @region', [
            '@id' => $block->id(),
            '@plugin' => (string) ($block->get('plugin') ?? ''),
            '@region' => (string) ($block->get('region') ?? '—'),
          ]) . '</p>',
        ],
        'back' => [
          '#type' => 'link',
          '#title' => $this->t('Voltar ao catálogo'),
          '#url' => Url::fromRoute('aculta_ui_library.blocks'),
        ],
      ],
    ];

    $account = $this->currentUser();
    try {
      $plugin = $block->getPlugin();
    }
    catch (\Throwable $exception) {
      return $this->unavailable($build, $this->t('O plugin do bloco não está disponível.'));
    }

    if (!$plugin instanceof BlockPluginInterface) {
      return $this->unavailable($build, $this->t('O plugin do bloco não está disponível.'));
    }

    if (!$plugin->access($account, TRUE)) {
      return $this->unavailable($build, $this->t('Prévia indisponível: o usuário atual não tem acesso a este bloco.'));
    }

    $missing = $this->missingRequiredContexts($plugin, $account);
    if ($missing !== []) {
      return $this->unavailable($build, $this->t('Prévia indisponível: contexto obrigatório ausente (@contexts).', [
        '@contexts' => implode(', ', $missing),
      ]));
    }

    try {
      $content = $plugin->build();
    }
    catch (\Throwable $exception) {
      // Não expor detalhes internos nem contornar a falha do plugin.
      return $this->unavailable($build, $this->t('Prévia indisponível: o bloco não pôde ser construído neste contexto.'));
    }

    if (!is_array($content)) {
      return $this->unavailable($build, $this->t('Prévia indisponível: o bloco não retornou uma renderização.'));
    }

    $metadata = CacheableMetadata::createFromRenderArray($content);
    $metadata->setCacheMaxAge(0);
    $metadata->addCacheContexts(['user.roles']);
    $metadata->applyTo($content);

    $build['content'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['aculta-ui-library-preview__content']],
      'block' => $content,
    ];

    $page = (new CacheableMetadata())
      ->addCacheContexts(['user.roles'])
      ->setCacheMaxAge(0);
    $page->applyTo($build);

    return $build;
  }

  /**
   * Returns the names of required contexts the plugin still lacks.
   *
   * Optional contexts available in the current request are assigned so blocks
   * that depend on the route, user or node can render without inventing data.
   *
   * @return list<string>
   *   Missing required context names.
   */
  private function missingRequiredContexts(BlockPluginInterface $plugin, AccountInterface $account): array {
    if (!$plugin instanceof ContextAwarePluginInterface) {
      return [];
    }

    $definitions = $plugin->getContextDefinitions();
    if ($definitions === []) {
      return [];
    }

    $available = $this->contextRepository->getRuntimeContexts(array_keys($definitions));
    foreach ($available as $name => $context) {
      if ($context instanceof ContextInterface) {
        $plugin->setContext($name, $context);
      }
    }

    $missing = [];
    foreach ($definitions as $name => $definition) {
      if (!$definition->isRequired()) {
        continue;
      }
      $context = $plugin->getContext($name);
      if (!$context instanceof ContextInterface || !$context->hasContextValue()) {
        $missing[] = (string) ($definition->getLabel() ?: $name);
      }
    }
    return $missing;
  }

  /**
   * Builds a safe unavailable state without leaking block data.
   */
  private function unavailable(array $build, mixed $message): array {
    $build['unavailable'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['messages', 'messages--warning']],
      'text' => ['#markup' => (string) $message],
    ];
    $metadata = (new CacheableMetadata())
      ->addCacheContexts(['user.roles'])
      ->setCacheMaxAge(0);
    $metadata->applyTo($build);
    return $build;
  }

}
