<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Plugin\views\field;

use Drupal\Component\Render\MarkupInterface;
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
#[ViewsField("private_message_thread_has_new_message_marker")]
class PrivateMessageThreadHasNewMessage extends FieldPluginBase {

  /**
   * Tipagem do porte Private Message.
   * @param array<string, mixed> $configuration
   */
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
   * @param array<string, mixed> $configuration
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
  public function render(ResultRow $values): MarkupInterface {
    /** @var \Drupal\aculta_messenger\Entity\PrivateMessageThread $thread */
    $thread = $this->getEntity($values);

    return $this->privateMessageService->getThreadUnreadMessageCount($this->currentUser->id(), $thread->id()) > 0 ?
      $this->t('New message!') :
      $this->t('No new messages');
  }

}
