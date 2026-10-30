<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta420\Unit;

use Drupal\aculta420\Hook\PortalUiHooks;
use Drupal\aculta420\Hook\ThemeHooks;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Url;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tradução de tokens `data-portal-ui` em classes do ACULTA420.
 *
 * O módulo marca elementos com tokens semânticos; se a tradução falhar, o
 * Portal aparece sem estilo. Estes testes não precisam de banco ou container.
 */
#[Group('aculta420')]
final class PortalUiHooksTest extends UnitTestCase {

  /**
   * The theme registers only one preprocess implementation per hook name.
   */
  public function testItemListPreprocessHasOneThemeHookProvider(): void {
    $implementations = [];
    foreach ([PortalUiHooks::class, ThemeHooks::class] as $provider) {
      $reflection = new \ReflectionClass($provider);
      foreach ($reflection->getMethods() as $method) {
        foreach ($method->getAttributes(Hook::class) as $attribute) {
          if ($attribute->newInstance()->hook === 'preprocess_item_list') {
            $implementations[] = $provider . '::' . $method->getName();
          }
        }
      }
    }

    self::assertSame([PortalUiHooks::class . '::preprocess'], $implementations);
  }

  /**
   * Event catalogue render arrays become the existing event card SDC.
   */
  public function testEventCatalogueUsesEventCardComponent(): void {
    $url = $this->createMock(Url::class);
    $url->method('toString')->willReturn('/eventos/encontro-cultural');
    $variables = [
      'wrapper_attributes' => new Attribute(),
      'items' => [
        [
          'value' => [
            '#attributes' => ['data-portal-ui' => 'event-card event-card-cancelled'],
            '#cache' => ['tags' => ['aculta_event_list']],
            'title' => ['link' => ['#title' => 'Encontro Cultural', '#url' => $url]],
            'subtitle' => ['#value' => 'Cultura &amp; direitos'],
            'type' => ['#value' => 'Encontro'],
            'meta' => ['#items' => ['Próxima data: 30/10/2026 19:00']],
            'cancelled' => ['#access' => TRUE],
          ],
          'attributes' => new Attribute(),
        ],
        [
          '#attributes' => ['data-portal-ui' => 'event-card'],
          '#wrapper_attributes' => ['class' => ['event-list-item']],
          'title' => ['link' => ['#title' => 'Outro evento', '#url' => $url]],
        ],
      ],
    ];

    (new PortalUiHooks())->preprocess($variables);

    self::assertContains('event-catalogue__list', $variables['wrapper_attributes']->getClass());
    self::assertSame('aculta420:event_card', $variables['items'][0]['value']['#component']);
    self::assertSame('Encontro Cultural', $variables['items'][0]['value']['#props']['title']);
    self::assertSame('/eventos/encontro-cultural', $variables['items'][0]['value']['#props']['url']);
    self::assertSame('Cultura & direitos', $variables['items'][0]['value']['#props']['subtitle']);
    self::assertSame('30/10/2026 19:00', $variables['items'][0]['value']['#props']['proxima']);
    self::assertTrue($variables['items'][0]['value']['#props']['cancelado']);
    self::assertSame(['tags' => ['aculta_event_list']], $variables['items'][0]['value']['#cache']);
    self::assertSame('aculta420:event_card', $variables['items'][1]['#component']);
    self::assertSame(['class' => ['event-list-item']], $variables['items'][1]['#wrapper_attributes']);
  }

  /**
   * Lists without the Events card token keep their normal token mapping.
   */
  public function testNonEventListsAreNotTransformed(): void {
    $item = [
      'value' => 'Section title',
      'attributes' => new Attribute(['data-portal-ui' => 'event-section-title']),
    ];
    $variables = ['items' => [$item]];

    (new PortalUiHooks())->preprocess($variables);

    self::assertArrayNotHasKey('#component', $variables['items'][0]);
    self::assertSame('Section title', $variables['items'][0]['value']);
    self::assertTrue($variables['items'][0]['attributes']->hasClass('section-title'));
  }

  /**
   * Token conhecido vira classes; o atributo de token é removido.
   */
  public function testMapsKnownTokensOnAttributeObjectAndRemovesTheTokenAttribute(): void {
    $variables = [
      'attributes' => new Attribute([
        'data-portal-ui' => 'notice-warning email-notice lms-activity-discussions lms-h5p-activity lms-h5p-discussions messenger-sender-avatar-image',
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
    $this->assertTrue($attributes->hasClass('aculta-messenger-sender__avatar-image'));
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
   * The Forum report queue uses neutral Portal tokens for theme presentation.
   */
  public function testMapsForumReportQueueTokens(): void {
    $variables = [
      'element' => [
        '#attributes' => ['data-portal-ui' => 'forum-report-queue'],
      ],
      'attributes' => new Attribute(['data-portal-ui' => 'forum-report-pager']),
    ];

    (new PortalUiHooks())->preprocess($variables);

    self::assertContains('aculta-forum-report-queue', $variables['element']['#attributes']['class']);
    self::assertArrayNotHasKey('data-portal-ui', $variables['element']['#attributes']);
    self::assertTrue($variables['attributes']->hasClass('aculta-pager'));
    self::assertFalse($variables['attributes']->offsetExists('data-portal-ui'));
  }

  /**
   * Checkout account registration and Google controls receive theme classes.
   */
  public function testMapsCheckoutAccountTokens(): void {
    $variables = [
      'element' => [
        '#attributes' => ['data-portal-ui' => 'checkout-guest-registration'],
      ],
      'attributes' => new Attribute(['data-portal-ui' => 'checkout-google']),
    ];

    (new PortalUiHooks())->preprocess($variables);

    $registration = $variables['element']['#attributes'];
    $this->assertArrayNotHasKey('data-portal-ui', $registration);
    $this->assertContains('card', $registration['class']);
    $this->assertContains('card-body', $registration['class']);
    $this->assertContains('mb-3', $registration['class']);
    $google = $variables['attributes'];
    $this->assertFalse($google->offsetExists('data-portal-ui'));
    $this->assertTrue($google->hasClass('d-flex'));
    $this->assertTrue($google->hasClass('justify-content-center'));
    $this->assertTrue($google->hasClass('mb-3'));
  }

  /**
   * WE SATIVA workspace and navigation actions receive responsive classes.
   */
  public function testMapsWeSativaWorkspaceAndLinkTokens(): void {
    $variables = [
      'element' => [
        '#attributes' => ['data-portal-ui' => 'we-sativa-workspace'],
      ],
      'options' => [
        'attributes' => new Attribute(['data-portal-ui' => 'we-sativa-create wiki-workspace-return']),
      ],
    ];

    (new PortalUiHooks())->preprocess($variables);

    $containerAttributes = $variables['element']['#attributes'];
    $this->assertContains('d-grid', $containerAttributes['class']);
    $this->assertContains('gap-4', $containerAttributes['class']);
    $this->assertContains('we-sativa__workspace', $containerAttributes['class']);
    $this->assertArrayNotHasKey('data-portal-ui', $containerAttributes);
    $linkAttributes = $variables['options']['attributes'];
    $this->assertInstanceOf(Attribute::class, $linkAttributes);
    $this->assertTrue($linkAttributes->hasClass('btn'));
    $this->assertTrue($linkAttributes->hasClass('btn-primary'));
    $this->assertTrue($linkAttributes->hasClass('btn-outline-primary'));
    $this->assertTrue($linkAttributes->hasClass('mb-3'));
    $this->assertFalse($linkAttributes->offsetExists('data-portal-ui'));
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
   * Render elements keep #attributes as arrays until the theme hook maps them.
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
   * Messenger notice filters and empty state receive theme classes.
   */
  public function testMapsMessengerNoticeFilterAndEmptyStateTokens(): void {
    $variables = [
      'element' => [
        '#attributes' => [
          'data-portal-ui' => 'alerts-filters alerts-filters-group messenger-state messenger-state-empty',
        ],
      ],
    ];

    (new PortalUiHooks())->preprocess($variables);

    $attributes = $variables['element']['#attributes'];
    $this->assertArrayNotHasKey('data-portal-ui', $attributes);
    $this->assertContains('aculta-alerts-filters', $attributes['class']);
    $this->assertContains('aculta-alerts-filters__group', $attributes['class']);
    $this->assertContains('aculta-messenger-state', $attributes['class']);
    $this->assertContains('aculta-messenger-state--empty', $attributes['class']);
  }

  /**
   * Account, reporting, commerce and notice tokens map to theme styles.
   */
  public function testMapsTokensPreviouslyMissingFromTheThemeRegistry(): void {
    $variables = [
      'attributes' => new Attribute([
        'data-portal-ui' => 'consent-banner studio-profile-editor studio-account-section notice-origin-section notice-origin-heading notice-related-group',
      ]),
    ];

    (new PortalUiHooks())->preprocess($variables);

    $attributes = $variables['attributes'];
    $this->assertTrue($attributes->hasClass('aculta-consent'));
    $this->assertTrue($attributes->hasClass('aculta-profile-editor'));
    $this->assertTrue($attributes->hasClass('studio__layout'));
    $this->assertTrue($attributes->hasClass('aculta-messenger-notice-origin'));
    $this->assertTrue($attributes->hasClass('aculta-messenger-notice-origin__heading'));
    $this->assertTrue($attributes->hasClass('aculta-messenger-notice-related-group'));
    $this->assertFalse($attributes->offsetExists('data-portal-ui'));
  }

  /**
   * Download formatter and saved-content tokens keep presentation in the theme.
   */
  public function testMapsDocumentDownloadAndStudioSavedContentTokens(): void {
    $variables = [
      'element' => [
        '#attributes' => [
          'data-portal-ui' => 'document-download-item studio-saved-content event-supporter-item',
        ],
      ],
      'attributes' => new Attribute([
        'data-portal-ui' => 'document-download-link document-download-format document-download-size studio-page-title studio-saved-tabs event-supporters event-press-quotes editorial-card',
      ]),
    ];

    (new PortalUiHooks())->preprocess($variables);

    $element = $variables['element']['#attributes'];
    $this->assertArrayNotHasKey('data-portal-ui', $element);
    $this->assertContains('aculta-download-meta-item', $element['class']);
    $this->assertContains('studio', $element['class']);
    $this->assertContains('studio-saved-page', $element['class']);
    $this->assertContains('sponsor-item', $element['class']);

    $attributes = $variables['attributes'];
    $this->assertFalse($attributes->offsetExists('data-portal-ui'));
    $this->assertTrue($attributes->hasClass('download-link'));
    $this->assertTrue($attributes->hasClass('download-badge-format'));
    $this->assertTrue($attributes->hasClass('download-badge-size'));
    $this->assertTrue($attributes->hasClass('page-title'));
    $this->assertTrue($attributes->hasClass('nav'));
    $this->assertTrue($attributes->hasClass('nav-tabs'));
    $this->assertTrue($attributes->hasClass('studio-saved-tabs'));
    $this->assertTrue($attributes->hasClass('event-supporters'));
    $this->assertTrue($attributes->hasClass('sponsor-logos'));
    $this->assertTrue($attributes->hasClass('event-press-quotes'));
    $this->assertTrue($attributes->hasClass('media-mentions'));
    $this->assertTrue($attributes->hasClass('aculta-card'));
    $this->assertTrue($attributes->hasClass('card'));
  }

  /**
   * Arrays of attributes become Attribute objects with mapped classes.
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
   * Positive unread counts become components; zero and invalid values do not.
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
