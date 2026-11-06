# Plano: layout de container por purpose (aculta420_v2)

Status: planejado com respostas do responsável em 2026-11-06. Execução pendente.

## Objetivo

Criar uma subaba nas configurações do tema `aculta420_v2` para definir o layout do container de cada purpose, no mesmo formato da escolha de paleta de cores por purpose.

## Decisões aprovadas

- **Padrão:** Boxed para todos os purposes. Outros modos ficam disponíveis na seleção.
- **Unboxed:** largura total da tela (faixa sem margens laterais).
- **Celular:** largura sempre 100%, ignorando os sliders.
- **Este documento** é o registro do planejamento; a implementação só começa depois de aprovação explícita.

## Modos de container

Pesquisa de modos comuns em temas e page builders (nomes variam por fornecedor):

| Modo | Comportamento |
| --- | --- |
| Boxed (padrão) | Conteúdo em largura fixa, centralizado, com margens laterais e fundo de bloco |
| Unboxed | Faixa de largura total da tela, sem margens laterais |
| Contained | Conteúdo e fundo restritos ao centro, com espaço branco dos dois lados |
| Max full width | Meio-termo: mais largo que o contained, sem chegar à tela inteira |

Referências: [Astra, full width contained](https://wpastra.com/es/docs/full-width-contained-container-layout/), [BoldGrid, layout](https://www.boldgrid.com/support/wp-json/wp/v2/posts/49310), [Heelium, theme container](https://heelium.web.unc.edu/documentation/theme-options/theme-container/), [Advanced Columns, boxed mode](https://advancedcolumns-site-t7wxk.kinsta.page/docs/columns/boxed-mode/), [Elementor, container layout](https://elementor.com/help/container-layout-tab-settings).

## Configuração

Subaba "Layout de container" em Aparência > aculta420_v2, com um bloco por purpose:

- **Modo:** Boxed (padrão), Unboxed, Contained ou Max full width.
- **Largura do container:** slider em px, de 960 a 1600 (padrão 1200).
- **Largura estreita:** slider em px, de 600 a 900 (padrão 750), para páginas de leitura.

## Armazenamento e aplicação

- Chave `aculta_purpose_container` em `theme_get_setting`, com um item por purpose.
- Valores validados: modo pela lista permitida, larguras por faixa numérica. Nenhum texto livre vai para o CSS.
- `preprocessHtml` emite `data-purpose-layout` no `<html>` e as variáveis `--aculta-container-width` e `--aculta-narrow-container-width`.
- O CSS passa a usar essas variáveis no lugar dos valores fixos, inclusive no painel de conta (hoje 84 rem) e no calendário.
- Celular (abaixo de 60 rem): largura 100%, qualquer que seja o modo.

## Fases

### Fase 1 — modelo e validação
- Sanitizador de `aculta_purpose_container` com faixas e lista permitida.
- Padrões por purpose (Boxed, 1200 px, 750 px).
- Aceite: testes unitários cobrindo valores válidos, inválidos e fallback.

### Fase 2 — formulário de configuração
- Subaba com um bloco por purpose, mesmo padrão da paleta.
- Aceite: teste de kernel do formulário; valores salvos aparecem na reabertura.

### Fase 3 — aplicação no HTML e no CSS
- Atributos e variáveis no `<html>`, por purpose.
- Substituir os valores fixos de container pelas variáveis.
- Aceite: probe de fundações e de schema SDC passam; sem overflow horizontal em 320 px.

### Fase 4 — validação visual
- Captura em Firefox ESR por purpose, claro e escuro, 320 e 1440 px, em `tests/browser/capturas/layout-container/<data>/` com `manifest.json`.
- Aceite: Unboxed ocupa a tela; Boxed mantém margens; celular sempre 100%.

### Fase 5 — entrega
- Commit em português com o trailer da sessão, push em `origin/dev`, confirmar hash com `git ls-remote`.
- Atualizar `docs/status-layouts-e-shell.md` e a documentação do tema.

## Pontos abertos

- Largura padrão do modo Contained e do Max full width (sugestão: 1000 px e 1400 px).
- Se a configuração vale para páginas de conteúdo editorial (Revista, Wiki) além do painel.
