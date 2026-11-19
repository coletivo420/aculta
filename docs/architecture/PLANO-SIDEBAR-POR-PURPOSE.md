# Plano: sidebar por purpose (aculta420_v2)

Status: planejado com respostas do responsável em 2026-11-07. Execução pendente.

## Decisões aprovadas

- **Lado:** esquerda ou direita, escolhido por purpose.
- **Estilo da sidebar:** Padrão (herda o estilo do container), Caixa ou Sem caixa. Mesmo modelo do Astra.
- **Layouts:** a sidebar só aparece no layout Padrão. Largura total e Estreito não têm sidebar, como no Astra.
- **Sidebar vazia:** some, e o conteúdo ocupa a largura disponível.
- **Celular:** a sidebar não aparece lado a lado; vai para o fim da página, abaixo do conteúdo principal.

## Modelo de dados

Dentro de cada purpose, no mesmo bloco de `aculta_purpose_container`, duas chaves novas:

- `sidebar_side`: `none`, `left` ou `right` (padrão `none`).
- `sidebar_style`: `default`, `boxed` ou `unboxed` (padrão `default`, que herda `style` do container).

Validação: lista permitida para cada chave. Valores fora da lista caem no padrão. Sem texto livre no CSS.

Regra de compatibilidade: se o layout não for Padrão, `sidebar_side` é tratado como `none`, mesmo que esteja salvo. Assim o valor gravado não quebra a página ao trocar de layout.

## Aplicação

- `preprocessHtml` emite `data-purpose-sidebar` (lado) e `data-purpose-sidebar-style` (estilo), só quando o layout é Padrão e há lado escolhido.
- O `main` já tem a classe `layout-page--aside` e o slot `sidebar_first`. O CSS usa o atributo para posicionar a coluna à esquerda ou à direita.
- Sidebar vazia: o CSS só reserva a coluna quando o slot tem conteúdo. Sem conteúdo, a coluna não aparece e o conteúdo ocupa a largura.
- No celular (abaixo de 60 rem), a coluna vira bloco no fim da página, em largura total.

## Fases

### Fase 1 — modelo e validação
- Chaves `sidebar_side` e `sidebar_style` no sanitizador, com padrões e lista permitida.
- Regra de compatibilidade com o layout.
- Aceite: testes unitários cobrindo lado válido, inválido, layout sem sidebar e padrões.

### Fase 2 — formulário
- Dois campos por purpose no bloco do layout de container: lado e estilo da sidebar.
- O campo de estilo fica desabilitado quando o layout não é Padrão, com nota explicativa.
- Aceite: teste de kernel do formulário; valores salvos reaparecem na reabertura.

### Fase 3 — aplicação no HTML e no CSS
- Atributos `data-purpose-sidebar` e `data-purpose-sidebar-style`.
- Regras de lado (esquerda e direita), de estilo (caixa e sem caixa) e de celular (bloco no fim).
- Aceite: probes de fundações e de schema SDC passam; sem overflow horizontal em 320 px.

### Fase 4 — validação visual
- Captura em Firefox ESR por purpose, lado esquerdo e direito, claro e escuro, 320 e 1440 px, em `tests/browser/capturas/sidebar-purpose/<data>/` com `manifest.json` e `results.json`.
- Aceite: sidebar some quando vazia; no celular fica no fim da página; layouts Largura total e Estreito não mostram sidebar.

### Fase 5 — entrega
- Commit em português com o trailer da sessão, push em `origin/dev`, confirmar hash com `git ls-remote`.
- Atualizar `docs/status-layouts-e-shell.md` e a documentação do tema.

## Pontos abertos

- Nenhum no momento. O escopo por página e item segue em planejamento separado.
