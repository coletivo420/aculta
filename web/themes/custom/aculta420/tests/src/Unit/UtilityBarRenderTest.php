<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta420\Unit;

use Drupal\aculta420\Hook\PortalUiHooks;
use Drupal\Core\Template\Attribute;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\Markup;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Verifies the rendered controls and their accessible count labels.
 */
#[Group('aculta420')]
final class UtilityBarRenderTest extends TestCase {

  /**
   * All four controls share the same structure; counts retain their meaning.
   */
  public function testControlsRenderWithCommonStructureAndCounts(): void {
    $root = dirname(__DIR__, 3);
    $twig = new Environment(new FilesystemLoader($root));
    $twig->addFilter(new TwigFilter('t', static fn($text, $args = []) => strtr($text, $args)));
    $twig->addFunction(new TwigFunction('attach_library', static fn($library) => ''));
    $attributes = new Attribute(['class' => ['use-ajax'], 'data-portal-ui' => 'search-utility']);
    $variables = ['trigger_attributes' => $attributes];
    (new PortalUiHooks())->preprocess($variables);
    $search = $twig->render('templates/portal-search-trigger.html.twig', [
      'url' => '/search',
      'label' => 'Buscar',
      'trigger_attributes' => new Markup((string) $attributes, 'UTF-8'),
    ]);
    $this->assertTrue($attributes->hasClass('use-ajax'));
    $this->assertTrue($attributes->hasClass('aculta-utility-trigger'));

    foreach ([0, 1, 101] as $count) {
      $html = $search;
      foreach (['cart-offcanvas', 'messenger-trigger', 'alerts-trigger'] as $component) {
        $html .= $twig->render("components/patterns/$component/$component.twig", [
          'count' => $count,
          'count_kind' => 'attention',
          'href' => '/preview',
          'all_href' => '',
          'empty' => TRUE,
          'banner' => '',
        ]);
      }
      $dom = new \DOMDocument();
      @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
      $xpath = new \DOMXPath($dom);
      $class = "contains(concat(' ', normalize-space(@class), ' '), ' aculta-utility-trigger ')";
      $icon = "contains(concat(' ', normalize-space(@class), ' '), ' aculta-utility-trigger__icon ')";
      $badge = "contains(concat(' ', normalize-space(@class), ' '), ' aculta-utility-trigger__count ')";
      $this->assertSame(4, $xpath->query("//*[$class]")->length);
      $this->assertSame(4, $xpath->query("//*[$class]/*[$icon]/i")->length);
      $this->assertSame(3, $xpath->query("//*[$class]/*[$icon]/*[$badge][@aria-hidden='true']")->length);
      foreach (['cart', 'messenger', 'alerts'] as $kind) {
        $node = $xpath->query("//*[@data-aculta-$kind-count]")->item(0);
        $this->assertSame((string) $count, $node->getAttribute('data-count'));
        $this->assertSame($count > 99 ? '99+' : (string) $count, trim($node->textContent));
      }
      foreach (['messenger' => 'mensagens', 'alerts' => 'avisos'] as $kind => $name) {
        $trigger = $xpath->query("//*[@data-aculta-$kind-count]/../..")->item(0);
        $label = $trigger->getAttribute('aria-label');
        $this->assertStringContainsString("Abrir $name", $label);
        if ($count > 0) {
          $this->assertStringContainsString((string) $count, $label);
        }
        else {
          $this->assertSame("Abrir $name", $label);
        }
      }
    }
  }

}
