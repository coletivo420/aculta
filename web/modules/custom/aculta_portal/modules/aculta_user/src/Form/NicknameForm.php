<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Form;

use Drupal\aculta_portal\Profile\PerfilDoPapel;
use Drupal\aculta_user\Account\AccountNames;
use Drupal\aculta_user\Account\NicknamePolicy;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\profile\Entity\ProfileInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Isolated owner Form API editor for the public role-Profile nickname only.
 */
final class NicknameForm extends FormBase {

  public function __construct(
    private readonly EntityTypeManagerInterface $entities,
    private readonly AccountProxyInterface $currentAccount,
    private readonly NicknamePolicy $nicknamePolicy,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('aculta_user.nickname_policy'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_user_studio_nickname_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $profile = $this->profile();
    $form['intro'] = [
      '#plain_text' => $this->t('O apelido identifica você na comunidade. A URL pública usa a versão minúscula, sem alterar a capitalização exibida.'),
    ];
    $form['nickname'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Apelido público'),
      '#default_value' => (string) ($profile->get('field_nickname')->value ?? ''),
      '#maxlength' => AccountNames::MAX,
      '#required' => TRUE,
      '#description' => $this->t('De 3 a 40 caracteres: letras sem acento, números e hífen. Alguns nomes são reservados. A alteração muda a URL pública sem redirecionar o endereço antigo.'),
    ];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Salvar apelido'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $nickname = trim((string) $form_state->getValue('nickname'));
    if (!AccountNames::isValid($nickname)) {
      $form_state->setErrorByName('nickname', $this->t('Use um apelido de 3 a 40 caracteres, com letras sem acento, números ou hífen, que não seja reservado.'));
      return;
    }
    if ($this->nicknamePolicy->isTaken($nickname, (int) $this->currentAccount->id())) {
      $form_state->setErrorByName('nickname', $this->t('Este apelido já está em uso.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Reload the owner's Profile and write this one field only.
    $profile = $this->profile();
    $profile->set('field_nickname', trim((string) $form_state->getValue('nickname')));
    $profile->save();
    $this->messenger()->addStatus($this->t('Apelido público salvo.'));
    $form_state->setRedirect('aculta_studio.profile');
  }

  /**
   * Resolves the current member's role Profile; never accepts a target UID.
   */
  private function profile(): ProfileInterface {
    $account = $this->currentAccount->getAccount();
    if (!$account->isAuthenticated() || !$account->hasPermission('access aculta portal')) {
      throw new AccessDeniedHttpException();
    }
    $profile = PerfilDoPapel::obter($this->entities->getStorage('profile'), $account);
    if ((int) $profile->getOwnerId() !== (int) $account->id() || !$profile->hasField('field_nickname')) {
      throw new AccessDeniedHttpException();
    }
    return $profile;
  }

}
