<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Form;

use Drupal\aculta_events\Entity\Event;
use Drupal\aculta_events\Entity\EventDate;
use Drupal\aculta_events\Entity\EventLocation;
use Drupal\aculta_events\Plugin\Field\FieldWidget\EventLocationModalWidget;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Formulário nativo para criação e edição de eventos com suas datas e locais.
 *
 * O Evento é a unidade editorial. Na criação, uma ou mais Datas são
 * configuradas no mesmo fluxo; na edição, as Datas do Evento são listadas e
 * administradas por popups Drupal/AJAX, sem formulário avulso de Data.
 */
final class EventForm extends ContentEntityForm {

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
      $form['event_dates'] = $this->buildEventDatesSection($entity);
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
      '#attributes' => ['class' => ['aculta-event-date-row']],
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
      '#attributes' => ['id' => $wrapperId, 'class' => ['aculta-event-location-widget']],
      '#attached' => ['library' => ['core/drupal.dialog.ajax']],
    ] + EventLocationModalWidget::buildCardOrEmpty($localId, 'local_id', $wrapperId);

    return $element;
  }

  /**
   * Lista as Datas do Evento em edição, com ações dentro do contexto.
   */
  private function buildEventDatesSection(Event $event): array {
    $section = [
      '#type' => 'details',
      '#title' => $this->t('Datas e locais deste evento'),
      '#open' => TRUE,
      '#weight' => 22,
      '#attached' => ['library' => ['core/drupal.dialog.ajax']],
    ];

    $storage = $this->entityTypeManager->getStorage('aculta_event_date');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('field_evento', (int) $event->id())
      ->execute();
    /** @var \Drupal\aculta_events\Entity\EventDate[] $dates */
    $dates = $storage->loadMultiple($ids);
    usort($dates, static function (EventDate $a, EventDate $b): int {
      return (string) $a->get('field_quando')->value <=> (string) $b->get('field_quando')->value;
    });

    $rows = [];
    foreach ($dates as $date) {
      $quando = $date->get('field_quando')->first();
      $local = $date->get('field_local')->entity;

      $actions = [
        '#type' => 'container',
        '#attributes' => ['class' => ['aculta-event-date-actions']],
        'edit' => [
          '#type' => 'link',
          '#title' => $this->t('Editar'),
          '#url' => Url::fromRoute('aculta_events.event_date_edit', [
            'aculta_event' => $event->id(),
            'aculta_event_date' => $date->id(),
          ]),
          '#attributes' => $this->dialogAttributes(),
        ],
        'delete' => [
          '#type' => 'link',
          '#title' => $this->t('Remover'),
          '#url' => Url::fromRoute('aculta_events.event_date_delete', [
            'aculta_event' => $event->id(),
            'aculta_event_date' => $date->id(),
          ]),
          '#attributes' => $this->dialogAttributes(),
        ],
      ];

      $rows[] = [
        (string) $date->label(),
        $this->formatDate($quando?->value),
        $this->formatDate($quando?->end_value),
        $local instanceof EventLocation ? (string) $local->label() : '',
        ['data' => $actions],
      ];
    }

    $section['list'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Título'),
        $this->t('Início'),
        $this->t('Fim'),
        $this->t('Local'),
        $this->t('Ações'),
      ],
      '#rows' => $rows,
      '#empty' => $this->t('Este evento ainda não tem datas.'),
    ];
    $section['add'] = [
      '#type' => 'link',
      '#title' => $this->t('Adicionar data a este evento'),
      '#url' => Url::fromRoute('aculta_events.event_date_add', ['aculta_event' => $event->id()]),
      '#attributes' => [
        'class' => ['button', 'button--primary', 'use-ajax'],
        'data-dialog-type' => 'modal',
        'data-dialog-options' => json_encode(['width' => 720]),
      ],
    ];
    $section['#cache'] = [
      'tags' => ['aculta_event_date_list'],
      'contexts' => ['user.permissions'],
    ];

    return $section;
  }

  /**
   * Atributos de link que abrem o popup Drupal.
   */
  private function dialogAttributes(): array {
    return [
      'class' => ['use-ajax'],
      'data-dialog-type' => 'modal',
      'data-dialog-options' => json_encode(['width' => 720]),
    ];
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
      foreach ($dates as $row) {
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
        ];
        if ($localId > 0) {
          $dateData['field_local'] = $localId;
        }
        EventDate::create($dateData)->save();
      }
    }
    else {
      $this->messenger()->addStatus($this->t('O evento %label foi atualizado com sucesso.', [
        '%label' => $entity->label(),
      ]));
    }

    $slug = (string) $entity->get('field_slug')->value;
    if ($slug !== '') {
      $form_state->setRedirect('aculta_events.page', ['slug' => $slug]);
    }
    else {
      $form_state->setRedirect('aculta_events.catalogue');
    }

    return $status;
  }

  /**
   * Formata um valor UTC armazenado no fuso do site para exibição.
   */
  private function formatDate(?string $value): string {
    if ($value === NULL || $value === '') {
      return '';
    }
    try {
      return (new \DateTimeImmutable($value, new \DateTimeZone('UTC')))
        ->setTimezone(new \DateTimeZone(date_default_timezone_get()))
        ->format('d/m/Y H:i');
    }
    catch (\Exception) {
      return $value;
    }
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
