# TODO — aculta420_v2

Estado: em desenvolvimento · versão 1.3.0 (`aculta420_v2.info.yml`) · status: sem status YAML.

## Próximo passo

F4 (UI Styles) implementada; accent por entidade na Revista depende de suporte do UI Styles. F5 geral implementada; listagem pública de Eventos existe na raiz, faltam View e validação visual. O candidato de Studio falhou em composição/modo escuro e ficou fora do commit. Loja requer autorização documentada; `price` permanece fora do escopo.
Próximo passo: avaliar a View de Eventos sobre a listagem existente; revisar o CSS/markup do mapeamento Studio antes de nova captura; validar cada adoção com captura antes/depois e gates.

## Dívidas

- Componente `price` (ADR-046): aguarda definição do contrato e autorização explícita da frente de Commerce/Loja.
- Faixa de acento (`card-cultural`): em modo DRAFT até entrega da variável neutra `domain_presentation.accent` (D1) pelo Portal.

## Leitura

- Leia só este arquivo, o código alvo e o `CHANGELOG.md` do componente se ele existir, pelo trecho necessário.
- Roadmap completo, grande: `web/themes/custom/aculta420_v2/docs/roadmap.md`. Leia só a seção pelo título: `grep -n '^## ' web/themes/custom/aculta420_v2/docs/roadmap.md`.

## Ao concluir uma fase

Atualize versão, estado e próximo passo aqui e em `docs/operations/status/aculta420_v2.yml` (quando houver), no mesmo commit. Remova o que foi concluído; não acrescente histórico neste arquivo.
