<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Plugin\views\filter;

use Drupal\Core\Session\AccountInterface;
use Drupal\views\Attribute\ViewsFilter;
use Drupal\views\Plugin\ViewsHandlerManager;
use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filters threads by the fact they are not cleared.
 *
 * @ingroup views_filter_handlers
 *
 * @phpstan-consistent-constructor
 */
#[ViewsFilter('private_message_thread_has_history')]
class PrivateMessageThreadCleanHistory extends FilterPluginBase {

  /**
   * Tipagem do porte Private Message.
   *
   * @param array<string, mixed> $configuration
   *   A configuração do plugin.
   * @param string $plugin_id
   *   O identificador do plugin.
   * @param mixed $plugin_definition
   *   A definição do plugin.
   * @param AccountInterface $currentUser
   *   A conta do usuário atual.
   * @param ViewsHandlerManager $joinHandler
   *   O valor informado para $joinHandler.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected AccountInterface $currentUser,
    protected ViewsHandlerManager $joinHandler,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   *
   * @param ContainerInterface $container
   *   O contêiner de serviços.
   * @param array<string, mixed> $configuration
   *   A configuração do plugin.
   * @param string $plugin_id
   *   O identificador do plugin.
   * @param mixed $plugin_definition
   *   A definição do plugin.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_user'),
      $container->get('plugin.manager.views.join')
    );
  }

  /**
   * Overrides the query.
   *
   * No filtering takes place if the user doesn't select any options.
   */
  public function query(): void {
    $current_user_id = (string) $this->currentUser->id();

    $definition = [
      'table' => 'aculta_pm_thread_history',
      'field' => 'thread_id',
      'left_table' => 'private_message_threads',
      'left_field' => 'id',
      'operator' => '=',
      'extra' => 'aculta_pm_thread_history.delete_timestamp <= private_message_threads.updated',
    ];

    /** @var \Drupal\views\Plugin\views\join\JoinPluginBase $join */
    $join = $this->joinHandler->createInstance('standard', $definition);
    /** @var \Drupal\views\Plugin\views\query\Sql $sqlQuery */
    $sqlQuery = $this->query;
    $sqlQuery->addRelationship('aculta_pm_thread_history', $join, 'delete_timestamp');
    $sqlQuery->addWhere('0', 'aculta_pm_thread_history.uid', $current_user_id);

    if (!empty($this->value)) {
      parent::query();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function adminSummary(): \Stringable|string {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function canExpose(): bool {
    return FALSE;
  }

}
