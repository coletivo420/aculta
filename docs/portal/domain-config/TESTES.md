# Testes — Domain Config e Layout de Blocos

Execução no Runtime de desenvolvimento em 2026-11-09, com sessão autenticada
de administrador (MAIN). Os testes A–E e H usam o bloco
`aculta420_v2_aculta_page_title` como amostra; o teste D usa a remoção do
override.

## A — Herança global

- Base: `block.block.aculta420_v2_aculta_page_title` em `region=navigation`,
  `weight=-18`, `settings.label=Page title`.
- Wiki (`wiki420.aculta.toca.net.br/verbete/proibicionismo`): o título do bloco
  aparece em `page__navigation` (região base), sem override.
- **Resultado: PASS.**

## B — Override independente

- Override criado para `conta_aculta_org` com
  `settings.label=Page title (conta)`.
- O formulário de edição no MAIN, com `config_domain=conta_aculta_org`, passou a
  exibir o label do override.
- Sem `config_domain`, o formulário continuou exibindo o label global.
- **Resultado: PASS.**

## C — Região e peso

- Override de `conta_aculta_org`: `region=footer`, `weight=0`.
- Base permaneceu `region=navigation`, `weight=-18`.
- Lista de blocos no MAIN:
  - sem `config_domain`: select de região em **Navegação**;
  - com `config_domain=conta_aculta_org`: select em **Rodapé**.
- Front-end da Conta: o título do bloco passou a aparecer junto de
  `page__footer-region`.
- Front-end da Wiki: permaneceu em `page__navigation`.
- **Resultado: PASS.**

## D — Remoção do override sem apagar o bloco global

- "Remove domain configuration" → confirmação **Apagar configuração**.
- A coleção `domain.conta_aculta_org` ficou sem
  `block.block.aculta420_v2_aculta_page_title`.
- O bloco global continuou existindo em `navigation`, peso `-18`.
- A Conta voltou a renderizar o bloco em `page__navigation`.
- `domain_config_ui.settings:overridable_configurations` voltou a `[]`.
- **Resultado: PASS.**

Desativação por domínio (`status: false` no diff) usa o mesmo caminho de
override; a operação de ativar/desativar da lista de blocos não foi executada
nesta rodada e fica registrada como pendente de homologação.

## E — Cache

- Aqueceu a Conta (override em `footer`) e depois a Wiki (base em
  `navigation`): cada domínio usou o próprio valor.
- Ordem inversa, após a remoção do override: a Conta voltou ao global e a Wiki
  permaneceu no global.
- **Resultado: PASS.**

## F — Bloco SDC/UI Patterns

- Bloco `block.block.aculta420_v2_institution_bar` (plugin `aculta_institution_bar`,
  UI Patterns/SDC `aculta420_v2:multidomain-bar`) recebeu override de
  `region=footer` para `links_aculta_org`.
- O diff na coleção `domain.links_aculta_org` continha `region: footer`; a base
  permaneceu `region: institution`.
- O override foi removido em seguida e a barra voltou à região institucional no
  domínio links (7 ocorrências de `aculta-institution-bar` no HTML).
- Observação operacional: o template do purpose links usa o rodapé social e
  ignora a região `footer`; mover a barra para lá a remove da página. A
  entidade e o override funcionaram, mas a posição `footer` não é recomendada
  para esse bloco.
- **Resultado: PASS** para a entidade de configuração do bloco SDC/UI Patterns.

## G — Exportação e importação

- `drush config:export --destination=/mnt/disco1/tmp/aculta-cex` exportou as
  coleções como `domain/<domain_id>/<config>.yml` (ex.:
  `domain/conta_aculta_org/system.site.yml`). Layout confirmado.
- A importação não foi executada no Runtime para não sobrescrever configuração
  divergente de outras sessões. Fica **DEFERRED** até um banco de staging ou
  suíte de kernel dedicada.

## H — Regressão de domínios

Varredura de 2026-11-09, sem 500:

| Host | Resposta |
| --- | --- |
| MAIN | 200 |
| Conta | 302 (visitante → login, esperado) |
| Wiki | 200 |
| Loja | 200 |
| Eventos | 200 (com SNI correto) |
| Cursos | 200 |
| Apoio | 200 |
| Links | 200 |
| Fórum (growroom420) | 200 (com SNI correto) |
| Revista | 200 |

O Layout de Blocos no MAIN respondeu 200. A Conta e a Wiki foram validadas com
override e fallback. Loja, Eventos e Cursos tiveram verificação de disponibilidade
HTTP, não de blocos individuais.

## Testes automatizados

- `web/modules/custom/aculta_portal/modules/aculta_domain_config/tests/src/Unit/DomainConfigEditingSubscriberTest.php`
  — 4 testes/12 asserções PASS.
- `web/modules/custom/aculta_portal/modules/aculta_domain_config/tests/src/Kernel/BlockOverrideReadKernelTest.php`
  — 1 teste/6 asserções PASS (storage domain-aware e leitura do override).
- `php -l` nos arquivos PHP novos.
- `git diff --check`.
