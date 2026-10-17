<?php

namespace Drupal\aculta_donation\Plugin\Block;

use Drupal\aculta_donation\Form\DonationSettingsForm;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a donation block.
 */
#[Block(
  id: 'aculta_donation_quick',
  admin_label: new TranslatableMarkup('Valores de doação rápida'),
  category: new TranslatableMarkup('ACULTA Doações'),
)]
class QuickDonationBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * Injected config service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;


  // phpcs:disable Generic.CodeAnalysis.UselessOverridingMethod.Found

  /**
   * {@inheritdoc}
   */
  final public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    // @see https://phpstan.org/blog/solving-phpstan-error-unsafe-usage-of-new-static
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  // phpcs:enable

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ) {
    $instance = new static(
      $configuration,
      $plugin_id,
      $plugin_definition
    );
    $instance->formBuilder = $container->get('form_builder');
    $instance->configFactory = $container->get('config.factory');
    return $instance;

  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $donationConfig = $this->configFactory->get('commerce_donation_flow.settings');
    $use_route = $donationConfig->get('donation_route');
    $route_allowed = $use_route === DonationSettingsForm::ROTA_DONATE || $use_route === DonationSettingsForm::ROTA_AMBAS;
    if (!$route_allowed) {
      $form_state->setError($form['label'], $this->t('The Quick Donation block is only usable if donations are configured in Commerce to use the /donate route. Adjust the donation configuration before configuring this block.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm(
    $form,
    FormStateInterface $form_state,
  ) {
    $form = parent::blockForm($form, $form_state);
    $levels = $this->configuration['levels'] ?? [];
    $form['levels'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Valores de doação rápida'),
      '#description' => $this->t(
        'These are fixed amounts offered for choice to a user.'
      ),
      '#tree' => TRUE,
    ];
    $form['compacto'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Espaço reduzido (até 3 sugestões e a opção "Outro")'),
      '#default_value' => $this->configuration['compacto'] ?? FALSE,
    ];
    for ($index = 0; $index < 4; $index++) {
      $form['levels'][$index] = [
        '#type' => 'number',
        '#default_value' => $levels[$index] ?? 5,
        '#min' => 5,
        '#step' => 5,
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['levels'] = $form_state->getValue('levels');
    $this->configuration['compacto'] = (bool) $form_state->getValue('compacto');
  }

  /**
   * Builds the donation form.
   *
   * @return array
   *   A render array.
   */
  public function build() {
    // Valores padrão quando o bloco não tem valores salvos (R$ 5, 10, 20 e 50). Sem isso o bloco novo sai sem botões.
    $compacto = (bool) ($this->configuration['compacto'] ?? FALSE);
    $levels = array_values($this->configuration['levels'] ?? [5, 10, 20, 50]);
    // Espaço reduzido: no máximo 3 sugestões e a opção curta "Outro", para caber em locais pequenos.
    if ($compacto) {
      $levels = array_slice($levels, 0, 3);
    }
    $links = [];
    foreach ($levels as $delta => $amount) {
      $links[$delta] = [
        'title' => 'R$ ' . $amount,
        'url' => Url::fromRoute(
          'aculta_donation.donation.quick',
          ['amount' => $amount]
        )->toString(),
      ];
    }
    // "Outros valores": o visitante digita o valor no fluxo de doação (campo "Other Amount" do widget).
    $links['outros'] = [
      'title' => (string) ($compacto ? $this->t('Outro') : $this->t('Outro valor')),
      'url' => Url::fromRoute('aculta_donation.donation.default')->toString(),
    ];
    // Dados neutros: o tema apresenta os valores (componente SDC donation-quick do tema).
    $build = [
      '#theme' => 'aculta_donation_quick',
      '#links' => $links,
    ];
    return $build;
  }

}
