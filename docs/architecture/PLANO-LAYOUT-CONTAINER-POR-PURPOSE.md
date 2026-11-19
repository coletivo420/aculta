> **Documento histórico, substituído pela ADR-054 (2026-11-09).** O painel administrativo `Layout de container` foi retirado do tema. Configuração e renderização legadas ainda podem ser lidas durante a migração paralela; não usar este plano para novas implementações. Plano vigente: `docs/architecture/ACULTA420-V2-REFATORACAO-SDC-LAYOUT-BUILDER.md`.

# Plano: layout de container por purpose (aculta420_v2)

Status: planejado com respostas do responsável em 2026-11-06. Execução pendente.

## Objetivo

Criar uma subaba nas configurações do tema `aculta420_v2` para definir o layout do container de cada purpose, no mesmo formato da escolha de paleta de cores por purpose.

## Decisões aprovadas

- **Padrão:** caixa centralizada (antes chamada Boxed) para todos os purposes. Outros modos ficam disponíveis na seleção.
- **Largura total:** 100% da tela, sem limite lateral.
- **Celular:** largura sempre 100%, ignorando os sliders.
- **Este documento** é o registro do planejamento; a implementação só começa depois de aprovação explícita.

## Modos de container

Revisão de 2026-11-07, pedida pelo responsável. Três modos:

| Modo | Comportamento |
| --- | --- |
| Padrão (padrão de todos os purposes) | Caixa centralizada com largura máxima de 1200 px (faixa usual 1140 a 1280), com margens laterais |
| Largura total | Ocupa 100% da tela, sem limite lateral |
| Estreito | Reduz a largura máxima do bloco de conteúdo para leitura focada (600 a 900 px, padrão 750) |

Valores gravados com os modos antigos (boxed, unboxed, contained, max_full_width) são convertidos automaticamente.
Revista (magazine) e Wiki (wiki) fazem parte da configuração, como os demais purposes.

A Conta (account) tem layout fixo pelo código, como o Fórum: modo Padrão (1200 px, estreito de 750 px)
e estilo sem caixa, em todas as páginas do domínio. Não aparece no seletor; valor salvo é ignorado.
A raiz do domínio é `/conta`, mas a variante de página segue a rota (Minha Conta usa `aside` no
desktop) e não cai em `full` por ser front page. Um único bloco de CSS do purpose centraliza o
container de todas as páginas e iguala o `padding-inline` ao da barra institucional, para o conteúdo
alinhar exatamente com a marca e as ações; a leitura estreita continua no conteúdo.

## Configuração

Subaba "Layout de container" em Aparência > aculta420_v2, com um bloco por purpose:

- **Modo:** Padrão (padrão), Largura total ou Estreito.
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
- Aceite: Largura total ocupa a tela; Padrão mantém margens; Estreito limita a leitura; celular sempre 100%.

### Fase 5 — entrega
- Commit em português com o trailer da sessão, push em `origin/dev`, confirmar hash com `git ls-remote`.
- Atualizar `docs/status-layouts-e-shell.md` e a documentação do tema.

## Pontos abertos

- Nenhum no momento. Revista e Wiki entram na configuração, como os demais purposes.
