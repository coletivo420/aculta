<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Form;

use Drupal\aculta_events\Entity\EventDate;
use Drupal\aculta_events\Entity\EventLocation;
use Drupal\aculta_events\Event\EventDateListBuilder;
use Drupal\aculta_events\Plugin\Field\FieldWidget\EventLocationModalWidget;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\aculta_events\Route\EventRouteContext;

/**
 * Formulário nativo para criação e edição de eventos com suas datas e locais.
 *
 * O Evento é a unidade editorial. Na criação, uma ou mais Datas são
 * configuradas no mesmo fluxo; na edição, as Datas do Evento são listadas e
 * administradas por popups Drupal/AJAX, sem formulário avulso de Data.
 */
final class EventForm extends ContentEntityForm {

  /**
   * Construtor da lista de Datas gerida dentro do Evento.
   */
  protected EventDateListBuilder $dateListBuilder;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $instance = parent::create($container);
    $instance->dateListBuilder = $container->get('aculta_events.date_list_builder');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildForm($form, $form_state);
    /** @var \Drupal\aculta_events\Entity\Event $entity */
    $entity = $this->entity;

    if ($entity->isNew() && $entity->get('field_organizador')->isEmpty()) {
      $entity->set('field_organizador', $this->currentUser()->id());
    }

    if ($entity->isNew()) {
      $this->buildDateCreationSection($form, $form_state);
    }
    else {
      $form['event_dates'] = [
        '#type' => 'details',
        '#title' => $this->t('Datas e locais deste evento'),
        '#open' => TRUE,
        '#weight' => 22,
        '#attached' => ['library' => ['core/drupal.dialog.ajax']],
      ];
      $form['event_dates']['list'] = $this->dateListBuilder->build($entity);
    }

    return $form;
  }

  /**
   * Seção de Datas na criação do Evento (uma ou mais).
   */
  private function buildDateCreationSection(array &$form, FormStateInterface $form_state): void {
    if ($form_state->get('date_delta') === NULL) {
      $form_state->set('date_delta', 1);
    }
    $delta = max(1, (int) $form_state->get('date_delta'));

    $form['date_location'] = [
      '#type' => 'details',
      '#title' => $this->t('Datas e locais do evento'),
      '#open' => TRUE,
      '#weight' => 22,
      '#tree' => TRUE,
    ];
    $form['date_location']['dates'] = [
      '#type' => 'container',
      '#tree' => TRUE,
      '#attributes' => ['id' => 'event-dates-wrapper'],
    ];

    for ($i = 0; $i < $delta; $i++) {
      $form['date_location']['dates'][$i] = $this->buildDateRow($i, $form_state);
    }

    $form['date_add_more'] = [
      '#type' => 'submit',
      '#name' => 'add_more_event_date',
      '#value' => $this->t('Adicionar outra data'),
      '#submit' => ['::addDateRow'],
      '#limit_validation_errors' => [],
      '#weight' => 23,
      '#ajax' => [
        'callback' => '::ajaxRebuildDates',
        'wrapper' => 'event-dates-wrapper',
      ],
    ];
  }

  /**
   * Constrói uma linha de Data e Local.
   */
  private function buildDateRow(int $delta, FormStateInterface $form_state): array {
    $row = (array) $form_state->getValue(['date_location', 'dates', $delta], []);
    $rawLocal = $row['local_id'] ?? NULL;
    $localId = is_array($rawLocal) ? (int) ($rawLocal['target_id'] ?? 0) : (int) ($rawLocal ?? 0);
    $wrapperId = 'event-date-local-' . $delta;

    $element = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'event-date-row'],
    ];

    $element['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Título da data (opcional)'),
      '#maxlength' => 255,
      '#default_value' => (string) ($row['title'] ?? ''),
    ];
    $element['starts_at'] = [
      '#type' => 'datetime',
      '#title' => $this->t('Data e hora de início'),
      '#default_value' => $row['starts_at'] ?? NULL,
    ];
    $element['ends_at'] = [
      '#type' => 'datetime',
      '#title' => $this->t('Data e hora de término'),
      '#default_value' => $row['ends_at'] ?? NULL,
    ];
    $element['local_id'] = [
      '#type' => 'container',
      '#attributes' => ['id' => $wrapperId, 'data-portal-ui' => 'event-location-widget'],
      '#attached' => ['library' => ['core/drupal.dialog.ajax']],
    ] + EventLocationModalWidget::buildCardOrEmpty($localId, 'local_id', $wrapperId);

    return $element;
  }

  /**
   * Adiciona uma nova linha de Data (rebuild AJAX).
   */
  public function addDateRow(array &$form, FormStateInterface $form_state): void {
    $form_state->set('date_delta', (int) $form_state->get('date_delta') + 1);
    $form_state->setRebuild(TRUE);
  }

  /**
   * Devolve o contêiner de Datas para o rebuild AJAX.
   */
  public function ajaxRebuildDates(array &$form, FormStateInterface $form_state): array {
    return $form['date_location']['dates'];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    /** @var \Drupal\aculta_events\Entity\Event $entity */
    $entity = $this->entity;
    if (!$entity->isNew()) {
      return;
    }

    $dates = (array) $form_state->getValue(['date_location', 'dates'], []);
    foreach ($dates as $i => $row) {
      $rawLocal = $row['local_id'] ?? NULL;
      $localId = is_array($rawLocal) ? (int) ($rawLocal['target_id'] ?? 0) : (int) ($rawLocal ?? 0);
      $start = $this->utcValue($row['starts_at'] ?? NULL);
      $end = $this->utcValue($row['ends_at'] ?? NULL);

      if ($start === NULL && $end === NULL && $localId < 1) {
        continue;
      }
      if ($start === NULL) {
        $form_state->setErrorByName('date_location][dates][' . $i . '][starts_at', (string) $this->t('Informe o início da data @number.', ['@number' => $i + 1]));
      }
      if ($end === NULL) {
        $form_state->setErrorByName('date_location][dates][' . $i . '][ends_at', (string) $this->t('Informe o fim da data @number.', ['@number' => $i + 1]));
      }
      if ($start !== NULL && $end !== NULL && $end <= $start) {
        $form_state->setErrorByName('date_location][dates][' . $i . '][ends_at', (string) $this->t('O fim da data @number deve ser posterior ao início.', ['@number' => $i + 1]));
      }
      if ($localId > 0) {
        $loc = $this->entityTypeManager->getStorage('aculta_event_location')->load($localId);
        if (!$loc instanceof EventLocation || !$loc->access('view', $this->currentUser())) {
          $form_state->setErrorByName('date_location][dates][' . $i . '][local_id', (string) $this->t('O local da data @number não existe ou não é acessível.', ['@number' => $i + 1]));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    /** @var \Drupal\aculta_events\Entity\Event $entity */
    $entity = $this->entity;
    $status = $entity->save();

    if ($status === SAVED_NEW) {
      $this->messenger()->addStatus($this->t('O evento %label foi criado com sucesso.', [
        '%label' => $entity->label(),
      ]));

      $dates = (array) $form_state->getValue(['date_location', 'dates'], []);
      foreach ($dates as $index => $row) {
        $start = $this->utcValue($row['starts_at'] ?? NULL);
        $end = $this->utcValue($row['ends_at'] ?? NULL);
        $rawLocal = $row['local_id'] ?? NULL;
        $localId = is_array($rawLocal) ? (int) ($rawLocal['target_id'] ?? 0) : (int) ($rawLocal ?? 0);

        if ($start === NULL) {
          continue;
        }
        if ($end === NULL || $end <= $start) {
          $end = (new \DateTimeImmutable($start))->modify('+2 hours')->format('Y-m-d\TH:i:s');
        }

        $dateData = [
          'title' => trim((string) ($row['title'] ?? '')) !== '' ? (string) $row['title'] : (string) $entity->label(),
          'field_evento' => (int) $entity->id(),
          'field_quando' => ['value' => $start, 'end_value' => $end],
          'field_peso' => (int) $index,
        ];
        if ($localId > 0) {
          $dateData['field_local'] = $localId;
        }
        try {
          EventDate::create($dateData)->save();
        }
        catch (\Exception $e) {
          $this->logger('aculta_events')->error('Falha ao gravar data inicial do evento @id: @msg', [
            '@id' => $entity->id(),
            '@msg' => $e->getMessage(),
          ]);
          $this->messenger()->addWarning($this->t('Houve uma falha ao cadastrar uma das datas iniciais. Você pode revisá-la ou adicioná-la a seguir.'));
        }
      }
    }
    else {
      $this->messenger()->addStatus($this->t('O evento %label foi atualizado com sucesso.', [
        '%label' => $entity->label(),
      ]));
    }

    if (EventRouteContext::route('aculta_events.catalogue', 'aculta_events.organizer_event_edit') === 'aculta_events.organizer_event_edit') {
      $form_state->setRedirect('aculta_events.organizer_event_edit', ['aculta_event' => $entity->id()]);
    }
    else {
      $slug = (string) $entity->get('field_slug')->value;
      if ($slug !== '') {
        $form_state->setRedirect('aculta_events.page', ['slug' => $slug]);
      }
      else {
        $form_state->setRedirect('aculta_events.catalogue');
      }
    }

    return $status;
  }

  /**
   * Normaliza um valor de data para o formato UTC da entidade.
   */
  private function utcValue(mixed $value): ?string {
    if ($value instanceof DrupalDateTime) {
      return $value->getPhpDateTime()
        ->setTimezone(new \DateTimeZone('UTC'))
        ->format('Y-m-d\TH:i:s');
    }
    if (!is_string($value) || $value === '') {
      return NULL;
    }
    try {
      return (new \DateTimeImmutable(
        $value,
        new \DateTimeZone(date_default_timezone_get()),
      ))
        ->setTimezone(new \DateTimeZone('UTC'))
        ->format('Y-m-d\TH:i:s');
    }
    catch (\Exception) {
      return NULL;
    }
  }

}
