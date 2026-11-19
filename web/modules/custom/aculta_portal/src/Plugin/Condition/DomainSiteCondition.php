<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Plugin\Condition;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Condition\Attribute\Condition;
use Drupal\Core\Condition\ConditionPluginBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\aculta_portal\Domain\DomainSiteManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controls block visibility by the configured ACULTA site key.
 */
#[Condition(
  id: 'aculta_domain_site',
  label: new TranslatableMarkup('ACULTA domain site'),
)]
final class DomainSiteCondition extends ConditionPluginBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected readonly DomainSiteManager $siteManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('aculta_portal.domain_site'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    // An unconfigured condition must never silently mean MAIN. Keeping the
    // empty default also lets explicit MAIN visibility survive config saves.
    return ['site_key' => ''] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['site_key'] = [
      '#type' => 'select',
      '#title' => $this->t('Domain site'),
      '#empty_option' => $this->t('- Select site -'),
      '#empty_value' => '',
      '#options' => [
        'main' => $this->t('Institutional site'),
        'account' => $this->t('Account'),
        'support' => $this->t('Support'),
        'magazine' => $this->t('Revista 420'),
        'wiki' => $this->t('Wiki420'),
        'shop' => $this->t('Shop'),
        'courses' => $this->t('Courses'),
        'forum' => $this->t('Forum'),
        'links' => $this->t('Social links'),
        'eventos' => $this->t('Events'),
      ],
      '#default_value' => $this->configuration['site_key'],
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['site_key'] = $form_state->getValue('site_key');
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate(): bool {
    return $this->siteManager->getCurrentSiteKey()?->value === $this->configuration['site_key'];
  }

  /**
   * {@inheritdoc}
   */
  public function summary(): string {
    return (string) $this->t('Visible for the @site domain site.', ['@site' => $this->configuration['site_key']]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    return ['domain'];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge(): int {
    return -1;
  }

}
