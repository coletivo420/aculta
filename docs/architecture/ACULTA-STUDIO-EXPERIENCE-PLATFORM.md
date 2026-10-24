# ACULTA Studio — plataforma transversal de interfaces próprias

**Contrato vigente da refatoração de identidade · 2026-10-24.** A lista abaixo substitui as identidades e URLs antigas descritas em seções históricas deste documento.

| Experiência | Nome público | ID |
| --- | --- | --- |
| Plataforma | ACULTA Studio | `aculta_studio` |
| Editorial | ACULTA Studio Marley | `marley` |
| Aprendizagem | ACULTA Studio Ganjalab | `ganjalab` |
| Contribuição Wiki | WE SATIVA | `we_sativa` |

Rotas canônicas: Marley em `/studio/marley` e níveis aninhados; Ganjalab em `/studio/ganjalab`; WE SATIVA em `/studio/we-sativa` e `/studio/we-sativa/inicio`. Todas ficam no purpose `account`, resolvido pelo Portal. `/studio/ganjalab` pertence exclusivamente ao dashboard estudantil, portanto a antiga entrada editorial não pode ser preservada nesse endereço. Aliases e histórico estão em [contrato de identidade](../portal/ACULTA-STUDIO-IDENTITY.md).

**Registros abaixo de 2026-10-24 são históricos.** Menções a GanjaLab editorial e GanjaLab420 estudantil descrevem nomenclaturas anteriores; a plataforma, ownership de negócio e WE SATIVA permanecem conforme o contrato vigente.

## Nova frente documental — Hub e Meu Espaço (STUDIO-P0, 2026-10-24)

A expansão da plataforma para **todos os membros** foi definida como alvo de
produto: `/studio` será o hub, `/studio/pessoal` será Meu Espaço, com menu
unificado de experiências e seções contextuais. Minha Conta e Meu Perfil
serão incorporados **somente na apresentação**, preservando Portal, User,
Messenger e outros owners. O perfil público `/u/{apelido}` permanece no
purpose `main`.

**Esta direção é planejamento, não estado do código nem autorização de corte
das rotas atuais.** O `StudioExperienceManager` e os providers Ganjalab/
WE SATIVA existem, mas o provider pessoal e a ponte Marley para o registry
ainda são candidatos; `/studio` hoje funciona como entrada legada Marley.
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
