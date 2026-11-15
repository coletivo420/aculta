<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Plugin\views\field;

use Drupal\Core\Session\AccountInterface;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Outputs thread messages count.
 *
 * @ingroup views_field_handlers
 */
#[ViewsField("private_message_thread_all_messages_number")]
class PrivateMessageThreadMessagesCount extends FieldPluginBase {

  /**
   * Tipagem do porte Private Message.
   * @param array<string, mixed> $configuration
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected AccountInterface $currentUser,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   * @param array<string, mixed> $configuration
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function query(): void {
    // Leave empty to avoid a query on this field.
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values): string {
    /** @var \Drupal\aculta_messenger\Entity\PrivateMessageThread $thread */
    $thread = $this->getEntity($values);
    return (string) count($thread->filterUserDeletedMessages($this->currentUser));
  }

}
