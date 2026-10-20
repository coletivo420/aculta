<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Form;

use Drupal\aculta_mailer\Log\MailLogSettings;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Registro de e-mail (modelo do WP Mail SMTP): ligar o registro, retenção, e o que não se aplica por política.
 */
final class MailLogSettingsForm extends ConfigFormBase {

  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typedConfigManager,
  ) {
    parent::__construct($config_factory, $typedConfigManager);
  }

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
    return 'aculta_mailer_log_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [MailLogSettings::CONFIG];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config(MailLogSettings::CONFIG);
    $enabled = $config->get('enabled') !== FALSE;
    $form['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Ativar registro'),
      '#default_value' => $enabled,
      '#description' => $this->t('Mantém um registro dos detalhes básicos de todos os e-mails enviados: data, assunto, estado, transporte e código de falha. Sem corpo e sem endereço em claro. Desligado, nada novo é registrado, e a cota diária por transporte (que conta o registro) deixa de valer.'),
    ];
    $form['retention_days'] = [
      '#type' => 'select',
      '#title' => $this->t('Período de retenção do registro'),
      '#options' => [
        30 => $this->t('1 mês'),
        90 => $this->t('3 meses'),
        180 => $this->t('6 meses'),
        365 => $this->t('1 ano'),
      ],
      '#default_value' => (int) $config->get('retention_days') ?: MailLogSettings::DEFAULT_RETENTION_DAYS,
      '#description' => $this->t('Os registros anteriores ao período são apagados pelo cron, em lotes. Reduzir o prazo apaga registros antigos no próximo cron.'),
    ];
    $form['not_applicable'] = [
      '#type' => 'details',
      '#title' => $this->t('Opções do WP Mail SMTP que não se aplicam aqui'),
      '#open' => TRUE,
      'content' => [
        '#type' => 'item',
        '#title' => $this->t('Registrar conteúdo do e-mail'),
        '#markup' => '<p>' . $this->t('Indisponível por política: o registro não guarda corpo nem destinatário em claro (LOGGING-PRIVACY). Para reenviar uma falha, o conteúdo sai da fila, não do registro.') . '</p>',
      ],
      'attachments' => [
        '#type' => 'item',
        '#title' => $this->t('Salvar anexos'),
        '#markup' => '<p>' . $this->t('Indisponível: os e-mails do sistema não têm anexos. Se um tipo passar a ter, esta opção será tratada na mesma fase.') . '</p>',
      ],
      'tracking' => [
        '#type' => 'item',
        '#title' => $this->t('Rastreamento de abertura e de cliques'),
        '#markup' => '<p>' . $this->t('Indisponível: fase F11, desligada por padrão. Exige decisão de consentimento e de privacidade antes de ligar (ver o roadmap do ACULTA Mailer).') . '</p>',
      ],
      'webhooks' => [
        '#type' => 'item',
        '#title' => $this->t('Status de webhooks'),
        '#markup' => '<p>' . $this->t('Nenhum provedor de webhook está ativo. O endpoint existe (F10), mas a assinatura e o cadastro no provedor são pendentes. Enquanto isso, falhas de entrega chegam pelo monitoramento IMAP.') . '</p>',
      ],
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config(MailLogSettings::CONFIG)
      ->set('enabled', (bool) $form_state->getValue('enabled'))
      ->set('retention_days', (int) $form_state->getValue('retention_days'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
