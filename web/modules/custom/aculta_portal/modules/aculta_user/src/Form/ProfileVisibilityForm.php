<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Form;

use Drupal\aculta_portal\Appearance\SettingsTabs;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\aculta_user\PublicProfilePolicy;
use Drupal\profile\Entity\ProfileInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Minha conta > Configurações > Privacidade.
 *
 * Escolhas de visibilidade do perfil público. A seção "Mensagens privadas" é acrescentada pelo aculta_messenger (form_alter).
 * Apelido é editado em Meus dados; bio e redes sociais, na visão geral. Autores e editores têm o perfil
 * público obrigatório (PublicProfilePolicy).
 */
final class ProfileVisibilityForm extends FormBase {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManagerService,
    private readonly AccountProxyInterface $currentUserService,
    private readonly SettingsTabs $settingsTabs,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('aculta_portal.settings_tabs'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_user_profile_visibility_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $profile = $this->loadOrCreateProfile();
    $obrigatorio = $this->isObrigatorioPublico();

    $form['tabs'] = $this->settingsTabs->build('privacy');
    $form['intro'] = ['#markup' => '<p>' . $this->t('Escolha o que aparece no seu perfil público. Quando o perfil está privado, aparecem somente a foto e o apelido.') . '</p>'];

    // Autores e editores têm perfil público obrigatório: o controle fica bloqueado, com o motivo.
    $form['publico'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Perfil público'),
      '#description' => $obrigatorio
        ? $this->t('Bloqueado: o perfil de autores e editores da Revista e da Wiki420 é sempre público, para que a associação identifique quem publica.')
        : $this->t('Padrão: perfil público. Quando desligado, aparecem somente a foto e o apelido.'),
      '#default_value' => $obrigatorio ? TRUE : $this->flag($profile, 'field_perfil_publico', TRUE),
      '#disabled' => $obrigatorio,
    ];
    $form['exibir_bio'] = ['#type' => 'checkbox', '#title' => $this->t('Mostrar bio'), '#default_value' => $this->flag($profile, 'field_exibir_bio', FALSE)];
    $form['exibir_redes'] = ['#type' => 'checkbox', '#title' => $this->t('Mostrar redes sociais'), '#default_value' => $this->flag($profile, 'field_exibir_redes', FALSE)];
    $form['exibir_email'] = ['#type' => 'checkbox', '#title' => $this->t('Mostrar e-mail'), '#default_value' => $this->flag($profile, 'field_exibir_email', FALSE)];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Salvar')];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $profile = $this->loadOrCreateProfile();
    // Controle bloqueado não é enviado pelo navegador; autor e editor continuam públicos.
    $profile->set('field_perfil_publico', $this->isObrigatorioPublico() ? TRUE : (bool) $form_state->getValue('publico'));
    $profile->set('field_exibir_bio', (bool) $form_state->getValue('exibir_bio'));
    $profile->set('field_exibir_redes', (bool) $form_state->getValue('exibir_redes'));
    $profile->set('field_exibir_email', (bool) $form_state->getValue('exibir_email'));
    $profile->save();
    $this->messenger()->addStatus($this->t('Privacidade salva.'));
    $form_state->setRedirect('aculta_user.configuracoes_privacidade');
  }

  /**
   * Indica se a conta atual é autor ou editor (perfil público obrigatório).
   */
  private function isObrigatorioPublico(): bool {
    return PublicProfilePolicy::isObrigatorioPublico($this->currentUserService->getAccount()->getRoles());
  }

  /**
   * Lê um flag booleano do perfil; campo ausente usa o padrão.
   */
  private function flag(ProfileInterface $profile, string $field, bool $default): bool {
    if (!$profile->hasField($field) || $profile->get($field)->isEmpty()) {
      return $default;
    }
    return (bool) $profile->get($field)->value;
  }

  /**
   * Carrega o perfil participante da conta atual, criando-o se ainda não existe.
   */
  private function loadOrCreateProfile(): ProfileInterface {
    $storage = $this->entityTypeManagerService->getStorage('profile');
    $account = $this->currentUserService->getAccount();
    return $storage->loadByUser($account, 'participante')
      ?? $storage->create(['type' => 'participante', 'uid' => (int) $account->id()]);
  }

}
