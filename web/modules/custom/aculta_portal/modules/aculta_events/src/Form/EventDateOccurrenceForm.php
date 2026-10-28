<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Form;

use Drupal\aculta_calendar\Recurrence\RecurrenceExpander;
use Drupal\aculta_events\Entity\EventDate;
use Drupal\aculta_events\Entity\EventDateOverride;
use Drupal\aculta_events\Occurrence\EventDateOccurrences;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Cancela, reagenda ou restaura uma ocorrência de série (D-14, RECORRENCIA.md, M1).
 *
 * A chave da exceção é o início original da ocorrência, não o índice: mudar a regra
 * não muda a chave. Cancelar grava a exceção sem horário; reagendar grava o novo
 * início e fim; restaurar apaga a exceção e devolve o horário da regra.
 */
final class EventDateOccurrenceForm extends FormBase {

  /**
   * Construtor.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entities,
    private readonly EventDateOccurrences $ocorrencias,
    private readonly TimeInterface $tempo,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('aculta_events.occurrences'),
      $container->get('datetime.time'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_events_date_occurrence';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?EventDate $aculta_event_date = NULL, ?string $inicio = NULL): array {
    if (!$aculta_event_date instanceof EventDate || !ctype_digit((string) $inicio)) {
      throw new NotFoundHttpException();
    }

    $ocorrencia = $this->ocorrencia($aculta_event_date, (int) $inicio);
    if ($ocorrencia === NULL) {
      throw new NotFoundHttpException();
    }

    $form['data_id'] = ['#type' => 'hidden', '#value' => $aculta_event_date->id()];
    $form['inicio'] = ['#type' => 'hidden', '#value' => (int) $inicio];

    $form['resumo'] = [
      '#type' => 'item',
      '#title' => $this->t('Ocorrência'),
      '#markup' => $this->t('@inicio — @fim', [
        '@inicio' => $ocorrencia['inicio']->format('d/m/Y H:i'),
        '@fim' => $ocorrencia['fim']->format('d/m/Y H:i'),
      ]),
    ];

    $form['acao'] = [
      '#type' => 'radios',
      '#title' => $this->t('O que fazer com esta ocorrência'),
      '#options' => [
        'cancelar' => $this->t('Cancelar a ocorrência'),
        'reagendar' => $this->t('Reagendar a ocorrência'),
        'restaurar' => $this->t('Voltar ao horário da regra'),
      ],
      '#default_value' => $this->excecao($aculta_event_date, (int) $inicio) === NULL ? 'cancelar' : 'restaurar',
      '#required' => TRUE,
    ];

    $form['novo_inicio'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Novo início'),
      '#description' => $this->t('Formato dd/mm/aaaa hh:mm, horário de Brasília. Obrigatório só para reagendar.'),
      '#size' => 20,
      '#maxlength' => 16,
      '#states' => [
        'visible' => [':input[name="acao"]' => ['value' => 'reagendar']],
      ],
    ];

    $form['novo_fim'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Novo fim'),
      '#description' => $this->t('Formato dd/mm/aaaa hh:mm, horário de Brasília. Obrigatório só para reagendar.'),
      '#size' => 20,
      '#maxlength' => 16,
      '#states' => [
        'visible' => [':input[name="acao"]' => ['value' => 'reagendar']],
      ],
    ];

    $form['motivo'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Motivo (opcional)'),
      '#maxlength' => 255,
      '#description' => $this->t('Aparece junto da ocorrência cancelada.'),
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Salvar ocorrência'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $data = $this->entities->getStorage('aculta_event_date')->load((int) $form_state->getValue('data_id'));
    $inicio = (int) $form_state->getValue('inicio');
    if (!$data instanceof EventDate || $this->ocorrencia($data, $inicio) === NULL) {
      $form_state->setErrorByName('acao', $this->t('A ocorrência não existe mais nesta série.'));
      return;
    }

    if ($form_state->getValue('acao') !== 'reagendar') {
      return;
    }

    $novoInicio = $this->dataBr((string) $form_state->getValue('novo_inicio'));
    $novoFim = $this->dataBr((string) $form_state->getValue('novo_fim'));
    if ($novoInicio === NULL) {
      $form_state->setErrorByName('novo_inicio', $this->t('Informe o novo início em dd/mm/aaaa hh:mm.'));
    }
    if ($novoFim === NULL) {
      $form_state->setErrorByName('novo_fim', $this->t('Informe o novo fim em dd/mm/aaaa hh:mm.'));
    }
    if ($novoInicio !== NULL && $novoFim !== NULL && $novoFim->getTimestamp() <= $novoInicio->getTimestamp()) {
      $form_state->setErrorByName('novo_fim', $this->t('O fim precisa ser depois do início.'));
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

    $inicio = (int) $form_state->getValue('inicio');
    $acao = (string) $form_state->getValue('acao');
    $storage = $this->entities->getStorage('aculta_event_override');
    $existente = $this->excecao($data, $inicio);

    if ($acao === 'restaurar') {
      $existente?->delete();
      $this->messenger()->addStatus($this->t('A ocorrência voltou ao horário da regra.'));
    }
    else {
      $excecao = $existente ?? $storage->create([
        'field_data_pai' => ['target_id' => $data->id()],
        'field_inicio_original' => ['value' => $this->utc($inicio)],
      ]);

      if ($acao === 'cancelar') {
        $excecao->set('field_inicio', NULL);
        $excecao->set('field_fim', NULL);
      }
      else {
        $novoInicio = $this->dataBr((string) $form_state->getValue('novo_inicio'));
        $novoFim = $this->dataBr((string) $form_state->getValue('novo_fim'));
        if ($novoInicio === NULL || $novoFim === NULL) {
          $this->messenger()->addError($this->t('Horário novo inválido: nada foi salvo.'));
          return;
        }
        $excecao->set('field_inicio', ['value' => $this->utc($novoInicio->getTimestamp())]);
        $excecao->set('field_fim', ['value' => $this->utc($novoFim->getTimestamp())]);
      }

      $excecao->set('field_motivo', trim((string) $form_state->getValue('motivo')));
      $excecao->save();
      $this->messenger()->addStatus($acao === 'cancelar'
        ? $this->t('Ocorrência cancelada.')
        : $this->t('Ocorrência reagendada.'));
    }

    $evento = $data->get('field_evento')->entity;
    $slug = $evento?->get('field_slug')->value;
    $form_state->setRedirectUrl($slug
      ? Url::fromRoute('aculta_events.page', ['slug' => (string) $slug])
      : Url::fromRoute('aculta_events.catalogue'));
  }

  /**
   * Ocorrência da data com este início original, ou NULL se não existir na série.
   *
   * @return array{inicio: \DateTimeImmutable, fim: \DateTimeImmutable, original: \DateTimeImmutable, cancelada: bool}|null
   *   Ocorrência expandida, ou NULL.
   */
  private function ocorrencia(EventDate $data, int $inicio): ?array {
    $fuso = new \DateTimeZone(RecurrenceExpander::FUSO);
    $momento = (new \DateTimeImmutable('@' . $inicio))->setTimezone($fuso);
    $agora = (new \DateTimeImmutable('@' . $this->tempo->getCurrentTime()))->setTimezone($fuso);

    foreach ($this->ocorrencias->expandir($data, $momento, $momento, $agora) as $ocorrencia) {
      if ($ocorrencia['original']->getTimestamp() === $inicio) {
        return $ocorrencia;
      }
    }
    return NULL;
  }

  /**
   * Exceção já gravada para o início original, ou NULL.
   */
  private function excecao(EventDate $data, int $inicio): ?EventDateOverride {
    $storage = $this->entities->getStorage('aculta_event_override');
    $ids = $storage->getQuery()
      ->condition('field_data_pai', $data->id())
      ->condition('field_inicio_original', $this->utc($inicio))
      ->accessCheck(FALSE)
      ->range(0, 1)
      ->execute();
    $excecao = $ids ? $storage->load(reset($ids)) : NULL;
    return $excecao instanceof EventDateOverride ? $excecao : NULL;
  }

  /**
   * Data e hora de Brasília em dd/mm/aaaa hh:mm; NULL quando inválida.
   */
  private function dataBr(string $valor): ?\DateTimeImmutable {
    $valor = trim($valor);
    $data = \DateTimeImmutable::createFromFormat('!d/m/Y H:i', $valor, new \DateTimeZone(RecurrenceExpander::FUSO));
    if ($data === FALSE || $data->format('d/m/Y H:i') !== $valor) {
      return NULL;
    }
    return $data;
  }

  /**
   * Timestamp em UTC no formato do campo datetime.
   */
  private function utc(int $timestamp): string {
    return gmdate('Y-m-d\TH:i:s', $timestamp);
  }

}
