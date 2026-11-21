<?php

declare(strict_types=1);

namespace Drupal\aculta_qrcode\Form;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\aculta_qrcode\Entity\QrCode;
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
  protected QrCodeManager $manager;

  /**
   * Presets, usados para validar as cores personalizadas.
   */
  protected QrCodePresetRegistry $presets;

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
   *
   * @param array<mixed> $form
   *   Value used for $form.
   * @param FormStateInterface $form_state
   *   Value used for $form_state.
   *
   * @return QrCode
   *   Value returned by the method.
   */
  public function buildEntity(array $form, FormStateInterface $form_state): QrCode {
    /** @var \Drupal\aculta_qrcode\Entity\QrCode $entity */
    $entity = parent::buildEntity($form, $form_state);
    if ($entity->isNew() && $entity->get('code')->isEmpty()) {
      $entity->set('code', $this->manager->generateCode());
    }
    return $entity;
  }

  /**
   * {@inheritdoc}
   *
   * @param array<mixed> $form
   *   Value used for $form.
   * @param FormStateInterface $form_state
   *   Value used for $form_state.
   *
   * @return ContentEntityInterface
   *   Value returned by the method.
   */
  public function validateForm(array &$form, FormStateInterface $form_state): ContentEntityInterface {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $parentResult */
    $parentResult = parent::validateForm($form, $form_state);
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
    return $parentResult;
  }

  /**
   * {@inheritdoc}
   *
   * @param array<array-key, mixed> $form
   *   Value used for $form.
   * @param FormStateInterface $form_state
   *   Value used for $form_state.
   *
   * @return int
   *   Value returned by the method.
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $this->entity->setNewRevision();
    return parent::save($form, $form_state);
  }

}
