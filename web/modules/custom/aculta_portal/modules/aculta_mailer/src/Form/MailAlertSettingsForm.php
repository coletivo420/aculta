<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Form;

use Drupal\aculta_mailer\Delivery\DeliverySettings;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Entrega, alertas e resumo semanal: endereço de retorno dos transportes SMTP, alertas de falha e resumo por e-mail.
 */
final class MailAlertSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('config.factory'), $container->get('config.typed'));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_mailer_delivery_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [DeliverySettings::CONFIG];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config(DeliverySettings::CONFIG);
    $form['bounce_address'] = [
      '#type' => 'email',
      '#title' => $this->t('Endereço de retorno (bounces) dos transportes SMTP'),
      '#default_value' => (string) $config->get('bounce_address'),
      '#maxlength' => 254,
      '#description' => $this->t('Vai no envelope (MAIL FROM) das mensagens por SMTP. As notificações de falha voltam a este endereço, e o monitoramento IMAP lê essa caixa. Ex.: dmarc@ do domínio. Não se aplica ao Brevo (API), que trata as devoluções pelo webhook. Vazio: o envelope segue o remetente, como antes.'),
    ];
    $form['alerts'] = [
      '#type' => 'details',
      '#title' => $this->t('Alertas de falha'),
      '#open' => TRUE,
      'alerts_enabled' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Enviar alerta quando um envio falhar ou um endereço devolver a mensagem'),
        '#default_value' => (bool) $config->get('alerts_enabled'),
        '#description' => $this->t('No máximo um alerta a cada 5 minutos; o restante é contado e aparece no próximo.'),
      ],
      'alerts_recipients' => [
        '#type' => 'textarea',
        '#title' => $this->t('Destinatários dos alertas'),
        '#default_value' => (string) $config->get('alerts_recipients'),
        '#rows' => 2,
        '#description' => $this->t('Um ou mais endereços, separados por vírgula ou quebra de linha. O alerta traz o texto completo do erro: envie só a administradores.'),
      ],
    ];
    $form['summary'] = [
      '#type' => 'details',
      '#title' => $this->t('Resumo semanal'),
      '#open' => TRUE,
      'summary_enabled' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Enviar resumo semanal dos envios'),
        '#default_value' => (bool) $config->get('summary_enabled'),
        '#description' => $this->t('Compara os últimos 7 dias com os 7 anteriores e lista os motivos de falha. Enviado pelo cron, uma vez por semana.'),
      ],
      'summary_recipients' => [
        '#type' => 'textarea',
        '#title' => $this->t('Destinatários do resumo'),
        '#default_value' => (string) $config->get('summary_recipients'),
        '#rows' => 2,
      ],
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
    foreach (['alerts_recipients', 'summary_recipients'] as $field) {
      $invalid = DeliverySettings::invalid((string) $form_state->getValue($field));
      if ($invalid !== []) {
        $form_state->setErrorByName($field, $this->t('Endereços inválidos: @list.', ['@list' => implode(', ', $invalid)]));
      }
    }
    if (trim((string) $form_state->getValue('bounce_address')) !== '' && DeliverySettings::invalid((string) $form_state->getValue('bounce_address')) !== []) {
      $form_state->setErrorByName('bounce_address', $this->t('Endereço de retorno inválido.'));
    }
    if ($form_state->getValue('alerts_enabled') && DeliverySettings::recipients((string) $form_state->getValue('alerts_recipients')) === []) {
      $form_state->setErrorByName('alerts_recipients', $this->t('Com os alertas ativos, informe ao menos um destinatário.'));
    }
    if ($form_state->getValue('summary_enabled') && DeliverySettings::recipients((string) $form_state->getValue('summary_recipients')) === []) {
      $form_state->setErrorByName('summary_recipients', $this->t('Com o resumo ativo, informe ao menos um destinatário.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config(DeliverySettings::CONFIG)
      ->set('bounce_address', strtolower(trim((string) $form_state->getValue('bounce_address'))))
      ->set('alerts_enabled', (bool) $form_state->getValue('alerts_enabled'))
      ->set('alerts_recipients', implode(', ', DeliverySettings::recipients((string) $form_state->getValue('alerts_recipients'))))
      ->set('summary_enabled', (bool) $form_state->getValue('summary_enabled'))
      ->set('summary_recipients', implode(', ', DeliverySettings::recipients((string) $form_state->getValue('summary_recipients'))))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
