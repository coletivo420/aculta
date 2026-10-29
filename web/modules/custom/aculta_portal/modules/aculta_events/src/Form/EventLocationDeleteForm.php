<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Form;

use Drupal\Core\Entity\ContentEntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Formulário de confirmação de exclusão de local de evento (LOC-02).
 */
final class EventLocationDeleteForm extends ContentEntityConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): string {
    return (string) $this->t('Você tem certeza que deseja excluir o local %title?', [
      '%title' => $this->entity->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    /** @var \Drupal\aculta_events\Entity\EventLocation $entity */
    $entity = $this->entity;
    if ($entity->access('view', $this->currentUser())) {
      return new Url('aculta_events.location_canonical', [
        'aculta_event_location' => $entity->id(),
      ]);
    }
    return new Url('aculta_events.locations');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText(): string {
    return (string) $this->t('Excluir');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $entity = $this->entity;
    $dateStorage = $this->entityTypeManager->getStorage('aculta_event_date');
    $inUse = (int) $dateStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('field_local', (int) $entity->id())
      ->count()
      ->execute();

    if ($inUse > 0) {
      $this->messenger()->addError($this->t('Este local não pode ser excluído porque está associado a @count data(s) de evento.', [
        '@count' => $inUse,
      ]));
      return [
        'message' => [
          '#markup' => '<p>' . $this->t('A exclusão foi bloqueada para proteger a integridade dos eventos associados.') . '</p>',
        ],
        'actions' => [
          '#type' => 'actions',
          'cancel' => [
            '#type' => 'link',
            '#title' => $this->t('Voltar'),
            '#url' => $this->getCancelUrl(),
            '#attributes' => ['class' => ['button']],
          ],
        ],
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\aculta_events\Entity\EventLocation $entity */
    $entity = $this->entity;
    $title = $entity->label();

    $dateStorage = $this->entityTypeManager->getStorage('aculta_event_date');
    $inUse = (int) $dateStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('field_local', (int) $entity->id())
      ->count()
      ->execute();

    if ($inUse > 0) {
      $this->messenger()->addError($this->t('Não foi possível excluir o local: existem datas associadas.'));
      $form_state->setRedirectUrl($this->getCancelUrl());
      return;
    }

    $entity->delete();
    $this->messenger()->addStatus($this->t('O local %title foi excluído.', [
      '%title' => $title,
    ]));

    $form_state->setRedirect('aculta_events.locations');
  }

}
