<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger_aviso_test\Plugin\Mail;

use Drupal\Core\Mail\Attribute\Mail;
use Drupal\Core\Mail\MailInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Mailer\Event\FailedMessageEvent;
use Symfony\Component\Mailer\Exception\UnexpectedResponseException;
use Symfony\Component\Mime\Email;

/**
 * Simulates a 421 transport failure followed by acceptance of the same digest.
 */
#[Mail(
  id: 'aculta_digest_retry_test',
  label: new TranslatableMarkup('ACULTA digest retry test transport'),
  description: new TranslatableMarkup('Fails once temporarily and accepts the retry.'),
)]
final class DigestRetryTestMailer implements MailInterface, ContainerFactoryPluginInterface {

  public function __construct(
    private readonly EventDispatcherInterface $dispatcher,
    private readonly StateInterface $state,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static($container->get('event_dispatcher'), $container->get('state'));
  }

  /**
   * {@inheritdoc}
   */
  public function format(array $message): array {
    return $message;
  }

  /**
   * {@inheritdoc}
   */
  public function mail(array $message): bool {
    $attempt = (int) $this->state->get('aculta_messenger_aviso_test.digest_retry_attempts', 0) + 1;
    $this->state->set('aculta_messenger_aviso_test.digest_retry_attempts', $attempt);
    $headers = $message['headers'] ?? [];
    $correlationId = (string) ($headers['X-Aculta-Mail-ID'] ?? '');
    $correlations = $this->state->get('aculta_messenger_aviso_test.digest_retry_correlations', []);
    $correlations[] = $correlationId;
    $this->state->set('aculta_messenger_aviso_test.digest_retry_correlations', $correlations);

    if ($attempt === 1) {
      $email = new Email();
      $email->getHeaders()->addTextHeader('X-Aculta-Mail-ID', $correlationId);
      $exception = new UnexpectedResponseException('Temporary test transport failure.', 421);
      $this->dispatcher->dispatch(new FailedMessageEvent($email, $exception));
      return FALSE;
    }

    return TRUE;
  }

}
