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
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly MessengerInterface $importMessenger,
    protected readonly CalendarImportRepository $repository,
    protected readonly DateFormatterInterface $dateFormatter,
  ) {}

  /**
   * Creates the form instance from the Drupal service container.
   */
  public static function create(ContainerInterface $container): static {
    /** @var \Drupal\aculta_calendar\Import\CalendarImportRepository $repository */
    $repository = $container->get('aculta_calendar.import_repository');
    return new static(
      $container->get('entity_type.manager'),
      $container->get('messenger'),
      $repository,
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
   *
   * @param array<string, mixed> $form
   *   Value used for $form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Value used for $form_state.
   *
   * @return array<string, mixed>
   *   Render array returned by the method.
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
      $externalUid = is_string($item->get('external_uid')->value) ? $item->get('external_uid')->value : '';
      $latest = $this->repository->findLatest($source, $externalUid) ?? $item;
      if ($latest->get('import_state')->value === 'publicado') {
        continue;
      }
      $key = (string) $item->id();
      $startVal = $latest->get('start')->value;
      $startTs = is_numeric($startVal) ? (int) $startVal : 0;
      $titleVal = $latest->get('title')->value;
      $uidVal = $latest->get('external_uid')->value;
      $stateVal = $latest->get('import_state')->value;
      $presenceVal = $item->get('source_presence')->value;

      $row = [];
      $row['title'] = ['#plain_text' => is_string($titleVal) ? $titleVal : ''];
      $row['uid'] = ['#plain_text' => is_string($uidVal) ? $uidVal : ''];
      $row['start'] = [
        '#plain_text' => $this->dateFormatter->format($startTs, 'custom', 'd/m/Y H:i'),
      ];
      $row['state'] = ['#plain_text' => is_string($stateVal) ? $stateVal : ''];
      $row['presence'] = ['#plain_text' => is_string($presenceVal) ? $presenceVal : ''];
      $is_cancellation = $stateVal === 'cancelado';
      $row['publish'] = [
        '#type' => 'submit',
        '#value' => $is_cancellation ? $this->t('Publicar cancelamento') : $this->t('Publicar'),
        '#name' => 'publish_' . $key,
        '#submit' => [$is_cancellation ? '::publishCancellation' : '::publishItem'],
        '#item_id' => $key,
        '#limit_validation_errors' => [],
      ];
      $row['keep_draft'] = [
        '#type' => 'submit',
        '#value' => $this->t('Manter rascunho'),
        '#name' => 'draft_' . $key,
        '#submit' => ['::keepDraft'],
        '#item_id' => $key,
        '#limit_validation_errors' => [],
      ];
      $form['items'][$key] = $row;
    }
    return $form;
  }

  /**
   * Describes the submitForm value.
   *
   * @param array<array-key, mixed> $form
   *   Value used for $form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Value used for $form_state.
   *
   * @return void
   *   Value returned by the method.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {}

  /**
   * Describes the publishItem value.
   *
   * @param array<array-key, mixed> $form
   *   Value used for $form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Value used for $form_state.
   *
   * @return void
   *   Value returned by the method.
   */
  public function publishItem(array &$form, FormStateInterface $form_state): void {
    $trigger = $form_state->getTriggeringElement();
    $itemId = isset($trigger['#item_id']) && is_string($trigger['#item_id']) ? $trigger['#item_id'] : '';
    $item = $this->latestItem($itemId);
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
   *
   * @param array<array-key, mixed> $form
   *   Value used for $form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Value used for $form_state.
   *
   * @return void
   *   Value returned by the method.
   */
  public function publishCancellation(array &$form, FormStateInterface $form_state): void {
    $trigger = $form_state->getTriggeringElement();
    $itemId = isset($trigger['#item_id']) && is_string($trigger['#item_id']) ? $trigger['#item_id'] : '';
    $item = $this->latestItem($itemId);
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
   *
   * @param array<array-key, mixed> $form
   *   Value used for $form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Value used for $form_state.
   *
   * @return void
   *   Value returned by the method.
   */
  public function keepDraft(array &$form, FormStateInterface $form_state): void {
    $trigger = $form_state->getTriggeringElement();
    $itemId = isset($trigger['#item_id']) && is_string($trigger['#item_id']) ? $trigger['#item_id'] : '';
    $item = $this->latestItem($itemId);
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
    $source = $entity->get('source')->entity;
    if (!$source instanceof CalendarImportSource) {
      return $entity;
    }
    $externalUid = is_string($entity->get('external_uid')->value) ? $entity->get('external_uid')->value : '';
    return $this->repository->findLatest($source, $externalUid) ?? $entity;
  }

}
