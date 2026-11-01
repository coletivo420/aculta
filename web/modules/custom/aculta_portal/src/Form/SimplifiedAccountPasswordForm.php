<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Form;

use Drupal\aculta_portal\EventSubscriber\SimplifiedAccountCheckoutSubscriber;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\UserDataInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Criação da senha da conta simplificada: senha e confirmação. Ao salvar, a conta deixa de ser simplificada pendente.
 */
final class SimplifiedAccountPasswordForm extends FormBase {

  /**
   * Cria o formulário.
   */
  public function __construct(
    private readonly UserDataInterface $userData,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('user.data'), $container->get('entity_type.manager'));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_portal_simplified_account_password';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['intro'] = ['#markup' => '<p>' . $this->t('Seu e-mail foi confirmado. Crie uma senha para acessar sua conta.') . '</p>'];
    $form['pass'] = [
      '#type' => 'password_confirm',
      '#required' => TRUE,
      '#size' => 25,
    ];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Salvar senha')];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $uid = (int) $this->currentUser()->id();
    $account = $uid > 0 ? $this->entityTypeManager->getStorage('user')->load($uid) : NULL;
    if ($account === NULL) {
      return;
    }
    $account->setPassword((string) $form_state->getValue('pass'));
    $account->save();
    $this->userData->delete('aculta_portal', (int) $account->id(), SimplifiedAccountCheckoutSubscriber::FLAG);
    $this->messenger()->addStatus($this->t('Senha criada. Bem-vindo!'));
    $form_state->setRedirect('<front>');
  }

}
