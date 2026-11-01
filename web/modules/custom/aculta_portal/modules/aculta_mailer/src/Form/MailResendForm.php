<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Form;

use Drupal\aculta_mailer\Delivery\MailResend;
use Drupal\aculta_mailer\Log\MailLogRecorder;
use Drupal\aculta_mailer\Log\SingleUseLinkMail;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Confirmação do reenvio de uma mensagem com falha. Mostra só o tipo, a data e o transporte, não o destinatário.
 */
final class MailResendForm extends ConfirmFormBase {

  /**
   * Transportes oferecidos, pelo nome usado no cabeçalho X-Transport.
   */
  private const TRANSPORTS = [
    'smtp' => 'Hostinger (SMTP)',
    'smtp2go' => 'SMTP2GO (contingência)',
    'brevo' => 'Brevo (API)',
  ];

  public function __construct(
    private readonly MailResend $resend,
    private readonly EntityTypeManagerInterface $entityTypes,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get(MailResend::class), $container->get('entity_type.manager'));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_mailer_resend_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): string {
    return (string) $this->t('Reenviar esta mensagem?');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): string {
    return (string) $this->t('O conteúdo é decifrado para o envio. A nova tentativa aparece no mesmo registro. Chave geral de envio e lista de supressão continuam valendo.');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    return Url::fromRoute('aculta_mailer.failures');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText(): string {
    return (string) $this->t('Reenviar');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?string $log_id = NULL): array {
    $log = $log_id !== NULL ? $this->entityTypes->getStorage(MailLogRecorder::ENTITY_TYPE)->load($log_id) : NULL;
    if ($log === NULL || !$this->resend->canResend($log_id)) {
      $this->messenger()->addError($this->t('Esta mensagem não pode ser reenviada: não tem conteúdo guardado ou não está com falha.'));
      $form_state->setRedirect('aculta_mailer.failures');
      return [];
    }
    $form['log_id'] = ['#type' => 'value', '#value' => $log_id];
    $form['summary'] = [
      '#markup' => '<p>' . $this->t('Tipo: @type. Data: @date. Tentativas até agora: @n.', [
        '@type' => (string) $log->get('type')->value,
        '@date' => date('Y-m-d H:i', (int) $log->get('created')->value),
        '@n' => (int) $log->get('attempts')->value,
      ]) . '</p>',
    ];
    if (SingleUseLinkMail::applies('aculta_mailer', (string) $log->get('type')->value)) {
      // Link de uso único: o dono do link gera um pedido novo, com token novo, e o transporte é o padrão do envio.
      $form['summary']['#markup'] .= '<p>' . $this->t('Este é um link de uso único. O reenvio gera um pedido novo, com link novo, e o conteúdo antigo é cancelado.') . '</p>';
      return parent::buildForm($form, $form_state);
    }
    $current = (string) $log->get('transport')->value;
    $form['transport'] = [
      '#type' => 'select',
      '#title' => $this->t('Transporte para o reenvio'),
      '#options' => self::TRANSPORTS,
      '#default_value' => isset(self::TRANSPORTS[$current]) ? $current : 'smtp',
      '#required' => TRUE,
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $result = $this->resend->resend((string) $form_state->getValue('log_id'), (string) ($form_state->getValue('transport') ?? ''));
    $this->messenger()->addMessage(match (TRUE) {
      $result->sent => $this->t('Mensagem aceita pelo transporte. O registro foi atualizado.'),
      $result->cancelled => $this->t('Reenvio suspenso: chave geral de envio desligada, ou destinatário na lista de supressão.'),
      default => $this->t('O transporte recusou o reenvio. A nova tentativa aparece no registro.'),
    }, $result->sent ? 'status' : ($result->cancelled ? 'warning' : 'error'));
    $form_state->setRedirect('aculta_mailer.failures');
  }

}
