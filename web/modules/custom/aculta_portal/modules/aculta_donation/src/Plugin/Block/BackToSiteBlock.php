<?php

namespace Drupal\aculta_donation\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RedirectDestinationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a block for exiting donation flow.
 *
 * The equivalent of "continue shopping"
 */
#[Block(
  id: 'aculta_donation_back',
  admin_label: new TranslatableMarkup('Voltar ao site'),
  category: new TranslatableMarkup('ACULTA Doações'),
)]
class BackToSiteBlock extends BlockBase implements ContainerFactoryPluginInterface {

  // Modeled on \Drupal\user\Plugin\Block\UserLoginBlock.
  use RedirectDestinationTrait;

  /**
   * Injected service.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Injected service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

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
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->requestStack = $container->get('request_stack');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->renderer = $container->get('renderer');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $config = $this->getConfiguration();
    $query = $this->requestStack->getCurrentRequest()->query;
    if (!$query->has('donate_return')) {
      $url = Url::fromRoute('<front>');
    }
    else {
      $url = Url::fromUri('internal:' . $query->get('donate_return'));
    }

    return [
      '#theme' => 'aculta_donation_back',
      '#label' => $this->t('Voltar ao site'),
      '#href' => $url->toString(),
      '#cache' => ['contexts' => ['url.path', 'url.query_args']],
    ];
  }

}
