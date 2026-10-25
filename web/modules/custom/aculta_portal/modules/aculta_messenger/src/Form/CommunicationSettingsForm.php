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
 * Minha conta > Configurações > preferências de e-mail.
 */
final class CommunicationSettingsForm extends FormBase {

  /**
   * Constructs the shared email preference form.
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
   * Builds the member's email preferences across notice origins.
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $uid = (int) $this->currentUser->id();
    $form['tabs'] = $this->settingsTabs->build('email');
    $form['intro'] = [
      '#plain_text' => $this->t('Avisos no site e mensagens na caixa de conversas continuam disponíveis. Escolha abaixo como receber cada tipo de aviso por e-mail. O conteúdo das mensagens privadas nunca é enviado por e-mail.'),
    ];
    $form['email_modes'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Entrega por e-mail'),
      '#tree' => TRUE,
    ];
    foreach (AvisoPreferencias::EMAIL_CATEGORIES as $category) {
      $form['email_modes'][$category] = [
        '#type' => 'select',
        '#title' => $this->emailCategoryTitle($category),
        '#options' => $this->emailModeOptions($category),
        '#default_value' => $this->preferences->emailMode($uid, $category),
        '#description' => $this->emailCategoryDescription($category),
      ];
    }
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Salvar preferências')];
    $form['#cache']['max-age'] = 0;
    return $form;
  }

  /**
   * Saves the selected email mode for every notice category.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $uid = (int) $this->currentUser->id();
    foreach (AvisoPreferencias::EMAIL_CATEGORIES as $category) {
      $mode = $form_state->getValue(['email_modes', $category]);
      if (is_string($mode)) {
        $this->preferences->saveEmailMode($uid, $category, $mode);
      }
    }
    $this->messenger()->addStatus($this->t('Preferências de e-mail salvas.'));
    $form_state->setRedirect('aculta_messenger.communication_settings');
  }

  /**
   * Provides modes allowed by the administrator for one category.
   */
  private function emailModeOptions(string $category): array {
    $labels = [
      AvisoPreferencias::EMAIL_OFF => $this->t('Somente no site'),
      AvisoPreferencias::EMAIL_DIGEST => $category === AvisoPreferencias::EMAIL_PRIVATE_MESSAGES
        ? $this->t('Resumo semanal por e-mail')
        : $this->t('Resumo diário por e-mail'),
      AvisoPreferencias::EMAIL_IMMEDIATE => $this->t('No site e por e-mail'),
    ];
    return array_intersect_key(
      $labels,
      array_flip($this->preferences->allowedEmailModes($category)),
    );
  }

  /**
   * Returns a translated title for one notice category.
   */
  private function emailCategoryTitle(string $category): string {
    return match ($category) {
      'forum_resposta' => (string) $this->t('Respostas em tópicos seguidos do Fórum'),
      'forum_atualizacao' => (string) $this->t('Atualizações de tópicos do Fórum'),
      'aprendizagem' => (string) $this->t('Aprendizagem'),
      'wiki' => (string) $this->t('Wiki'),
      'revista' => (string) $this->t('Revista'),
      'loja' => (string) $this->t('Loja'),
      'doacao' => (string) $this->t('Doações'),
      AvisoPreferencias::EMAIL_PRIVATE_MESSAGES => (string) $this->t('Mensagens privadas'),
      AvisoPreferencias::EMAIL_FRIENDSHIP => (string) $this->t('Amizades'),
      default => throw new \InvalidArgumentException('Categoria de aviso inválida.'),
    };
  }

  /**
   * Returns a description for one notice category.
   */
  private function emailCategoryDescription(string $category): string {
    return match ($category) {
      AvisoPreferencias::EMAIL_PRIVATE_MESSAGES => (string) $this->t('O e-mail informa que há nova mensagem e quem enviou, sem conteúdo da conversa.'),
      AvisoPreferencias::EMAIL_FRIENDSHIP => (string) $this->t('Pedidos e aceites continuam disponíveis na área de mensagens do site.'),
      'doacao' => (string) $this->t('Inclui avisos distintos para pedido de doação criado e pagamento aprovado.'),
      default => (string) $this->t('O aviso no site permanece ativo; a administração pode limitar as opções de e-mail.'),
    };
  }

}
