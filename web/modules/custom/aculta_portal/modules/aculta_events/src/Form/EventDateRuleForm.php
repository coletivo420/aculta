<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Form;

use Drupal\aculta_calendar\Recurrence\RecurrenceException;
use Drupal\aculta_calendar\Recurrence\RecurrenceRuleParser;
use Drupal\aculta_events\Entity\EventDate;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Edita a regra de recorrência de uma data (D-14, RECORRENCIA.md, seções 5 e 7).
 *
 * A regra é validada e gravada na forma canônica do RecurrenceRuleParser, para que
 * duas escritas da mesma regra não gerem valores diferentes no campo.
 */
final class EventDateRuleForm extends FormBase {

  /**
   * Construtor.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entities,
    private readonly RecurrenceRuleParser $parser,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      new RecurrenceRuleParser(),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_events_date_rule';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?EventDate $aculta_event_date = NULL): array {
    if (!$aculta_event_date instanceof EventDate) {
      throw new NotFoundHttpException();
    }

    $form['data_id'] = ['#type' => 'hidden', '#value' => $aculta_event_date->id()];

    $form['field_regra'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Regra de recorrência'),
      '#default_value' => (string) $aculta_event_date->get('field_regra')->value,
      '#maxlength' => 255,
      '#description' => $this->t('Vazio para data única. Exemplos: @diaria, @semanal, @mensal. FREQ aceita DAILY, WEEKLY ou MONTHLY; BYDAY só com WEEKLY; BYMONTHDAY só com MONTHLY; COUNT e UNTIL não podem vir juntos.', [
        '@diaria' => 'FREQ=DAILY;INTERVAL=3;COUNT=5',
        '@semanal' => 'FREQ=WEEKLY;BYDAY=MO,WE',
        '@mensal' => 'FREQ=MONTHLY;BYMONTHDAY=1',
      ]),
    ];

    $form['field_limite_meses'] = [
      '#type' => 'number',
      '#title' => $this->t('Horizonte da série aberta (meses)'),
      '#default_value' => (int) ($aculta_event_date->get('field_limite_meses')->value ?? 12),
      '#min' => 1,
      '#max' => 60,
      '#step' => 1,
      '#description' => $this->t('Vale só quando a regra não tem COUNT nem UNTIL.'),
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Salvar recorrência'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $regra = trim((string) $form_state->getValue('field_regra'));
    if ($regra === '') {
      return;
    }
    try {
      // Grava a forma canônica: mesma regra, mesma string no campo.
      $form_state->setValue('field_regra', $this->parser->normalize($regra));
    }
    catch (RecurrenceException $e) {
      $form_state->setErrorByName('field_regra', $this->t('Regra de recorrência inválida: @erro', ['@erro' => $e->getMessage()]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $data = $this->entities->getStorage('aculta_event_date')->load((int) $form_state->getValue('data_id'));
    if (!$data instanceof EventDate) {
      $this->messenger()->addError($this->t('A data de evento não existe mais.'));
      return;
    }

    $data->set('field_regra', trim((string) $form_state->getValue('field_regra')));
    $data->set('field_limite_meses', (int) $form_state->getValue('field_limite_meses'));
    $data->save();

    $this->messenger()->addStatus($this->t('Recorrência de @titulo salva.', ['@titulo' => $data->label()]));

    $evento = $data->get('field_evento')->entity;
    $slug = $evento?->get('field_slug')->value;
    if ($slug) {
      $form_state->setRedirect('aculta_events.page', ['slug' => (string) $slug]);
      return;
    }
    $form_state->setRedirect('aculta_events.catalogue');
  }

}
