# ACULTA Studio — plataforma transversal de interfaces próprias

## ADR-038 - caminho aprovado para as Suites (implementação parcial, 2026-10-30)

Marley `conta.aculta.*/marley`; Ganjalab `cursos.aculta.*/ganjalab`; WE SATIVA `wiki.aculta.*/wesativa`; Green Stash `loja.aculta.*/greenstash`; Culturas `eventos.aculta.*/culturas`. `/studio` e todo `/studio/*` serão removidos e responderão 404, sem aliases/redirects. `aculta_studio` permanece framework compartilhado. A raiz de `conta` será entrada de Conta, fora de `/studio`; Conta e Perfil permanecem separados. As referências de rota anteriores neste arquivo descrevem o estado pré-migração e não são destino para implementação nova. Consulte ADR-038.

## Contratos anteriores

O restante deste documento inclui descrições das rotas anteriores à ADR-038. Leia-as como histórico/estado pré-migração; não como padrão para novas implementações.

## Contrato anterior de identidade — caminhos substituídos pela ADR-038

O Studio mantinha **Minha Conta** (`/studio/conta`) e **Meu Perfil**
(`/studio/perfil`) como providers independentes; esses caminhos agora são
`/conta` e `/perfil` sem unir os dois owners. Não há experiência
`pessoal` ou aliases. Conta incorpora serviços de cadastro, segurança
e pedidos do Portal. Perfil administra exclusivamente identidade
comunitária por componentes do User e não incorpora dados cadastrais.

**Minha Conta legada em `/` no Domain ACCOUNT permanece para sempre,
inclusive depois do aceite visual/funcional.** O caminho técnico
direto `/minha-conta` continua 404. Nunca remover `AccountShellBuilder`,
a navegação original ou seus formulários como parte da migração.
Esta regra substitui toda indicação antiga de limpeza final do legado.

[Contrato normativo](ACULTA-STUDIO-CONTA-PERFIL-SEPARADOS.md).
Dev.13 implementada no código Git, ainda sem homologação Drupal/Runtime.


**Contrato vigente da refatoração de identidade · 2026-10-24.** A lista abaixo substitui as identidades e URLs antigas descritas em seções históricas deste documento.

| Experiência | Nome público | ID |
| --- | --- | --- |
| Plataforma | ACULTA Studio | `aculta_studio` |
| Editorial | ACULTA Studio Marley | `marley` |
| Aprendizagem | ACULTA Studio Ganjalab | `ganjalab` |
| Gestão comercial | ACULTA STUDIO GREEN STASH | `green_stash` |
| Contribuição Wiki | ACULTA STUDIO WE SATIVA | `we_sativa` |

Rotas canônicas: Marley em `/studio/marley` e níveis aninhados; Ganjalab em `/studio/ganjalab`; ACULTA STUDIO WE SATIVA em `/studio/we-sativa` e `/studio/we-sativa/inicio`; ACULTA STUDIO GREEN STASH em `/studio/green-stash`. Todas ficam no purpose `account`, resolvido pelo Portal. `/studio/ganjalab` pertence exclusivamente ao dashboard estudantil, portanto a antiga entrada editorial não pode ser preservada nesse endereço. Aliases e histórico estão em [contrato de identidade](../portal/ACULTA-STUDIO-IDENTITY.md).

**Registros abaixo de 2026-10-24 são históricos.** Menções a GanjaLab editorial e GanjaLab420 estudantil descrevem nomenclaturas anteriores; a plataforma, ownership de negócio, ACULTA STUDIO GREEN STASH e ACULTA STUDIO WE SATIVA permanecem conforme o contrato vigente.

## Entrada vigente Studio — P1-C, dev.10 (2026-10-24)

No código, **`/studio` é o hub** e **`/studio/marley` é a entrada
canônica do Marley**, sempre em minúsculas. O route ID editorial
`aculta_studio.home` continua inalterado. Os caminhos
`/studio/Marley` e `/studio/inicio` agora são aliases 302 GET/HEAD;
não representam URLs canônicas. Os caminhos internos de Marley
`/studio/marley/{aba}/...` continuam como antes.

Esse incremento ainda não foi homologado no Runtime; as fases de
formulários pessoais/conta e navegação contextual seguem planejadas.
O texto histórico abaixo deve ser interpretado conforme essa definição.

## Nova frente documental — Hub e Meu Espaço (STUDIO-P0, 2026-10-24)

**Atualização de implementação P1-B (dev.9):** Marley agora também
é exposto pelo registry de experiências, por um provider que consulta
`MarleyPresenter` sem substituir suas abas. `/studio/inicio` apresenta
uma prévia autorizada do hub, enquanto `/studio` permanece sob o
controller legado do Marley. Testes Unit foram criados, mas os gates
PHPUnit/Runtime deste incremento não foram executados.



A expansão da plataforma para **todos os membros** foi definida como alvo de
produto: `/studio` será o hub, `/studio/pessoal` será Meu Espaço, com menu
unificado de experiências e seções contextuais. Minha Conta e Meu Perfil
serão incorporados **somente na apresentação**, preservando Portal, User,
Messenger e outros owners. O perfil público `/u/{apelido}` permanece no
purpose `main`.

**Esta direção é planejamento, não estado do código nem autorização de corte
das rotas atuais.** O `StudioExperienceManager` e os providers Ganjalab/WE SATIVA existem. A fase **P1-A (dev.8)** acrescentou o provider `pessoal`, o controller e a rota explícita `/studio/pessoal`, com links para a Conta e testes Unit ainda não executados. A ponte Marley para o registry e o hub `/studio` continuam planejados; `/studio` hoje funciona como entrada legada Marley.
Preservar `/` de account e `/minha-conta` técnico bloqueado. Não sobrepor o
`AccountShellBuilder` e o shell próprio do Studio numa mesma rota.

O documento normativo específico, com matriz de ownership, mapa de rotas,
compatibilidade e fases P0–P7, é
[ACULTA-STUDIO-PERSONAL-EXPERIENCE.md](ACULTA-STUDIO-PERSONAL-EXPERIENCE.md).
Históricos e ADRs abaixo continuam relevantes onde não incompatíveis com
essa direção; referências antigas de identidade não representam Runtime atual.

## Correção de nomenclatura e ownership

**`aculta_studio` é o submódulo Drupal já existente**, localizado em `web/modules/custom/aculta_portal/modules/aculta_studio/`; possui `aculta_studio.info.yml`, services, plugin `StudioTab`, rotas e frontend progressivo. **Não criar novo submódulo** nem tratar GanjaLab como sinônimo técnico do módulo.

**ACULTA Studio é a plataforma de experiência de interfaces próprias do usuário ACULTA**, presente e futura:
- **GanjaLab**: espaço de trabalho para autores, editores, moderadores e educadores; já possui áreas/seções/abas, navegação AJAX/HTTP e rota `/ganjalab`.
- **GanjaLab420**: experiência visual/identidade própria associada ao ecossistema GanjaLab conforme consumidores reais e futura especificação; **não declarar módulo ou rota próprios sem confirmação**.
- **WE SATIVA**: experiência de contribuição e escrita da Wiki420, projetada sobre contratos comuns de interface ACULTA Studio; **não é uma aplicação de backend autônoma** e não deve duplicar navegação, Form API ou shell.
- **Futuras experiências de usuário**: devem reutilizar as capacidades neutras do ACULTA Studio quando houver consumidores reais.

**Distinção:** a experiência do colaborador WE SATIVA permanece separada da experiência de moderação do GanjaLab. Compartilham uma plataforma de UI/orquestração, não a mesma tela, fila ou privilégios.

## Camadas e contratos

| Camada | Responsável | Limites |
| --- | --- | --- |
| Drupal Core/contrib | Entity API, Form API, Content Moderation, User, Group, CKEditor5, Views, AJAX | fonte de verdade |
| `aculta_portal` | Domain/purpose, integração global e headers do site | não absorver UI própria Studio |
| `aculta_user` | identidade, participação, papéis/associação/autor | sem fila Wiki ou inbox |
| **`aculta_studio`** | contratos de interface, layout/orquestração de experiências, navegação/ações autorizadas, estados acessíveis, pontos de extensão | sem storage editorial, grants alternativos ou workflow |
| `aculta_wiki` | conteúdo, criação/revisão, propostas, moderação backend, Entity Access | provê formulários/ações ao WE SATIVA e à moderação no GanjaLab |
| `aculta_forum`, LMS, Revista, Gallery | conteúdo e workflows específicos | provêm recursos ao Studio via adapters |
| `aculta_messenger` | mensagens, avisos, contadores/entrega | Studio consome, não duplica |
| `aculta420` | Core SDC + UI Suite (UI Patterns/UI Styles), tokens, modo claro/escuro | aparência compartilhada; sem ACL |

### O que pode ser compartilhado dentro do aculta_studio

- Registro/descoberta de experiências **por plugin/provider** com ID estável, label, purpose permitido, disponibilidades reais, rotas reais e `AccessResultInterface` cacheável.
- Shell de *conteúdo contextual* e breadcrumbs internos; nunca duplicar Institution Bar ou Domain Header multidomínio.
- Contratos de navegação progressiva HTTP + AJAX, URL canônica, foco, aria-live e estados de erro.
- Orquestração de forms, actions, listas e painéis autorizados **fornecidos pelo dono**; listas sensíveis sempre com entity/revision access e cache correto.
- Estados visuais genéricos (`loading`, `empty`, `error`, `forbidden`) e experiência mobile-first; tokens e renderização no tema por SDC/UI Suite.
- Integração de retorno e redirecionamento entre experiências via URI/purpose canônicos, sem furar acesso.

**Não compartilhar:** formulários de negócio duplicados, entidades de conteúdo, aprovação/publicação direta no Studio, storage de propostas, permissões mágicas, scripts CSS/JS legados não-SDC, nem menus globais alternativos.

## Estrutura recomendada (planejada, não criada)

```
aculta_studio/
  src/Attribute/StudioTab.php                  # já existe; área GanjaLab
  src/Plugin/StudioTab/                        # já existe; providers de áreas
  src/StudioPresenter.php                      # já existe; filtra áreas
  src/StudioListingBuilder.php                 # já existe; requer hardening
  src/Experience/                             # futuro: interfaces neutras de experiência
  src/Navigation/                             # futuro: URL, focus, enhancement
  docs/ARCHITECTURE.md                        # este contrato poderá ser espelhado
  docs/ROADMAP.md                             # planejamento de implantação
```

**Não forçar WE SATIVA para dentro de `StudioTab` GanjaLab.** Evoluir adapter/contrato de experiência reutilizável e renderizar WE SATIVA no purpose Wiki, enquanto GanjaLab usa sua navegação atual no purpose account. O Portal resolve Domain/purpose; cada destino revalida acesso.

## Plano versionado

| Fase | Entrega |
| --- | --- |
| STUDIO 0.1-A | inventariar módulos, rotas e componentes reais de GanjaLab/WE SATIVA/GanjaLab420, separar implementado de planejado |
| STUDIO 0.1-B | ADR de `ExperienceProviderInterface` (nome provisório), cacheability, URL/purpose, actions e dependências |
| STUDIO 0.1-C | refatorar gradualmente orquestração compartilhável a partir de Studio, preservando `StudioTab` e links externos |
| STUDIO 0.1-D | integrar WE SATIVA como experiência de autoria (V01–V12 sem fila de moderação), com provider do Wiki |
| STUDIO 0.1-E | integrar moderação Wiki à aba Wiki **já existente** no GanjaLab, com provider Wiki seguro; nunca expor via `create wiki_entry` |
| STUDIO 0.1-F | avaliar outras interfaces próprias e GanjaLab420 por consumidores reais, sem declarar funcionalidades fictícias |
| STUDIO 0.1-R | testes Unit/Kernel/Runtime, cache, roles, Domain, no-JS, AJAX, WCAG, 320–1440px, claro/escuro |

### Compatibilidade e gates

- Não renomear `aculta_studio` nem desativar `/ganjalab`; nomenclatura de apresentação GanjaLab pode permanecer, mas a descrição do módulo passa a ser ACULTA Studio.
- Corrigir progressivamente a arquitetura documental anterior que chamava `aculta_studio` apenas de GanjaLab; essa decisão prevalece.
- `StudioListingBuilder` ainda exige revisão da consulta de moderação/revision/cache antes de oferecer fila; nenhuma aprovação sem Node/Revision Access e transição Content Moderation.
- GanjaLab tem experiência própria de moderador; WE SATIVA experiência própria do colaborador; **ambas são parte de ACULTA Studio** e usam os mesmos fundamentos de UI.

## STUDIO 0.1-B — contrato de experiência (ADR confirmado, API não implementada)

O `StudioTab` continua exclusivo à navegação da experiência **GanjaLab**. A plataforma ACULTA Studio terá um registro separado de experiências próprias, sem transferir o purpose `wiki` para rotas de conta.

**Contrato candidato `StudioExperienceProviderInterface`:** ID estável, rótulo, propósito de domínio, disponibilidade, `AccessResultInterface` cacheável para cada usuário, URL canônica de entrada, slots/componentes existentes e render array de navegação/ações já verificadas pelo dono. O tipo não devolve permissão booleana como substituto de Entity Access.

**Orquestrador:** recebe providers via serviços tagueados somente após existir um primeiro consumidor concreto; resolve purpose com `aculta_portal`, aplica permissão do provider, não guarda conteúdo, oferece navegação progressiva com URL real e Form API do dono. GanjaLab usa seus `StudioTab` existentes; WE SATIVA será um provider próprio que consome formulários do `aculta_wiki`. Nenhum CSS/Twig fora de Core SDC/UI Suite é reaproveitado.

**Fases:** B1 inventário de consumidores; B2 API e cache; B3 provedor inicial Wiki; B4 integração visual e testes de dois propósitos; B-R homelab e regressão.

## STUDIO 0.1-C / D-A.2-T2 — estado técnico

A enumeração sem filtro de `content_moderation_state` no `StudioListingBuilder::build()` foi retirada em favor da leitura da última revisão de Nodes já retornados por consulta com `accessCheck(TRUE)`, após verificação `view` e `view revision` por item. Isso evita carregar todos os estados de moderação no GanjaLab.

**Limite conhecido:** a consulta de Nodes é truncada em 50 antes do filtro de estado e pode produzir uma aba incompleta; última revisão indisponível resulta em ausência de estado (fail closed), não em informação de draft/review. Isso NÃO é provider de fila moderadora. Prioridade seguinte: provider próprio do Wiki baseado em revisões paginadas e controle de acesso por revisão, sem retornar conteúdo não autorizado. Validar em Kernel (duas contas mesmo papel, drafts privados, revisão nova versus default, Content Moderation, grupos LMS e caches quentes). Não afirmar testes executados.

## GanjaLab420 — migração para ACULTA Studio (G420-A)

[Contrato e inventário de migração](GANJALAB420-TO-ACULTA-STUDIO-MIGRATION.md). GanjaLab420 é **a experiência LMS do estudante** dentro de ACULTA Studio. Os entrypoints de `/ganjalab420` e `/minha-aprendizagem` foram transferidos para `aculta_studio.ganjalab420_controller`, que delega ao controlador LMS; o LMS preserva dados, progresso, matrículas, certificados, controle de acesso e cache. Não confundir com GanjaLab editorial nem com WE SATIVA. A extração da composição visual para Studio será a G420-C; não declarar concluída. Antes do deploy habilitar Studio se necessário e executar testes Function/Kernel e smoke de rotas.

## Regra de domínio e URL canônica do Studio — 2026-10-22

Decisão normativa mais recente: **todas as experiências e componentes de interface próprios do ACULTA Studio pertencem ao propósito `account`, servido em `conta.aculta.*`, sob namespace `/studio`**. O domínio é escolhido pelo Portal/Domain para cada ambiente, sem host fixo. GanjaLab editorial tem entrada `/studio/ganjalab`; GanjaLab420 estudante tem entrada `/studio/ganjalab420`; WE SATIVA começou em `/studio/we-sativa`. Caminhos antigos continuam como aliases temporários para evitar quebra dos consumidores. Esta regra substitui o planejamento anterior de renderizar o *shell* WE SATIVA no purpose Wiki. A entrega inicial WE SATIVA contém provider, Meu trabalho e link de criação, consumindo o serviço proprietário da Wiki sem deslocar entidades ou regras de acesso; os formulários e restante da jornada seguem pendentes. O cabeçalho multidomínio global permanece obrigatório em todas as telas; Studio não cria um segundo.

### WE SATIVA — entrada inicial no Studio (2026-10-23)

O Studio registra `WeSativaExperienceProvider` e a rota `/studio/we-sativa` no
purpose `account`; `aculta_wiki.contributor_workspace` decide acesso, lista
verbetes/propostas próprios e gera links sob purpose `wiki`. A tela limita a 20
itens de cada tipo e revalida Entity Access antes da renderização. Kernel focal
Wiki: 1 teste / 37 asserções; classe completa: 15 testes / 612 asserções em
MariaDB isolada (286 depreciações Drupal/contrib e 16 PHPUnit).
Form API para criação/edição, prévia, envio, paginação e homologação visual
continuam pendentes.

## Nova experiência aprovada — ACULTA Studio Culturas (ADR-035)

## ACULTA Studio Culturas — decisão de produto e domínio (2026-10-30)

O nome aprovado para a experiência de organizadores é **ACULTA Studio Culturas**. A rota canônica `/culturas` está declarada parcialmente no owner `aculta_events`, atendida no purpose `eventos`; Runtime e homologação permanecem pendentes. `/studio/culturas` não é rota-alvo e será removida sem alias ou redirect, conforme ADR-038.

- A sessão administrativa técnica continua no domínio principal, em `/painel-administrativo/eventos`, restrita a administradores. Configuração global de permissões, auditoria e operações administrativas permanecem ali.
- ACULTA Studio Culturas é a experiência de organizadores: composição de fluxos do owner `aculta_events`, para criar e gerir Eventos e seus componentes (Datas e Locais), usando Form API, Entity Access e moderação existentes. Não cria entidade, formulário de persistência, permissão ou catálogo duplicado.
- A autorização para criar/editar/publicar Eventos e cadastrar Locais deve ser configurável pela administração de Eventos, com `content_editor` como padrão inicial. A configuração não concede acesso sozinha: permissões efetivas e access handlers do servidor continuam obrigatórios; criar, editar e publicar são capacidades distintas.
- Escritas só são aceitas no purpose `eventos` através de rotas canônicas da experiência. O domínio `account` pode linkar para ela, mas não pode fazer POST nem gravar conteúdo de Eventos. Não introduzir proxy de escrita, alias de rota ou redirect de compatibilidade.
- As telas públicas de Eventos continuam no purpose `eventos`. Outros domínios podem compor exibição somente leitura de Eventos publicados e acessíveis, sempre com link canônico de Eventos e sem expor Eventos privados do LMS.
- Esta decisão substitui qualquer texto anterior que diga que todo trabalho editorial de Eventos só pode ocorrer no MAIN ou que o purpose `eventos` é exclusivamente público. O painel de administração permanece no MAIN; a área do organizador é a exceção explícita e separada em ACULTA Studio Culturas.
- Estado: decisão e planejamento documental; implementação, autorização por domínio, navegação e homologação ainda precisam ser concluídas.

É uma exceção explícita ao padrão atual das experiências do Studio em `account`: sua interface de organizadores pertence ao purpose `eventos`. A identidade/navigation global pode apontar para essa área usando serviço multidomínio; a autorização e mutações continuam no host de Eventos.

## Contrato de rotas atualizado - ADR-038

## Contrato futuro: Suites do ACULTA Studio por domínio (ADR-038)

Cada Suite responde em seu próprio domínio/purpose e sob o caminho-base abaixo. O prefixo público `/studio` e todas as rotas `/studio/*` serão retirados e responderão 404, sem alias ou redirect. `aculta_studio` permanece como infraestrutura compartilhada de composição e registry, não como raiz de URL.

A tabela de rotas antiga deste documento descreve o estado antes da migração; use a matriz abaixo e o sitemap como destino. Implementação pendente.
