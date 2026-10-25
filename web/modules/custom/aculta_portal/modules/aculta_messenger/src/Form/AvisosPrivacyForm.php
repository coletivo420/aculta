<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\flag\FlagServiceInterface;
use Drupal\flag\FlaggingInterface;
use Drupal\flag\FlagInterface;
use Drupal\node\NodeInterface;

/**
 * Bloco "Avisos" da página de privacidade da conta (ACULTA Messenger, F5.2).
 *
 * Os avisos no site são obrigatórios. O bloco gerencia apenas acompanhamentos.
 */
final class AvisosPrivacyForm {

  public function __construct(
    private readonly AccountProxyInterface $currentUser,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly FlagServiceInterface $flagService,
    private readonly DomainPurposeManager $domainPurposeManager,
    private readonly MessengerInterface $messenger,
  ) {}

  /**
   * Monta o bloco dentro do formulário de privacidade.
   */
  public function build(array &$form): void {
    $form['avisos'] = [
      '#type' => 'fieldset',
      '#title' => new TranslatableMarkup('Avisos'),
      '#weight' => 11,
      'aviso_sempre' => [
        '#type' => 'item',
        '#markup' => new TranslatableMarkup('Avisos no site são sempre exibidos: respostas em tópicos que você segue e atualizações desses tópicos.'),
      ],
      'assinaturas' => $this->subscriptionsBuild(),
    ];
    $emailSettingsUrl = $this->domainPurposeManager->routeUrl('account', 'aculta_messenger.communication_settings');
    if ($emailSettingsUrl !== NULL) {
      $form['avisos']['email_settings'] = [
        '#type' => 'link',
        '#title' => new TranslatableMarkup('Gerenciar preferências de e-mail'),
        '#url' => $emailSettingsUrl,
      ];
    }
  }

  /**
   * Grava alterações de acompanhamentos sem mudar a entrega de avisos.
   */
  public function submit(array &$form, FormStateInterface $formState): void {
    $this->removeSelectedSubscriptions($formState);
  }

  /**
   * Processes followed-topic management without submitting profile settings.
   */
  public function removeSubscriptionsSubmit(array &$form, FormStateInterface $formState): void {
    $removed = $this->removeSelectedSubscriptions($formState);
    $this->messenger->addStatus($removed > 0
      ? new TranslatableMarkup('Os tópicos selecionados deixaram de ser seguidos.')
      : new TranslatableMarkup('Nenhum acompanhamento foi removido.'));
  }

  /**
   * Lists the followed topics without disclosing inaccessible content.
   */
  private function subscriptionsBuild(): array {
    $details = [
      '#type' => 'details',
      '#title' => new TranslatableMarkup('Tópicos que você segue'),
      '#open' => FALSE,
      '#tree' => TRUE,
      '#cache' => ['contexts' => ['user'], 'max-age' => 0],
      'description' => [
        '#type' => 'item',
        '#plain_text' => new TranslatableMarkup('Seguir um tópico controla os avisos; não altera o acesso ao conteúdo.'),
      ],
    ];
    $flag = $this->flagService->getFlagById('seguir_topico');
    if (!$flag instanceof FlagInterface) {
      $details['empty'] = ['#plain_text' => new TranslatableMarkup('Nenhum tópico seguido.')];
      return $details;
    }

    $flaggings = [];
    $topicIds = [];
    foreach ($this->flagService->getAllFlaggingByUser($this->currentUser) as $flagging) {
      if (!$flagging instanceof FlaggingInterface
        || $flagging->getFlagId() !== 'seguir_topico'
        || $flagging->getFlaggableType() !== 'node'
        || (int) $flagging->getOwnerId() !== (int) $this->currentUser->id()) {
        continue;
      }
      $topicId = (string) $flagging->getFlaggableId();
      if (!ctype_digit($topicId) || (int) $topicId < 1) {
        continue;
      }
      $flaggings[] = $flagging;
      $topicIds[] = $topicId;
    }

    $topics = $topicIds === []
      ? []
      : $this->entityTypeManager->getStorage('node')->loadMultiple(array_values(array_unique($topicIds)));
    $rows = [];
    foreach ($flaggings as $flagging) {
      $topic = $topics[$flagging->getFlaggableId()] ?? NULL;
      if (!$topic instanceof NodeInterface || $topic->bundle() !== 'aculta_forum_topic') {
        continue;
      }
      $access = $topic->access('view', $this->currentUser, TRUE);
      $title = $access->isAllowed()
        ? $topic->getTitle()
        : new TranslatableMarkup('Tópico indisponível');
      $rows[(string) $flagging->id()] = [
        'remove' => [
          '#type' => 'checkbox',
          '#title' => new TranslatableMarkup('Parar de seguir @topic', ['@topic' => $title]),
        ],
        'topic' => $access->isAllowed() && ($url = $this->domainPurposeManager->routeUrl('forum', 'entity.node.canonical', ['node' => $topic->id()])) !== NULL
          ? ['#type' => 'link', '#title' => $title, '#url' => $url]
          : ['#plain_text' => $title],
      ];
    }

    if ($rows === []) {
      $details['empty'] = ['#plain_text' => new TranslatableMarkup('Nenhum tópico seguido.')];
      return $details;
    }
    $details['topics'] = [
      '#type' => 'table',
      '#header' => [
        'remove' => new TranslatableMarkup('Acompanhamento'),
        'topic' => new TranslatableMarkup('Tópico'),
      ],
      '#rows' => $rows,
    ];
    $details['remove_selected'] = [
      '#type' => 'submit',
      '#value' => new TranslatableMarkup('Parar de seguir selecionados'),
      '#submit' => ['aculta_messenger.avisos_privacy_form:removeSubscriptionsSubmit'],
      '#limit_validation_errors' => [['assinaturas']],
    ];
    return $details;
  }

  /**
   * Removes only selected Flag records currently owned by this account.
   */
  private function removeSelectedSubscriptions(FormStateInterface $formState): int {
    $values = $formState->getValue(['assinaturas', 'topics'], []);
    if (!is_array($values)) {
      return 0;
    }
    $selected = array_fill_keys(array_map('strval', array_keys(array_filter(
      $values,
      static fn(mixed $row): bool => is_array($row) && !empty($row['remove']),
    ))), TRUE);
    if ($selected === []) {
      return 0;
    }
    $flag = $this->flagService->getFlagById('seguir_topico');
    if (!$flag instanceof FlagInterface) {
      return 0;
    }
    $flaggings = [];
    $topicIds = [];
    foreach ($this->flagService->getAllFlaggingByUser($this->currentUser) as $flagging) {
      if (!$flagging instanceof FlaggingInterface
        || !isset($selected[(string) $flagging->id()])
        || $flagging->getFlagId() !== 'seguir_topico'
        || $flagging->getFlaggableType() !== 'node'
        || (int) $flagging->getOwnerId() !== (int) $this->currentUser->id()) {
        continue;
      }
      $topicId = (string) $flagging->getFlaggableId();
      if (ctype_digit($topicId) && (int) $topicId > 0) {
        $flaggings[] = $flagging;
        $topicIds[] = $topicId;
      }
    }
    if ($topicIds === []) {
      return 0;
    }

    $topics = $this->entityTypeManager->getStorage('node')->loadMultiple(array_values(array_unique($topicIds)));
    $removed = 0;
    foreach ($flaggings as $flagging) {
      $topic = $topics[$flagging->getFlaggableId()] ?? NULL;
      if (!$topic instanceof NodeInterface || $topic->bundle() !== 'aculta_forum_topic') {
        continue;
      }
      try {
        $this->flagService->unflag($flag, $topic, $this->currentUser);
        $removed++;
      }
      catch (\LogicException) {
        // Another request may have removed this same personal flagging first.
      }
    }
    return $removed;
  }

}
