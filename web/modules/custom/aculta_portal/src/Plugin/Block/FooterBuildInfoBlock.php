<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Plugin\Block;

use Drupal\Component\Utility\Html;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Linha legal do rodapé: copyright com ano atual e versão pelo hash do commit.
 *
 * O ano é calculado a cada renderização (cache de uma hora). O hash curto vem de
 * .git/HEAD, lido diretamente, sem executar git. Sem repositório, mostra "desconhecida".
 */
#[Block(
  id: 'aculta_footer_build_info',
  admin_label: new TranslatableMarkup('Rodapé: copyright e versão'),
  category: new TranslatableMarkup('ACULTA'),
)]
final class FooterBuildInfoBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $year = (new \DateTimeImmutable('now', new \DateTimeZone('America/Sao_Paulo')))->format('Y');
    $version = Html::escape(self::shortHash());

    return [
      '#markup' => sprintf(
        '<span>© %s ACULTA</span><span>Software livre · versão %s</span>',
        $year,
        $version,
      ),
      '#cache' => [
        'max-age' => 3600,
      ],
    ];
  }

  /**
   * Retorna os 7 primeiros caracteres do commit atual, ou "desconhecida".
   */
  public static function shortHash(): string {
    $root = dirname((string) \Drupal::root());
    $head_file = $root . '/.git/HEAD';
    if (!is_readable($head_file)) {
      return 'desconhecida';
    }
    $head = trim((string) file_get_contents($head_file));
    if (str_starts_with($head, 'ref: ')) {
      $ref = substr($head, 5);
      $ref_file = $root . '/.git/' . $ref;
      if (is_readable($ref_file)) {
        $sha = trim((string) file_get_contents($ref_file));
      }
      else {
        $sha = self::packedRef($root, $ref) ?? '';
      }
    }
    else {
      $sha = $head;
    }
    return preg_match('/^[0-9a-f]{40}$/', $sha) === 1 ? substr($sha, 0, 7) : 'desconhecida';
  }

  /**
   * Procura uma referência em .git/packed-refs.
   */
  private static function packedRef(string $root, string $ref): ?string {
    $file = $root . '/.git/packed-refs';
    if (!is_readable($file)) {
      return null;
    }
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
      if (str_ends_with($line, ' ' . $ref)) {
        return explode(' ', $line, 2)[0];
      }
    }
    return null;
  }

}
