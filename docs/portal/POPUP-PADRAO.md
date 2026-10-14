# Popup padrão do Portal: modal do Drupal

Data: 2026-10-14. Gate: `php scripts/validate-portal-popup-rule.php`.

## Regra

1. **O popup preferencial do `aculta_portal` é o modal do Drupal** (`core/drupal.dialog`, via `use-ajax` com `data-dialog-type="modal"` ou `Drupal.dialog(...).showModal()` em JavaScript de comportamento).
2. **Não usar popup nativo** (`<dialog>` ou `HTMLDialogElement.showModal()` diretamente) no módulo nem no tema. Ele não tem o mesmo tratamento de foco, de sobreposição e de tema do Drupal.
3. **Exceção só por escrito.** Um popup nativo ou fora do padrão só entra com uma linha na tabela de exceções abaixo, com o caminho do arquivo, o motivo técnico e quem aprovou.
4. **Comportamento no módulo, aparência no tema.** O módulo informa título e comportamento (`modal`, `closeOnEscape`, `beforeClose`). Largura, altura, posição e cores são do tema (`css/components/dialog.css` e `portal-account-terms-modal.css`), com o alvo marcado por `data-portal-ui` dentro do conteúdo.
5. **Sem JSON de tamanho no módulo.** `data-dialog-options` do módulo só pode ter `title` e `closeText`. Chaves `width`, `maxHeight`, `position` e `dialogClass` são reprovadas pelo gate.

## Por que

- O modal do Drupal já vem com foco preso, fechamento por Esc controlado, sobreposição e bloqueio de rolagem da página. Cada popup nativo reimplementado precisava desses cuidados à mão (o popup de aceite tinha JavaScript próprio para isso).
- Tamanho e posição no módulo misturavam apresentação com comportamento. Quando o tema muda, o popup precisa continuar funcionando sem o ACULTA420 (testado com o Olivero).

## Como criar um popup

- Link ou botão: `class="use-ajax"`, `data-dialog-type="modal"`, `data-dialog-options` com `title` (e `closeText`, se necessário).
- Conteúdo: um elemento com `data-portal-ui="<token-do-dialogo>"` dentro do formulário ou da renderização.
- Tema: uma regra em `dialog.css` com `.ui-dialog:has(...)` apontando para esse token, definindo `inline-size`, `max-block-size` e, se necessário, posição.
- Documente o token em `TOKENS-E-ATRIBUTOS.md` (o gate de tokens exige).

## Dialogs de comportamento especial

Quando o diálogo não pode ser fechado sem uma ação (ex.: aceite dos termos), use `Drupal.dialog` com `closeOnEscape: false` e `beforeClose: () => false`, e esconda o X pelo tema. Isso é modal do Drupal: não é exceção.

## Exceções explícitas

Nenhuma exceção ativa. Para incluir uma, adicione uma linha abaixo (o gate lê esta tabela):

| Arquivo | Motivo técnico | Aprovado por | Data |
| --- | --- | --- | --- |
