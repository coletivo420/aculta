<?php

/**
 * Barreira do aculta_image: todo uso de imagem passa pelo sistema único (docs do submódulo, seção 3.8).
 * Somente leitura; não inicia o Drupal.
 *
 * Regra (AGENTS.md, "Imagens e arquivos de imagem"): tolerância zero. Vale para código novo e antigo. Código existente
 * fora do aculta_image deve ser refatorado; enquanto não for, o gate reprova e lista cada ocorrência.
 *
 * Verifica:
 * - R1: estilo de imagem renderizado ou referenciado fora do aculta_image (image_style, #theme image_style);
 * - R2: limite de upload próprio (FileExtension, FileSizeLimit, FileImageDimensions) fora da política;
 * - R3: upload de imagem em public:// fora da política;
 * - R4: processamento direto de imagem (ImageFactory, image.factory);
 * - R5: exclusão direta de arquivo (file_unmanaged_delete, delete() em File);
 * - R6: estilo de imagem em config/sync que não está no catálogo;
 * - R7: campo de imagem em config/sync sem a política do aculta_image em third_party_settings;
 * - R8: estilo de imagem em Twig ou CSS do tema.
 * - R9: estilos configurados em Views e modos de exibição, validados contra o catálogo.
 * - R10: cópia direta de imagens em mirroring de assets fora do aculta_image.
 *
 * Exceção aprovada (EXCECOES): conquista do aculta_game mantém 2 MB e 4096 px, somente no formulário da conquista.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$image = 'web/modules/custom/aculta_portal/modules/aculta_image/';
$catalogFile = $root . '/' . $image . 'src/ImageCatalog.php';

/** Exceções aprovadas: arquivo => [padrão => motivo]. Só o formulário da conquista. */
$EXCECOES = [
  'web/modules/custom/aculta_portal/modules/aculta_game/src/Form/AchievementDefinitionForm.php' => [
    'FileSizeLimit' => 'Exceção aprovada pelo responsável: a conquista mantém 2 MB próprios.',
    'FileImageDimensions' => 'Exceção aprovada pelo responsável: a conquista mantém 4096 px próprios.',
    'FileExtension' => 'Exceção aprovada pelo responsável: a conquista usa png/jpg/jpeg/webp.',
    'public://conquistas' => 'Exceção aprovada: pasta própria das conquistas.',
  ],
];

/** Caminhos que pertencem ao próprio sistema. */
$DONO = [$image];

/** Estilos do catálogo (lista lida de ImageCatalog::STYLES). */
$estilos = [];
if (is_file($catalogFile)) {
  if (preg_match('/const STYLES = \[(.*?)\];/s', (string) file_get_contents($catalogFile), $m)) {
    preg_match_all("/'([a-z0-9_]+)'/", $m[1], $nomes);
    $estilos = $nomes[1];
  }
}

$violacoes = [];
$registrar = static function (string $regra, string $arquivo, int $linha, string $trecho) use (&$violacoes): void {
  $violacoes[] = sprintf('%s  %s:%d  %s', $regra, $arquivo, $linha, trim($trecho));
};

$emDono = static function (string $rel) use ($DONO): bool {
  foreach ($DONO as $d) {
    if (str_starts_with($rel, $d)) {
      return TRUE;
    }
  }
  return FALSE;
};

$excecaoPara = static function (string $rel, string $padrao) use ($EXCECOES): bool {
  return isset($EXCECOES[$rel][$padrao]);
};

// Varredura de código PHP, Twig e CSS.
$dirs = ['web/modules/custom', 'web/themes/custom/aculta420'];
foreach ($dirs as $dir) {
  $base = $root . '/' . $dir;
  if (!is_dir($base)) {
    continue;
  }
  $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
  foreach ($it as $arquivo) {
    $caminho = $arquivo->getPathname();
    $rel = str_replace($root . '/', '', $caminho);
    if (!preg_match('/\.(php|module|inc|install|twig|css|js|yml)$/', $rel)
      || str_contains($rel, '/node_modules/') || str_contains($rel, '/vendor/') || str_contains($rel, '/docs/')
      || str_contains($rel, '/tests/') || str_ends_with($rel, '.md')) {
      continue;
    }
    if ($emDono($rel)) {
      continue;
    }
    $linhas = file($caminho, FILE_IGNORE_NEW_LINES) ?: [];
    $conteudoArquivo = implode("\n", $linhas);
    $assetImagePublisher = preg_match(
      '/if\s*\(in_array\(\$extension,\s*\[[^]]*(?:png|gif|jpe?g|webp|svg)[^]]*\].*?\)\s*\{[\s\S]{0,800}imagePublisher->publish\([\s\S]{0,400}?continue;\s*\}/i',
      $conteudoArquivo,
    );
    if (preg_match('/(?:scanDirectory|glob|RecursiveDirectoryIterator)[\s\S]{0,1200}\\.(?:jpe\?g|png|gif|svg|webp|bmp|ico)/i', $conteudoArquivo)
      && preg_match('/(?:fileSystem|file_system|filesystem)->copy\s*\(/i', $conteudoArquivo)
      && !$assetImagePublisher) {
      $linhaCopia = 1;
      foreach ($linhas as $indice => $linha) {
        if (preg_match('/(?:fileSystem|file_system|filesystem)->copy\s*\(/i', $linha)) {
          $linhaCopia = $indice + 1;
          break;
        }
      }
      $registrar('R10', $rel, $linhaCopia, 'cópia direta de arquivos em árvore que inclui imagens');
    }
    $ehTema = str_starts_with($rel, 'web/themes/custom/aculta420/');
    foreach ($linhas as $i => $linha) {
      $n = $i + 1;
      $trim = trim($linha);
      // Comentários não contam (documentação não é uso).
      if (str_starts_with($trim, '//') || str_starts_with($trim, '*') || str_starts_with($trim, '/*') || str_starts_with($trim, '#')) {
        continue;
      }
      // R1: uso da API de estilo (renderização, storage, carga) ou nome literal de estilo em chave de configuração.
      $usoApi = preg_match("/#theme.*image_style|getStorage\(['\"]image_style|ImageStyle::load|image_style_url/", $linha);
      $nomeLiteral = $estilos !== [] && preg_match(
        "/(image_style|style_name|preview_image_style)['\"]?\\s*(=>|:)\\s*['\"](" . implode('|', array_map('preg_quote', $estilos)) . ")['\"]/",
        $linha,
      );
      if (($usoApi || $nomeLiteral) && !$excecaoPara($rel, 'image_style')) {
        if ($ehTema && str_ends_with($rel, '.css')) {
          $registrar('R8', $rel, $n, $linha);
        }
        else {
          $registrar($ehTema ? 'R8' : 'R1', $rel, $n, $linha);
        }
      }
      foreach (['FileExtension', 'FileSizeLimit', 'FileImageDimensions'] as $validador) {
        if (str_contains($linha, $validador) && !$excecaoPara($rel, $validador)) {
          $registrar('R2', $rel, $n, $linha);
        }
      }
      if (preg_match("/upload_location.*public:\\/\\//", $linha) && !$excecaoPara($rel, 'public://conquistas')) {
        $registrar('R3', $rel, $n, $linha);
      }
      if (preg_match('/ImageFactory|image\.factory/', $linha)) {
        $registrar('R4', $rel, $n, $linha);
      }
      if (preg_match('/file_unmanaged_delete|file_delete\(/', $linha)
        || (preg_match('/\bFile\b|\$file\w*/', $linha) && str_contains($linha, '->delete()'))) {
        $registrar('R5', $rel, $n, $linha);
      }
    }
  }
}

// Configuração exportada: estilos e campos de imagem.
foreach (glob($root . '/config/sync/image.style.*.yml') ?: [] as $arquivo) {
  $id = preg_replace('/^image\.style\.|\.yml$/', '', basename($arquivo));
  if (!in_array($id, $estilos, TRUE)) {
    $violacoes[] = sprintf('R6  config/sync/%s  estilo fora do catálogo (%s)', basename($arquivo), $id);
  }
}
foreach (glob($root . '/config/sync/field.field.*.yml') ?: [] as $arquivo) {
  $texto = (string) file_get_contents($arquivo);
  $ehImagem = preg_match('/^field_type: image$/m', $texto)
    || (preg_match('/^field_type: file$/m', $texto) && preg_match('/file_extensions: .*(jpe?g|png|webp|gif)/', $texto));
  if ($ehImagem && !preg_match('/^\s*aculta_image:/m', $texto)) {
    $violacoes[] = sprintf('R7  config/sync/%s  campo de imagem sem política do aculta_image em third_party_settings', basename($arquivo));
  }
}

// Views e modos de exibição precisam apontar para estilos pertencentes ao
// catálogo do aculta_image. Core/contrib ainda recebem o ID exigido pela API,
// mas a configuração exportada é a fronteira controlada pelo catálogo.
$configuracoesComFormatadores = array_merge(
  glob($root . '/config/sync/views.view.*.yml') ?: [],
  glob($root . '/config/sync/core.entity_view_display.*.yml') ?: [],
);
foreach ($configuracoesComFormatadores as $arquivo) {
  $linhas = file($arquivo, FILE_IGNORE_NEW_LINES) ?: [];
  foreach ($linhas as $i => $linha) {
    if (preg_match('/^\s*(?:image_style|photoswipe_(?:thumbnail|image)_style):\s*([a-z0-9_]+)\s*$/', $linha, $m)
      && !in_array($m[1], $estilos, TRUE)) {
      $registrar('R9', str_replace($root . '/', '', $arquivo), $i + 1, $linha);
    }
  }
}

$sistema = $violacoes === [];
echo "Barreira do aculta_image (tolerância zero; ver docs/PLANO.md, seção 3.8)\n";
echo sprintf("Estilos no catálogo: %d\n", count($estilos));
if ($sistema) {
  echo "PASS: nenhum uso de imagem fora do aculta_image.\n";
  exit(0);
}
echo sprintf("FAIL: %d ocorrência(s) fora do aculta_image:\n", count($violacoes));
foreach ($violacoes as $v) {
  echo '  ' . $v . "\n";
}
exit(1);
