<?php

declare(strict_types=1);

namespace Drupal\aculta_calendar\Form;

use Drupal\aculta_calendar\Entity\CalendarImportedItem;
use Drupal\aculta_calendar\Entity\CalendarImportSource;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\aculta_calendar\Import\CalendarImportRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Editorial review queue for imported calendar items. */
final class CalendarImportReviewForm extends FormBase {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly MessengerInterface $importMessenger,
    private readonly CalendarImportRepository $repository,
    private readonly DateFormatterInterface $dateFormatter,
  ) {}

  /**
   * Creates the form instance from the Drupal service container.
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('messenger'),
      $container->get('aculta_calendar.import_repository'),
      $container->get('date.formatter'),
    );
  }

  /**
   * Returns the form ID used by Drupal Form API.
   */
  public function getFormId(): string {
    return 'aculta_calendar_import_review';
  }

  /**
   * Describes the buildForm value.
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['items'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Título'),
        $this->t('UID externo'),
        $this->t('Início'),
        $this->t('Estado'),
        $this->t('Presença na fonte'),
        $this->t('Ações'),
      ],
      '#empty' => $this->t('Não há itens importados para revisar.'),
    ];
    $storage = $this->entityTypeManager->getStorage('aculta_calendar_imported_item');
    $ids = $storage->getQuery()->accessCheck(FALSE)->sort('id')->execute();
    foreach ($storage->loadMultiple($ids) as $item) {
      if (!$item instanceof CalendarImportedItem) {
        continue;
      }
      $source = $item->get('source')->entity;
      if (!$source instanceof CalendarImportSource) {
        // A dangling source reference must not take down the whole queue.
        continue;
      }
      $latest = $this->repository->findLatest($source, $item->get('external_uid')->value) ?? $item;
      if ($latest->get('import_state')->value === 'publicado') {
        continue;
      }
      $key = (string) $item->id();
      $form['items'][$key]['title'] = ['#plain_text' => $latest->get('title')->value];
      $form['items'][$key]['uid'] = ['#plain_text' => $latest->get('external_uid')->value];
      $form['items'][$key]['start'] = [
        '#plain_text' => $this->dateFormatter->format((int) $latest->get('start')->value, 'custom', 'd/m/Y H:i'),
      ];
      $form['items'][$key]['state'] = ['#plain_text' => $latest->get('import_state')->value];
      $form['items'][$key]['presence'] = ['#plain_text' => $item->get('source_presence')->value];
      $is_cancellation = $latest->get('import_state')->value === 'cancelado';
      $form['items'][$key]['publish'] = [
        '#type' => 'submit',
        '#value' => $is_cancellation ? $this->t('Publicar cancelamento') : $this->t('Publicar'),
        '#name' => 'publish_' . $key,
        '#submit' => [$is_cancellation ? '::publishCancellation' : '::publishItem'],
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

  /**
   * Describes the submitForm value.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {}

  /**
   * Describes the publishItem value.
   */
  public function publishItem(array &$form, FormStateInterface $form_state): void {
    $item = $this->latestItem((string) $form_state->getTriggeringElement()['#item_id']);
    if ($item->get('import_state')->value === 'cancelado') {
      $this->importMessenger->addError($this->t('Este item é um cancelamento; use “Publicar cancelamento” para exibir o aviso.'));
      $form_state->setRedirect('aculta_calendar.import_review');
      return;
    }
    $item->setImportState('publicado');
    $item->setNewRevision(TRUE);
    $item->isDefaultRevision(TRUE);
    $item->setRevisionLogMessage((string) $this->t('Publicado após revisão editorial da importação.'));
    $item->save();
    $this->importMessenger->addStatus($this->t('O item foi publicado.'));
    $form_state->setRedirect('aculta_calendar.import_review');
  }

  /**
   * Publishes a cancellation notice without presenting it as an active event.
   */
  public function publishCancellation(array &$form, FormStateInterface $form_state): void {
    $item = $this->latestItem((string) $form_state->getTriggeringElement()['#item_id']);
    if ($item->get('import_state')->value !== 'cancelado') {
      $this->importMessenger->addError($this->t('Este item não está marcado como cancelado.'));
      $form_state->setRedirect('aculta_calendar.import_review');
      return;
    }
    $item->setImportState('cancelado');
    $item->setPublished();
    $item->setNewRevision(TRUE);
    $item->isDefaultRevision(TRUE);
    $item->setRevisionLogMessage((string) $this->t('Cancelamento publicado após revisão editorial da importação.'));
    $item->save();
    $this->importMessenger->addStatus($this->t('O cancelamento foi publicado.'));
    $form_state->setRedirect('aculta_calendar.import_review');
  }

  /**
   * Describes the keepDraft value.
   */
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

  /**
   * Describes the latestItem value.
   */
  private function latestItem(string $id): CalendarImportedItem {
    $entity = $this->entityTypeManager->getStorage('aculta_calendar_imported_item')->load($id);
    if (!$entity instanceof CalendarImportedItem) {
      throw new \InvalidArgumentException('Item importado não encontrado.');
    }
    return $this->repository->findLatest($entity->get('source')->entity, $entity->get('external_uid')->value) ?? $entity;
  }

}
