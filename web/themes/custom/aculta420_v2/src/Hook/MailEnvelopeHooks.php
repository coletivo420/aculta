<?php

declare(strict_types=1);

namespace Drupal\aculta420_v2\Hook;

use Drupal\Core\Extension\ExtensionList;
use Drupal\Core\Hook\Attribute\Hook;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Apresentação do envelope HTML dos e-mails (aculta_mailer_wrap), montada pelo tema V2.
 *
 * As cores vêm dos tokens do próprio tema (src/styles/tokens.css), lidos a cada montagem: quando um token muda, o e-mail
 * muda junto, sem alteração manual. O modo escuro usa as sobrescritas do bloco :root[data-theme="dark"] do mesmo arquivo.
 * O logo vem do asset do próprio tema, sem depender da configuração da V1.
 */
final class MailEnvelopeHooks {

  /**
   * Caminho do logo do envelope, relativo ao tema.
   */
  private const LOGO = 'assets/logos/aculta420-horizontal-white-900x300.png';

  /**
   * Papel visual do e-mail => token do tema V2.
   */
  private const ROLES = [
    'page' => '--color-canvas',
    'card' => '--color-surface-raised',
    'text' => '--color-text-primary',
    'header' => '--color-brand-primary',
    'footer' => '--color-surface-institution',
    'footer_text' => '--color-text-secondary',
    'accent' => '--color-link-editorial',
  ];

  public function __construct(
    #[Autowire(service: 'extension.list.theme')]
    private readonly ExtensionList $themes,
  ) {}

  /**
   * Implements hook_preprocess_HOOK() para o envelope de e-mail.
   */
  #[Hook('preprocess_aculta_mailer_wrap')]
  public function preprocess(array &$variables): void {
    $base = (string) ($variables['public_base'] ?? '');
    $variables['theme_logo'] = $base !== '' ? $base . self::LOGO : '/' . self::LOGO;

    $tokens = $this->tokens();
    $light = [];
    $dark = [];
    foreach (self::ROLES as $role => $token) {
      $light[$role] = $tokens['light'][$token] ?? '#000000';
      $dark[$role] = $tokens['dark'][$token] ?? $light[$role];
    }
    // Modo de cor do site (o mesmo controle da aparência): light, dark ou auto.
    $mode = in_array($variables['color_mode'] ?? NULL, ['light', 'dark', 'auto'], TRUE) ? $variables['color_mode'] : 'auto';
    if ($mode === 'light') {
      $dark = $light;
    }
    elseif ($mode === 'dark') {
      $light = $dark;
    }
    $variables['color_mode'] = $mode;
    $variables['mail_colors'] = ['light' => $light, 'dark' => $dark];
    // Com layout do aculta_mailer, o rodapé da marca já está no corpo: o envelope não repete o rodapé.
    $variables['show_footer'] = empty($variables['layout']);
  }

  /**
   * Valores dos tokens em claro e em escuro (escuro = claro sobreposto pelo bloco :root[data-theme="dark"]).
   *
   * @return array{light: array<string, string>, dark: array<string, string>}
   *   Valores resolvidos, em claro e em escuro.
   */
  private function tokens(): array {
    $css = (string) @file_get_contents($this->themes->getPath('aculta420_v2') . '/src/styles/tokens.css');
    // Claro: o primeiro bloco :root (sem seletor de atributo).
    $light = preg_match('/:root\s*\{([^}]*)\}/', $css, $lightBlock) === 1 ? $this->declarations($lightBlock[1]) : [];
    // Escuro: o bloco :root[data-theme="dark"], aplicado sobre o claro.
    $dark = preg_match('/:root\[data-theme="dark"\]\s*\{([^}]*)\}/', $css, $darkBlock) === 1
      ? array_replace($light, $this->declarations($darkBlock[1]))
      : $light;
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
