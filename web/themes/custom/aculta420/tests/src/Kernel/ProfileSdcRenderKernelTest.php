<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta420\Kernel;

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\KernelTests\Components\ComponentKernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Proves that the private-profile SDCs render with Core props and slots.
 *
 * Uses only synthetic content. No account, profile, storage or permissions
 * are supplied by theme components; those belong to the Studio/User owners.
 */
#[Group('aculta420')]
final class ProfileSdcRenderKernelTest extends ComponentKernelTestBase {

  /**
   * The ACULTA420 theme and its Bootstrap base are installed by the test base.
   *
   * @var string[]
   */
  protected static $themes = ['aculta420'];

  /**
   * All four component IDs are discoverable by Drupal SDC plugin manager.
   */
  public function testDiscoveryOfPrivateProfileComponents(): void {
    foreach (['profile-editor', 'profile-identity', 'profile-bio', 'profile-social'] as $name) {
      self::assertTrue($this->manager->hasDefinition('aculta420:' . $name), $name);
    }
  }

  /**
   * Render real nested slots, escaped props, actions and empty profile state.
   */
  public function testRenderNestedProfileEditor(): void {
    $build = [
      '#type' => 'component',
      '#component' => 'aculta420:profile-editor',
      '#attributes' => ['data-test-profile-root' => 'yes'],
      '#slots' => [
        'intro' => ['#plain_text' => 'Apenas informações comunitárias.'],
        'identity' => [
          '#type' => 'component',
          '#component' => 'aculta420:profile-identity',
          '#props' => [
            'display_name' => '<Conta privada>',
            'hint' => 'Apelido público separado do cadastro.',
          ],
          '#slots' => [
            'actions' => [
              '#type' => 'html_tag',
              '#tag' => 'a',
              '#value' => 'Editar apelido',
              '#attributes' => ['href' => '#editar-apelido'],
            ],
          ],
        ],
        'biography' => [
          '#type' => 'component',
          '#component' => 'aculta420:profile-bio',
          '#props' => ['title' => 'Biografia'],
          '#slots' => ['body' => ['#plain_text' => 'Ainda sem biografia.']],
        ],
        'social' => [
          '#type' => 'component',
          '#component' => 'aculta420:profile-social',
          '#props' => ['title' => 'Redes sociais'],
          '#slots' => ['items' => ['#plain_text' => 'Instagram: não informado.']],
        ],
      ],
      '#cache' => [
        'contexts' => ['user', 'user.permissions'],
        'tags' => ['user:7', 'profile:15'],
        'max-age' => 0,
      ],
    ];
    $metadata = new BubbleableMetadata();
    $crawler = $this->renderComponentRenderArray($build, $metadata);

    self::assertCount(1, $crawler->filter('.aculta-profile-editor[data-test-profile-root="yes"]'));
    self::assertCount(1, $crawler->filter('.aculta-profile-identity'));
    self::assertCount(1, $crawler->filter('.aculta-profile-bio'));
    self::assertCount(1, $crawler->filter('.aculta-profile-social'));
    self::assertSame('<Conta privada>', $crawler->filter('.aculta-profile-identity__name')->text());
    self::assertStringNotContainsString('<Conta privada>', $crawler->html());
    self::assertCount(1, $crawler->filter('.aculta-profile-identity__actions a'));
    self::assertCount(0, $crawler->filter('.aculta-profile-identity__public-link'));
    self::assertStringContainsString('Ainda sem biografia.', $crawler->filter('.aculta-profile-bio__body')->text());
    self::assertStringContainsString('não informado', $crawler->filter('.aculta-profile-social__items')->text());
    self::assertSame(0, $metadata->getCacheMaxAge());
    self::assertContains('user:7', $metadata->getCacheTags());
    self::assertContains('profile:15', $metadata->getCacheTags());
  }

  /**
   * A missing optional slot must not generate unauthorized public actions.
   */
  public function testEmptyProfileNeverInventsLinks(): void {
    $build = [
      '#type' => 'component',
      '#component' => 'aculta420:profile-editor',
      '#slots' => [
        'identity' => [
          '#type' => 'component',
          '#component' => 'aculta420:profile-identity',
          '#props' => ['display_name' => 'Apelido não informado'],
        ],
      ],
    ];
    $crawler = $this->renderComponentRenderArray($build);
    self::assertCount(1, $crawler->filter('.aculta-profile-identity__avatar--initial'));
    self::assertCount(0, $crawler->filter('a'));
    self::assertCount(0, $crawler->filter('.aculta-profile-editor__biography'));
    self::assertCount(0, $crawler->filter('.aculta-profile-editor__social'));
  }

}
