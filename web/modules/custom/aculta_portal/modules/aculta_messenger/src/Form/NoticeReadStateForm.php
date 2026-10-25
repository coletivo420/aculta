<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Form;

use Drupal\aculta_messenger\Service\NoticeListFilter;
use Drupal\aculta_messenger\Service\NoticeReadStateManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides a CSRF-protected read-state control for a single personal notice.
 */
final class NoticeReadStateForm extends FormBase {

  public function __construct(
    private readonly NoticeReadStateManagerInterface $readState,
    private readonly AccountInterface $currentUser,
    private readonly NoticeListFilter $filter,
    private readonly RequestStack $noticeRequestStack,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $form = new static(
      $container->get('aculta_messenger.notice_read_state'),
      $container->get('current_user'),
      $container->get('aculta_messenger.notice_list_filter'),
      $container->get('request_stack'),
    );
    $form->setStringTranslation($container->get('string_translation'));
    $form->setMessenger($container->get('messenger'));
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_messenger_notice_read_state';
  }

  /**
   * Builds one action using the state already resolved for the account list.
   */
  public function buildForm(array $form, FormStateInterface $form_state, int $messageId = 0, bool $targetRead = TRUE): array {
    $form['#attributes']['data-portal-ui'] = 'notice-read-state-form';
    $form['message_id'] = [
      '#type' => 'hidden',
      '#value' => $messageId,
    ];
    $form['target_read'] = [
      '#type' => 'hidden',
      '#value' => $targetRead ? 1 : 0,
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $targetRead ? $this->t('Marcar como lido') : $this->t('Marcar como não lido'),
      '#attributes' => [
        'data-portal-ui' => 'notice-read-state-action',
      ],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $messageId = filter_var($form_state->getValue('message_id'), FILTER_VALIDATE_INT);
    $targetRead = filter_var($form_state->getValue('target_read'), FILTER_VALIDATE_INT);
    if ($messageId === FALSE || $messageId < 1 || !in_array($targetRead, [0, 1], TRUE)
      || !$this->readState->setRead($messageId, $this->currentUser, (bool) $targetRead)) {
      $form_state->setErrorByName('message_id', $this->t('Este aviso não está mais disponível.'));
      return;
    }

    $this->messenger()->addStatus($targetRead ? $this->t('Aviso marcado como lido.') : $this->t('Aviso marcado como não lido.'));
    $request = $this->noticeRequestStack->getCurrentRequest();
    $filters = $this->filter->normalize($request?->query->all() ?? []);
    $query = array_filter($filters, static fn(string $value): bool => $value !== '');
    if ($request !== NULL) {
      $requestQuery = $request->query->all();
      $pagination = match ($filters['origem']) {
        'forum' => ['forum_offset' => $requestQuery['forum_offset'] ?? NULL],
        'comunicado', 'alerta', 'aprendizagem', 'wiki', 'loja', 'doacao' => [
          'notice_offset' => $requestQuery['notice_offset'] ?? NULL,
        ],
        default => [],
      };
      foreach ($pagination as $key => $value) {
        $offset = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if ($offset !== FALSE) {
          $query[$key] = $offset;
        }
      }
    }
    $form_state->setRedirect('aculta_messenger.avisos', ['aba' => 'avisos'], ['query' => $query]);
  }

}
