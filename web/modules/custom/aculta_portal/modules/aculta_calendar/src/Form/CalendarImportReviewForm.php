<?php

declare(strict_types=1);

namespace Drupal\aculta_calendar\Form;

use Drupal\aculta_calendar\Entity\CalendarImportedItem;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\aculta_calendar\Import\CalendarImportRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Editorial review queue for imported calendar items. */
final class CalendarImportReviewForm extends FormBase {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly MessengerInterface $importMessenger,
    private readonly CalendarImportRepository $repository,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('messenger'),
      $container->get('aculta_calendar.import_repository'),
    );
  }

  public function getFormId(): string {
    return 'aculta_calendar_import_review';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['items'] = [
      '#type' => 'table',
      '#header' => [$this->t('Título'), $this->t('UID externo'), $this->t('Início'), $this->t('Estado'), $this->t('Presença na fonte'), $this->t('Ações')],
      '#empty' => $this->t('Não há itens importados para revisar.'),
    ];
    $storage = $this->entityTypeManager->getStorage('aculta_calendar_imported_item');
    $ids = $storage->getQuery()->accessCheck(FALSE)->sort('id')->execute();
    foreach ($storage->loadMultiple($ids) as $item) {
      if (!$item instanceof CalendarImportedItem) {
        continue;
      }
      $latest = $this->repository->findLatest($item->get('source')->entity, $item->get('external_uid')->value) ?? $item;
      if ($latest->get('import_state')->value === 'publicado') {
        continue;
      }
      $key = (string) $item->id();
      $form['items'][$key]['title'] = ['#plain_text' => $latest->get('title')->value];
      $form['items'][$key]['uid'] = ['#plain_text' => $latest->get('external_uid')->value];
      $form['items'][$key]['start'] = ['#plain_text' => gmdate('d/m/Y H:i', (int) $latest->get('start')->value)];
      $form['items'][$key]['state'] = ['#plain_text' => $latest->get('import_state')->value];
      $form['items'][$key]['presence'] = ['#plain_text' => $item->get('source_presence')->value];
      $form['items'][$key]['publish'] = [
        '#type' => 'submit',
        '#value' => $this->t('Publicar'),
        '#name' => 'publish_' . $key,
        '#submit' => ['::publishItem'],
        '#item_id' => $key,
        '#limit_validation_errors' => [],
      ];
      $form['items'][$key]['keep_draft'] = [
        '#type' => 'submit',
        '#value' => $this->t('Manter rascunho'),
        '#name' => 'draft_' . $key,
        '#submit' => ['::keepDraft'],
        '#item_id' => $key,
        '#limit_validation_errors' => [],
      ];
    }
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {}

  public function publishItem(array &$form, FormStateInterface $form_state): void {
    $item = $this->latestItem((string) $form_state->getTriggeringElement()['#item_id']);
    $item->setImportState('publicado');
    $item->setNewRevision(TRUE);
    $item->isDefaultRevision(TRUE);
    $item->setRevisionLogMessage((string) $this->t('Publicado após revisão editorial da importação.'));
    $item->save();
    $this->importMessenger->addStatus($this->t('O item foi publicado.'));
    $form_state->setRedirect('aculta_calendar.import_review');
  }

  public function keepDraft(array &$form, FormStateInterface $form_state): void {
    $item = $this->latestItem((string) $form_state->getTriggeringElement()['#item_id']);
    if ($item->get('import_state')->value !== 'rascunho') {
      $item->setImportState('rascunho');
      $item->setNewRevision(TRUE);
      $item->isDefaultRevision(FALSE);
      $item->save();
    }
    $this->importMessenger->addStatus($this->t('O item permanece como rascunho.'));
    $form_state->setRedirect('aculta_calendar.import_review');
  }

  private function latestItem(string $id): CalendarImportedItem {
    $entity = $this->entityTypeManager->getStorage('aculta_calendar_imported_item')->load($id);
    if (!$entity instanceof CalendarImportedItem) {
      throw new \InvalidArgumentException('Item importado não encontrado.');
    }
    return $this->repository->findLatest($entity->get('source')->entity, $entity->get('external_uid')->value) ?? $entity;
  }

}
