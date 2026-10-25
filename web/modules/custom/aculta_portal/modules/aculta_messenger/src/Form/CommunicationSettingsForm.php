<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Form;

use Drupal\aculta_messenger\Aviso\AvisoPreferencias;
use Drupal\aculta_portal\Appearance\SettingsTabs;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Minha conta > Configurações > entrega de e-mail das mensagens privadas. */
final class CommunicationSettingsForm extends FormBase {

  /**
   * Constructs the private message email preference form.
   */
  public function __construct(
    private readonly AccountProxyInterface $currentUser,
    private readonly AvisoPreferencias $preferences,
    private readonly SettingsTabs $settingsTabs,
  ) {}

  /**
   * Creates the form from the service container.
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('current_user'),
      $container->get('aculta_messenger.aviso_preferencias'),
      $container->get('aculta_portal.settings_tabs'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_messenger_communication_settings';
  }

  /**
   * Builds the member's private message email preferences.
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $uid = (int) $this->currentUser->id();
    $form['tabs'] = $this->settingsTabs->build('messaging');
    $form['intro'] = [
      '#plain_text' => $this->t('As mensagens privadas permanecem na caixa de conversas do site. Você pode acrescentar um aviso por e-mail; o conteúdo da conversa não é incluído.'),
    ];
    $form['receive_notification'] = [
      '#type' => 'select',
      '#title' => $this->t('Avisos por e-mail de mensagens privadas'),
      '#options' => $this->emailModeOptions(),
      '#default_value' => $this->preferences->emailMode($uid, AvisoPreferencias::EMAIL_PRIVATE_MESSAGES),
      '#description' => $this->t('A administração pode desativar o e-mail. As mensagens continuam disponíveis no site.'),
    ];
    $form['subscriptions'] = [
      '#type' => 'item',
      '#title' => $this->t('Assinaturas de tópicos'),
      '#plain_text' => $this->t('As assinaturas são escolhidas em cada tópico do fórum. Seguir um tópico não altera o acesso ao conteúdo.'),
    ];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Salvar preferências')];
    $form['#cache']['max-age'] = 0;
    return $form;
  }

  /**
   * Saves the selected private message email mode.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $mode = $form_state->getValue('receive_notification');
    if (is_string($mode)) {
      $this->preferences->saveEmailMode(
        (int) $this->currentUser->id(),
        AvisoPreferencias::EMAIL_PRIVATE_MESSAGES,
        $mode,
      );
    }
    $this->messenger()->addStatus($this->t('Preferências de mensagens salvas.'));
    $form_state->setRedirect('aculta_messenger.communication_settings');
  }

  /**
   * Provides site-only or site-plus-email choices allowed by policy.
   */
  private function emailModeOptions(): array {
    $labels = [
      AvisoPreferencias::EMAIL_OFF => $this->t('Somente no site'),
      AvisoPreferencias::EMAIL_IMMEDIATE => $this->t('No site e por e-mail'),
    ];
    return array_intersect_key(
      $labels,
      array_flip($this->preferences->allowedEmailModes(AvisoPreferencias::EMAIL_PRIVATE_MESSAGES)),
    );
  }

}
