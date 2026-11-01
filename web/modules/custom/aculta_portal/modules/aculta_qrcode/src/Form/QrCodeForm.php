<?php

declare(strict_types=1);

namespace Drupal\aculta_qrcode\Form;

use Drupal\aculta_qrcode\Exception\InvalidQrCodeException;
use Drupal\aculta_qrcode\Service\QrCodeManager;
use Drupal\aculta_qrcode\Service\QrCodePresetRegistry;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Formulário administrativo de código QR dinâmico.
 *
 * O código público é gerado pelo `QrCodeManager` na criação e não aparece no
 * formulário. Cada salvamento cria uma revisão. A validação de janela usa o
 * mesmo serviço que a gravação pela API.
 */
final class QrCodeForm extends ContentEntityForm {

  /**
   * Serviço de criação e validação.
   */
  private QrCodeManager $manager;

  /**
   * Presets, usados para validar as cores personalizadas.
   */
  private QrCodePresetRegistry $presets;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $instance = parent::create($container);
    $instance->manager = $container->get('aculta_qrcode.manager');
    $instance->presets = $container->get('aculta_qrcode.preset_registry');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildEntity(array $form, FormStateInterface $form_state) {
    $entity = parent::buildEntity($form, $form_state);
    if ($entity->isNew() && $entity->get('code')->isEmpty()) {
      $entity->set('code', $this->manager->generateCode());
    }
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
    $entity = $this->buildEntity($form, $form_state);
    try {
      $this->manager->validateWindow($entity->getValidFrom(), $entity->getValidUntil());
    }
    catch (InvalidQrCodeException $exception) {
      $form_state->setErrorByName('valid_until', $exception->getMessage());
    }
    try {
      $this->presets->customized($entity->getPresetId(), $entity->getForeground(), $entity->getBackground());
    }
    catch (InvalidQrCodeException $exception) {
      $form_state->setErrorByName('foreground', $exception->getMessage());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $this->entity->setNewRevision();
    return parent::save($form, $form_state);
  }

}
