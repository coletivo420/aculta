<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Plugin\Condition;

use Drupal\Core\Condition\Attribute\Condition;
use Drupal\Core\Condition\ConditionPluginBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controls block visibility by the configured ACULTA domain purpose.
 */
#[Condition(
  id: 'aculta_domain_purpose',
  label: new TranslatableMarkup('ACULTA domain purpose'),
)]
final class DomainPurposeCondition extends ConditionPluginBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly DomainPurposeManager $domainPurposeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('aculta_portal.domain_purpose'),
    );
  }

  public function defaultConfiguration(): array {
    // An unconfigured condition must never silently mean MAIN. Keeping the
    // empty default also lets explicit MAIN visibility survive config saves.
    return ['purpose' => ''] + parent::defaultConfiguration();
  }

  public function buildConfigurationForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state): array {
    $form['purpose'] = [
      '#type' => 'select',
      '#title' => $this->t('Domain purpose'),
      '#empty_option' => $this->t('- Select purpose -'),
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
      ],
      '#default_value' => $this->configuration['purpose'],
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  public function submitConfigurationForm(array &$form, \Drupal\Core\Form\FormStateInterface $form_state): void {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['purpose'] = $form_state->getValue('purpose');
  }

  public function evaluate(): bool {
    return $this->domainPurposeManager->getCurrentPurpose() === $this->configuration['purpose'];
  }

  public function summary(): string {
    return (string) $this->t('Visible for the @purpose domain purpose.', ['@purpose' => $this->configuration['purpose']]);
  }

  public function getCacheContexts(): array {
    return ['domain'];
  }

  public function getCacheTags(): array {
    return [];
  }

  public function getCacheMaxAge(): int {
    return -1;
  }

}
