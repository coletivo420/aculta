# ACULTA Studio — Roadmap multi-experiência


## S-R3-B2 — separação de cadastro e apelido (Studio dev.18 / Portal dev.30 / User dev.74)

- **Código entregue na PR #297:** `/studio/conta/dados` compõe Profile Form API somente com nome e WhatsApp privado. `/studio/perfil/apelido` entrega editor isolado do campo original `field_nickname` com as regras da ACULTA User. Não cria schema, alias nem editor de dados privados no Perfil.
- `/conta-legada` permanece Minha Conta original; `/dados` legado preserva o formulário misto para comparação e `/` continua hub do Studio.
- **Sem execução de testes novos:** PHPUnit Unit, Kernel/Functional, lint, PHPCS, validadores de rotas, Domain, aliases, cache, CSRF, sessão anônima/autenticada, CPF/CNPJ opcional, checkout, segurança/OAuth, CEP/Número/S-N, nickname inválido/duplicado/concorrente, no-JS, Firefox ESR/X11 e HTTP dependem de checkout e Runtime autorizados.
- **Próxima fase S-R3-B3 após gates:** incluir pedidos, apoio e preferências na composição de Conta com owners originais; preparar S-R5 comparação funcional e visual da interface permanente.

## S-R3-B1 — Documentos da Conta, implementação dev.17

- **Feito no código:** `/studio/conta/documentos` via o mesmo
  Portal form proprietário de CPF/CNPJ, com disponibilidade opcional
  e retorno contextual; nenhuma duplicação de armazenamento ou
  exibição no Meu Perfil.
- **Contrato da raiz:** hub público na raiz `/` do ACCOUNT; caminho
  `/studio` interno e 404 em acesso direto; Minha Conta original
  em `/conta-legada`, mantida permanentemente.
- **Não validado nesta etapa:** novos Unit, Kernel/Functional, HTTP
  autenticado/anônimo, serviço CPF/CNPJ disponível/ausente,
  submit e retorno de checkout, navegador e no-JS.
- **Próxima fase S-R3-B2:** separar dados cadastrais privados do campo
  apelido público (owner Portal/User); integrar formulário Conta sem
  carregar o campo de apelido; preparar editor isolado de apelido em
  Perfil, sem mudar schema nem quebrar o form legado.
- Após isso, avançar preferências, pedidos e apoio com os serviços
  proprietários e comparar Conta Studio x `/conta-legada` antes
  de aprovar a nova interface.


## S-R3-A — composição de Conta por Portal, código dev.14

**Implementado:** endereço, segurança/e-mail, senha/OAuth e conexões em
rotas canônicas de `/studio/conta`. Controller Studio apenas compõe
render arrays e navegação; dados e operações permanecem no Portal.
A regra de Endereço da Conta legada foi estendida para a nova rota do
Studio sem criar entidades ou lógica de endereço duplicadas. Submit
volta à interface correspondente. Testes Unit e gate estático escritos.

**Pendências reais antes de homologar:** PHPUnit, Kernel/Functional,
testes HTTP e Firefox ESR/X11 no Domain ACCOUNT, submissão de CEP/Número/S-N,
endereço refletido em checkout, e-mail/primeira senha OAuth e ausência
de duplo shell. O cadastro em `/dados` e outros destinos ficam legados
até se separar `field_nickname` e concluir S-R3-B.

**Regra permanente:** o legado na raiz `/` não pode ser removido
depois da comparação, e `/minha-conta` direto permanece 404.
Não criar aliases, duplicar storage nem ampliar o escopo do Perfil.

## S-R2 concluído no código: experiências independentes (dev.13)

- **Feito:** providers independentes `conta` e `perfil`, rotas
  `/studio/conta`, `/studio/perfil`, rotas do User em
  `/studio/perfil/{bio,privacidade,redes/{rede}}`, controle de acesso,
  testes Unit e roteamento atualizados. A experiência `pessoal` e suas
  rotas desapareceram, **sem alias temporário**.
- **Preservação obrigatória:** Minha Conta legada continua em `/`
  (Domain ACCOUNT), com `/minha-conta` técnico direto respondendo 404.
  **Não há fase futura de desativação do legado**, mesmo após aceite
  da nova interface.
- **S-R3 próxima:** extrair a composição das seções Conta do Portal sem
  invadir Meu Perfil e sem aninhar dois shells. Hoje os links iniciais
  ainda abrem os formulários do Portal no contexto original.
- **S-R4:** editor isolado de apelido, aperfeiçoamento do Perfil e
  testes de upload e Form API no-JS.
- **S-R5:** comparação e aceite visual/funcional entre Conta legada
  e Studio, preservando a antiga.
- **Testes pendentes:** Unit, Kernel, Functional, cache/access, Drush,
  mobile/Firefox ESR-X11 e validação de rotas no Runtime.

**Documento prioritário:**
[Conta e Perfil separados](../../../../../../../docs/architecture/ACULTA-STUDIO-CONTA-PERFIL-SEPARADOS.md).
As fases antigas P0–P7 abaixo são históricas; qualquer menção a
remoção final do shell antigo é revogada.


## Incremento STUDIO-P2-A — editor privado parcial (dev.12, 2026-10-24)

**Implementado no código:** `/studio/pessoal/perfil` com leitura
exclusiva da conta corrente, renderização reutilizada de
`AccountBioCard`/`SocialLinksCard` do User, modal de foto atual, link
ao formulário de apelido e serviço oficial de perfil público de
`aculta_user`. Entrada `/studio/pessoal` inclui navegação ao editor.
As operações continuam nos serviços/formulários proprietários; não
há novos campos, entities, handlers, aliases nem copias de perfil.

**P2-B pendente:** integrar apelido e privacidade no contexto Studio
sem sobrepor o `AccountShellBuilder` e sem reescrever o User,
melhorar fallback sem JavaScript (o acesso GET direto aos modais de
bio/redes hoje é bloqueado pelo User), preservar erros, CSRF e uploads.
**Gates P2-A pendentes:** Unit e Kernel/Functional, acesso entre contas,
privacidade, nome inválido/perfil privado, ajax modal, foto/recorte,
cache/Domain e Firefox ESR X11. Não declarar Runtime homologado.

## Incremento dev.11 — remover compatibilidade temporária (2026-10-24)

A home canônica `/studio` conserva somente o route ID
`aculta_studio.hub`; `/studio/marley` permanece o início editorial
e as áreas/subáreas reais estão em `/studio/marley/{aba}/...`.
Foram eliminadas as rotas de prévia, capitalização, árvore editorial antiga
e os redirects legados, inclusive os aliases estudantis do LMS.
O contrato de acesso do Studio, MarleyTabManager, providers, Conta e perfil
público não mudou. P2 (editor de perfil) continua o próximo trabalho
funcional, condicionado à homologação de rotas, Unit/Kernel e Runtime.
`scripts/validate-no-temporary-route-aliases.php` evita reintrodução
de aliases. As fases dev.7–10 descritas abaixo são históricas.



**Versão STUDIO 0.1 · 2026-10-22**. GanjaLab e o entrypoint inicial WE SATIVA existem em `aculta_studio`; detalhes de cobertura e pendências abaixo distinguem o que está implementado do que segue em planejamento.

Fonte de verdade: [arquitetura ACULTA Studio](../../../../../../../docs/architecture/ACULTA-STUDIO-EXPERIENCE-PLATFORM.md).

## Incremento STUDIO-P1-C — hub canônico e Marley (dev.10, aguardando gates)

**Implementado no Git:** `/studio` renderiza o hub autorizado; Marley
mantém `aculta_studio.home` em **`/studio/marley`**, e todas as suas
subrotas canônicas continuam minúsculas. `/studio/Marley` e
`/studio/inicio` são redirecionamentos temporários 302 apenas GET/HEAD.
A filtragem de catálogo e as áreas reais do `MarleyPresenter` permanecem.
Os testes Unit de rotas e do hub foram atualizados para essa decisão.

**Não executado nesta edição:** PHPUnit, Kernel/Functional, reconstrução
de cache de rotas, teste autenticado no purpose `account` e Firefox ESR/X11.
Sem evidência de homologação do Runtime. Preservar P2/P3 para editor pessoal
e formulários; não mover dados, `AccountShellBuilder`, login, pagamentos,
mensageria ou `/u/{apelido}`.

**Critérios de validação antes do próximo incremento:** testar usuário comum
sem Marley, editor com Marley, módulo Wiki/LMS ausente, links canônicos e
redirects GET/HEAD; confirmar /studio apenas como hub, a home
/studio/marley, o logout, cache/acesso e fallback sem JavaScript.

## Incremento STUDIO-P1-B — código dev.9, validação pendente

- **Implementado em Git:** provider de metadados Marley com acesso vinculado
  às áreas visíveis do `MarleyPresenter`; enumeração ordenada de providers;
  registro de Marley; controller e rota de **prévia** `/studio/inicio` com
  filtro por disponibilidade, autorização e existência de rota.
- **Preservado:** `/studio` ainda é a entrada histórica do Marley;
  `/studio/marley`, `/studio/ganjalab`, `/studio/we-sativa`,
  `/studio/pessoal`, home `/` da Conta e `/u/{apelido}` permanecem
  com seus donos. Não houve alteração de formulários ou shell.
- **Gates pendentes:** executar PHPUnit Unit, Kernel/Functional de registry,
  container e rotas, e smoke Firefox ESR/X11 autenticado em ambiente de teste
  com permissionamento de usuário comum/editor, módulos opcionais habilitados
  e ausentes. O cache da ponte Marley é intencionalmente `max-age: 0`.
- **Próximo incremento:** implantação controlada do hub canônico em
  `/studio` após revisar os consumidores e a compatibilidade de `legacy_home`;
  depois P2/P3 para formulários proprietários. Não avançar estados Dev→RC
  sem evidências de testes e Runtime.

## STUDIO-P0–P7 — migração futura de Minha Conta e Meu Perfil (decisão 2026-10-24)

**P0 documental concluída; P1-A–P1-C implementadas em código até a dev.10, sem homologação Runtime.**
Foi registrada a experiência `pessoal` e sua rota `/studio/pessoal`, com
controller autorizado, links para a Conta e testes Unit adicionados. **Nenhum
teste PHPUnit/Kernel/Runtime foi executado nesta edição pelo GitHub**;
P1-A requer gates antes de ser considerada homologada. O editor pessoal e a navegação contextual unificada seguem pendentes;
a ponte Marley e a prévia `/studio/inicio` já existem em código.
A arquitetura de referência está em
[Hub e Meu Espaço](../../../../../../../docs/architecture/ACULTA-STUDIO-PERSONAL-EXPERIENCE.md).
Os incrementos editoriais/LMS/Wiki registrados abaixo não são revogados nem
renomeados; continuam com seus gates próprios e status histórico.

| Fase | Ordem | Entrega prevista | Aceite mínimo |
| --- | --- | --- | --- |
| **P0** | Agora; docs | Contrato, identidade, ownership, rotas atuais/alvo, menu e dependências Studio/Portal/User/Messenger/tema. | Documentação alinhada; nenhum código/Runtime modificado. |
| **P1** | Depois de P0 | Hub `/studio`, provider `pessoal`, ponte de metadados Marley para registry; experiências autorizadas. | Sem colisão com Marley/Ganjalab, cache/access, fallback full-page. |
| **P2** | Após P1 | Editor privado de Meu Perfil usando formulários User; perfil público fica `/u/{apelido}` em `main`. | Foto, avatar, bio, redes, visibilidade, duas contas e cache. |
| **P3** | Após P1 | Minha Conta: dados, endereço/CEP/S-N, documentos opcionais, segurança, OAuth e confirmação. | Form API/POST/CSRF e checkout intactos; segurança testada. |
| **P4** | Após P1 | Avisos e mensagens como atalhos, atividades, apoio, pedidos e preferências. | Ownership correto, sem storage, SMTP ou inbox duplicados. |
| **P5** | Após P2–P4 | Um shell, menu unificado/contextual, links canônicos e aliases GET/HEAD seguros. | Sem `AccountShellBuilder` aninhado, URLs e forms legados íntegros. |
| **P6** | Após P5 | Unit, Kernel, Functional, Runtime e Firefox ESR/X11 autorizado; mobile/teclado/no-JS. | Gates proporcionais, privacidade, Domain, WCAG e temas. |
| **P7** | Depois do aceite P6 | Retirar só a apresentação antiga duplicada; conservar entidades/APIs e rollback. | Aprovação operacional, equivalência e nenhuma perda de dados. |

**Bloqueadores já identificados:** `aculta_studio.legacy_home` usa `/studio`
para Marley; `AccountShellBuilder` e `account-navigation.js` produzem o
shell da Conta; a raiz `/` do purpose `account` continua a home da conta;
`/minha-conta` é bloqueado; formulários de endereço, foto e segurança
têm políticas e handlers próprios. **A definição futura de navegação não
autoriza redirects ou alterações de HTTP/POST nesta fase.**

### Incremento Studio dev.6 — início WE SATIVA

Adicionada a rota `/studio/we-sativa/inicio`, purpose `account`, com um provider
de página inicial opcional. O provider encaminha o render ao serviço Wiki,
responsável por dados, autorização e cache. O Kernel integrado validou a rota e
os links condicionados a acesso e HTML renderizado no ACULTA420 (2 testes / 208
asserções junto ao owner Wiki).
Teste Unit do controller inclui retorno owner, rota sem o contrato de início e
acesso negado (3/3); a suíte Studio completa passou em 20 testes / 61 asserções.
Runtime e Firefox ESR/X11, incluindo 320px e Olivero, permanecem pendentes.

### Incremento Studio dev.4 — disponibilidade da aba Wiki

`WikiTab::isAvailable()` exige o módulo proprietário `aculta_wiki` além do
bundle `wiki_entry`. A configuração do tipo de conteúdo pode permanecer após
desativar o módulo; nesse caso, Studio não apresenta uma aba sem serviços do
owner. Unit cobre o módulo desligado com bundle retido, o par habilitado e o
bundle ausente (focal 3/10; suíte Studio Unit completa 13/41). Runtime intocado.

### Incremento Studio dev.5 — separar autoria WE SATIVA do GanjaLab editorial

`create wiki_entry content` sozinho não dá acesso à área Wiki do GanjaLab nem
exibe seu inventário compartilhado. A autoria continua na WE SATIVA. GanjaLab
abre com permissão de edição ampla, revisão de revisões ou moderação de
propostas; a seção de verbetes exige `edit any wiki_entry content`. Teste Unit
focal: 7 testes / 27 asserções. A suíte Firefox ESR visível foi iniciada, mas
ficou aguardando o Drush `uli` sem produzir URL de login/artefatos; foi encerrada
após cerca de dois minutos. Runtime e visual seguem sem homologação.

## Prioridades

| Marco | Trabalho | Gate |
| --- | --- | --- |
| 0.1-A | inventariar infra já presente: StudioTab, StudioPresenter, listing, GanjaLab, rotas e consumidores projetados WE SATIVA/GanjaLab420 | inventário existente versus planejado |
| 0.1-B | ADR de contrato genérico de experiência separando purpose, navegação, provider de ações e ACL | não acoplar WE SATIVA ao StudioTab de conta |
| 0.1-C | endurecer cache e revision/entity access de StudioListingBuilder; corrigir queries auxiliares e paginação; Unit/Kernel | nenhum vazamento entre usuários, LMS, Wiki, Forum/Revista |
| 0.1-D | WE SATIVA: interface de autoria no purpose wiki usando adapters aculta_wiki, Core SDC/UI Suite e Form API | criar/enviar/acompanhar sem acesso de moderador |
| 0.1-E | GanjaLab: seção Wiki/Moderação na WikiTab existente; provider/POST de aculta_wiki | content_editor e transições oficiais, concorrência e feedback |
| 0.1-F | GanjaLab420 e futuras interfaces: inventário de consumidores reais e adaptação, sem inventar rotas | contratos testados por consumidor |
| 0.1-R | homelab, acessibilidade, HTTP sem JS, AJAX e cache por purpose | evidência real |

### WE SATIVA 0.1-D0 — entrypoint e Meu trabalho (Studio dev.3 / Wiki dev.39)

Implementado `/studio/we-sativa` no purpose `account`, com provider Studio que
delega ao `aculta_wiki.contributor_workspace`. O serviço Wiki consulta verbetes
e propostas próprios por cursor independente, revalida Entity Access e compõe
Meu Trabalho com SDCs. Criação, edição, contribuição e status usam as rotas,
serviços e Form API da Wiki; Studio não duplica armazenamento nem ACL.

O Kernel Wiki/Studio combinado foi ampliado desde a entrada inicial; no HEAD
Wiki dev.77, a classe completa de ciclo da Wiki passou em MariaDB (23 testes /
1.039 assertions) e inclui regressões de rota, propósito, autoria, paginação e
acesso. Os formulários Wiki têm retorno cacheável ao workspace quando o
provider/acesso estão disponíveis. A interface visual da experiência WE SATIVA ainda não foi homologada em
Firefox ESR/X11, 320 px, Olivero ou Runtime; a suíte atual do browser Studio
exercita principalmente GanjaLab. O smoke WE SATIVA foi adicionado em
`tests/browser/aculta-wiki/we-sativa.py`; a execução de 2026-10-24 não encontrou
rota/provider no container ativo do Runtime, apesar de Studio e Wiki constarem
habilitados. Reconstrução do container segue pendente de autorização; não
declarar a validação visual aprovada.

## Regras para agentes

**Não criar novo módulo ou renomear machine name.** ACULTA Studio é o submódulo presente. Não copiar cabeçalhos nem criar design system paralelo. `aculta420` fornece visual via Core SDC/UI Suite; `aculta_studio` fornece orquestração e contratos de UI; User/Wiki/Forum/LMS/Messenger mantêm sua lógica. Preservar branch e arquivos de outros agentes. Abrir fases por commits pequenos; não alegar validação em homelab sem executá-la.

### GanjaLab Wiki — integração inicial (Studio dev.2 / Wiki dev.19)

A `WikiTab` existente agora consome a fila autorizada de revisões e propostas fornecida por `aculta_wiki`. Propostas abrem o Form API de decisão; revisões abrem o formulário nativo do Node após checagem de rota no purpose Wiki. Kernel combinado cobre gates de permissão e listagens. Runtime e revisão visual Firefox/X11 ainda não foram homologados; a Wiki segue desabilitada no Runtime.

## Estado D-A.2-T

Primeira etapa realizada: cache de listagens por usuário, checagem explícita de revisão, teste Unit escrito, ainda sem execução Runtime. **Pendentes**: consulta de content_moderation_state, paginação, isolamento entre usuários/Group/purpose e provider da moderação Wiki. Não implantar fila antes dos gates.

## Atualização STUDIO 0.1-B / D-A.2-T2

STUDIO 0.1-B: contrato de providers de experiência definido no ADR da plataforma. StudioTab permanece do GanjaLab; WE SATIVA terá provider no purpose Wiki. D-A.2-T2: removida a consulta global content_moderation_state accessCheck(FALSE) do StudioListingBuilder e passado o estado pela última revisão visível. Pendências: paginação correta, grants e testes Kernel, provider Wiki, nenhuma fila ativada.

## GanjaLab420 — migração para ACULTA Studio (G420-A)

[Contrato e inventário de migração](../../../../../../../docs/architecture/GANJALAB420-TO-ACULTA-STUDIO-MIGRATION.md). GanjaLab420 é **a experiência LMS do estudante** dentro de ACULTA Studio. Os entrypoints de `/ganjalab420` e `/minha-aprendizagem` foram transferidos para `aculta_studio.ganjalab420_controller`, que delega ao controlador LMS; o LMS preserva dados, progresso, matrículas, certificados, controle de acesso e cache. Não confundir com GanjaLab editorial nem com WE SATIVA. A extração da composição visual para Studio será a G420-C; não declarar concluída. Antes do deploy habilitar Studio se necessário e executar testes Function/Kernel e smoke de rotas.

## G420-B — Provider de experiência implementado (código, validação pendente)

Contrato `StudioExperienceProviderInterface`, registry `StudioExperienceManager` e provider `GanjaLab420ExperienceProvider` registrados por DI. Entry point `GanjaLab420Controller` migrou da dependência direta do controller LMS para o manager Studio; render do LMS preservado. `StudioTab` permanece exclusivo de GanjaLab editorial; WE SATIVA não ganhou provider fictício. Foram escritos testes Unit de negação e registry, **não executados aqui**. G420-C: extrair composição visual, mantendo estado acadêmico, Group e autorização no LMS. O contrato completo está em [migração GanjaLab420](../../../../../../../docs/architecture/GANJALAB420-TO-ACULTA-STUDIO-MIGRATION.md).

## G420-C — Dashboard no Studio e domínio de conta

`LearnerDashboardComposer` agora é responsável pela composição do dashboard; `LearningDashboardManager` continua dono de dados/cache/regras LMS. O provider do estudante usa o composer e as antigas chamadas `LearningDashboardController::student()` delegam a ele. Namespace canônico: `/studio/ganjalab`, `/studio/ganjalab420`, sob `_aculta_domain_purpose: account` (host `conta.aculta.*` definido pelo Portal). Aliases temporários mantidos. Documentação de arquitetura consolidada em `docs/architecture/GANJALAB420-TO-ACULTA-STUDIO-MIGRATION.md`. **Implementação sem gate Runtime:** precedência de rotas, DI, Kernel, HTTP, SDC responsivo e testes anteriores precisam de verificação antes do aceite. Próxima G420-C2: concluir compatibilidade/testes do compositor, separar view models e elevar cartões para SDC/UI Suite; não duplicar dados ou serviços acadêmicos.

## G420-C2 — cartões SDC do painel estudante (2026-10-22)

**Implementado em código, não homologado no Runtime.** O `LearnerDashboardComposer` passou a compor as listas de cursos e certificados com o componente já existente `aculta420:card`, distribuído pelo `aculta420:content-grid` (duas colunas responsivas). Mantidos `aculta420:context-header` e `aculta420:empty-state`. A progressão permanece em elemento HTML nativo `progress` com texto e `aria-label`, dentro do slot do card; não se criou CSS, Twig ou design system paralelo.

Os dados e sua cacheabilidade continuam provenientes do `LearningDashboardManager` no LMS, inclusive autorização, notas e certificados. Cursos mantêm URLs fornecidas pelo Portal/LMS, certificados usam rota de verificação existente; nenhum ACL novo. `#cache.max-age: 0` conservado até aferição em ambiente real. Teste Unit existente atualizado para preservar header e estados vazios SDC; execução PHP lint, PHPUnit, inspeção UI Suite/SDC, contraste dark/light, 320–1440px, navegação por teclado e smoke de domínio conta ainda **pendentes**.

**Próxima C2-T:** fixture de curso matriculado, avaliação pendente e certificado ativo/revogado, testes Kernel/Functional de renderização com SDC e regressão de URLs no purpose account. Após homologação, avaliar um componente de progresso próprio do ACULTA420 apenas se houver segundo consumidor real.

## Replanejamento transversal — 2026-10-22 · STUDIO 0.1-C2-R

**Prioridade ativa: GanjaLab editorial.** G420-C2 permanece implementada estaticamente e não homologada. Não bloquear o desenvolvimento editorial pela construção paralela de componentes LMS/tema. A homologação G420-C2-T fica condicionada a fixtures reais, layout em ACULTA420, contratos dos dados LMS, acessibilidade, mobile, light/dark e gates Domain/HTTP. `aculta420:card`, `content-grid`, `context-header`, `empty-state` são componentes existentes, mas existência de YAML/Twig/story não é evidência de consumidor homologado, comportamento responsivo ou UI Suite validada. Progresso, avaliação pendente, estados de certificado e contexto de turma seguem acoplados aos dados/provedores LMS; não declará-los componentes de design maduros. Não criar um novo componente só para passar o gate.

### Matriz de dependências e frentes

| Frente | Código constatado | Não concluído / dependência | Política |
| --- | --- | --- | --- |
| G420-C2 dashboard | Composer no Studio, cards/grid/empty/context-header SDC existentes, dados em `LearningDashboardManager` | fixtures matriculado/não matriculado, certificados válidos/revogados, UI Suite real, Kernel/Functional, browser, mobile/dark, propósito account | congelar novas abstrações e acompanhar trabalho LMS/tema |
| G420-D salas e turmas | LMS, Group e recursos especializados existentes | adapters e UX integrada, avaliações e materiais/certificados em desenvolvimento paralelo | não iniciar pela UI sem consumidores e autorização confirmados |
| ACULTA420 | SDC card/grid/header/empty e stories estáticas | verificar render real, contratos de slots, tokens, 320–1440px, dark/light e gates UI Suite | tema é dono do visual; Studio não cria CSS concorrente |
| GanjaLab editorial | StudioTab/Presenter/Controller/AJAX, plugins Revista/Wiki/Forum/Cursos/Galerias | hardening de consultas, ações reais, jornadas próprias, editor e fila | prioridade imediata |
| Wiki moderação | proposta e WikiTab existentes | provider autorizado/paginado, grants por revisão, Kernel, Content Moderation | bloqueado para produção |
| WE SATIVA | provider e `/studio/we-sativa`; workspace Wiki com paginação, SDCs, Entity Access e links para fluxos Form API do dono | validação visual/browser de autoria a 320 px, Olivero, fallback sem JS e Runtime | avançar visualização sem criar storage ou ACL no Studio |

### Frente prioritária: GANJALAB E-0.1 — Editor Studio

- **E-A inventário/contrato:** conferir consumidores de cada plugin, rotas e forms dos donos, ownership por área, URLs canônicas `conta.aculta.*/studio/ganjalab`, links e dependências; limpar README/docs obsoletos sem remover rotas legadas.
- **E-B Meu trabalho:** padrão default `Meus conteúdos`, acessos autor/equipe/moderador distintos, filtros autorizados, paginação depois dos grants e render access/cache por item. Prioridade segurança antes de disponibilizar listagens novas.
- **E-C Jornada editorial Revista:** criar, salvar rascunho, editar, pré-visualizar, enviar para revisão e acompanhar pelo Form API e transições existentes do dono; Studio apenas navegação, composição e feedback.
- **E-D Editor shell:** layout mobile-first e progressive enhancement, foco, erro, aviso de alterações não salvas e ações contextualizadas via Core SDC + UI Suite do ACULTA420, preservando Institution/Domain Header.
- **E-E adapters:** estender a Wiki/Fórum/Galerias/Cursos somente após cada dono confirmar forms, ACL e estados; moderação Wiki em microfase própria com provider e testes.
- **E-R aceite:** testes Unit/Kernel/Functional de dois autores e editor, revisão privada, grants Domain e Group, 403/404, cache quente, no-JS/AJAX, 320–1440px, contraste e teclado.

**Não habilitar nova fila nem ampliar visibilidade de estados no E-A.** Cada microfase terá commit próprio na PR corrente, sem misturar trabalho paralelo. Testes de runtime não executados nesta reclassificação documental.

## GANJALAB E-A.1 — Entrada simples de publicação (código em 2026-10-22)

`StudioController::welcome()` renderiza na entrada do GanjaLab o SDC `context-header`, cartões `card` e grade `content-grid` com ações filtradas pelos providers `StudioTab`/Presenter e acesso às rotas do dono. Nenhuma entidade ou workflow migrou. Prioridade imediata **E-A.2 testes reais de ações/domain/visual** e **E-B Meu trabalho seguro**, antes de implementar editores inline ou moderação. Auditoria: [MARLEY-E-A-EDITOR-AUDIT.md](MARLEY-E-A-EDITOR-AUDIT.md). Sem PASS de Runtime, mobile ou PHP lint nesta sessão.

## GANJALAB E-B.1 — Meu trabalho (2026-10-22)

**Implementação parcial, não homologada:** área Revista usa seção padrão `Meu trabalho` com aba inicial `Meus conteúdos` e demais filtros pessoais. Todas as abas dessa seção declaram `scope: mine`, e o `StudioListingBuilder` impõe `uid = conta atual` na entity query antes da paginação Drupal de 20 itens. A tabela continua verificando o acesso da entidade e da última revisão individualmente; o cache varia por usuário e parâmetros do pager. Foi criado teste de caracterização por código, sem execução PHPUnit/Kernel/HTTP nesta sessão.

**Limitação fundamental:** os filtros rascunho/revisão/publicado ainda são aplicados após a paginação, porque dependem da última revisão visível. Assim, páginas filtradas podem aparecer incompletas; o pager conta candidatos por autor, não apenas os que correspondem ao estado. Não anunciar paginação de estados pronta nem ativar listas de equipe/moderação. Para E-B.2, implementar provider proprietário com consulta paginada coerente com workflow/revision grants ou refinar indexação segura, com testes Kernel de dois autores, drafts privados, revisão não-default, cache quente, domínio e mudança de estado. Nenhum bypass de `accessCheck(TRUE)` ou da revisão é permitido.

**UX alvo:** a home GanjaLab mantém convite para criar; `Meu trabalho` oferece continuar, revisar pendências e localizar itens próprios. Equipe e moderação são seções distintas, com autorização explícita do dono, nunca a aba `Todos` genérica. E-B.3 converte listagens autorizadas em cartões SDC mobile-first do ACULTA420 quando o contrato de dados estiver estável, com link real, foco, estado acessível e feedback. Continuar com E-C apenas após aceite dos grants das jornadas de publicação.

## GANJALAB E-B.2 — paginação pós-ACL da última revisão (2026-10-22)

**Implementado em código, não homologado.** Para `scope: mine`, o Studio faz consulta de nós com `accessCheck(TRUE)`, bundle e `uid` antes de examinar revisões; varre candidatos do autor em lotes de 100, valida `view` do nó e `view` + `view revision` da última revisão, determina o estado e só então cria o pager Drupal sobre os IDs correspondentes (20/página). Uma revisão privada não deve fornecer estado ou posição de paginação; sem revisão autorizada o item pessoal é omitido. As demais listagens genéricas continuam com limite histórico de 50 e **não estão homologadas como moderação**. O serviço `pager.manager` é injetado no `StudioListingBuilder`; teste estático de caracterização atualizado.

**Gate de escalabilidade pendente:** varrer todos os IDs acessíveis do autor a cada pedido pode ser caro em acervos grandes; requer benchmarks, Kernel com múltiplos autores e revisões, teste de acesso/cache e futuro provider paginado/indexado com garantias equivalentes. Não criar indexação de estados com `accessCheck(FALSE)` nem ignorar grants. O estado e o count podem variar durante edições concorrentes; sem snapshots transacionais nessa microfase.

**Próxima E-B.2-T:** homelab `drush cr`, PHPUnit, Kernel com 2 autores e revisões default/não-default, pagers com muitos registros, rota `conta.aculta.*/studio/ganjalab/revista/publicacoes/rascunhos`, evitar leakage em cache quente. E-B.3 só após validar o contrato de visualização e conteúdo.

## GANJALAB E-B.2-PERF — cursor de volume elevado (2026-10-22)

A varredura integral de IDs do autor por request foi substituída por **paginação progressiva por cursor (keyset/seek)** em `StudioListingBuilder`. Em `scope: mine`, a entidade é buscada com `accessCheck(TRUE)`, `uid`, `bundle`, ordem `changed DESC, nid DESC` e cursor `studio_cursor` validado como posição, não autorização. Cada consulta busca até 100 candidatos e há limite de dez lotes por request; cada candidato exibido exige `view` no nó e `view` + `view revision` na revisão mais recente, com filtro de estado antes da exibição. A navegação passa a usar link real “Carregar mais publicações” (não pager numérico); sem JS funciona. `url.query_args:studio_cursor` participa do cache. Serviços usam `request_stack` em vez de `pager.manager`.

**Escalabilidade e precisão:** custo por requisição limitado a até 1000 candidatos, sem `count(*)` condicionado por permissões de revisão e sem ler toda a coleção pessoal; porém estados muito raros podem demandar vários cliques de continuidade, inclusive páginas sem registros. O cursor é uma posição textual e **não é uma garantia de snapshot**: concorrência e alteração de `changed` podem mover itens entre páginas. Não há contagem total nem paginação numerada exata. O código ainda precisa de Kernel/Functional e benchmarks com milhares de nós/revisões. Para exigência futura de contagem precisa/ordenação estável sob edição concorrente, especificar índice derivado revision-aware com invalidação e grants corretos — nunca índice público de metadados de revisões privadas.

**Status:** implementação com limite de custo, sem homologação Runtime; não declarar “otimização definitiva” no sentido de contagem exata ou isolamento transacional. Próximo gate E-B.2-T: simular muitos candidatos sem resultado, acesso negado e última revisão privada, mudanças concorrentes, URLs inválidas e DOM no purpose account, mais testes de performance e verificação de DBCache.
# Identidade vigente — 2026-10-24

ACULTA Studio Marley (`marley`) é a experiência editorial e ACULTA Studio
Ganjalab (`ganjalab`) é a experiência de aprendizagem. `aculta_studio` continua
sendo a plataforma compartilhada. As seções abaixo de G420/GanjaLab preservam
o histórico das fases anteriores; nomes e caminhos atuais estão no
[contrato de identidade](../../../../../../docs/portal/ACULTA-STUDIO-IDENTITY.md).

