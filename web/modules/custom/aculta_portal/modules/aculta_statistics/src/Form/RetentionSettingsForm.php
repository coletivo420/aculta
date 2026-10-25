<?php

declare(strict_types=1);

namespace Drupal\aculta_statistics\Form;

use Drupal\aculta_statistics\Catalog\MetricCatalog;
use Drupal\aculta_statistics\Retention\RetentionPolicy;
use Drupal\aculta_statistics\Storage\AggregateStorageInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Prazo de retenção por métrica, com confirmação antes de reduzir.
 *
 * Reduzir um prazo marca linhas para apagar no próximo cron. O painel mostra
 * quantas linhas serão afetadas e exige confirmação (ADR-0003).
 */
final class RetentionSettingsForm extends ConfigFormBase {

  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typed_config_manager,
    private readonly MetricCatalog $catalog,
    private readonly RetentionPolicy $retention,
    private readonly AggregateStorageInterface $storage,
    private readonly LoggerChannelFactoryInterface $auditLoggerFactory,
    private readonly AccountProxyInterface $operator,
  ) {
    parent::__construct($config_factory, $typed_config_manager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('aculta_statistics.catalog'),
      $container->get('aculta_statistics.retention'),
      $container->get('aculta_statistics.storage'),
      $container->get('logger.factory'),
      $container->get('current_user'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_statistics_retention_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['aculta_statistics.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildForm($form, $form_state);

    $form['retention'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Prazo de retenção por métrica'),
      '#description' => $this->t('Dados anteriores ao prazo são apagados no cron. "Indefinido" mantém os dados sem prazo.'),
      '#tree' => TRUE,
    ];
    foreach ($this->catalog->all() as $id => $definition) {
      $form['retention'][$id] = [
        '#type' => 'select',
        '#title' => $this->t('@label', ['@label' => $definition->label]),
        '#description' => $this->t('Métrica <code>@id</code>, unidade: @unit.', ['@id' => $id, '@unit' => $definition->unit]),
        '#options' => $this->periodOptions(),
        '#default_value' => $this->retention->daysFor($id),
      ];
    }

    $form['confirm_reduction'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Confirmo a exclusão, no próximo cron, dos dados anteriores ao novo prazo das métricas reduzidas.'),
      '#default_value' => FALSE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    $pending = [];
    foreach ($this->catalog->all() as $id => $definition) {
      $days = (int) $form_state->getValue(['retention', $id]);
      if (!$this->retention->isValidOption($days)) {
        $form_state->setErrorByName("retention][$id", $this->t('Prazo inválido para @label.', ['@label' => $definition->label]));
        continue;
      }
      $count = $this->rowsToDelete($id, $this->retention->daysFor($id), $days);
      if ($count > 0) {
        $pending[] = $this->t('@label: até @count linhas', ['@label' => $definition->label, '@count' => $count]);
      }
    }

    if ($pending !== [] && !$form_state->getValue('confirm_reduction')) {
      $form_state->setErrorByName('confirm_reduction', $this->t('Confirme a exclusão antes de salvar. Serão afetadas: @list.', [
        '@list' => implode('; ', array_map('strval', $pending)),
      ]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $retention = [];
    foreach (array_keys($this->catalog->all()) as $id) {
      $retention[] = ['metric' => $id, 'days' => (int) $form_state->getValue(['retention', $id])];
    }

    // Auditoria (ADR-0003): quem alterou, qual métrica e de quanto para quanto. Só registra prazo que mudou.
    foreach ($retention as $row) {
      $old = $this->retention->daysFor($row['metric']);
      if ($old !== $row['days']) {
        $this->auditLoggerFactory->get('aculta_statistics')->notice('Prazo de retenção alterado: @metric de @old para @new dias pelo usuário @uid.', [
          '@metric' => $row['metric'],
          '@old' => $old,
          '@new' => $row['days'],
          '@uid' => (int) $this->operator->id(),
        ]);
      }
    }

    $this->config('aculta_statistics.settings')
      ->set('retention', $retention)
      ->save();

    $this->messenger()->addStatus($this->t('Prazos de retenção salvos. Reduções serão aplicadas no próximo cron.'));
    parent::submitForm($form, $form_state);
  }

  /**
   * Opções de prazo do painel.
   *
   * @return array<int, \Drupal\Core\StringTranslation\TranslatableMarkup>
   *   Prazo em dias (0 = indefinido) e rótulo.
   */
  private function periodOptions(): array {
    return [
      30 => $this->t('30 dias'),
      90 => $this->t('90 dias'),
      180 => $this->t('180 dias'),
      365 => $this->t('365 dias'),
      RetentionPolicy::INDEFINITE => $this->t('Indefinido'),
    ];
  }

  /**
   * Quantas linhas o prazo novo marcaria para exclusão, quando é uma redução.
   *
   * Aumentar prazo e manter indefinido não apaga nada. Trocar de indefinido
   * para um prazo finito é redução e pode apagar linhas antigas.
   */
  private function rowsToDelete(string $metric, int $oldDays, int $newDays): int {
    if ($newDays === RetentionPolicy::INDEFINITE) {
      return 0;
    }
    if ($oldDays !== RetentionPolicy::INDEFINITE && $newDays >= $oldDays) {
      return 0;
    }
    $cutoff = $this->retention->cutoffDay($newDays, $this->storage->currentDay());
    return $cutoff === NULL ? 0 : $this->storage->countBefore($metric, $cutoff);
  }

}
