<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Form;

use Drupal\aculta_forum\Poll\ForumPollManager;
use Drupal\aculta_forum\Poll\PollResults;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Votação, resultados e encerramento de uma enquete nativa do Fórum, na página do tópico.
 *
 * Sem JavaScript: o envio é um POST comum à própria página. O voto único lógico por UID
 * é garantido pelo ForumPollManager (lock e transação). Quem pode encerrar é o autor do
 * tópico ou quem tem 'close aculta forum polls'; a regra é conferida de novo no envio.
 */
final class ForumPollVoteForm extends FormBase {

  public function __construct(
    protected readonly ForumPollManager $pollManager,
    protected readonly AccountInterface $account,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly DateFormatterInterface $dateFormatter,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('aculta_forum.poll_manager'),
      $container->get('current_user'),
      $container->get('entity_type.manager'),
      $container->get('date.formatter'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_forum_poll_vote';
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *   Render array do formulário.
   * @param FormStateInterface $form_state
   *   Estado atual do formulário.
   * @param int|null $pollId
   *   ID da enquete a exibir.
   *
   * @return array<string, mixed>
   *   Render array do formulário.
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?int $pollId = NULL): array {
    $form['#cache'] = ['max-age' => 0];
    if ($pollId === NULL) {
      return $form;
    }
    $results = $this->pollManager->getResults($pollId, $this->account);
    if (!$results instanceof PollResults) {
      return $form;
    }

    $form_state->set('poll_id', $pollId);
    $form['poll'] = [
      '#type' => 'fieldset',
      '#title' => $results->title,
      '#attributes' => ['class' => ['aculta-forum-poll']],
    ];

    if ($results->status === 'open' && $results->closeAt !== NULL) {
      $form['poll']['closes'] = [
        '#markup' => '<p>' . $this->t('Encerra em @date.', [
          '@date' => $this->dateFormatter->format($results->closeAt, 'short'),
        ]) . '</p>',
      ];
    }

    if ($this->canManage($pollId) && $results->status === 'open') {
      $form['poll']['close'] = [
        '#type' => 'submit',
        '#value' => $this->t('Encerrar enquete'),
        '#name' => 'close_poll',
        // Encerrar não escolhe opção: pula a validação da seleção.
        '#limit_validation_errors' => [],
        '#submit' => [[$this, 'closePoll']],
        '#weight' => 10,
      ];
    }
    if ($this->canManage($pollId) && $results->status === 'closed') {
      $form['poll']['reopen'] = [
        '#type' => 'submit',
        '#value' => $this->t('Reabrir enquete'),
        '#name' => 'reopen_poll',
        // Reabrir não escolhe opção: pula a validação da seleção.
        '#limit_validation_errors' => [],
        '#submit' => [[$this, 'reopenPoll']],
        '#weight' => 10,
      ];
    }

    if ($results->canVote) {
      $options = [];
      foreach ($results->options as $option) {
        $options[$option['id']] = $option['label'];
      }
      $isMultiple = $results->pollType === 'multiple';
      $form['poll']['selection'] = [
        '#type' => $isMultiple ? 'checkboxes' : 'radios',
        '#title' => $this->t('Escolha'),
        '#title_display' => 'invisible',
        '#options' => $options,
        '#required' => !$isMultiple,
      ];
      $form['poll']['actions'] = [
        '#type' => 'actions',
        'submit' => [
          '#type' => 'submit',
          '#value' => $this->t('Votar'),
          '#button_type' => 'primary',
        ],
      ];
      return $form;
    }

    if (!$results->resultsVisible) {
      $form['poll']['hidden'] = [
        '#markup' => '<p>' . $this->t('Os resultados serão exibidos após o voto ou o encerramento da enquete.') . '</p>',
      ];
      return $form;
    }

    // Results are only rendered after PollResults has allowed disclosure for
    // this viewer. The SDC does not fetch votes or decide visibility.
    $rows = [];
    foreach ($results->options as $option) {
      $rows[] = [
        'label' => (string) $option['label'],
        'votes' => (int) $option['votes'],
        'percentage' => max(0.0, min(100.0, (float) $option['percentage'])),
      ];
    }
    $form['poll']['results'] = [
      '#theme' => 'portal_component',
      '#name' => 'forum-poll-results',
      '#props' => [
        'title' => (string) $this->t('Resultados da enquete'),
        'voters' => $results->totalVoters,
        'selections' => $results->totalVotes,
        'multiple' => $results->pollType === 'multiple',
        'options' => $rows,
      ],
    ];
    if ($results->status !== 'open') {
      $form['poll']['status'] = [
        '#markup' => '<p>' . $this->t('Enquete encerrada.') . '</p>',
      ];
    }
    return $form;
  }

  /**
   * Informa se a conta pode encerrar a enquete: autor do tópico ou com permissão, enquete aberta.
   */
  private function canManage(int $pollId): bool {
    $poll = $this->pollManager->getPoll($pollId);
    if ($poll === NULL || (int) $this->account->id() <= 0) {
      return FALSE;
    }
    if ($this->account->hasPermission('close aculta forum polls')) {
      return TRUE;
    }
    $nid = $poll['nid'];
    if (!is_numeric($nid)) {
      return FALSE;
    }
    $topic = $this->entityTypeManager->getStorage('node')->load((int) $nid);
    return $topic instanceof NodeInterface
      && (string) $topic->getOwnerId() === (string) $this->account->id();
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *   Render array do formulário.
   * @param FormStateInterface $form_state
   *   Estado atual do formulário.
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    if ($this->selectedOptionIds($form_state) === []) {
      $form_state->setErrorByName('selection', $this->t('Escolha pelo menos uma opção.'));
    }
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *   Render array do formulário.
   * @param FormStateInterface $form_state
   *   Estado atual do formulário.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $pollId = $form_state->get('poll_id');
    if (!is_int($pollId)) {
      $this->messenger()->addError($this->t('Enquete não encontrada.'));
      return;
    }
    try {
      $this->pollManager->castVote($pollId, $this->account, $this->selectedOptionIds($form_state));
      $this->messenger()->addStatus($this->t('Voto registrado.'));
    }
    catch (\LogicException | \InvalidArgumentException) {
      // O motivo técnico fica no log do ForumPollManager; ao usuário vai um texto genérico.
      $this->messenger()->addError($this->t('Não foi possível registrar o voto. A enquete pode estar encerrada ou você já votou.'));
    }
    catch (\RuntimeException) {
      $this->messenger()->addError($this->t('Há outro voto em andamento. Tente novamente.'));
    }
  }

  /**
   * Encerra a enquete a pedido do autor do tópico ou de quem tem permissão.
   *
   * @param array<string, mixed> $form
   *   Render array do formulário.
   * @param FormStateInterface $form_state
   *   Estado atual do formulário.
   */
  public function closePoll(array &$form, FormStateInterface $form_state): void {
    $pollId = $form_state->get('poll_id');
    if (!is_int($pollId) || !$this->canManage($pollId)) {
      $this->messenger()->addError($this->t('Você não pode encerrar esta enquete.'));
      return;
    }
    $this->pollManager->closePoll($pollId, $this->account);
    $this->messenger()->addStatus($this->t('Enquete encerrada.'));
  }

  /**
   * Reabre a enquete encerrada manualmente, com a mesma autorização de quem encerra.
   *
   * @param array<string, mixed> $form
   *   Render array do formulário.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Estado do formulário.
   */
  public function reopenPoll(array &$form, FormStateInterface $form_state): void {
    $pollId = $form_state->get('poll_id');
    if (!is_int($pollId) || !$this->canManage($pollId)) {
      $this->messenger()->addError($this->t('Você não pode reabrir esta enquete.'));
      return;
    }
    try {
      $this->pollManager->reopenPoll($pollId, $this->account);
      $this->messenger()->addStatus($this->t('Enquete reaberta.'));
    }
    catch (\LogicException) {
      // Enquete vencida pela data não reabre: o motivo técnico fica no log do gerenciador.
      $this->messenger()->addError($this->t('Esta enquete não pode ser reaberta: a data de encerramento já passou.'));
    }
  }

  /**
   * Converte a seleção do formulário (radios ou checkboxes) em IDs de opção válidos.
   *
   * @return list<int>
   *   IDs de opção válidos escolhidos no formulário.
   */
  private function selectedOptionIds(FormStateInterface $form_state): array {
    $selection = $form_state->getValue('selection');
    $candidates = is_array($selection) ? $selection : [$selection];
    $ids = [];
    foreach ($candidates as $candidate) {
      if (is_numeric($candidate) && (int) $candidate > 0) {
        $ids[] = (int) $candidate;
      }
    }
    return $ids;
  }

}
