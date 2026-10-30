<?php

declare(strict_types=1);

namespace Drupal\aculta_studio\Plugin\MarleyTab;

use Drupal\aculta_studio\Attribute\MarleyTab;
use Drupal\aculta_studio\MarleyTabBase;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\aculta_magazine\Access\EditorialCapability;

/**
 * Revista tab.
 *
 * The owner is aculta_magazine; the states come from the editorial workflow
 * `revista`. Publicações lists the nodes by state (Todos, Rascunhos, Em
 * revisão, Publicados, Arquivados) through the listing of the Studio, which
 * keeps the node access of the owner. Editorial operations resolve on the main
 * domain, as the Core marks every node operation route as an admin route; the
 * Magazine keeps the route contract and the Studio consumes it. Criar keeps
 * the create route.
 */
#[MarleyTab(
  id: 'revista',
  label: new TranslatableMarkup('Revista'),
  weight: 10,
)]
final class RevistaTab extends MarleyTabBase {

  /**
   * Creates the Magazine tab with current role configuration.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    ModuleHandlerInterface $moduleHandler,
    TranslationInterface $stringTranslation,
    DomainPurposeManager $domainPurposes,
    EntityTypeManagerInterface $entityTypeManager,
    private readonly ConfigFactoryInterface $configFactory,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $moduleHandler, $stringTranslation, $domainPurposes, $entityTypeManager);
  }

  /**
   * Creates the tab with its role configuration dependency.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('module_handler'), $container->get('string_translation'), $container->get('aculta_portal.domain_purpose'), $container->get('entity_type.manager'), $container->get('config.factory'));
  }

  /**
   * {@inheritdoc}
   */
  public function isAvailable(): bool {
    return $this->moduleHandler->moduleExists('aculta_magazine');
  }

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account): AccessResultInterface {
    $roles = $account->getRoles();
    $config = $this->configFactory->get('aculta_magazine.settings');
    $allowed = FALSE;
    $capabilityKeys = [
      EditorialCapability::AUTHORING => 'authoring_roles',
      EditorialCapability::REVIEW => 'review_roles',
      EditorialCapability::COMMENTS => 'comments_roles',
    ];
    foreach ($capabilityKeys as $key) {
      $allowed = $allowed || array_intersect($roles, $config->get($key) ?? []) !== [];
    }
    return AccessResult::allowedIf($allowed)->addCacheContexts(['user.roles'])->addCacheTags(['config:aculta_magazine.settings']);
  }

  /**
   * {@inheritdoc}
   */
  public function sections(AccountInterface $account): array {
    $states = [
      'todos' => ['label' => $this->t('Publicações acessíveis'), 'state' => NULL],
      'rascunhos' => ['label' => $this->t('Rascunhos'), 'state' => 'draft'],
      'em-revisao' => ['label' => $this->t('Em revisão'), 'state' => 'review'],
      'publicados' => ['label' => $this->t('Publicados'), 'state' => 'published'],
      'arquivados' => ['label' => $this->t('Arquivados'), 'state' => 'archived'],
    ];
    $listTabs = [];
    foreach ($states as $id => $item) {
      $listTabs[$id] = [
        'label' => $item['label'],
        'weight' => count($listTabs),
        'listing' => [
          'bundle' => 'revista',
          'scope' => 'mine',
          'state' => $item['state'],
          'editable' => TRUE,
        ],
        'actions' => [],
      ];
    }
    $listTabs['todos']['actions'][] = $this->routeAction($this->t('Abrir painel de publicações'), 'aculta_magazine.studio_list', [], $account);
    $sections = [
      'publicacoes' => [
        'label' => $this->t('Meu trabalho'),
        'weight' => 0,
        'tabs' => $listTabs,
      ],
      'criar' => [
        'label' => $this->t('Criar'),
        'weight' => 10,
        'tabs' => [
          'nova-publicacao' => [
            'label' => $this->t('Nova publicação'),
            'weight' => 0,
            'actions' => [
              $this->routeAction($this->t('Nova publicação'), 'aculta_magazine.studio_add', [], $account),
            ],
          ],
        ],
      ],
    ];
    $commentRoles = $this->configFactory->get('aculta_magazine.settings')->get('comments_roles') ?? [];
    $canModerateComments = array_intersect($account->getRoles(), $commentRoles) !== []
      && $account->hasPermission('administer comments');
    if ($canModerateComments) {
      $sections['comentarios'] = [
        'label' => $this->t('Comentários e moderação'),
        'weight' => 20,
        'tabs' => [
          'pendentes' => [
            'label' => $this->t('Aguardando moderação'),
            'weight' => 0,
            'listing' => [
              'entity' => 'comment',
              'bundle' => 'aculta_discussion_post',
              'status' => 0,
              'host_bundle' => 'revista',
            ],
            'actions' => [],
          ],
        ],
      ];
    }
    return $sections;
  }

}
