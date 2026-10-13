<?php

/**
 * Gate: regras de estado da foto de perfil (aculta_user). Ver web/modules/custom/aculta_portal/modules/aculta_user/docs/FOTO-PERFIL.md.
 *
 * Regras (invariantes INV-FOTO-*):
 * - INV-FOTO-1: avatar do sistema (acervo) não tem botão "Remover" (o campo de envio não recebe remove_button).
 * - INV-FOTO-2: "Sortear outro avatar" só existe quando a foto atual é do acervo (if ($isAcervo)).
 * - INV-FOTO-3: não existe link "Recortar foto" no popup; o recorte só abre logo após o envio (photo-flow.js).
 * - INV-FOTO-4: o recorte só aceita o arquivo recém-enviado: nada de fallback para a foto atual nem para o recorte pendente.
 * - INV-FOTO-5: o campo de recorte aparece uma única vez no modal.
 * - INV-FOTO-6: aplicar o recorte troca a foto da conta e recarrega o painel, sem reabrir o popup.
 * - INV-FOTO-8: sortear atualiza os avatares por AJAX; fechar o modal depois do sorteio recarrega a página.
 * - INV-FOTO-7: o popup não mostra nome nem link da foto atual; "Remover foto" (só personalizada) volta a um avatar sorteado do acervo.
 *
 * Verificação estática do código-fonte (não depende de servidor). A verificação de navegador está no roteiro do docs.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$module = $root . '/web/modules/custom/aculta_portal/modules/aculta_user';
$errors = [];

$read = static function (string $relative) use ($module): string {
  $file = $module . '/' . $relative;
  if (!is_file($file)) {
    return '';
  }
  return (string) file_get_contents($file);
};

$picture = $read('src/Form/UserPictureForm.php');
$crop = $read('src/Form/AvatarCropForm.php');
$hooks = $read('src/Hook/PhotoEditorHooks.php');
$flow = $read('js/photo-flow.js');

if ($picture === '' || $crop === '') {
  fwrite(STDERR, "ACULTA USER PHOTO FAIL:\n- arquivos do formulário de foto ausentes\n");
  exit(1);
}

// INV-FOTO-1: remover só sai do campo quando a foto não é do acervo.
if (!str_contains($picture, "'#aculta_acervo' => \$isAcervo")) {
  $errors[] = 'INV-FOTO-1: o campo de envio não recebe #aculta_acervo = $isAcervo';
}
if (!preg_match('/function withoutRemoveForAcervo\(.*?unset\(\$element\[\'remove_button\'\]\)/s', $picture)) {
  $errors[] = "INV-FOTO-1: withoutRemoveForAcervo não remove 'remove_button'";
}
if (!str_contains($picture, "'#after_build' => [[self::class, 'withoutRemoveForAcervo']]")) {
  $errors[] = 'INV-FOTO-1: withoutRemoveForAcervo não está ligado como #after_build do campo';
}

// INV-FOTO-2: sortear só para acervo.
$sortearPos = strpos($picture, "\$form['sortear']");
$acervoPos = strpos($picture, 'if ($isAcervo) {');
if ($sortearPos === FALSE) {
  $errors[] = "INV-FOTO-2: \$form['sortear'] ausente";
}
elseif ($acervoPos === FALSE || $acervoPos > $sortearPos || str_contains(substr($picture, $acervoPos, $sortearPos - $acervoPos), '}')) {
  $errors[] = "INV-FOTO-2: \$form['sortear'] não está dentro de 'if (\$isAcervo)'";
}

// INV-FOTO-3: sem link de recorte no popup nem no painel.
if (preg_match('/aculta_user\.avatar_crop/', $picture) || preg_match('/aculta_user\.avatar_crop/', $hooks)) {
  $errors[] = "INV-FOTO-3: link de recorte ('aculta_user.avatar_crop') presente no formulário do popup";
}
if (str_contains($picture, 'Recortar foto') || str_contains($hooks, 'Recortar foto')) {
  $errors[] = "INV-FOTO-3: texto 'Recortar foto' presente no popup";
}
if (!str_contains($flow, 'openCrop(fid)')) {
  $errors[] = 'INV-FOTO-3: photo-flow.js não abre o recorte ao receber o envio (openCrop)';
}

// INV-FOTO-5: o recorte aparece uma única vez (o achatamento limpa o pre_render que reagrupa os filhos).
if (!str_contains($crop, "\$element[\$key]['#pre_render'] = [];") || !str_contains($crop, "\$element['#pre_render'] = [];")) {
  $errors[] = 'INV-FOTO-5: flattenElement não limpa #pre_render; o campo de recorte sai duplicado';
}

// INV-FOTO-7: o campo de envio não mostra a foto atual (nome e link); "Remover foto" é botão próprio, só para foto personalizada, e sorteia do acervo.
if (!str_contains($picture, "'#default_value' => NULL")) {
  $errors[] = 'INV-FOTO-7: o campo de envio tem valor padrão (mostra nome/link da foto atual)';
}
if (!preg_match("/if \\(!\\\$isAcervo && \\\$current > 0\\) \\{.*?'remover'.*?'::removePersonal'/s", $picture)) {
  $errors[] = "INV-FOTO-7: botão 'remover' com '::removePersonal' ausente ou fora do estado de foto personalizada";
}
if (!preg_match('/function removePersonal\(.*?avatarPool->reroll\(\$account\)/s', $picture)) {
  $errors[] = 'INV-FOTO-7: removePersonal não sorteia do acervo (AvatarPool::reroll)';
}

// INV-FOTO-8: sortear atualiza os avatares por AJAX e marca o body; fechar o modal depois disso recarrega a página.
$reroll = $read('src/Controller/AvatarRerollController.php');
if (!str_contains($reroll, "data-aculta-avatar-changed")) {
  $errors[] = 'INV-FOTO-8: o sorteio não marca o body (data-aculta-avatar-changed)';
}
if (!str_contains($flow, "dialog:afterclose") || !str_contains($flow, "window.location.reload()")) {
  $errors[] = 'INV-FOTO-8: photo-flow.js não recarrega a página ao fechar o modal depois do sorteio';
}

// INV-FOTO-6: aplicar o recorte troca a foto da conta e recarrega o painel; não há etapa de "Salvar" nem reabertura do popup.
if (str_contains($crop, 'PENDING_KEY') || str_contains($crop, 'aculta_recorte') || !str_contains($crop, "set('user_picture'")) {
  $errors[] = 'INV-FOTO-6: o recorte não troca a foto da conta, ou volta ao recorte pendente / reabertura do popup';
}
if (str_contains($flow, 'aculta_recorte') || str_contains($flow, 'reopen')) {
  $errors[] = 'INV-FOTO-6: photo-flow.js reabre o popup ao voltar (loop de reabertura)';
}

// INV-FOTO-4: recorte só do arquivo recém-enviado.
if (!preg_match('/function targetFile\(\).*?\$file->isPermanent\(\)/s', $crop)) {
  $errors[] = 'INV-FOTO-4: targetFile não recusa arquivo permanente (foto já salva)';
}
$targetStart = strpos($crop, 'function targetFile(');
$targetEnd = $targetStart === FALSE ? FALSE : strpos($crop, '/** Imagem que não é do acervo', $targetStart);
if ($targetStart === FALSE || $targetEnd === FALSE) {
  $errors[] = 'INV-FOTO-4: targetFile não encontrado';
}
else {
  $target = substr($crop, $targetStart, $targetEnd - $targetStart);
  if (str_contains($target, 'PENDING_KEY') || str_contains($target, "'user_picture'")) {
    $errors[] = 'INV-FOTO-4: targetFile voltou a usar o recorte pendente ou a foto atual';
  }
}

if ($errors !== []) {
  fwrite(STDERR, "ACULTA USER PHOTO FAIL:\n- " . implode("\n- ", $errors) . "\n");
  exit(1);
}

echo 'ACULTA USER PHOTO: PASS (INV-FOTO-1 a INV-FOTO-8)' . PHP_EOL;
