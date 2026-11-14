<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure settings for ACULTA node co-authorship.
 */
final class CoAuthorSettingsForm extends ConfigFormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs a CoAuthorSettingsForm.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typedConfigManager,
    EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($config_factory, $typedConfigManager);
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   *
   * @return array<int, string>
   *   An array of configuration names that are editable by this form.
   */
  protected function getEditableConfigNames(): array {
    return ['aculta_user.coauthor_settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_user_coauthor_settings_form';
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array<string, mixed>
   *   The form structure.
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('aculta_user.coauthor_settings');

    $form['bundles_wrapper'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Tipos de conteúdo com coautoria'),
      '#description' => $this->t('Selecione quais tipos de conteúdo aceitam a atribuição de coautores. O Fórum é expressamente incompatível e não pode ser habilitado.'),
    ];

    $nodeTypes = $this->entityTypeManager->getStorage('node_type')->loadMultiple();
    $options = [];
    foreach ($nodeTypes as $type) {
      if ($type->id() === 'aculta_forum_topic') {
        continue;
      }
      $options[(string) $type->id()] = $type->label();
    }

    $defaultBundles = $config->get('enabled_bundles');
    if ($defaultBundles === NULL) {
      $defaultBundles = [
        'revista' => 'revista',
        'wiki_entry' => 'wiki_entry',
        'activity' => 'activity',
        'document' => 'document',
        'editorial_highlight' => 'editorial_highlight',
        'page' => 'page',
      ];
    }

    $form['bundles_wrapper']['enabled_bundles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Tipos habilitados'),
      '#options' => $options,
      '#default_value' => is_array($defaultBundles) ? array_keys(array_filter($defaultBundles)) : [],
    ];

    $form['policy_wrapper'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Política de coautoria e privacidade'),
    ];

    $form['policy_wrapper']['info'] = [
      '#type' => 'item',
      '#markup' => '<p>' . $this->t('A concessão de coautoria permite edição colaborativa quando o usuário possui as permissões do bundle. Nenhum e-mail ou dado privado é exposto publicamente.') . '</p>',
    ];

    /** @var array<string, mixed> $built */
    $built = parent::buildForm($form, $form_state);
    return $built;
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    /** @var array<string, mixed> $rawBundles */
    $rawBundles = (array) $form_state->getValue('enabled_bundles');
    $enabledBundles = [];
    foreach ($rawBundles as $bundle => $value) {
      if (!empty($value) && $bundle !== 'aculta_forum_topic') {
        $enabledBundles[(string) $bundle] = (string) $bundle;
      }
    }

    $this->config('aculta_user.coauthor_settings')
      ->set('enabled_bundles', $enabledBundles)
      ->save();

    /** @var array<string, mixed> $parentForm */
    $parentForm = &$form;
    parent::submitForm($parentForm, $form_state);
  }

}
