# Versionamento

## Ciclo de entrega (vigente em 2026-10-20)

Toda alteração de fase no ciclo Dev → RC → deploy/validação Alpha → produção
deve ser refletida neste versionamento, no roadmap do tema e nas instruções de
IA (`AGENTS.md`). RC só é declarado quando código, gates/testes, revisão de
código e validação visual pré-deploy aplicável estiverem concluídos. Alpha é a
implantação/validação no servidor de desenvolvimento; depois de Alpha aprovado,
o mesmo candidato segue para preparação de produção e validação pós-deploy.
Registros anteriores permanecem históricos. Mudança apenas documental segue a
regra específica abaixo e não avança marcador de versão do código.

O versionamento do ACULTA420 controla as mudanças e os avanços **no código**. A versão é
assinalada dentro do repositório. Tags no GitHub não são criadas por padrão.

Aplica-se também ao `aculta_portal`, com a mesma regra (ver "Portal" abaixo).

## Regra principal

- Toda mudança que altera código, configuração, componente ou gate avança a versão
  marcada no código, de acordo com a classificação abaixo.
- Assinalar a versão significa atualizar três marcadores no mesmo commit ou PR:
  1. `aculta420.info.yml`: campo `version` com a versão atual;
  2. `CHANGELOG.md`: seção com a versão, a data e as mudanças principais;
  3. `docs/roadmap.md`: status da fase ou linha.
- Gate que checa a versão (`validate-aculta420-foundation.php`) é atualizado no mesmo PR.
- **Tag Git não é criada sem pedido explícito do responsável.** Quando o responsável pedir,
  a tag segue o formato `aculta420-theme-vX.Y.Z[-dev.N]`, apontando para o commit já mesclado.

## SemVer

Formato `MAJOR.MINOR.PATCH`, com pré-release `-dev.N` quando a linha ainda não fechou.

Antes de 1.0:

- MINOR: entrega coerente de arquitetura ou feature, incluindo componente novo `experimental`;
- PATCH: correção compatível, documentação, acessibilidade ou performance;
- `-dev.N`: subversão marcada dentro de uma linha aberta. Exemplo: `0.4.0-dev.1`.

Exemplos: `0.2.0`, `0.3.0`, `0.3.1`, `0.4.0-dev.1`, `0.4.0`.

## Proteção de componentes stable

- PATCH não quebra props, slots ou semântica stable.
- Quebra intencional exige MINOR e nota de migração.
- Remoção documenta a substituição quando houver consumidor.
- Componente experimental pode mudar em MINOR, com quebra explícita.

## Classificar antes de codar

| Tipo de mudança | Versão alvo | Exemplo |
| --- | --- | --- |
| correção, acessibilidade, performance, sem API nova | PATCH | corrigir sticky, reduced motion |
| documentação sem código, config ou componente | PATCH sem código novo; registrar no CHANGELOG | revisão de roadmap |
| componente novo `experimental`, feature compatível | MINOR da linha aberta (`-dev.N`) | project-card, rail |
| quebra de componente `stable` ou de contrato Portal → tema | MINOR pré-1.0, com nota de migração | troca de props stable |
| breaking em 1.0 ou depois | MAJOR | — |

Antes de codar, anotar a versão alvo e a subversão prevista para cada mudança principal.

## Versão sequencial por merge (vigente a partir de 0.4.1)

Duas sequências, com papéis distintos:

- **`-dev.N` nomeia commits.** Enquanto a PR está aberta, cada commit que altera a versão marcada
  usa `<linha>-dev.N`, com N crescendo a cada commit da PR: `0.4.1-dev.1`, `0.4.1-dev.2`, ...
- **`0.4.X` nomeia merges.** Cada PR mesclada na `main` recebe a próxima versão da sequência da
  linha: `0.4.1`, `0.4.2`, `0.4.3` e assim por diante.
- A versão final (`0.4.X`, sem `-dev`) é marcada por um commit de release feito imediatamente antes
  do merge. Esse commit é o único que remove o `-dev` e fecha a versão da PR.
- O número `0.4.X` é conferido contra a `main` antes do release. Se outra PR mesclar antes, a PR
  seguinte é renumerada para o próximo número livre.
- A primeira versão sob esta regra é `0.4.1`, porque a última mesclada é `0.4.0-dev.6` (PR #112).
  Commits anteriores desta PR (`0.4.0-dev.7`, `0.4.0-dev.8`) ficam no histórico como estão.
- Documentação sem código não avança a versão (ver "Mudanças apenas documentais").
- A classificação MINOR ou MAJOR continua sendo decisão do responsável e aparece no CHANGELOG;
  a sequência avança o PATCH dentro da linha.
- Tag continua sob pedido explícito do responsável.

## Marcar a versão de uma mudança

Ao validar uma mudança principal (gates de tema e Runtime e, quando couber, HTTP ou navegador):

1. aplicar a versão sequencial por merge (acima) em `aculta420.info.yml`;
2. adicionar a entrada no `CHANGELOG.md` com: versão, data, mudança principal, SHA do commit
   na `main` e status de validação (validado ou não validado);
3. atualizar o status em `docs/roadmap.md`;
4. ajustar a asserção de versão do gate de Foundation.

Estado não validado não recebe marcação de versão nova. Ele fica no changelog como "não validado".

## Fechar uma linha de release

Quando a fase fecha:

1. `aculta420.info.yml` recebe a versão final, sem `-dev`;
2. `CHANGELOG.md` recebe a seção final com data;
3. `docs/roadmap.md` marca a fase como concluída;
4. o gate de Foundation passa a esperar a versão final;
5. a tag só é criada se o responsável pedir.

## Mudanças apenas documentais

- Mudança só de documentação entra no `CHANGELOG.md` com a versão vigente e não altera o
  `info.yml`.
- Documentação e código no mesmo PR seguem a classificação de código.

## Portal

- Mudança em `aculta_portal` entra no `CHANGELOG.md` do módulo com a versão do Portal marcada
  em `aculta_portal.info.yml`.
- A versão do Portal segue a mesma classificação e a mesma regra de marcação.
- Tag `portal-vX.Y.Z` só é criada sob pedido explícito do responsável.

## Configuração e conteúdo

- Mudança de configuração ou de conteúdo que outro ambiente precisa é registrada no CHANGELOG,
  com a dependência de conteúdo.

## PRs

- Uma PR por fase ou subversão. Ao fechar uma PR obsoleta, registrar o motivo na própria PR e
  arquivar o que for único fora do repositório antes de excluir a branch.
- PR que muda a versão marcada precisa manter `info.yml`, `CHANGELOG.md`, roadmap e gate coerentes.

## Histórico de marcações

Marcações já feitas, por linha:

| Versão | Marcação no código | Observação |
| --- | --- | --- |
| 0.6.1-dev.7 | Schema das configurações do tema e render da home Wiki em Kernel | marcada no código; HTTP/Runtime pendente |
| 0.1.0 | Foundation | concluída |
| 0.2.0 | fechamento da linha 0.2 | concluída |
| 0.3.0 | fechamento da linha 0.3 | concluída |
| 0.3.1 | fase de padrões SDC | versão decidida pelo responsável, exceção à classificação MINOR |
| 0.4.0-dev.1 | rail de cursos | marcada no código |
| 0.4.0-dev.6 | logomarca Wiki420 (PR #112) | marcada no código |
| 0.4.1 | T4, T5 e T6 (PR #113) | primeira versão pela regra sequencial por merge |
| 0.4.2-dev.1 | assets Podplant420 e validador (PR #114) | marcada no código |
| 0.4.2-dev.2 | handoff Podplant420 fora do tema (PR #114) | marcada no código |
| 0.4.2 | release da PR #114 (assets Podplant420) | marcada no código; primeira versão do merge |
| 0.4.3-dev.1 | kit Baque Sativa (PR #115) | marcada no código |
| 0.4.3 | release da PR #115 (kit Baque Sativa) | marcada no código; primeira versão do merge |
| 0.4.5-dev.1 | kit de artes Bloco Sativa 420 | marcada no código |
| 0.4.5-dev.2 | imagem dos cards de projeto e grade responsiva | marcada no código |
| 0.4.5-dev.3 | cards de projeto: imagem 256 px com 2x e conteúdo centralizado | marcada no código |
| 0.4.5-dev.4 | validador de navegador: página de apoio no host SUPPORT | marcada no código; merge `0.4.5` pendente |
| 0.6.0-dev.2 | validador de navegador (correção do PR #143, refeita sobre a main) | marcada no código |
| 0.6.0-dev.1 | F4 (UI Patterns): biblioteca de componentes e stories | marcada no código |
| 0.5.1 | F2 (ícones) concluída; gate sem SVG inline de ícone no tema | marcada no código (release) |
| 0.5.0 | F1 (modo de cor) concluída e validada no Runtime oficial (2026-10-09) | marcada no código (release) |
| 0.5.0-dev.3 | F1 (modo de cor): atributo via b5_theme_mode | marcada no código; RUNTIME STATUS DEFERRED |
| 0.5.0-dev.2 | F1 (modo de cor): modo claro, escuro e automático | marcada no código; RUNTIME STATUS DEFERRED |
| 0.5.0-dev.1 | F1 (modo de cor): medição do modo escuro em navegador | marcada no código |
| 0.4.5 | fechamento da linha 0.4.x (2026-10-09) | marcada no código (release); sem tag |
| 0.4.0 | linha 0.4.x fechada em 0.4.5, sem versão 0.4.0 retroativa (decisão do responsável, 2026-10-09) | fechamento documental |
| 0.4.5-dev.5 | sitemap do validador delegado ao `aculta_deployer sitemap`; DT-T18 pendente | marcada no código; merge `0.4.5` pendente |
| 0.4.4-dev.1 | gates no runtime e gate institucional da home | marcada no código; merge `0.4.4` pendente |

Tags criadas antes desta política: ver a nota em `docs/operations/RELEASES.md`.
