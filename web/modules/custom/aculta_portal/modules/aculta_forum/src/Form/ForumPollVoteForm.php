<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Form;

use Drupal\aculta_forum\Poll\ForumPollManager;
use Drupal\aculta_forum\Poll\PollResults;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Votação e resultados de uma enquete nativa do Fórum, dentro da página do tópico.
 *
 * Sem JavaScript: o envio é um POST comum à própria página. Voto único lógico por UID
 * é garantido pelo ForumPollManager (lock e transação). Resultados aparecem conforme
 * a política de visibilidade da enquete.
 */
final class ForumPollVoteForm extends FormBase {

  public function __construct(
    protected readonly ForumPollManager $pollManager,
    protected readonly AccountInterface $account,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('aculta_forum.poll_manager'),
      $container->get('current_user'),
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

    $rows = [];
    foreach ($results->options as $option) {
      $rows[] = [
        $option['label'],
        (string) $option['votes'],
        $this->t('@pct%', ['@pct' => (string) $option['percentage']]),
      ];
    }
    $form['poll']['results'] = [
      '#type' => 'table',
      '#header' => [$this->t('Opção'), $this->t('Votos'), $this->t('Percentual')],
      '#rows' => $rows,
      '#caption' => $this->t('@count voto(s) de @voters pessoa(s)', [
        '@count' => (string) $results->totalVotes,
        '@voters' => (string) $results->totalVoters,
      ]),
    ];
    if ($results->status !== 'open') {
      $form['poll']['status'] = [
        '#markup' => '<p>' . $this->t('Enquete encerrada.') . '</p>',
      ];
    }
    return $form;
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
    $selection = $form_state->getValue('selection');
    $candidates = is_array($selection) ? $selection : [$selection];
    $hasChoice = FALSE;
    foreach ($candidates as $candidate) {
      if (is_numeric($candidate) && (int) $candidate > 0) {
        $hasChoice = TRUE;
      }
    }
    if (!$hasChoice) {
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
    $selection = $form_state->getValue('selection');
    $candidates = is_array($selection) ? $selection : [$selection];
    $optionIds = [];
    foreach ($candidates as $candidate) {
      if (is_numeric($candidate) && (int) $candidate > 0) {
        $optionIds[] = (int) $candidate;
      }
    }

    try {
      $this->pollManager->castVote($pollId, $this->account, $optionIds);
      $this->messenger()->addStatus($this->t('Voto registrado.'));
    }
    catch (\LogicException | \InvalidArgumentException $exception) {
      // A mensagem técnica do domínio fica no log; ao usuário vai um texto genérico em português.
      $this->messenger()->addError($this->t('Não foi possível registrar o voto. A enquete pode estar encerrada ou você já votou.'));
    }
    catch (\RuntimeException) {
      $this->messenger()->addError($this->t('Há outro voto em andamento. Tente novamente.'));
    }
  }

}
