# Foto de perfil: regras por estado

Motivo: a regra de cada estado da foto já regrediu duas vezes (remover no avatar do sistema, sortear com foto
personalizada, recorte que reabre sem novo envio). Este documento é a fonte das regras; o gate
`scripts/validate-aculta-user-photo.php` e o roteiro de navegador (TESTES-NAVEGADOR, seção P) verificam.

## Estados

| Estado | Prévia | Campo de envio | "Remover" | "Sortear outro avatar" | "Recortar foto" |
|---|---|---|---|---|---|
| Avatar do sistema (acervo, `public://pictures/acervo/`) | avatar do acervo | sim, vazio ("Escolha uma imagem") | **não** | **sim** | **não** |
| Avatar personalizado (enviado pela pessoa) | foto própria | sim, com a foto | **sim** (volta ao acervo ao salvar) | **não** | **não** |

Sem link "Recortar foto" em nenhum estado. O recorte abre sozinho logo após o envio de uma imagem, pelo JavaScript
`js/photo-flow.js`, e só para o arquivo recém-enviado.

## Regras (invariantes)

- **INV-FOTO-1 — avatar do sistema não tem "Remover".** O campo de envio recebe `#aculta_acervo = $isAcervo`; o
  `#after_build` `UserPictureForm::withoutRemoveForAcervo` retira `remove_button` nesse caso.
- **INV-FOTO-2 — sortear só para o acervo.** O link "Sortear outro avatar" é criado somente dentro de
  `if ($isAcervo)`. Com foto personalizada ele some.
- **INV-FOTO-3 — sem recorte manual.** Não existe link para recortar no popup. O recorte só é aberto pelo
  `photo-flow.js` ao chegar um novo arquivo no campo (`imagem[fids]`).
- **INV-FOTO-4 — recorte só do arquivo recém-enviado.** `AvatarCropForm::targetFile()` aceita apenas o `fid` do
  arquivo temporário do próprio usuário. Não há fallback para a foto atual nem para o recorte pendente, e foto já
  salva (permanente) é recusada.

"Remover" de um envio ainda não salvo (campo com um arquivo temporário, no acervo) também não aparece: o campo de
envio do acervo não tem remove_button. Para desfazer um envio, a pessoa envia outra imagem ou sai sem salvar.

## Onde está no código

- `src/Form/UserPictureForm.php`: estado (`$isAcervo`), padrão do campo, remove_button, sortear.
- `src/Form/AvatarCropForm.php`: `targetFile()` (INV-FOTO-4), `flattenCropWrappers` (recorte sem abas).
- `src/Hook/PhotoEditorHooks.php`: gatilho do popup (`aculta_portal_photo_editor_alter`).
- `js/photo-flow.js`: abertura automática do recorte após o envio; reabre o popup depois do recorte.
- `src/Avatar/AvatarPool.php::reroll()`: sortear troca a foto e descarta o recorte pendente.

## Barreiras

1. Gate: `php scripts/validate-aculta-user-photo.php` (estático; falha se um invariante sair do código).
2. Roteiro de navegador: TESTES-NAVEGADOR, seção P (executado pelo agente de testes após cada alteração na foto).
3. Revisão: qualquer diff em `UserPictureForm`, `AvatarCropForm`, `photo-flow.js` ou no gatilho de
   `PhotoEditorHooks` precisa conferir a tabela acima.

## Como não regredir

- Não adicionar "Remover" para o avatar do sistema, nem sortear para a foto personalizada.
- Não recriar "Recortar foto" nem fallback de recorte para foto já salva.
- Alterar uma regra exige alterar esta tabela, o gate e o roteiro no mesmo commit.
