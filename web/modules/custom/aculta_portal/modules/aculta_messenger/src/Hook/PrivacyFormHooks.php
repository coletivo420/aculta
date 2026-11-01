<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Hook;

use Drupal\aculta_messenger\Form\MessagePrivacyForm;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Adds the Messenger's messaging privacy to Minha conta > Configurações > Privacidade.
 *
 * The privacy form belongs to aculta_user, which must not know Messenger rules. The Messenger adds its own
 * section and its own submit callback, so the preference stays owned by the module that enforces it.
 */
final class PrivacyFormHooks {

  public function __construct(
    #[Autowire(service: 'aculta_messenger.message_privacy_form')]
    private readonly MessagePrivacyForm $privacy,
  ) {}

  #[Hook('form_alter')]
  public function formAlter(array &$form, FormStateInterface $formState, string $formId): void {
    if ($formId !== 'aculta_user_profile_visibility_form') {
      return;
    }

    $saved = $this->privacy->currentPreference();
    $form['messaging'] = [
      '#type' => 'fieldset',
      '#title' => new TranslatableMarkup('Mensagens privadas'),
      '#weight' => 10,
      'message_privacy' => [
        '#type' => 'radios',
        '#title' => new TranslatableMarkup('Quem pode iniciar uma conversa com você?'),
        '#options' => [
          'all' => new TranslatableMarkup('Todos os membros'),
          'friends' => new TranslatableMarkup('Somente amizades confirmadas'),
        ],
        '#default_value' => $saved,
        '#description' => new TranslatableMarkup('A preferência vale para novas conversas. Pessoas com conversa existente ainda podem responder. Administradores e editores de conteúdo podem iniciar conversas; seus bloqueios continuam valendo.'),
      ],
      'blocking' => [
        '#type' => 'item',
        '#title' => new TranslatableMarkup('Bloquear uma pessoa'),
        '#markup' => new TranslatableMarkup('O bloqueio é feito nas conversas. Ele impede novas interações com a pessoa bloqueada.'),
      ],
    ];
    $form['actions']['#weight'] = 100;
    $form['#submit'][] = 'aculta_messenger.message_privacy_form:submit';
  }

}
