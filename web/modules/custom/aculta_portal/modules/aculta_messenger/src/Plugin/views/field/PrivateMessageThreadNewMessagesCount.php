<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Plugin\views\field;

use Drupal\Core\Session\AccountInterface;
use Drupal\aculta_messenger\Service\PrivateMessageServiceInterface;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Outputs thread new messages count.
 *
 * @ingroup views_field_handlers
 */
#[ViewsField("private_message_thread_new_messages_count")]
class PrivateMessageThreadNewMessagesCount extends FieldPluginBase {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected AccountInterface $currentUser,
    protected PrivateMessageServiceInterface $privateMessageService,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_user'),
      $container->get('aculta_messenger.pm.service')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function query(): void {
    // Disable query.
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values): string {
    /** @var \Drupal\aculta_messenger\Entity\PrivateMessageThread $thread */
    $thread = $this->getEntity($values);

    // @todo Optimize this, consider deletions and banned users.
    return (string) $this->privateMessageService->getThreadUnreadMessageCount($this->currentUser->id(), $thread->id());
  }

}
