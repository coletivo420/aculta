<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\aculta_messenger\Aviso\AvisoPreferencias;
use Drupal\aculta_messenger\Entity\Aviso;

/**
 * Bloco "Avisos" da página de privacidade da conta (ACULTA Messenger, F5.2).
 *
 * Os avisos no site são obrigatórios. O bloco controla e-mail direto e resumo.
 */
final class AvisosPrivacyForm {

  public function __construct(
    private readonly AccountProxyInterface $currentUser,
    private readonly AvisoPreferencias $preferencias,
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
      'resumo_email' => [
        '#type' => 'checkbox',
        '#title' => new TranslatableMarkup('Receber resumo diário por e-mail'),
        '#description' => new TranslatableMarkup('Um e-mail por dia, somente quando houve novidade, agrupado por tópico. Desligado por padrão.'),
        '#default_value' => $this->preferencias->resumoEmailLigado($uid),
      ],
      'email_respostas' => [
        '#type' => 'select',
        '#title' => new TranslatableMarkup('E-mails de respostas em tópicos seguidos'),
        '#description' => new TranslatableMarkup('O aviso no site permanece ativo. Escolha sem e-mail, resumo diário ou envio direto.'),
        '#options' => $this->emailModeOptions(),
        '#default_value' => $this->preferencias->emailMode($uid, Aviso::TIPO_RESPOSTA),
      ],
      'email_atualizacoes' => [
        '#type' => 'select',
        '#title' => new TranslatableMarkup('E-mails de atualizações de tópicos'),
        '#description' => new TranslatableMarkup('O aviso no site permanece ativo. Escolha sem e-mail, resumo diário ou envio direto.'),
        '#options' => $this->emailModeOptions(),
        '#default_value' => $this->preferencias->emailMode($uid, Aviso::TIPO_ATUALIZACAO),
      ],
      'email_aprendizagem' => [
        '#type' => 'select',
        '#title' => new TranslatableMarkup('E-mails de aprendizagem'),
        '#description' => new TranslatableMarkup('Avisos no site continuam ativos. Escolha sem e-mail, resumo diário ou envio direto. O resumo também exige o opt-in geral do Messenger; administradores podem limitar tipos no ACULTA Mailer.'),
        '#options' => [
          AvisoPreferencias::EMAIL_OFF => new TranslatableMarkup('Sem e-mail'),
          AvisoPreferencias::EMAIL_DIGEST => new TranslatableMarkup('Resumo diário'),
          AvisoPreferencias::EMAIL_IMMEDIATE => new TranslatableMarkup('Envio direto'),
        ],
        '#default_value' => $this->preferencias->emailMode($uid, 'aprendizagem'),
      ],
      'email_wiki' => [
        '#type' => 'select',
        '#title' => new TranslatableMarkup('E-mails da Wiki'),
        '#description' => new TranslatableMarkup('Avisos no site continuam ativos. Escolha sem e-mail, resumo diário ou envio direto. O resumo também exige o opt-in geral do Messenger; administradores podem limitar tipos no ACULTA Mailer.'),
        '#options' => $this->emailModeOptions(),
        '#default_value' => $this->preferencias->emailMode($uid, 'wiki'),
      ],
      'email_loja' => [
        '#type' => 'select',
        '#title' => new TranslatableMarkup('E-mails da Loja'),
        '#description' => new TranslatableMarkup('Avisos no site continuam ativos. Escolha sem e-mail, resumo diário ou envio direto; administradores podem limitar cada tipo pelo ACULTA Mailer.'),
        '#options' => $this->emailModeOptions(),
        '#default_value' => $this->preferencias->emailMode($uid, 'loja'),
      ],
      'email_doacao' => [
        '#type' => 'select',
        '#title' => new TranslatableMarkup('E-mails de doações'),
        '#description' => new TranslatableMarkup('Avisos no site continuam ativos. Você pode receber aviso quando o pedido for criado e quando cada pagamento for aprovado; escolha sem e-mail, resumo diário ou envio direto. O resumo diário também exige o opt-in geral do Messenger; administradores podem limitar a entrega pelo ACULTA Mailer.'),
        '#options' => $this->emailModeOptions(),
        '#default_value' => $this->preferencias->emailMode($uid, 'doacao'),
      ],
    ];
  }

  /**
   * Grava as preferências individuais sem alterar os avisos no site.
   */
  public function submit(array &$form, FormStateInterface $formState): void {
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
  }

  /**
   * Returns the available e-mail modes shared across notice categories. */
  private function emailModeOptions(): array {
    return [
      AvisoPreferencias::EMAIL_OFF => new TranslatableMarkup('Sem e-mail'),
      AvisoPreferencias::EMAIL_DIGEST => new TranslatableMarkup('Resumo diário'),
      AvisoPreferencias::EMAIL_IMMEDIATE => new TranslatableMarkup('Envio direto'),
    ];
  }

}
