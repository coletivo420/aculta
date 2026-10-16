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
 * Os avisos no site são obrigatórios. O bloco só controla o resumo por e-mail: a chave geral e cada tipo.
 */
final class AvisosPrivacyForm {

  public function __construct(
    private readonly AccountProxyInterface $currentUser,
    private readonly AvisoPreferencias $preferencias,
  ) {}

  /** Monta o bloco dentro do formulário de privacidade. */
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
        '#type' => 'checkbox',
        '#title' => new TranslatableMarkup('Incluir respostas novas no resumo'),
        '#default_value' => $this->preferencias->tipoNoResumo($uid, Aviso::TIPO_RESPOSTA),
      ],
      'email_atualizacoes' => [
        '#type' => 'checkbox',
        '#title' => new TranslatableMarkup('Incluir atualizações de tópicos no resumo'),
        '#default_value' => $this->preferencias->tipoNoResumo($uid, Aviso::TIPO_ATUALIZACAO),
      ],
    ];
  }

  /** Submit do bloco: grava as preferências individuais. Não altera os avisos no site. */
  public function submit(array &$form, FormStateInterface $formState): void {
    $this->preferencias->salvar(
      (int) $this->currentUser->id(),
      (bool) $formState->getValue('resumo_email'),
      (bool) $formState->getValue('email_respostas'),
      (bool) $formState->getValue('email_atualizacoes'),
    );
  }

}
