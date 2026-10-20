<?php

declare(strict_types=1);

namespace Drupal\aculta420\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ExtensionList;
use Drupal\Core\Hook\Attribute\Hook;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Apresentação do envelope HTML dos e-mails (aculta_mailer_wrap), montada pelo tema.
 *
 * As cores vêm dos tokens do próprio tema (css/tokens.css), lidos a cada montagem: quando um token muda, o e-mail muda
 * junto, sem alteração manual. O modo escuro usa as sobrescritas do bloco prefers-color-scheme do mesmo arquivo.
 */
final class MailEnvelopeHooks {

  /**
   * Papel visual do e-mail => token do tema.
   */
  private const ROLES = [
    'page' => '--aculta-surface-page',
    'card' => '--aculta-surface-raised',
    'text' => '--aculta-text-primary',
    'header' => '--aculta-green-dark',
    'footer' => '--aculta-surface-muted',
    'footer_text' => '--aculta-text-secondary',
    'accent' => '--aculta-text-accent',
  ];

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    #[Autowire(service: 'extension.list.theme')]
    private readonly ExtensionList $themes,
  ) {}

  /**
   * Implements hook_preprocess_HOOK() para o envelope de e-mail.
   */
  #[Hook('preprocess_aculta_mailer_wrap')]
  public function preprocess(array &$variables): void {
    $path = ltrim((string) $this->configFactory->get('aculta420.settings')->get('logo.path'), '/');
    $base = (string) ($variables['public_base'] ?? '');
    $variables['theme_logo'] = $base !== '' ? $base . $path : '/' . $path;

    $tokens = $this->tokens();
    $light = [];
    $dark = [];
    foreach (self::ROLES as $role => $token) {
      $light[$role] = $tokens['light'][$token] ?? '#000000';
      $dark[$role] = $tokens['dark'][$token] ?? $light[$role];
    }
    $variables['mail_colors'] = ['light' => $light, 'dark' => $dark];
  }

  /**
   * Valores dos tokens em claro e em escuro (escuro = claro sobreposto pelo bloco prefers-color-scheme).
   *
   * @return array{light: array<string, string>, dark: array<string, string>}
   *   Valores resolvidos, em claro e em escuro.
   */
  private function tokens(): array {
    $css = (string) @file_get_contents($this->themes->getPath('aculta420') . '/css/tokens.css');
    // Claro: o primeiro bloco :root do arquivo (os demais blocos de tema não entram).
    $rootAt = strpos($css, ':root');
    $rootEnd = $rootAt === FALSE ? FALSE : strpos($css, '}', $rootAt);
    $lightCss = $rootAt === FALSE || $rootEnd === FALSE ? '' : substr($css, $rootAt, $rootEnd - $rootAt + 1);
    // Escuro: o bloco prefers-color-scheme, aplicado sobre o claro.
    $marker = '@media (prefers-color-scheme: dark)';
    $pos = strpos($css, $marker);
    $darkCss = $pos === FALSE ? '' : substr($css, $pos);
    $light = $this->declarations($lightCss);
    $dark = array_replace($light, $this->declarations($darkCss));
    return ['light' => $this->resolve($light), 'dark' => $this->resolve($dark)];
  }

  /**
   * Declarações --nome: valor de um trecho de CSS.
   *
   * @return array<string, string>
   *   Valores por nome de token.
   */
  private function declarations(string $css): array {
    preg_match_all('/--([a-z0-9-]+)\s*:\s*([^;]+);/i', $css, $matches, PREG_SET_ORDER);
    $out = [];
    foreach ($matches as $match) {
      $out['--' . $match[1]] = trim($match[2]);
    }
    return $out;
  }

  /**
   * Substitui var(--token) pelo valor, em profundidade limitada.
   *
   * @param array<string, string> $values
   *   Valores por nome de token.
   *
   * @return array<string, string>
   *   Valores com as referências substituídas.
   */
  private function resolve(array $values): array {
    $resolved = [];
    foreach ($values as $name => $value) {
      $current = $value;
      for ($i = 0; $i < 5 && preg_match('/var\((--[a-z0-9-]+)\)/i', $current, $ref) === 1; $i++) {
        $current = str_replace($ref[0], $values[$ref[1]] ?? '', $current);
      }
      $resolved[$name] = $current;
    }
    return $resolved;
  }

}
