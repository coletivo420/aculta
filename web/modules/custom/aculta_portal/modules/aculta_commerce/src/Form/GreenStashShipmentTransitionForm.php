<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce\Form;

use Drupal\aculta_commerce\Entity\ShipmentInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Confirms a Green Stash shipment state transition.
 *
 * The available transitions come from the shipment workflow and are evaluated
 * again when the form is built, so a stale link cannot apply an invalid state.
 */
final class GreenStashShipmentTransitionForm extends ConfirmFormBase {

  /**
   * The shipment being transitioned.
   */
  protected ?ShipmentInterface $shipment = NULL;

  /**
   * The transition ID.
   */
  protected string $transition = '';

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_commerce_green_stash_shipment_transition';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): TranslatableMarkup {
    return $this->t('Aplicar a transição “@transition” à remessa do pedido @order?', [
      '@transition' => $this->transition,
      '@order' => $this->shipment?->getOrder()?->getOrderNumber() ?: '—',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): TranslatableMarkup {
    return $this->t('Estado atual: @state. A ação altera somente o estado logístico da remessa.', [
      '@state' => $this->shipment?->getState()->getId() ?: '—',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    return Url::fromRoute('aculta_commerce.green_stash_area', ['secao' => 'envios']);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText(): TranslatableMarkup {
    return $this->t('Aplicar transição');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?ShipmentInterface $aculta_shipment = NULL, ?string $transition = NULL): array {
    if (!$aculta_shipment instanceof ShipmentInterface || $transition === NULL) {
      throw new NotFoundHttpException();
    }
    $transitions = $aculta_shipment->getState()->getTransitions();
    if (!isset($transitions[$transition])) {
      throw new NotFoundHttpException();
    }
    $this->shipment = $aculta_shipment;
    $this->transition = $transition;

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    if ($this->shipment) {
      try {
        $this->shipment->getState()->applyTransitionById($this->transition);
        $this->shipment->save();
        $this->messenger()->addStatus($this->t('Remessa atualizada para @state.', [
          '@state' => $this->shipment->getState()->getId(),
        ]));
      }
      catch (\InvalidArgumentException) {
        $this->messenger()->addError($this->t('A transição não está mais disponível para o estado atual.'));
      }
    }
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
