<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Form;

use Drupal\aculta_mailer\Entity\MailBrand;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\file\FileUsage\FileUsageInterface;
use Drupal\aculta_image\UploadPolicy;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Editor de marca: nome e rodapé institucional usados pelos layouts.
 */
final class MailBrandForm extends EntityForm {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypes,
    private readonly UploadPolicy $uploadPolicy,
    private readonly FileUsageInterface $fileUsage,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('aculta_image.upload_policy'),
      $container->get('file.usage'),
    );
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
    $form['logo'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Logo (PNG, GIF, JPG ou WebP, até 10 MB)'),
      '#description' => $this->t('Imagem do cabeçalho do envelope. Enviada pela política de imagens do ACULTA.'),
      '#upload_location' => 'public://aculta-mailer/marcas',
      '#upload_validators' => $this->uploadPolicy->validators(),
      '#default_value' => $brand->getLogoFid() > 0 ? [$brand->getLogoFid()] : [],
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
    $previous = $brand->getLogoFid();
    $fid = (int) (($form_state->getValue('logo')[0] ?? 0));
    $fileStorage = $this->entityTypes->getStorage('file');
    if ($fid > 0 && $fid !== $previous && ($file = $fileStorage->load($fid)) !== NULL) {
      $file->setPermanent();
      $file->save();
      $this->fileUsage->add($file, 'aculta_mailer', 'aculta_mailer_brand', $brand->id(), 1);
    }
    if ($previous > 0 && $fid !== $previous && ($old = $fileStorage->load($previous)) !== NULL) {
      $this->fileUsage->delete($old, 'aculta_mailer', 'aculta_mailer_brand', $brand->id(), 1);
    }
    $brand->setLogoFid($fid);
    $status = $brand->save();
    $this->messenger()->addStatus($this->t('Marca salva.'));
    $form_state->setRedirect('entity.' . MailBrand::ENTITY_TYPE . '.collection');
    return $status;
  }

}
