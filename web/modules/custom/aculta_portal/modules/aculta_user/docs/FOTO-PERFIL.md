# Foto de perfil: regras por estado

Motivo: a regra de cada estado da foto já regrediu duas vezes (remover no avatar do sistema, sortear com foto
personalizada, recorte que reabre sem novo envio). Este documento é a fonte das regras; o gate
`scripts/validate-aculta-user-photo.php` e o roteiro de navegador (TESTES-NAVEGADOR, seção P) verificam.

## Ações

"Sortear novo avatar" (acervo) e "Remover foto" (personalizada) ficam na mesma linha de ações, ao lado de "Salvar foto do perfil".

## Contrato do sorteio e da foto

- **Sortear** (`aculta_user.avatar_reroll`, AJAX): escolhe uma entrada do catálogo, guarda como escolha pendente
  (`user.data` `sorteio_pendente`) e troca **só a prévia dentro do modal** (`.ui-dialog [data-aculta-user-avatar]`).
  Não grava a foto e não altera a visão geral.
- **Salvar foto do perfil** aplica a escolha pendente (`AvatarPool::applyPending`): copia para o acervo, troca a foto,
  apaga a cópia anterior do acervo se ninguém mais a usa, e recarrega o painel.
- **Abrir o modal** (GET sem entrada) descarta escolha pendente. **Fechar sem salvar** descarta a escolha.
- **Remover foto** (personalizada) é imediato: sorteia um avatar do acervo e recarrega o painel.
- **Foto importada por OAuth** é personalizada (ver `docs/integrations/GOOGLE.md`).
- Marcadores: `data-aculta-user-avatar` identifica os avatares (gatilho e prévia). Módulo entrega dados e rotas; o tema só
  apresenta. Nenhum dos dois decide a regra de estado.

## Estados

| Estado | Prévia | Campo de envio | "Remover" | "Sortear novo avatar" | "Recortar foto" |
|---|---|---|---|---|---|
| Avatar do sistema (acervo, `public://pictures/acervo/`) | avatar do acervo | sim, vazio ("Escolha uma imagem") | **não** | **sim** | **não** |
| Avatar personalizado (enviado pela pessoa) | foto própria (só a prévia) | sim, vazio | **sim**, botão "Remover foto" (volta a um avatar sorteado do acervo e recarrega o painel) | **não** | **não** |

Sem link "Recortar foto" em nenhum estado. Aplicar o recorte troca a foto e recarrega o painel. O recorte abre sozinho logo após o envio de uma imagem, pelo JavaScript
`js/photo-flow.js`, e só para o arquivo recém-enviado.

## Regras (invariantes)

- **INV-FOTO-1 — avatar do sistema não tem "Remover".** O campo de envio recebe `#aculta_acervo = $isAcervo`; o
  `#after_build` `UserPictureForm::withoutRemoveForAcervo` retira `remove_button` nesse caso.
- **INV-FOTO-2 — sortear só para o acervo.** O link "Sortear novo avatar" é criado somente dentro de
  `if ($isAcervo)`. Com foto personalizada ele some.
- **INV-FOTO-3 — sem recorte manual.** Não existe link para recortar no popup. O recorte só é aberto pelo
  `photo-flow.js` ao chegar um novo arquivo no campo (`imagem[fids]`).
- **INV-FOTO-8 — sortear só troca a prévia; a foto muda ao salvar.** "Sortear novo avatar" troca por AJAX apenas a
  prévia dentro do modal e guarda a escolha pendente. Só "Salvar foto do perfil" grava a foto (e recarrega o painel).
  Fechar o modal sem salvar descarta a escolha. Nada é gravado no sorteio.
- **INV-FOTO-9 — foto importada por OAuth é personalizada.** A foto baixada pelo Social Auth na criação da conta fica
  permanente e fora do acervo (`AccountCreationHooks::userPresave`).
- **INV-FOTO-7 — o popup não mostra nome nem link da foto atual.** O campo de envio começa vazio nos dois estados
  (`#default_value` NULL). Na foto personalizada, a única ação de remoção é o botão "Remover foto"
  (`UserPictureForm::removePersonal`, `AvatarPool::reroll`): volta a um avatar sorteado do acervo e recarrega o painel.
  "Salvar" com o campo vazio não altera a foto.
- **INV-FOTO-6 — recorte aplicado troca a foto e recarrega o painel.** "Aplicar recorte" torna o arquivo permanente,
  grava-o como foto da conta e redireciona ao painel, sem parâmetro e sem reabrir o popup. Não há etapa de "Salvar"
  depois do recorte. O loop de reabertura era causado pela reabertura automática do popup ao voltar.
- **INV-FOTO-5 — uma única caixa de recorte.** O achatamento das abas do `image_crop` troca o tipo e o
  `#theme_wrappers` e limpa o `#pre_render`; sem isso o pre_render reagrupa os filhos e o campo sai duplicado.
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
