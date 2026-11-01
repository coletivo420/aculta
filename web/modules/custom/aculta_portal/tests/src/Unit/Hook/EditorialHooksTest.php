<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit\Hook;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_portal\Hook\EditorialHooks;
use Drupal\block_content\BlockContentInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Tests\UnitTestCase;
use Drupal\domain\DomainNegotiatorInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Verifies section block presentation data passed from Portal to the theme.
 */
#[Group('aculta_portal')]
final class EditorialHooksTest extends UnitTestCase {

  /**
   * Verifies a configured color is forwarded without changing the variant. */
  public function testBasicBlockPassesConfiguredSectionColor(): void {
    $fields = [
      'field_section_title' => $this->field('Formação'),
      'field_section_heading' => $this->field('Acesso à cultura'),
      'field_section_variant' => $this->field('city'),
      'field_section_color' => $this->field('yellow'),
    ];
    $block = $this->block($fields);
    $build = ['body' => ['#markup' => 'Corpo editorial']];

    $this->hooks()->entityViewAlter($build, $block, $this->createMock(EntityViewDisplayInterface::class));

    self::assertSame('aculta_section', $build['#theme']);
    self::assertSame('Formação', $build['#section_title']);
    self::assertSame('Acesso à cultura', $build['#section_heading']);
    self::assertSame('city', $build['#section_variant']);
    self::assertSame('yellow', $build['#section_color']);
    self::assertSame(['#markup' => 'Corpo editorial'], $build['#section_body']);
    self::assertArrayNotHasKey('body', $build);
  }

  /**
   * Existing basic blocks without the color field continue using the default. */
  public function testExistingBasicBlockWithoutColorUsesDefault(): void {
    $fields = [
      'field_section_title' => $this->field('Quem somos'),
      'field_section_heading' => $this->field(''),
      'field_section_variant' => $this->field('default'),
    ];
    $block = $this->block($fields);
    $build = ['body' => ['#markup' => 'Texto existente']];

    $this->hooks()->entityViewAlter($build, $block, $this->createMock(EntityViewDisplayInterface::class));

    self::assertSame('default', $build['#section_color']);
    self::assertSame('Texto existente', $build['#section_body']['#markup']);
  }

  /**
   * Other block content bundles are not changed by the editorial hook. */
  public function testNonBasicBlockIsLeftUntouched(): void {
    $block = $this->createMock(BlockContentInterface::class);
    $block->method('bundle')->willReturn('hero');
    $block->expects(self::never())->method('hasField');
    $build = ['body' => ['#markup' => 'Texto']];

    $this->hooks()->entityViewAlter($build, $block, $this->createMock(EntityViewDisplayInterface::class));

    self::assertSame(['body' => ['#markup' => 'Texto']], $build);
  }

  /**
   * Creates a basic block mock with the supplied field lists.
   *
   * @param array<string, \Drupal\Core\Field\FieldItemListInterface> $fields
   *   Field lists indexed by machine name.
   *
   * @return \Drupal\block_content\BlockContentInterface
   *   A basic block mock.
   */
  private function block(array $fields): BlockContentInterface {
    $block = $this->createMock(BlockContentInterface::class);
    $block->method('bundle')->willReturn('basic');
    $block->method('hasField')->willReturnCallback(
      static fn (string $name): bool => array_key_exists($name, $fields),
    );
    $block->method('get')->willReturnCallback(
      static fn (string $name): FieldItemListInterface => $fields[$name],
    );

    return $block;
  }

  /**
   * Creates a field list mock that exposes the supplied value. */
  private function field(string $value): FieldItemListInterface {
    $field = $this->createMock(FieldItemListInterface::class);
    $field->method('__get')->with('value')->willReturn($value);

    return $field;
  }

  /**
   * Builds the hook with unused request dependencies satisfied. */
  private function hooks(): EditorialHooks {
    $requestStack = new RequestStack();
    $purposeManager = new DomainPurposeManager(
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(DomainNegotiatorInterface::class),
      $this->createMock(ConfigFactoryInterface::class),
      $requestStack,
    );

    return new EditorialHooks($purposeManager, new CurrentPathStack($requestStack));
  }

}
