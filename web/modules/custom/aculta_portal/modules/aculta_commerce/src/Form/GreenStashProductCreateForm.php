<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\aculta_commerce\Store\GreenStashStoreResolver;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\aculta_studio\Experience\StudioExperienceManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Creates an unpublished Commerce store product without using admin forms.
 *
 * Variations, images and publishing are deliberately separate Studio phases.
 */
final class GreenStashProductCreateForm extends FormBase {

  public function __construct(
    private readonly EntityTypeManagerInterface $entities,
    private readonly AccountInterface $account,
    private readonly StudioExperienceManager $experiences,
    private readonly GreenStashStoreResolver $storeResolver,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('aculta_studio.experience_manager'),
      $container->get('aculta_commerce.green_stash_store_resolver'),
    );
  }

  public function getFormId(): string {
    return 'aculta_commerce_green_stash_product_create';
  }

  private function assertCreateAccess(): void {
    if (!$this->experiences->access('green_stash', 'account', $this->account)->isAllowed()
      || !$this->entities->getAccessControlHandler('commerce_product')->createAccess('produto_loja', $this->account)) {
      throw new AccessDeniedHttpException();
    }
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $this->assertCreateAccess();
    if ($this->storeResolver->resolve($this->account) === NULL) {
      throw new AccessDeniedHttpException();
    }
    $form['#attributes']['data-portal-ui'] = 'green-stash-product-create';
    $form['intro'] = [
      '#type' => 'item',
      '#markup' => $this->t('Cadastre as informações iniciais. O produto será salvo como rascunho e não estará à venda.'),
    ];
    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Nome do produto'),
      '#required' => TRUE,
      '#maxlength' => 255,
    ];
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Salvar rascunho'),
      '#button_type' => 'primary',
    ];
    $form['#cache']['max-age'] = 0;
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $this->assertCreateAccess();
    $title = trim((string) $form_state->getValue('title'));
    if ($title === '') {
      $form_state->setErrorByName('title', $this->t('Informe o nome do produto.'));
    }

  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->assertCreateAccess();
    $store = $this->storeResolver->resolve($this->account);
    if ($store === NULL) {
      throw new AccessDeniedHttpException();
    }
    $product = $this->entities->getStorage('commerce_product')->create([
      'type' => 'produto_loja',
      'title' => trim((string) $form_state->getValue('title')),
      'stores' => [$store->id()],
      'status' => FALSE,
    ]);
    $violations = $product->validate();
    if ($violations->count() > 0) {
      $this->messenger()->addError($this->t('Não foi possível validar o rascunho do produto. Verifique a configuração do catálogo.'));
      return;
    }
    $product->save();
    $this->messenger()->addStatus($this->t('Rascunho do produto criado.'));
    $form_state->setRedirect('aculta_commerce.green_stash_product_detail', ['produto' => $product->id()]);
  }

}
