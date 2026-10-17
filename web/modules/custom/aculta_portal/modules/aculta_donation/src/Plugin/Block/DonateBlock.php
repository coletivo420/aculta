<?php

namespace Drupal\aculta_donation\Plugin\Block;

use Drupal\aculta_donation\Form\DonationSettingsForm;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RedirectDestinationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\path_alias\AliasManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'DonateBlock' block.
 */
#[Block(
  id: 'aculta_donation_donate',
  admin_label: new TranslatableMarkup('Doar'),
  category: new TranslatableMarkup('ACULTA Doações'),
)]
class DonateBlock extends BlockBase implements ContainerFactoryPluginInterface {

  // Modeled on \Drupal\user\Plugin\Block\UserLoginBlock.
  use RedirectDestinationTrait;

  /**
   * Injected service.
   *
   * @var \Drupal\Core\Path\PathMatcherInterface
   */
  protected $pathMatcher;

  /**
   * Injected service.
   *
   * @var \Drupal\path_alias\AliasManagerInterface
   */
  protected $aliasManager;

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

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static(
      $configuration,
      $plugin_id,
      $plugin_definition
    );
    $instance->pathMatcher = $container->get('path.matcher');
    if ($container->has('path_alias.manager')) {
      $instance->aliasManager = $container->get('path_alias.manager');
    }
    $instance->configFactory = $container->get('config.factory');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $config = parent::defaultConfiguration();
    $config['return_path'] = FALSE;
    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['return_path'] = [
      '#type' => 'checkbox',
      '#title' => $this
        ->t('Add a return path to urls.'),
      '#default_value' => $this->configuration['return_path'],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $donationConfig = $this->configFactory->get('commerce_donation_flow.settings');
    $use_route = $donationConfig->get('donation_route');
    $route_allowed = $use_route === DonationSettingsForm::ROTA_DONATE || $use_route === DonationSettingsForm::ROTA_AMBAS;
    if (!$route_allowed) {
      $form_state->setError($form['label'], $this->t('The Donate block is only usable if donations are configured in Commerce to use the /donate route. Adjust the donation configuration before configuring this block.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['return_path'] = $form_state->getValue('return_path') ? TRUE : FALSE;
    parent::blockSubmit($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $options = [];
    if ($this->configuration['return_path']) {
      $returnPath = ['donate_return' => $this->determineReturnPath()];
      $options = ['query' => $returnPath];
    }
    // Dados neutros: o tema escolhe a apresentação (ver templates/aculta-donation-donate).
    $build = [
      '#theme' => 'aculta_donation_donate',
      '#label' => $this->t('Doar'),
      '#href' => Url::fromRoute('aculta_donation.donation.default', [], $options)->toString(),
      '#cache' => ['contexts' => ['url.path', 'url.query_args']],
    ];
    return $build;
  }

  /**
   * Helper function to create a user friendly return path.
   *
   * @return string
   *   The return path.
   */
  protected function determineReturnPath() {
    if ($this->pathMatcher->isFrontPage()) {
      return '/';
    }
    $returnPath = $this->getRedirectDestination()->get();
    if ($this->aliasManager instanceof AliasManagerInterface) {
      return $this->aliasManager->getAliasByPath($returnPath);
    }
    return $returnPath;
  }

}
