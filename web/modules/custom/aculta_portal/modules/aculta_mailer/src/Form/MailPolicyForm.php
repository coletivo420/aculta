<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Form;

use Drupal\aculta_mailer\Plugin\AcultaMailerAdjuster\TransportAdjuster;
use Drupal\aculta_mailer\Policy\TransportQuota;
use Drupal\aculta_mailer\Type\MailTypeRegistry;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Política de envio por instância (F5): padrão global e ajuste por tipo de e-mail, e limites por transporte.
 *
 * Os valores são os mesmos que os plugins de ajuste aceitam (TransportAdjuster, ReplyToAdjuster, PriorityAdjuster,
 * SkipAdjuster). A camada de cima (tipo) sobrescreve a de baixo (global), na ordem do MailPolicyResolver.
 */
final class MailPolicyForm extends ConfigFormBase {

  /**
   * Janelas do limite por transporte, na ordem da tela.
   */
  private const WINDOWS = ['hour' => 'Por hora', 'day' => 'Por dia', 'week' => 'Por semana', 'month' => 'Por mês'];

  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typedConfigManager,
    private readonly MailTypeRegistry $types,
  ) {
    parent::__construct($config_factory, $typedConfigManager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('config.factory'), $container->get('config.typed'), $container->get(MailTypeRegistry::class));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_mailer_policy_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['aculta_mailer.policy'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('aculta_mailer.policy');
    $form['intro'] = ['#markup' => '<p>' . $this->t('O tipo sobrescreve o padrão global, campo a campo. Vazio significa "herdar". Suspender impede o envio e não reagenda.') . '</p>'];

    $form['global'] = $this->policyFields(
      $this->t('Padrão global (todos os tipos)'),
      (array) $config->get('global'),
      TRUE,
    );

    $form['types'] = ['#type' => 'details', '#title' => $this->t('Ajuste por tipo de e-mail'), '#open' => TRUE];
    foreach ($this->types->ids() as $id) {
      $typeValues = (array) (($config->get('types') ?? [])[$id] ?? []);
      $form['types'][$id] = $this->policyFields($this->t('Tipo: @id', ['@id' => $id]), $typeValues, FALSE);
    }

    $form['rate_limit'] = [
      '#type' => 'details',
      '#title' => $this->t('Limite de envio por transporte'),
      '#open' => FALSE,
    ];
    $form['rate_limit']['help'] = ['#markup' => '<p>' . $this->t('Quantidade de mensagens aceitas por janela. Vazio: sem limite. Ao atingir, o transporte sai de uso e a mensagem segue pela contingência.') . '</p>'];
    foreach (TransportAdjuster::ALLOWED as $transport) {
      $limits = (array) (($config->get('rate_limit') ?? [])[$transport] ?? []);
      $form['rate_limit'][$transport] = ['#type' => 'fieldset', '#title' => $transport];
      foreach (array_keys(TransportQuota::INTERVALS) as $window) {
        $form['rate_limit'][$transport][$window] = [
          '#type' => 'number',
          '#title' => self::WINDOWS[$window],
          '#default_value' => $limits[$window] ?? NULL,
          '#min' => 0,
          '#step' => 1,
        ];
      }
    }
    return parent::buildForm($form, $form_state);
  }

  /**
   * Campos de um nível de política (global ou tipo).
   *
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $title
   *   Título do bloco.
   * @param array $values
   *   Valores atuais do nível.
   * @param bool $global
   *   TRUE para o padrão global (sem "herdar").
   */
  private function policyFields($title, array $values, bool $global): array {
    $transportOptions = $global ? [] : ['' => $this->t('Herdar')];
    $priorityOptions = ['' => $this->t('Herdar')] + array_combine(range(1, 5), range(1, 5));
    foreach (TransportAdjuster::ALLOWED as $transport) {
      $transportOptions[$transport] = $transport;
    }
    if ($global) {
      $transportOptions = ['' => $this->t('Padrão do sistema')] + $transportOptions;
    }
    return [
      '#type' => 'details',
      '#title' => $title,
      '#open' => FALSE,
      'transport' => [
        '#type' => 'select',
        '#title' => $this->t('Transporte'),
        '#options' => $transportOptions,
        '#default_value' => (string) ($values['transport'] ?? ''),
      ],
      'reply_to' => [
        '#type' => 'email',
        '#title' => $this->t('Responder para'),
        '#default_value' => (string) ($values['reply_to'] ?? ''),
      ],
      'priority' => [
        '#type' => 'select',
        '#title' => $this->t('Prioridade'),
        '#options' => $priorityOptions,
        '#default_value' => isset($values['priority']) ? (string) $values['priority'] : '',
      ],
      'skip' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Suspender envio deste tipo'),
        '#default_value' => (bool) ($values['skip'] ?? FALSE),
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
    $levels = array_merge([$form_state->getValue('global')], array_values($form_state->getValue('types') ?? []));
    foreach ($levels as $level) {
      $reply = trim((string) ($level['reply_to'] ?? ''));
      if ($reply !== '' && filter_var($reply, FILTER_VALIDATE_EMAIL) === FALSE) {
        $form_state->setErrorByName('reply_to', $this->t('Endereço de resposta inválido.'));
      }
    }
    foreach (TransportAdjuster::ALLOWED as $transport) {
      foreach (array_keys(TransportQuota::INTERVALS) as $window) {
        $value = $form_state->getValue(['rate_limit', $transport, $window]);
        if ($value !== NULL && $value !== '' && (!is_numeric($value) || (int) $value < 0)) {
          $form_state->setErrorByName("rate_limit][$transport][$window", $this->t('Limite deve ser um número inteiro não negativo.'));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $config = $this->config('aculta_mailer.policy');
    $config->set('global', $this->levelValues($form_state->getValue('global'), TRUE));

    $types = [];
    foreach ($this->types->ids() as $id) {
      $level = $this->levelValues($form_state->getValue(['types', $id]) ?? [], FALSE);
      if ($level !== []) {
        $types[$id] = $level;
      }
    }
    $config->set('types', $types);

    $rate = [];
    foreach (TransportAdjuster::ALLOWED as $transport) {
      $windows = [];
      foreach (array_keys(TransportQuota::INTERVALS) as $window) {
        $value = $form_state->getValue(['rate_limit', $transport, $window]);
        if ($value !== NULL && $value !== '') {
          $windows[$window] = (int) $value;
        }
      }
      if ($windows !== []) {
        $rate[$transport] = $windows;
      }
    }
    $config->set('rate_limit', $rate)->save();
    parent::submitForm($form, $form_state);
  }

  /**
   * Valores de um nível, só com o que foi definido.
   */
  private function levelValues(array $level, bool $global): array {
    $out = [];
    if (($level['transport'] ?? '') !== '') {
      $out['transport'] = (string) $level['transport'];
    }
    if (trim((string) ($level['reply_to'] ?? '')) !== '') {
      $out['reply_to'] = trim((string) $level['reply_to']);
    }
    if (($level['priority'] ?? '') !== '') {
      $out['priority'] = (int) $level['priority'];
    }
    if (!empty($level['skip'])) {
      $out['skip'] = TRUE;
    }
    return $out;
  }

}
