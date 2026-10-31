# Regras específicas — consulta sob demanda

## ADR-038 — caminhos vigentes das Suites (retificado pelo responsável em 2026-10-30)

Marley `account:/marley`; Ganjalab `courses:/ganjalab`; WE SATIVA `wiki:/studio/we-sativa`; Green Stash `shop:/studio/green-stash`; Culturas `eventos:/culturas`. Os dois caminhos `/studio/*` oficiais são ACULTA STUDIO WE SATIVA e ACULTA STUDIO GREEN STASH em seus purposes proprietários; `/studio` e os demais caminhos `/studio/*` retornam 404 sem alias/redirect. O Domain Wiki canônico é `wiki420`. `aculta_studio` permanece framework compartilhado. A raiz de `account` apresenta a entrada da Conta; Conta e Perfil permanecem separados. Consulte a retificação vigente do ADR-038.


## Contrato histórico antes do ADR-038

A raiz pública `/` no purpose ACCOUNT apresentava
`aculta_studio.hub` (caminho técnico `/studio`, 404 direto).
A Minha Conta original está em **`/conta-legada` de forma
permanente**. Nunca restabelecer o legado na raiz, criar alias
temporário nem retirar o shell do Portal.

Experiências próprias eram `/studio/conta` e `/studio/perfil`,
com Portal dono de cadastro/endereço/documentos/segurança e User
dono de identidade pública. `/studio/conta/documentos` só exibe
link se `aculta_id_cpf.account_document` estiver disponível.
Não duplicar CPF/CNPJ, nem incluir esse dado em Meu Perfil.
Comparar comportamento das duas interfaces antes de qualquer aceite.


## Decisão de ownership: experiências Conta/Perfil independentes

Não reintroduzir a experiência `pessoal`. Usar somente
`/conta` e `/perfil` como experiências separadas após ADR-038;
perfil público em `/u/{apelido}` é independente. O legado da Conta
permanece na raiz `/` de ACCOUNT **mesmo após a homologação**, com
`/minha-conta` técnico 404. Não fazer redirects temporários, não
retirar `AccountShellBuilder`, não misturar Form API cadastral com
editor público nem duplicar User/Profile.

[Contrato atual](../../../../../../docs/architecture/ACULTA-STUDIO-CONTA-PERFIL-SEPARADOS.md).
As regras históricas abaixo devem ser lidas à luz desta decisão.


## WikiTab availability and access (Studio dev.5)

The tab checks both the owner module `aculta_wiki` and its `wiki_entry` bundle.
Do not infer owner availability from retained configuration alone. The Unit
regression covers module disabled with bundle still present, both available,
and bundle absent.
`create wiki_entry content` alone is not a GanjaLab permission: contributor
authorship belongs in WE SATIVA. GanjaLab entry requires `edit any
wiki_entry content`, `view wiki_entry revisions`, or `moderate wiki proposals`;
the shared entry inventory requires `edit any`. The focal test passed 7/27.

## WE SATIVA — entrypoint e workspace existentes (Studio dev.3 / Wiki dev.76)

A rota oficial é `/studio/we-sativa` no purpose `wiki`, conforme retificação
vigente do ADR-038, e usa o provider Studio;
`aculta_wiki.contributor_workspace` é dono dos dados, grants, paginação e links
Wiki. Meu Trabalho separa cursores para verbetes/propostas, usa SDCs e revalida
Entity Access. Criar/editar/contribuir/acompanhar continuam em rotas e Form API
do módulo Wiki, com link de retorno cacheável dos formulários ao Meu Trabalho
quando Studio e acesso estiverem disponíveis. A classe Kernel integrada passou
em Wiki dev.77 (23/1.039 para o ciclo Wiki, incluindo o workspace); Firefox/X11,
320 px, Olivero e Runtime seguem sem homologação.

Estas regras foram realocadas do arquivo histórico de instruções; aplique somente no escopo correspondente. O `AGENTS.md` raiz continua obrigatório.

## ACULTA Studio (regra permanente)

### WE SATIVA start page (dev.6)

The `/studio/we-sativa/inicio` controller delegates to the Wiki owner. Unit
coverage verifies a provider with the optional start-page contract, a provider
without it, and owner-denied access (3/3 focal; Studio Unit suite 20/61). Kernel
Wiki integration verifies the route, access-conditioned links and rendered
ACULTA420 HTML (2/208). Runtime and
Firefox/X11 remain pending because the active Runtime container lacks the
workspace service and WE SATIVA routes.

- `aculta_studio` é o framework compartilhado de registry e composição, sem hub público em `/studio`. Agrega
  ações dos donos e não é dono de conteúdo, permissões, discussões, galerias nem tabelas próprias equivalentes.
- Cada aba é um plugin `#[StudioTab]` com três contratos: `isAvailable()` (o dono está habilitado), `access()` (a
  permissão real do dono, nunca nome de papel) e `actions()` (ação com o acesso da rota do dono). Ação para outro
  domínio usa `DomainPurposeManager::routeUrl()` e avalia o acesso com `runInPurpose()`.
- Nenhuma rota ou ação do Studio contorna Form API, Entity Access, Content Moderation nem as permissões do dono.
- **Rotas canônicas, sem aliases:** somente `/studio/we-sativa/**` em `wiki` e `/studio/green-stash/**` em `shop` usam o prefixo `/studio/`; `/studio` e todos os outros caminhos `/studio/*` respondem 404. Marley usa `/marley/{aba}[/{secao}[/{sub}]]` em `account`; Ganjalab `/ganjalab` em `courses`; Culturas `/culturas` em `eventos`. Conta e Perfil ficam em `/conta` e `/perfil`, separados. Não criar redirects de compatibilidade; URLs aposentadas respondem 404.
  Cada aba principal, opção de submenu e aba interna tem URL própria; o AJAX melhora a troca, mas não é a única forma de
  acesso. Segmento fora do alcance da conta responde 404. Os níveis são: áreas em abas superiores (uma por dono);
  submenu à esquerda com as seções da área; abas internas da seção, com as ações no painel. Cada área implementa `sections()` com esses níveis. Apresentação: tokens `data-portal-ui`
  (`studio-*`) e atributos `data-aculta-studio*` de comportamento; classes e aparência ficam no ACULTA420.
- O Studio tem página, shell, JavaScript (`js/studio-navigation.js`) e hooks próprios. Não usa o shell, o menu ou
  o `account-navigation.js` da Minha conta, e não aparece no menu da conta.
- Melhoria contínua: toda frente que criar função com potencial de aba ou ação deve investigar o Studio antes de
  concluir, propor ou implementar a aba dentro desse contrato, e atualizar a matriz em
  `docs/portal/STUDIO-MAGAZINE-ROADMAP.md`, o README do `aculta_studio` e o status do componente.
- Acessibilidade e recuo: troca de nível com foco no título, região aria-live para carregamento e erros, voltar e
  avançar do navegador, e links utilizáveis sem JavaScript. Ações de edição, aprovação, exclusão e publicação mantêm as
  verificações de acesso, CSRF e confirmação do Drupal.
- Guardrails: não adicionar aba sem permissão real e sem teste de acesso; não expor dados de terceiros; não copiar
  tabela ou fluxo de permissão do dono; não ampliar o acesso de uma aba para contornar o dono; não mover ou apagar
  dados por ação do Studio sem a operação do dono.
- Antes de encerrar uma mudança no Studio: `php scripts/validate-module-theme-boundary.php`,
  `php scripts/validate-aculta-portal-drupal11.php`, `php scripts/validate-project-status-index.php` e a suíte
  `tests/browser/aculta-studio/visual.mjs`, com o resultado registrado.

## ACULTA Studio Culturas (implementação em andamento)

### Revista no Studio Marley (MAG dev.14)

- A experiência editorial é ACULTA Studio Marley em `account:/marley`; use as
  rotas owner `/marley/revista/*`. Não encaminhe usuários editoriais para
  `/admin/content/revista` nem para `/painel-administrativo/revista`.
- `aculta_magazine` continua owner do Node `revista`, fields, Form API e
  workflow. Revalide Entity/Revision Access e a capability ACULTA no acesso
  direto e no submit; transições vêm de `StateTransitionValidation`.
- Discussão é o Comment `aculta_discussion_post` em
  `field_aculta_discussion`. Use `aculta_forum.discussion_context_resolver` e
  `PublicationContextProvider`; preserve acesso host/campo, política de Comment
  e isolamento do Growroom. Não crie ForumPost, tópico ou fila paralela.
- Administração/capacidades ficam em `/painel-administrativo/revista`, purpose
  `main`, somente para administradores. Papéis configurados são uma condição
  adicional e não substituem permissões Drupal ou access handlers.

O nome aprovado para a experiência de organizadores é **ACULTA Studio Culturas**, com rota `/culturas` no purpose `eventos`. A entrada da Conta pode linkar por `DomainPurposeManager`, mas não grava Eventos. A área administrativa permanece no MAIN em `/painel-administrativo/eventos`, restrita a administradores. Usar `aculta_events` para persistência e autorização; Datas e Locais pertencem ao Evento. Não duplicar o painel administrativo na interface de organizadores.

## Contrato de migração das Suites (ADR-038)

Cada Suite responde em seu próprio domínio/purpose e sob o caminho-base abaixo. O prefixo público `/studio` e todas as rotas `/studio/*` foram retirados e respondem 404, sem alias ou redirect. `aculta_studio` permanece como infraestrutura compartilhada de composição e registry, não como raiz de URL.

| Suite | Purpose | Host de produção | Host de teste | Caminho-base |
| --- | --- | --- | --- | --- |
| Marley | `account` | configurado no Domain | configurado no Domain | `/marley` |
| Ganjalab | `courses` | configurado no Domain | configurado no Domain | `/ganjalab` |
| ACULTA STUDIO WE SATIVA | `wiki` | configurado no Domain (`wiki420`) | configurado no Domain (`wiki420`) | `/studio/we-sativa` |
| ACULTA STUDIO GREEN STASH | `shop` | configurado no Domain | configurado no Domain | `/studio/green-stash` |
| Culturas | `eventos` | configurado no Domain | configurado no Domain | `/culturas` |

Manter Conta e Perfil como experiências diferentes, fora das Suites. A raiz de `conta` apresenta a entrada da Conta e não reescreve para `/studio`. `/conta/*`, `/perfil/*` e `/salvos` preservam owners e dados separados. A Conta legada continua em `/conta-legada`.

Regras de implementação:
- Rotas declaram o purpose correto e validam usuário, módulo owner e permissão no servidor. Purpose incorreto responde 404, inclusive POST. Links cross-domain são absolutos via `DomainPurposeManager`; não enviar POST/proxy de escrita entre purposes.
- Preservar route IDs e owners sempre que possível. Rotas de negócio podem ser declaradas pelo módulo owner e ter path/purpose ajustados conforme a Suite responsável.
- Novas Suites escolhem host/purpose e slug próprio antes de declarar rotas; não introduzir `/studio/{suite}`.
- Hosts são lidos da configuração ativa por ambiente e URLs absolutas passam pelo `DomainPurposeManager`. Wiki mantém o Domain configurado `wiki420`; não renomear para `wiki.aculta.*`.
- `PurposeFrontPathProcessor`, `FrontPathPolicy`, subscriber de propósito, navegação e testes já usam os caminhos canônicos. Preservar a matriz de propósitos e as respostas 404 cross-purpose, inclusive escritas.
- `/studio` e `/studio/*` estão removidos, sem redirects legados; canonical e links apontam aos hosts e caminhos resolvidos pela configuração do ambiente.
- A migração de links, login/retorno, e-mail, notificações, sitemap, canonical, cache e testes foi feita nos consumidores inventariados. Continuar verificando novos consumidores sem transferir ownership de entidades, formulários, ACL ou workflows.

Estado em 2026-10-30: caminhos canônicos e bloqueio de `/studio*` testados após rebuild de cache; matriz HTTP 275/275 e baseline Firefox ESR capturado em 1440/390 px. A Wiki usa o Domain canônico `wiki420`; HTTPS e captura autenticada foram verificados no ambiente de desenvolvimento. DNS de produção `wiki420.aculta.org` não foi verificado com sucesso neste ambiente.

As rotas foram movidas no código. Não declare a migração funcional integral concluída até validar grants e fluxos das Suites. `wiki420` permanece o host canônico; não há troca para `wiki.aculta.*` planejada.
