<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Form;

use Drupal\aculta_mailer\Entity\MailBrand;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Editor de marca: nome e rodapé institucional usados pelos layouts.
 *
 * O logo não é cadastrado aqui: o envelope usa o logo do tema (aculta420.settings), sem uma cópia por marca.
 */
final class MailBrandForm extends EntityForm {

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
    /** @var \Drupal\aculta_mailer\Entity\MailBrand $brand */
    $brand = $this->entity;
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Nome interno'),
      '#default_value' => $brand->label(),
      '#required' => TRUE,
      '#maxlength' => 255,
    ];
    $form['id'] = [
      '#type' => 'machine_name',
      '#title' => $this->t('ID'),
      '#default_value' => $brand->id(),
      '#maxlength' => 32,
      '#machine_name' => [
        'exists' => [$this, 'exists'],
        'source' => ['label'],
      ],
      '#disabled' => !$brand->isNew(),
    ];
    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Nome exibido no cabeçalho'),
      '#default_value' => $brand->getName(),
      '#required' => TRUE,
      '#maxlength' => 255,
    ];
    $form['footer'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Rodapé institucional (texto simples)'),
      '#default_value' => $brand->getFooter(),
      '#rows' => 6,
    ];
    return $form;
  }

  /**
   * Verifica se já existe marca com o ID (para o componente de ID).
   */
  public function exists(string $id): bool {
    return (bool) $this->entityTypes->getStorage(MailBrand::ENTITY_TYPE)->load($id);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    /** @var \Drupal\aculta_mailer\Entity\MailBrand $brand */
    $brand = $this->entity;
    $brand->set('label', (string) $form_state->getValue('label'));
    $brand->setName((string) $form_state->getValue('name'));
    $brand->setFooter((string) $form_state->getValue('footer'));
    $status = $brand->save();
    $this->messenger()->addStatus($this->t('Marca salva.'));
    $form_state->setRedirect('entity.' . MailBrand::ENTITY_TYPE . '.collection');
    return $status;
  }

}
