<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer_test\Plugin\Mail;

use Drupal\Core\Mail\Attribute\Mail;
use Drupal\Core\Mail\MailInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Mailer\Event\FailedMessageEvent;
use Symfony\Component\Mailer\Exception\UnexpectedResponseException;
use Symfony\Component\Mime\Email;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Transporte de teste que falha como o SMTP falharia: dispara FailedMessageEvent com o código de resposta.
 *
 * O código vem do estado `aculta_mailer_test.failure_code` (padrão 550). Guarda os cabeçalhos da última mensagem em
 * `aculta_mailer_test.last_headers`, para conferir o transporte escolhido.
 */
#[Mail(
  id: 'aculta_mailer_failing_test',
  label: new TranslatableMarkup('Falha de teste do ACULTA Mailer'),
  description: new TranslatableMarkup('Simula falhas SMTP para os testes Kernel.'),
)]
final class FailingTestMailer implements MailInterface, ContainerFactoryPluginInterface {

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
  public function format(array $message) {
    return $message;
  }

  /**
   * {@inheritdoc}
   */
  public function mail(array $message) {
    $this->state->set('aculta_mailer_test.last_headers', $message['headers'] ?? []);
    $email = new Email();
    $email->getHeaders()->addTextHeader('X-Aculta-Mail-ID', (string) ($message['headers']['X-Aculta-Mail-ID'] ?? ''));
    $code = (int) $this->state->get('aculta_mailer_test.failure_code', 550);
    $error = new UnexpectedResponseException('Expected response code "250" but got code "' . $code . '".', $code);
    $this->dispatcher->dispatch(new FailedMessageEvent($email, $error));
    return FALSE;
  }

}
