<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta420\Unit;

use Drupal\aculta420\Hook\PortalUiHooks;
use Drupal\Core\Template\Attribute;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tradução de tokens `data-portal-ui` em classes do ACULTA420.
 *
 * O módulo marca o elemento com tokens semânticos; se a tradução falhar, o
 * Portal aparece sem estilo. Estes testes não precisam de banco nem de container.
 */
#[Group('aculta420')]
final class PortalUiHooksTest extends UnitTestCase {

  /**
   * Token conhecido vira classes; o atributo de token é removido.
   */
  public function testMapsKnownTokensOnAttributeObjectAndRemovesTheTokenAttribute(): void {
    $variables = [
      'attributes' => new Attribute([
        'data-portal-ui' => 'notice-warning email-notice lms-activity-discussions lms-h5p-activity lms-h5p-discussions',
        'class' => ['existente'],
      ]),
    ];

    (new PortalUiHooks())->preprocess($variables);

    $attributes = $variables['attributes'];
    $this->assertInstanceOf(Attribute::class, $attributes);
    $this->assertTrue($attributes->hasClass('messages'));
    $this->assertTrue($attributes->hasClass('messages--warning'));
    $this->assertTrue($attributes->hasClass('aculta-email-notice'));
    $this->assertTrue($attributes->hasClass('card'));
    $this->assertTrue($attributes->hasClass('card-body'));
    $this->assertTrue($attributes->hasClass('mt-3'));
    $this->assertTrue($attributes->hasClass('d-grid'));
    $this->assertTrue($attributes->hasClass('gap-3'));
    $this->assertTrue($attributes->hasClass('existente'));
    $this->assertFalse($attributes->offsetExists('data-portal-ui'));
  }

  /**
   * Forum dialog tokens map to the theme classes used by dialog.css.
   */
  public function testMapsForumDialogTokens(): void {
    $variables = [
      'attributes' => new Attribute([
        'data-portal-ui' => 'forum-attachment-dialog forum-gallery-dialog',
      ]),
    ];

    (new PortalUiHooks())->preprocess($variables);

    $attributes = $variables['attributes'];
    $this->assertTrue($attributes->hasClass('aculta-forum-attachment-dialog'));
    $this->assertTrue($attributes->hasClass('aculta-forum-gallery-dialog'));
    $this->assertFalse($attributes->offsetExists('data-portal-ui'));
  }

  /**
   * Token desconhecido não gera classe e ainda assim é removido.
   */
  public function testUnknownTokenGeneratesNoClassAndIsRemoved(): void {
    $variables = [
      'attributes' => new Attribute(['data-portal-ui' => 'token-que-nao-existe']),
    ];

    (new PortalUiHooks())->preprocess($variables);

    $this->assertFalse($variables['attributes']->offsetExists('data-portal-ui'));
    $this->assertFalse($variables['attributes']->hasClass('token-que-nao-existe'));
    $this->assertFalse($variables['attributes']->hasClass('messages'));
  }

  /**
   * Elementos de render guardam #attributes como array: a tradução grava no próprio elemento.
   */
  public function testMapsTokensInRenderElementArrayAttributes(): void {
    $variables = [
      'element' => [
        '#attributes' => ['data-portal-ui' => 'email-notice', 'class' => ['x']],
      ],
    ];

    (new PortalUiHooks())->preprocess($variables);

    $attributes = $variables['element']['#attributes'];
    $this->assertArrayNotHasKey('data-portal-ui', $attributes);
    $this->assertContains('aculta-email-notice', $attributes['class']);
    $this->assertContains('x', $attributes['class']);
  }

  /**
   * Atributos ainda em array (antes da conversão do núcleo) viram Attribute com as classes.
   */
  public function testArrayAttributesAreConvertedToAttributeObjects(): void {
    $variables = [
      'attributes' => ['data-portal-ui' => 'email-notice'],
    ];

    (new PortalUiHooks())->preprocess($variables);

    $this->assertInstanceOf(Attribute::class, $variables['attributes']);
    $this->assertTrue($variables['attributes']->hasClass('aculta-email-notice'));
    $this->assertFalse($variables['attributes']->offsetExists('data-portal-ui'));
  }

  /**
   * Contador de não lidos: valor válido vira componente; zero e valor inválido não.
   */
  public function testUnreadBadgeIsAddedOnlyForPositiveIntegerCounts(): void {
    $variables = [
      'menu_items' => [
        ['title' => 'Mensagens', 'badge_count' => 3],
        ['title' => 'Avisos', 'badge_count' => 0],
        ['title' => 'Outro', 'badge_count' => 'abc'],
      ],
    ];

    (new PortalUiHooks())->preprocess($variables);

    $this->assertSame('aculta420:unread-badge', $variables['menu_items'][0]['unread_badge']['#component']);
    $this->assertSame(3, $variables['menu_items'][0]['unread_badge']['#props']['count']);
    $this->assertArrayNotHasKey('unread_badge', $variables['menu_items'][1]);
    $this->assertArrayNotHasKey('unread_badge', $variables['menu_items'][2]);
    $this->assertArrayNotHasKey('badge_count', $variables['menu_items'][0]);
    $this->assertArrayNotHasKey('badge_count', $variables['menu_items'][1]);
  }

}
