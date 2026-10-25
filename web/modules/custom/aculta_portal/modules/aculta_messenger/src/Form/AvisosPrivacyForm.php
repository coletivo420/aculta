<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\aculta_messenger\Aviso\AvisoPreferencias;
use Drupal\aculta_messenger\Entity\Aviso;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\flag\FlagServiceInterface;
use Drupal\flag\FlaggingInterface;
use Drupal\flag\FlagInterface;
use Drupal\node\NodeInterface;

/**
 * Bloco "Avisos" da página de privacidade da conta (ACULTA Messenger, F5.2).
 *
 * Os avisos no site são obrigatórios. O bloco controla e-mail direto e resumo.
 */
final class AvisosPrivacyForm {

  public function __construct(
    private readonly AccountProxyInterface $currentUser,
    private readonly AvisoPreferencias $preferencias,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly FlagServiceInterface $flagService,
    private readonly DomainPurposeManager $domainPurposeManager,
    private readonly MessengerInterface $messenger,
  ) {}

  /**
   * Monta o bloco dentro do formulário de privacidade.
   */
  public function build(array &$form): void {
    $uid = (int) $this->currentUser->id();
    $form['avisos'] = [
      '#type' => 'fieldset',
      '#title' => new TranslatableMarkup('Avisos'),
      '#weight' => 11,
      'aviso_sempre' => [
        '#type' => 'item',
        '#markup' => new TranslatableMarkup('Avisos no site são sempre exibidos: respostas em tópicos que você segue e atualizações desses tópicos.'),
      ],
      'assinaturas' => $this->subscriptionsBuild(),
      'resumo_email' => [
        '#type' => 'checkbox',
        '#title' => new TranslatableMarkup('Receber resumo diário por e-mail'),
        '#description' => new TranslatableMarkup('Um e-mail por dia, somente quando houve novidade. Desligado por padrão. Este opt-in controla resumos; o envio direto é configurado separadamente por categoria.'),
        '#default_value' => $this->preferencias->resumoEmailLigado($uid),
      ],
      'email_respostas' => [
        '#type' => 'select',
        '#title' => new TranslatableMarkup('E-mails de respostas em tópicos seguidos'),
        '#description' => new TranslatableMarkup('O aviso no site permanece ativo. O resumo diário também exige o opt-in geral acima; o envio direto não depende dele. O administrador pode limitar os modos disponíveis.'),
        '#options' => $this->emailModeOptions(Aviso::TIPO_RESPOSTA),
        '#default_value' => $this->preferencias->emailMode($uid, Aviso::TIPO_RESPOSTA),
      ],
      'email_atualizacoes' => [
        '#type' => 'select',
        '#title' => new TranslatableMarkup('E-mails de atualizações de tópicos'),
        '#description' => new TranslatableMarkup('O aviso no site permanece ativo. O resumo diário também exige o opt-in geral acima; o envio direto não depende dele. O administrador pode limitar os modos disponíveis.'),
        '#options' => $this->emailModeOptions(Aviso::TIPO_ATUALIZACAO),
        '#default_value' => $this->preferencias->emailMode($uid, Aviso::TIPO_ATUALIZACAO),
      ],
      'email_aprendizagem' => [
        '#type' => 'select',
        '#title' => new TranslatableMarkup('E-mails de aprendizagem'),
        '#description' => new TranslatableMarkup('O aviso no site permanece ativo. O resumo diário também exige o opt-in geral acima; o envio direto não depende dele. O administrador pode limitar os modos disponíveis.'),
        '#options' => $this->emailModeOptions('aprendizagem'),
        '#default_value' => $this->preferencias->emailMode($uid, 'aprendizagem'),
      ],
      'email_wiki' => [
        '#type' => 'select',
        '#title' => new TranslatableMarkup('E-mails da Wiki'),
        '#description' => new TranslatableMarkup('O aviso no site permanece ativo. O resumo diário também exige o opt-in geral acima; o envio direto não depende dele. O administrador pode limitar os modos disponíveis.'),
        '#options' => $this->emailModeOptions('wiki'),
        '#default_value' => $this->preferencias->emailMode($uid, 'wiki'),
      ],
      'email_loja' => [
        '#type' => 'select',
        '#title' => new TranslatableMarkup('E-mails da Loja'),
        '#description' => new TranslatableMarkup('O aviso no site permanece ativo. O resumo diário também exige o opt-in geral acima; o envio direto não depende dele. O administrador pode limitar os modos disponíveis.'),
        '#options' => $this->emailModeOptions('loja'),
        '#default_value' => $this->preferencias->emailMode($uid, 'loja'),
      ],
      'email_doacao' => [
        '#type' => 'select',
        '#title' => new TranslatableMarkup('E-mails de doações'),
        '#description' => new TranslatableMarkup('O aviso no site permanece ativo. Inclui criação do pedido e aprovação de cada pagamento. O resumo diário também exige o opt-in geral acima; o envio direto não depende dele. O administrador pode limitar os modos disponíveis.'),
        '#options' => $this->emailModeOptions('doacao'),
        '#default_value' => $this->preferencias->emailMode($uid, 'doacao'),
      ],
      'email_amizade' => [
        '#type' => 'select',
        '#title' => new TranslatableMarkup('E-mails de amizade'),
        '#description' => new TranslatableMarkup('Pedidos recebidos e aceites continuam visíveis no site. O e-mail direto pode ser desativado por você ou pela administração.'),
        '#options' => $this->emailModeOptions(AvisoPreferencias::EMAIL_FRIENDSHIP),
        '#default_value' => $this->preferencias->emailMode($uid, AvisoPreferencias::EMAIL_FRIENDSHIP),
      ],
    ];
  }

  /**
   * Grava as preferências individuais sem alterar os avisos no site.
   */
  public function submit(array &$form, FormStateInterface $formState): void {
    $this->removeSelectedSubscriptions($formState);
    $this->preferencias->salvar(
      (int) $this->currentUser->id(),
      (bool) $formState->getValue('resumo_email'),
      $formState->getValue('email_respostas') === AvisoPreferencias::EMAIL_DIGEST,
      $formState->getValue('email_atualizacoes') === AvisoPreferencias::EMAIL_DIGEST,
    );
    foreach ([Aviso::TIPO_RESPOSTA, Aviso::TIPO_ATUALIZACAO] as $category) {
      $mode = $formState->getValue('email_' . ($category === Aviso::TIPO_RESPOSTA ? 'respostas' : 'atualizacoes'));
      if (is_string($mode)) {
        $this->preferencias->saveEmailMode((int) $this->currentUser->id(), $category, $mode);
      }
    }
    $mode = $formState->getValue('email_aprendizagem');
    if (is_string($mode)) {
      $this->preferencias->saveEmailMode((int) $this->currentUser->id(), 'aprendizagem', $mode);
    }
    foreach (['wiki', 'loja', 'doacao'] as $category) {
      $mode = $formState->getValue('email_' . $category);
      if (is_string($mode)) {
        $this->preferencias->saveEmailMode((int) $this->currentUser->id(), $category, $mode);
      }
    }
    $mode = $formState->getValue('email_amizade');
    if (is_string($mode)) {
      $this->preferencias->saveEmailMode((int) $this->currentUser->id(), AvisoPreferencias::EMAIL_FRIENDSHIP, $mode);
    }
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
   * Returns the available e-mail modes shared across notice categories. */
  private function emailModeOptions(string $category): array {
    $labels = [
      AvisoPreferencias::EMAIL_OFF => new TranslatableMarkup('Sem e-mail'),
      AvisoPreferencias::EMAIL_DIGEST => new TranslatableMarkup('Resumo diário'),
      AvisoPreferencias::EMAIL_IMMEDIATE => new TranslatableMarkup('Envio direto'),
    ];
    return array_intersect_key($labels, array_flip($this->preferencias->allowedEmailModes($category)));
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
