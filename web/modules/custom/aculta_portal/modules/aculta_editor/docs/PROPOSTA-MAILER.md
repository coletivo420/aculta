# Proposta: editor visual de modelos no aculta_mailer

Status: **implementada no `aculta_mailer` 0.1.0-dev.61**, com autorização do responsável. Este documento registra o desenho e os testes. Itens 1 a 4 entregues; item 5 permanece como fora de escopo.

Data: 2026-10-22. Base: `MailTemplateForm.php`, `MailTemplateRenderer.php` e `CONTROL-SCREENS.md` do mailer, no estado da branch `docs/aculta-forum-0.1.0-dev.1`.

## 1. Estado atual (fatos verificados)

| Ponto | Onde | Situação |
| --- | --- | --- |
| Campo do corpo | `src/Form/MailTemplateForm.php:89-95` | `#type => textarea`, título "Corpo (texto simples)" |
| Gravação | `src/Form/MailTemplateForm.php:165` | `setBody((string) $form_state->getValue('body'))` |
| Validação de variáveis | `src/Form/MailTemplateForm.php:146-156` | `MailTemplateRenderer::unknownPlaceholders()` sobre a string bruta do campo |
| Renderização | `src/Template/MailTemplateRenderer.php:80-86` (`fill()`) | substituição de texto, **sem escape** |
| Layout | `MailTemplateRenderer.php:108-114` (`wrap()`) | insere o conteúdo em `{{ content }}` sem escape |
| Decisão do editor visual | `docs/CONTROL-SCREENS.md`, "Decisões tomadas (2026-10-20)" | editor visual com **formato de texto dedicado (HTML restrito)** |

O formulário ainda não tem editor: a decisão existe, a implementação não.

## 2. Alteração proposta no formulário (frente do mailer)

Trocar o `textarea` por `text_format`, com o formato `aculta_email` (criado pelo `aculta_editor`) como padrão:

```php
$form['body'] = [
  '#type' => 'text_format',
  '#title' => $this->t('Corpo'),
  '#default_value' => $template->getBody() !== '' ? $template->getBody() : ($type?->defaultBody() ?? ''),
  '#format' => 'aculta_email',
  '#allowed_formats' => ['aculta_email'],
  '#required' => TRUE,
  '#rows' => 14,
];
```

Ajustes necessários no mesmo formulário:

- `validateForm()`: ler `$form_state->getValue(['body', 'value'])` em vez de `getValue('body')`.
- `save()`: `setBody((string) $form_state->getValue(['body', 'value']))`.
- O formato gravado (`aculta_email`) deve ser registrado no modelo, ou o corpo é sempre renderizado com esse formato. Escolher uma das duas opções na implementação.

## 3. Pré-requisito obrigatório: escape dos valores em corpo HTML

`fill()` substitui `{{ var }}` pelo valor bruto. Com corpo HTML, um valor vindo de usuário (por exemplo nome de perfil) seria interpretado como HTML. Antes de liberar o editor:

- Quando o corpo for HTML, escapar cada valor com `htmlspecialchars($value, ENT_QUOTES, 'UTF-8')` dentro de `fill()`, ou passar um modo de renderização explícito.
- Manter o comportamento atual (texto puro, sem escape) para modelos existentes em texto simples.
- Teste de regressão: valor com `<script>` ou `<img onerror>` não aparece como marcação no e-mail renderizado.

Sem esse passo, o editor torna possível injeção de HTML em e-mails enviados pelo site.

## 4. Testes necessários na frente do mailer

- Modelo salvo com tabela e títulos reaparece igual ao abrir o formulário (ciclo editar → salvar → reabrir).
- Variável desconhecida continua reprovada na validação, agora em `['body', 'value']`.
- Valor de variável com HTML é escapado (item 3).
- Modelos antigos em texto simples renderizam como antes.

## 5. O que não é deste submódulo

Inserção de tokens pelo editor, pré-visualização e envio dependem da tela de modelos do mailer. O `aculta_editor` fornece só o formato `aculta_email`. Nenhum envio ou token é implementado aqui.

## 6. Situação

- Seções 2 a 4: implementadas no mailer (`body_format`, `text_format` no formulário, escape em `fill()` para corpo HTML, filtro do formato `aculta_email` no render, parte HTML sem escape duplo no envio). Testes: unitários e Kernel do mailer.
- Seção 5 continua fora de escopo deste submódulo.
