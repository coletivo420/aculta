# TODO — aculta420_v2

Estado: em desenvolvimento · versão 1.3.0 (`aculta420_v2.info.yml`) · status: sem status YAML.

## Próximo passo

Revista: configuração global `revista_accent` no `aculta420_v2.settings`; preprocess do teaser aplica a classe global e prioriza UI Styles. Campo legado permanece sem widget/leitura e sem remoção destrutiva. Banco consultado: 5 nós Revista, zero valores próprios; node 602 vazio. Submit administrativo e validação do teaser público no Firefox ESR pendentes.

PHPUnit focal do tema: 11 testes/51 assertions PASS; bootstrap cria a saída automaticamente em caminho relativo ao projeto. Foundations, UI Styles, SDC schema e SDC render PASS. Runtime Firefox ESR confirmou palette e tokens primário/contorno dos botões, 358/360 verificações; overflow do Fórum a 320 px mantém F3 pendente. Evidência em `tests/browser/capturas/purpose-buttons/2026-11-06/`.

F2: shell não alterado. Capturas iniciais Firefox ESR em `tests/browser/capturas/shell/2026-11-06/`; 68/120 verificações. Fórum tem overflow em 320 px e problemas de contraste; corrigir probes, repetir matriz completa e aguardar aceitação visual.

Studio: `/conta`, abas, identidade, bio e redes validados em fase anterior. Loja aguarda autorização; `price` fora. Bootstrap contrib permanece ativo para a rodada de validação V1.

## Dívidas

- Componente `price` (ADR-046): aguarda definição do contrato e autorização explícita da frente de Commerce/Loja.
- Faixa de acento (`card-cultural`): em modo DRAFT até entrega da variável neutra `domain_presentation.accent` (D1) pelo Portal.

## Leitura

- Leia só este arquivo, o código alvo e o `CHANGELOG.md` do componente se ele existir, pelo trecho necessário.
- Roadmap completo, grande: `web/themes/custom/aculta420_v2/docs/roadmap.md`. Leia só a seção pelo título: `grep -n '^## ' web/themes/custom/aculta420_v2/docs/roadmap.md`.

## Ao concluir uma fase

Atualize versão, estado e próximo passo aqui e em `docs/operations/status/aculta420_v2.yml` (quando houver), no mesmo commit. Remova o que foi concluído; não acrescente histórico neste arquivo.
