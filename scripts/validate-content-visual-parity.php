<?php

/**
 * Gate: modelo visual idêntico entre Notícia (article) e Editorial (blog).
 *
 * Regra do responsável (2026-10-14): Editorial/Blog e Notícia têm o mesmo modelo visual. Nas visualizações
 * (padrão, teaser e completa), os campos, a ordem e as configurações de exibição são os mesmos. Diferença
 * visual só é permitida se estiver listada abaixo, com motivo.
 *
 * Diferenças de formulário que são funcionais (não visuais) também ficam listadas: o Editorial passa pelo
 * fluxo de moderação; a Notícia não.
 *
 * Só lê config/sync. Não importa configuração nem consulta o Runtime.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;

$root = dirname(__DIR__);
$errors = [];

// Diferenças permitidas (chave => motivo). Só aceitas com motivo funcional.
$allowed_form_diffs = [
  'moderation_state' => 'Editorial passa pelo fluxo de moderação (workflow editorial); Notícia não.',
];

$view_modes = ['default', 'teaser', 'full'];
foreach ($view_modes as $mode) {
  $article = $root . "/config/sync/core.entity_view_display.node.article.$mode.yml";
  $blog = $root . "/config/sync/core.entity_view_display.node.blog.$mode.yml";
  if (!is_file($blog)) {
    $errors[] = "visualização '$mode' do Editorial (blog) ausente";
    continue;
  }
  if (!is_file($article)) {
    $errors[] = "visualização '$mode' da Notícia (article) ausente";
    continue;
  }
  $a = Yaml::parseFile($article)['content'] ?? [];
  $b = Yaml::parseFile($blog)['content'] ?? [];
  if ($a !== $b) {
    $only_a = array_diff_key($a, $b);
    $only_b = array_diff_key($b, $a);
    $changed = array_keys(array_filter(array_intersect_key($a, $b), fn($k) => $a[$k] !== $b[$k]));
    $errors[] = "visualização '$mode' difere: só na Notícia=[" . implode(',', array_keys($only_a)) . '] só no Editorial=[' . implode(',', array_keys($only_b)) . '] alterados=[' . implode(',', $changed) . ']';
  }
}

$form_a = Yaml::parseFile($root . '/config/sync/core.entity_form_display.node.article.default.yml')['content'] ?? [];
$form_b = Yaml::parseFile($root . '/config/sync/core.entity_form_display.node.blog.default.yml')['content'] ?? [];
foreach (array_diff_key($form_b, $form_a) as $field => $_) {
  if (!isset($allowed_form_diffs[$field])) {
    $errors[] = "formulário do Editorial tem '$field' que a Notícia não tem, sem motivo registrado";
  }
}
foreach (array_diff_key($form_a, $form_b) as $field => $_) {
  $errors[] = "formulário da Notícia tem '$field' que o Editorial não tem";
}

if ($errors !== []) {
  echo "CONTENT VISUAL PARITY: FAIL\n";
  foreach ($errors as $e) {
    echo " - $e\n";
  }
  exit(1);
}
echo 'CONTENT VISUAL PARITY: PASS (' . count($view_modes) . ' visualizações iguais; formulário com ' . count($allowed_form_diffs) . " diferença funcional registrada)\n";
