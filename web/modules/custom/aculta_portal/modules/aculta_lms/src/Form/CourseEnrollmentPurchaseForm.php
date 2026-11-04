<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Form;

use CommerceGuys\Intl\Formatter\CurrencyFormatterInterface;
use Drupal\aculta_portal\Checkout\CheckoutSettings;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\commerce_cart\CartManagerInterface;
use Drupal\commerce_cart\CartProviderInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\commerce_product\Entity\ProductVariationInterface;
use Drupal\commerce_store\CurrentStoreInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupRelationshipInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Adds a course and the learner's chosen class to a Commerce cart. */
final class CourseEnrollmentPurchaseForm extends FormBase {

  public function __construct(
    #[Autowire(service: 'entity_type.manager')]
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'commerce_cart.cart_provider')]
    protected readonly CartProviderInterface $cartProvider,
    #[Autowire(service: 'commerce_cart.cart_manager')]
    protected readonly CartManagerInterface $cartManager,
    #[Autowire(service: 'commerce_store.current_store')]
    protected readonly CurrentStoreInterface $currentStore,
    #[Autowire(service: 'commerce_price.currency_formatter')]
    protected readonly CurrencyFormatterInterface $currencyFormatter,
    #[Autowire(service: 'current_user')]
    protected readonly AccountProxyInterface $account,
    #[Autowire(service: 'messenger')]
    protected readonly MessengerInterface $messengerService,
    #[Autowire(service: 'string_translation')]
    TranslationInterface $translation,
    #[Autowire(service: 'aculta_portal.domain_purpose')]
    protected readonly DomainPurposeManager $purposeManager,
    #[Autowire(service: 'aculta_portal.checkout_settings')]
    protected readonly ?CheckoutSettings $checkoutSettings = NULL,
  ) {
    $this->stringTranslation = $translation;
  }

  public function getFormId(): string {
    return 'aculta_lms_course_enrollment_purchase';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?GroupInterface $course = NULL): array {
    if (!$course instanceof GroupInterface || $course->bundle() !== 'lms_course') {
      return $form;
    }
    $form_state->set('aculta_lms_course_id', (string) $course->id());
    $cacheability = (new CacheableMetadata())
      ->addCacheableDependency($course)
      ->addCacheContexts(['user', 'user.group_permissions', 'domain'])
      ->addCacheTags(['group_relationship_list:plugin:lms_classes:group:' . $course->id()]);

    $options = [];
    foreach ($course->getRelationships('lms_classes') as $relationship) {
      $class = $relationship instanceof GroupRelationshipInterface ? $relationship->getEntity() : NULL;
      if (!$class instanceof GroupInterface || $class->bundle() !== 'lms_class') {
        continue;
      }
      $cacheability->addCacheableDependency($class);
      if ($class->isPublished()) {
        $options[(string) $class->id()] = (string) $class->label();
      }
    }
    $cacheability->applyTo($form);
    if ($options === []) {
      $form['message'] = ['#plain_text' => $this->t('Este curso ainda não possui turmas disponíveis para matrícula.')];
      return $form;
    }

    $variation = $this->courseVariation($course, $cacheability);
    $cacheability->applyTo($form);
    if (!$variation instanceof ProductVariationInterface) {
      $form['message'] = ['#plain_text' => $this->t('A inscrição online ainda não está disponível para este curso.')];
      return $form;
    }

    $form['course_class'] = [
      '#type' => 'select',
      '#title' => $this->t('Turma'),
      '#options' => $options,
      '#empty_option' => $this->t('- Escolha uma turma -'),
      '#required' => TRUE,
    ];
    $price = $variation->getPrice();
    $form['price'] = [
      '#type' => 'item',
      '#title' => $this->t('Valor'),
      '#plain_text' => $price->isZero()
        ? $this->t('Gratuito')
        : $this->currencyFormatter->format($price->getNumber(), $price->getCurrencyCode()),
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $price->isZero() ? $this->t('Continuar para inscrição gratuita') : $this->t('Adicionar ao carrinho e comprar'),
    ];
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $course = $this->loadCourse($form_state);
    $class_id = (string) $form_state->getValue('course_class');
    if (!$course instanceof GroupInterface || $course->bundle() !== 'lms_course' || !$this->classIsAvailable($course, $class_id)) {
      $form_state->setErrorByName('course_class', $this->t('Selecione uma turma disponível deste curso.'));
    }
    if (!$course instanceof GroupInterface || !$this->courseVariation($course) instanceof ProductVariationInterface) {
      $form_state->setErrorByName('course_class', $this->t('A inscrição online não está disponível para este curso.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $course = $this->loadCourse($form_state);
    $variation = $course instanceof GroupInterface ? $this->courseVariation($course) : NULL;
    $class = $this->entityTypeManager->getStorage('group')->load((string) $form_state->getValue('course_class'));
    if (!$course instanceof GroupInterface || $course->bundle() !== 'lms_course'
      || !$variation instanceof ProductVariationInterface
      || !$class instanceof GroupInterface
      || !$this->classIsAvailable($course, (string) $class->id())) {
      $this->messengerService->addError($this->t('Não foi possível preparar a inscrição. Atualize a página e tente novamente.'));
      return;
    }

    $store = $this->currentStore->getStore();
    $orderType = $this->checkoutSettings?->lmsOrderType() ?? CheckoutSettings::LEGACY_LMS_ORDER_TYPE;
    $cart = $this->cartProvider->getCart($orderType, $store);
    if (!$cart instanceof OrderInterface) {
      $cart = $this->cartProvider->createCart($orderType, $store, $this->account);
    }
    $item = $this->cartManager->createOrderItem($variation);
    $item->set('field_course_class', ['target_id' => $class->id()]);
    $this->cartManager->addOrderItem($cart, $item, FALSE);

    $checkoutUrl = $this->purposeManager->routeUrl('main', 'aculta_portal.cart_checkout');
    if ($checkoutUrl === NULL) {
      $this->messengerService->addError($this->t('Não foi possível abrir o checkout agora. O curso foi adicionado ao carrinho.'));
      return;
    }
    $form_state->setResponse(new TrustedRedirectResponse($checkoutUrl->toString()));
  }

  private function loadCourse(FormStateInterface $form_state): ?GroupInterface {
    $course = $this->entityTypeManager->getStorage('group')->load($form_state->get('aculta_lms_course_id'));
    return $course instanceof GroupInterface && $course->bundle() === 'lms_course' ? $course : NULL;
  }

  private function classIsAvailable(GroupInterface $course, string $class_id): bool {
    $class = $this->entityTypeManager->getStorage('group')->load($class_id);
    if (!$class instanceof GroupInterface || $class->bundle() !== 'lms_class' || !$class->isPublished()) {
      return FALSE;
    }
    foreach ($course->getRelationships('lms_classes') as $relationship) {
      $courseClass = $relationship instanceof GroupRelationshipInterface ? $relationship->getEntity() : NULL;
      if ($courseClass instanceof GroupInterface && (string) $courseClass->id() === $class_id) {
        return TRUE;
      }
    }
    return FALSE;
  }

  private function courseVariation(GroupInterface $course, ?CacheableMetadata $cacheability = NULL): ?ProductVariationInterface {
    if (!$course->hasField('field_course_product') || $course->get('field_course_product')->isEmpty()) {
      return NULL;
    }
    $product = $course->get('field_course_product')->entity;
    if ($product instanceof ProductInterface) {
      $cacheability?->addCacheableDependency($product);
    }
    if (!$product instanceof ProductInterface || $product->bundle() !== 'produto_curso' || !$product->isPublished()) {
      return NULL;
    }
    foreach ($product->getVariations() as $variation) {
      if ($variation instanceof ProductVariationInterface) {
        $cacheability?->addCacheableDependency($variation);
      }
      if ($variation instanceof ProductVariationInterface
        && $variation->bundle() === 'curso'
        && $variation->isPublished()
        && $variation->getPrice() !== NULL) {
        return $variation;
      }
    }
    return NULL;
  }

}
