<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Form;

use Drupal\aculta_mailer\Entity\MailBrand;
use Drupal\aculta_mailer\Entity\MailLayout;
use Drupal\aculta_mailer\Template\MailTemplateRenderer;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Editor de layout: cabeçalho e rodapé, com `{{ content }}` no lugar do corpo e os campos da marca.
 */
final class MailLayoutForm extends EntityForm {

  /**
   * Variáveis aceitas em layouts.
   */
  public const VARIABLES = ['content', 'brand_name', 'brand_footer'];

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypes,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('entity_type.manager'));
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);
    /** @var \Drupal\aculta_mailer\Entity\MailLayout $layout */
    $layout = $this->entity;
    $brands = [];
    foreach ($this->entityTypes->getStorage(MailBrand::ENTITY_TYPE)->loadMultiple() as $brand) {
      $brands[$brand->id()] = $brand->label();
    }
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Nome'),
      '#default_value' => $layout->label(),
      '#required' => TRUE,
      '#maxlength' => 255,
    ];
    $form['id'] = [
      '#type' => 'machine_name',
      '#title' => $this->t('ID'),
      '#default_value' => $layout->id(),
      '#maxlength' => 32,
      '#machine_name' => [
        'exists' => [$this, 'exists'],
        'source' => ['label'],
      ],
      '#disabled' => !$layout->isNew(),
    ];
    $form['brand'] = [
      '#type' => 'select',
      '#title' => $this->t('Marca'),
      '#options' => $brands,
      '#default_value' => $layout->getBrand(),
      '#required' => TRUE,
      '#empty_option' => $this->t('- Escolha uma marca -'),
    ];
    $form['header'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Cabeçalho (texto simples)'),
      '#default_value' => $layout->getHeader() !== '' ? $layout->getHeader() : '{{ brand_name }}',
      '#rows' => 3,
    ];
    $form['footer'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Rodapé (texto simples)'),
      '#default_value' => $layout->getFooter() !== '' ? $layout->getFooter() : '{{ brand_footer }}',
      '#rows' => 4,
      '#description' => $this->t('Use {{ content }} para o corpo, {{ brand_name }} e {{ brand_footer }} para a marca.'),
    ];
    return $form;
  }

  /**
   * Verifica se já existe layout com o ID (para o componente de ID).
   */
  public function exists(string $id): bool {
    return (bool) $this->entityTypes->getStorage(MailLayout::ENTITY_TYPE)->load($id);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
    foreach (['header', 'footer'] as $field) {
      $unknown = MailTemplateRenderer::unknownPlaceholders((string) $form_state->getValue($field), self::VARIABLES);
      if ($unknown !== []) {
        $form_state->setErrorByName($field, $this->t('Variável desconhecida: @names.', [
          '@names' => implode(', ', array_map(static fn(string $name): string => '{{ ' . $name . ' }}', $unknown)),
        ]));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    /** @var \Drupal\aculta_mailer\Entity\MailLayout $layout */
    $layout = $this->entity;
    $layout->set('label', (string) $form_state->getValue('label'));
    $layout->setBrand((string) $form_state->getValue('brand'));
    $layout->setHeader((string) $form_state->getValue('header'));
    $layout->setFooter((string) $form_state->getValue('footer'));
    $status = $layout->save();
    $this->messenger()->addStatus($this->t('Layout salvo.'));
    $form_state->setRedirect('entity.' . MailLayout::ENTITY_TYPE . '.collection');
    return $status;
  }

}
