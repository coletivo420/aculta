# TODO — aculta420_v2

Estado: em desenvolvimento · versão 1.3.0 (`aculta420_v2.info.yml`) · status: sem status YAML.

## Próximo passo

Revista: configuração global submetida via UI Styles; preprocess aplica classe ao wrapper do node e ao SDC, com precedência administrativa. Quatro teasers confirmados no Firefox ESR em claro/escuro, 320/1440 px e sem overflow. Configuração global restaurada vazia; banco: 5 nós Revista, zero valores próprios; node 602 vazio. Campo legado preservado sem widget/leitura e sem migração destrutiva.

PHPUnit focal do tema: 12 testes/56 assertions PASS; bootstrap cria a saída automaticamente em caminho relativo ao projeto. Foundations, UI Styles, SDC schema e SDC render PASS. Firefox ESR: palette e botões primário/contorno em dez purposes, claro/escuro, 320/1440 px: 360/360 verificações; sem overflow no Fórum a 320 px. F3 concluída com evidência em `tests/browser/capturas/purpose-buttons/2026-11-06/`.

F2: shell não alterado. A suíte de shell será registrada após correção do probe de fundo transparente; aceitação visual do responsável permanece pendente.

Studio: `/conta`, abas, identidade, bio e redes validados em fase anterior. Loja aguarda autorização; `price` fora. Bootstrap contrib permanece ativo para a rodada de validação V1.

## Dívidas

- Componente `price` (ADR-046): aguarda definição do contrato e autorização explícita da frente de Commerce/Loja.
- Faixa de acento (`card-cultural`): em modo DRAFT até entrega da variável neutra `domain_presentation.accent` (D1) pelo Portal.

## Leitura

- Leia só este arquivo, o código alvo e o `CHANGELOG.md` do componente se ele existir, pelo trecho necessário.
- Roadmap completo, grande: `web/themes/custom/aculta420_v2/docs/roadmap.md`. Leia só a seção pelo título: `grep -n '^## ' web/themes/custom/aculta420_v2/docs/roadmap.md`.

## Ao concluir uma fase

Atualize versão, estado e próximo passo aqui e em `docs/operations/status/aculta420_v2.yml` (quando houver), no mesmo commit. Remova o que foi concluído; não acrescente histórico neste arquivo.
