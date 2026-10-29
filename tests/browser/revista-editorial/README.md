# Revista — fluxo editorial (ARQ-08)

`flow.py` valida em Firefox ESR visível o fluxo editorial do único bundle
`revista`, no servidor de desenvolvimento (`https://aculta.toca.net.br`) e no
Runtime real:

- criação pelo formulário nativo com CKEditor, seletor de moderação e mídia;
- erros de validação do Form API em campo principal e em campo secundário;
- edição, salvamento, novas revisões e pré-visualização;
- transições `draft → review → published → archived → draft`;
- acesso do editor, da conta sem papel editorial e do visitante anônimo;
- formulário sem JavaScript;
- chamada, resumo, imagem, autoria, compartilhamento, metatags e JSON-LD na
  publicação pública;
- comentário real: conta moderada (primeiro comentário retido) e editor
  isento, com verificação de visibilidade anônima.

## Contas e conteúdo

O script usa contas `agente-teste-` criadas para a sessão (regra em
`docs/portal/GUARDRAILS.md`), com `content_editor` e `email_confirmed`:

```sh
REVISTA_EDITOR_UID=255 REVISTA_READER_UID=256
```

O login usa link de uso único gerado por Drush (`user_pass_reset_url`), sem
senha no script. O aceite único dos termos foi registrado pelo serviço real
para as contas efêmeras.

O script **cria uma publicação** `[AGENTE ARQ-08] <epoch>` e imprime o NID no
fim. A publicação e as contas devem ser excluídas ao fim da sessão, conforme a
regra de usuários/conteúdo de teste.

## Execução

```sh
DISPLAY=:0 SUITE_OUT=/home/piradopirata/worktrees/suite-out/revista-editorial \
  python3 -I tests/browser/revista-editorial/flow.py
```

Variáveis: `REVISTA_BASE_URL`, `REVISTA_SERVED_ROOT` (checkout servido, usado
pelo Drush), `REVISTA_EDITOR_UID`, `REVISTA_READER_UID`, `SUITE_OUT`.
Capturas e `report.json` ficam em `$SUITE_OUT`; o perfil Firefox temporário é
criado e removido pelo `wdlib`.

## Limitação conhecida

Em Drupal 11.4.10, o botão visível **Inserir selecionado(s)** da biblioteca de
mídia fica fora do `<form>` (jQuery UI o move para o rodapé do diálogo). O POST
chega sem `media_library_select_form[...]` e o Core lança
`array_filter(): Argument #1 must be of type array, null given` em
`MediaLibrarySelectForm::viewsFormValidate()`; o watchdog registra o
`TypeError`. O defeito é do Core/media_library, não do `aculta_magazine`.

O script registra a falha em `media_library_visual_failure` e usa o caminho AJAX
equivalente (envio do `FormData` completo e execução dos comandos
`invoke val` / `invoke trigger mousedown` / `closeDialog`), que é exatamente o
que o Core devolveria. A seleção resultante é a mesma e o restante do fluxo é
validado normalmente.
