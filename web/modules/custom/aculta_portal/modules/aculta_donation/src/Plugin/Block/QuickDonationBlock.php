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
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_portal\Support\SupportAmountsProviderInterface;
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

  /**
   * Valores da campanha vigente (mesma fonte da página de apoio); NULL se o módulo não estiver disponível.
   */
  protected ?SupportAmountsProviderInterface $campanhas = NULL;

  /**
   * Gerenciador de domínio: os links de doação apontam direto ao domínio principal (rota transacional).
   */
  protected DomainPurposeManager $purposes;


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
    $instance->campanhas = $container->has('aculta_donation.campaigns') ? $container->get('aculta_donation.campaigns') : NULL;
    $instance->purposes = $container->get('aculta_portal.domain_purpose');
    return $instance;

  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $donationConfig = $this->configFactory->get('aculta_donation.settings');
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

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['compacto'] = (bool) $form_state->getValue('compacto');
  }

  /**
   * Builds the donation form.
   *
   * @return array
   *   A render array.
   */
  public function build() {
    // Mesma fonte da página de apoio: campanha vigente; sem campanha, os valores padrão de "Valores padrão" (painel).
    $campanha = $this->campanhas?->valoresSugeridos();
    $padrao = $this->configFactory->get('aculta_portal.support');
    $valores = array_values($campanha['amounts'] ?? ($padrao->get('amounts') ?: [5, 10, 20, 50]));
    $permiteOutro = (bool) ($campanha['customAmount'] ?? ($padrao->get('custom_amount') ?? TRUE));
    // Espaço reduzido: no máximo 3 sugestões e a opção curta "Outro", para caber em locais pequenos.
    if ($this->configuration['compacto'] ?? FALSE) {
      $valores = array_slice($valores, 0, 3);
    }
    $links = [];
    foreach ($valores as $delta => $amount) {
      $links[$delta] = [
        'title' => 'R$ ' . number_format((float) $amount, 0, ',', '.'),
        'url' => $this->purposes->routeUrl('support', 'aculta_donation.donation.quick', ['amount' => $amount])?->toString() ?? Url::fromRoute('aculta_donation.donation.quick', ['amount' => $amount])->toString(),
      ];
    }
    // "Outro": o visitante digita o valor no fluxo de doação (campo "Outro valor" do widget).
    if ($permiteOutro) {
      $links['outro'] = [
        'title' => (string) $this->t('Outro'),
        'url' => $this->purposes->routeUrl('support', 'aculta_donation.donation.default')?->toString() ?? Url::fromRoute('aculta_donation.donation.default')->toString(),
      ];
    }
    // Dados neutros: o tema apresenta os valores (componente SDC donation-quick do tema).
    $build = [
      '#theme' => 'aculta_donation_quick',
      '#links' => $links,
    ];
    return $build;
  }

}
