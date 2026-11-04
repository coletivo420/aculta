<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\aculta_lms\Form\CourseEnrollmentPurchaseForm;
use Drupal\commerce_order\Entity\OrderItemType;
use Drupal\commerce_order\Entity\OrderType;
use Drupal\commerce_price\Price;
use Drupal\commerce_product\Entity\Product;
use Drupal\commerce_product\Entity\ProductType;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\commerce_product\Entity\ProductVariationType;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\group\Entity\Group;
use Drupal\Tests\commerce_cart\Kernel\CartKernelTestBase;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group as TestGroup;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Exercises adding a selected class to the learner's Commerce cart.
 */
#[TestGroup('aculta_lms')]
#[RunTestsInSeparateProcesses]
final class CourseEnrollmentPurchaseKernelTest extends CartKernelTestBase {

  use AcultaLmsDependencyHarnessTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system', 'user', 'file', 'field', 'filter', 'image', 'media', 'options',
    'text', 'text_with_summary', 'comment', 'domain', 'flexible_permissions', 'group', 'views',
    'key', 'entity_print', 'entity_usage', 'aculta_image',
    'aculta_file_uploader', 'aculta_lms', 'commerce', 'commerce_price',
    'commerce_store', 'commerce_product', 'commerce_order', 'commerce_cart',
    'entity_reference_revisions', 'profile', 'state_machine',
    'commerce_number_pattern', 'address', 'datetime', 'entity',
    'inline_entity_form', 'path', 'path_alias',
  ];

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    $this->registerLmsDependencyServices($container);
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->prepareAcultaLmsGroupTypes();
    $this->prepareAcultaLmsClassRelationship();
    $this->installSchema('entity_usage', ['entity_usage']);
    $this->installSchema('file', ['file_usage']);
    $this->installConfig([
      'commerce_product', 'commerce_order', 'commerce_cart', 'system',
    ]);

    OrderItemType::create([
      'id' => 'curso', 'label' => 'Curso', 'purchasableEntityType' => 'commerce_product_variation',
      'orderType' => 'curso',
    ])->save();
    OrderType::create([
      'id' => 'curso', 'label' => 'Curso', 'workflow' => 'order_default',
      'numberPattern' => 'order_default',
    ])->save();
    ProductVariationType::create([
      'id' => 'curso', 'label' => 'Curso', 'orderItemType' => 'curso',
      'generateTitle' => TRUE,
    ])->save();
    ProductType::create([
      'id' => 'produto_curso', 'label' => 'Produto de curso',
      'variationType' => 'curso', 'variationTypes' => ['curso'], 'multipleVariations' => FALSE,
    ])->save();

    $this->createReferenceField('group', 'field_course_product', 'commerce_product', 'lms_course');
    $this->createReferenceField('commerce_order_item', 'field_course_class', 'group', 'curso');
    FieldStorageConfig::create([
      'field_name' => 'field_course_order', 'entity_type' => 'group_relationship',
      'type' => 'entity_reference', 'settings' => ['target_type' => 'commerce_order'],
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_course_order', 'entity_type' => 'group_relationship',
      'bundle' => 'lms_class-group_membership', 'label' => 'Pedido',
      'settings' => ['handler' => 'default', 'handler_settings' => []],
    ])->save();
    $this->container->get('commerce_price.currency_importer')->import('BRL');
  }

  /**
   * Verifies that the selected class persists on the cart order item.
   */
  public function testSelectedClassIsAddedToCourseCart(): void {
    $learner = User::create(['name' => 'lms-kernel-learner', 'status' => 1]);
    $learner->save();
    $class = Group::create([
      'type' => 'lms_class', 'label' => 'Turma disponível', 'uid' => $learner->id(), 'status' => 1,
    ]);
    $class->save();
    $closedClass = Group::create([
      'type' => 'lms_class', 'label' => 'Turma encerrada', 'uid' => $learner->id(), 'status' => 0,
    ]);
    $closedClass->save();
    $course = Group::create([
      'type' => 'lms_course', 'label' => 'Curso comprado', 'uid' => $learner->id(), 'status' => 1,
    ]);
    $course->save();
    $course->addRelationship($class, 'lms_classes')->save();
    $course->addRelationship($closedClass, 'lms_classes')->save();

    $variation = ProductVariation::create([
      'type' => 'curso', 'sku' => 'lms-course-kernel', 'price' => new Price('125.00', 'BRL'), 'status' => 1,
    ]);
    $variation->save();
    $product = Product::create([
      'type' => 'produto_curso', 'title' => 'Curso comprado', 'stores' => [$this->container->get('commerce_store.current_store')->getStore()],
      'variations' => [$variation], 'status' => 1,
    ]);
    $product->save();
    $course->set('field_course_product', $product)->save();

    $form = $this->createPurchaseForm();
    $formState = new FormState();
    $build = $form->buildForm([], $formState, $course);
    self::assertSame([(string) $class->id() => 'Turma disponível'], $build['course_class']['#options']);
    self::assertNotEmpty($build['price']['#plain_text']);
    self::assertNotEmpty($build['actions']['submit']['#value']);
    $formState->set('aculta_lms_course_id', (string) $course->id());
    $formState->setValue('course_class', (string) $class->id());
    $formArray = [];
    $form->submitForm($formArray, $formState);

    $cart = $this->container->get('commerce_cart.cart_provider')->getCart('curso', $this->container->get('commerce_store.current_store')->getStore());
    self::assertNotNull($cart);
    self::assertCount(1, $cart->getItems());
    $item = $cart->getItems()[0];
    self::assertSame((string) $variation->id(), (string) $item->getPurchasedEntityId());
    self::assertSame((string) $class->id(), (string) $item->get('field_course_class')->target_id);
    self::assertNotEmpty($this->container->get('messenger')->messagesByType('error'));
  }

  /**
   * Creates the purchase form using the real Commerce and Portal services.
   */
  private function createPurchaseForm(): CourseEnrollmentPurchaseForm {
    return new CourseEnrollmentPurchaseForm(
      $this->container->get('entity_type.manager'),
      $this->container->get('commerce_cart.cart_provider'),
      $this->container->get('commerce_cart.cart_manager'),
      $this->container->get('commerce_store.current_store'),
      $this->container->get('commerce_price.currency_formatter'),
      $this->container->get('current_user'),
      $this->container->get('messenger'),
      $this->container->get('string_translation'),
      $this->container->get('aculta_portal.domain_purpose'),
    );
  }

  /**
   * Creates a reference field needed by the partial LMS Kernel.
   */
  private function createReferenceField(string $entityType, string $name, string $targetType, string $bundle): void {
    FieldStorageConfig::create([
      'field_name' => $name,
      'entity_type' => $entityType,
      'type' => 'entity_reference',
      'settings' => ['target_type' => $targetType],
    ])->save();
    FieldConfig::create([
      'field_name' => $name,
      'entity_type' => $entityType,
      'bundle' => $bundle,
      'label' => $name,
      'settings' => ['handler' => 'default', 'handler_settings' => []],
    ])->save();
  }

}
