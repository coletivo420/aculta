<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Form;

use Drupal\aculta_portal\Appearance\SettingsTabs;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\user\UserDataInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Account preferences backed by the native Private Message user data. */
final class CommunicationSettingsForm extends FormBase {

  public function __construct(
    private readonly AccountProxyInterface $currentUser,
    private readonly UserDataInterface $userData,
    private readonly ConfigFactoryInterface $settingsFactory,
    private readonly SettingsTabs $settingsTabs,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('current_user'),
      $container->get('user.data'),
      $container->get('config.factory'),
      $container->get('aculta_portal.settings_tabs'),
    );
  }

  public function getFormId(): string {
    return 'aculta_messenger_communication_settings';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $uid = (int) $this->currentUser->id();
    $config = $this->settingsFactory->get('private_message.settings');
    $savedPreference = $this->userData->get('private_message', $uid, 'receive_notification');
    $default = $savedPreference === NULL
      ? (bool) $config->get('notify_by_default')
      : (bool) $savedPreference;

    $form['tabs'] = $this->settingsTabs->build('messaging');
    $form['intro'] = [
      '#plain_text' => $this->t('Controle os avisos por e-mail sobre novas mensagens privadas. O conteúdo das mensagens não é incluído no e-mail.'),
    ];
    $form['receive_notification'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Receber aviso por e-mail quando chegar uma mensagem privada'),
      '#default_value' => $default,
      '#disabled' => !(bool) $config->get('enable_notifications'),
      '#description' => $config->get('enable_notifications')
        ? $this->t('O envio usa a ferramenta de e-mail já instalada no site.')
        : $this->t('Os avisos por e-mail estão desativados pela configuração do site.'),
    ];
    $privacyPreference = $this->userData->get('aculta_messenger', $uid, 'message_privacy');
    $form['message_privacy'] = [
      '#type' => 'radios',
      '#title' => $this->t('Quem pode iniciar uma conversa com você?'),
      '#options' => [
        'all' => $this->t('Todos os membros'),
        'friends' => $this->t('Somente amizades confirmadas'),
      ],
      '#default_value' => $privacyPreference === 'friends' ? 'friends' : 'all',
      '#description' => $this->t('A preferência vale para novas conversas. Pessoas com conversa existente ainda podem responder. Administradores e editores de conteúdo podem iniciar conversas; seus bloqueios continuam valendo.'),
    ];
    $form['blocking'] = [
      '#type' => 'item',
      '#title' => $this->t('Bloquear uma pessoa'),
      '#plain_text' => $this->t('O bloqueio é gerenciado nas conversas pelo Private Message. Ele impede novas interações com a pessoa bloqueada.'),
    ];
    $form['subscriptions'] = [
      '#type' => 'item',
      '#title' => $this->t('Assinaturas'),
      '#plain_text' => $this->t('As assinaturas são escolhidas em cada tópico do fórum. Seguir um tópico não altera o acesso ao conteúdo.'),
    ];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Salvar preferências')];
    $form['#cache']['max-age'] = 0;
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    if (!$this->settingsFactory->get('private_message.settings')->get('enable_notifications')) {
      $this->messenger()->addWarning($this->t('Os avisos por e-mail estão desativados pela configuração do site.'));
    }
    else {
      $this->userData->set(
        'private_message',
        (int) $this->currentUser->id(),
        'receive_notification',
        (bool) $form_state->getValue('receive_notification'),
      );
    }
    $privacyPreference = $form_state->getValue('message_privacy');
    $this->userData->set(
      'aculta_messenger',
      (int) $this->currentUser->id(),
      'message_privacy',
      $privacyPreference === 'friends' ? 'friends' : 'all',
    );
    $this->messenger()->addStatus($this->t('Preferências de mensagens salvas.'));
    $form_state->setRedirect('aculta_messenger.communication_settings');
  }

}
