<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Appearance;

use Drupal\Core\Cache\Cache;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Minha Conta > Configurações: escolha do modo de cor da própria conta. */
final class AppearanceForm extends FormBase implements ContainerInjectionInterface {

  public function __construct(
    private readonly ColorModePreference $preference,
    private readonly AccountProxyInterface $currentUser,
    private readonly SettingsTabs $settingsTabs,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('aculta_portal.color_mode_preference'),
      $container->get('current_user'),
      $container->get('aculta_portal.settings_tabs'),
    );
  }

  public function getFormId(): string {
    return 'aculta_portal_appearance_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $uid = (int) $this->currentUser->id();
    $form['tabs'] = $this->settingsTabs->build('color');
    $form['intro'] = ['#markup' => '<p>' . $this->t('Escolha como a página aparece para você. Automático segue a preferência do seu sistema.') . '</p>'];
    $form['color_mode'] = [
      '#type' => 'radios',
      '#title' => $this->t('Modo de cor'),
      '#options' => [
        'light' => $this->t('Claro'),
        'dark' => $this->t('Escuro'),
        'auto' => $this->t('Automático'),
      ],
      '#default_value' => $this->preference->forUser($uid) ?? $this->preference->siteDefault(),
      '#required' => TRUE,
    ];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Salvar')];
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $uid = (int) $this->currentUser->id();
    $this->preference->save($uid, (string) $form_state->getValue('color_mode'));
    // A página em cache desta pessoa carrega o modo anterior: invalida a tag do usuário.
    Cache::invalidateTags(['user:' . $uid]);
    $this->messenger()->addStatus($this->t('Modo de cor salvo.'));
  }

}
