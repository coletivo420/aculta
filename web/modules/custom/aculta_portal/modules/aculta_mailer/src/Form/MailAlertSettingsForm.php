<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Form;

use Drupal\aculta_mailer\Delivery\DeliverySettings;
use Drupal\aculta_mailer\Delivery\MailSuppression;
use Drupal\aculta_mailer\Monitor\MailMonitor;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Entrega, alertas e resumo semanal: endereço de retorno dos transportes SMTP, alertas de falha e resumo por e-mail.
 */
final class MailAlertSettingsForm extends ConfigFormBase {

  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typedConfigManager,
    private readonly MailMonitor $monitor,
    private readonly MailSuppression $suppression,
  ) {
    parent::__construct($config_factory, $typedConfigManager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get(MailMonitor::class),
      $container->get(MailSuppression::class),
    );
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
    $form['switches'] = [
      '#type' => 'details',
      '#title' => $this->t('Ligar e desligar'),
      '#open' => TRUE,
      'sending_enabled' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Envio geral ligado (chave geral)'),
        '#default_value' => $config->get('sending_enabled') !== FALSE,
        '#description' => $this->t('Desligado, nenhum e-mail do sistema é enviado. As mensagens ficam no registro como suspensas.'),
      ],
      'tracking_enabled' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Rastreamento de aberturas e cliques ligado'),
        '#default_value' => $config->get('tracking_enabled') === TRUE,
        '#description' => $this->t('Desligado, nada é rastreado. Ligado, só rastreia quem aceitou o consentimento de rastreamento na conta (página /aceite-rastreamento). Não guarda IP nem dados do navegador.'),
      ],
      'brevo_enabled' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Transporte Brevo ligado'),
        '#default_value' => $config->get('brevo_enabled') !== FALSE,
        '#description' => $this->t('Desligado, mensagens pedidas ao Brevo seguem pelo transporte de contingência.'),
      ],
      'imap_enabled' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Monitoramento IMAP ligado (bounces e DMARC)'),
        '#default_value' => $this->monitor->enabled(),
        '#description' => $this->t('Só liga com servidor, usuário e senha preenchidos na página de monitoramento IMAP.'),
      ],
    ];
    $form['suppression'] = [
      '#type' => 'details',
      '#title' => $this->t('Lista de supressão'),
      '#open' => TRUE,
      'info' => [
        '#markup' => '<p>' . $this->t('Endereços suprimidos: @n. Entram por devolução permanente, endereço inválido ou reclamação de spam. Guardam só o identificador do endereço, não o endereço.', ['@n' => $this->suppression->count()]) . '</p>',
      ],
      'clear_suppression' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Limpar a lista de supressão ao salvar'),
        '#description' => $this->t('Use quando um endereço foi suprimido por engano ou o problema da caixa foi resolvido. Não há desfazer por endereço.'),
      ],
    ];
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
      'alerts_admins' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Enviar aos administradores do site'),
        '#default_value' => (bool) $config->get('alerts_admins'),
        '#description' => $this->t('Todos os usuários ativos com o papel de administrador. O alerta traz o texto completo do erro.'),
      ],
      'alerts_recipients' => [
        '#type' => 'textarea',
        '#title' => $this->t('Outros destinatários dos alertas'),
        '#default_value' => (string) $config->get('alerts_recipients'),
        '#rows' => 2,
        '#description' => $this->t('Opcional. Um ou mais endereços, separados por vírgula ou quebra de linha.'),
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
      'summary_admins' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Enviar aos administradores do site'),
        '#default_value' => (bool) $config->get('summary_admins'),
      ],
      'summary_recipients' => [
        '#type' => 'textarea',
        '#title' => $this->t('Outros destinatários do resumo'),
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
    if ($form_state->getValue('imap_enabled') && $this->monitor->client() === NULL) {
      $form_state->setErrorByName('imap_enabled', $this->t('Para ligar o monitoramento, preencha servidor, usuário e senha na página de monitoramento IMAP.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    if ($form_state->getValue('clear_suppression')) {
      $this->suppression->clear();
    }
    $this->config(DeliverySettings::CONFIG)
      ->set('sending_enabled', (bool) $form_state->getValue('sending_enabled'))
      ->set('tracking_enabled', (bool) $form_state->getValue('tracking_enabled'))
      ->set('brevo_enabled', (bool) $form_state->getValue('brevo_enabled'))
      ->set('bounce_address', strtolower(trim((string) $form_state->getValue('bounce_address'))))
      ->set('alerts_enabled', (bool) $form_state->getValue('alerts_enabled'))
      ->set('alerts_admins', (bool) $form_state->getValue('alerts_admins'))
      ->set('alerts_recipients', implode(', ', DeliverySettings::recipients((string) $form_state->getValue('alerts_recipients'))))
      ->set('summary_enabled', (bool) $form_state->getValue('summary_enabled'))
      ->set('summary_admins', (bool) $form_state->getValue('summary_admins'))
      ->set('summary_recipients', implode(', ', DeliverySettings::recipients((string) $form_state->getValue('summary_recipients'))))
      ->save();
    $this->config(MailMonitor::CONFIG)->set('enabled', (bool) $form_state->getValue('imap_enabled'))->save();
    parent::submitForm($form, $form_state);
  }

}
