# ACULTA Studio — plataforma transversal de interfaces próprias

**Decisão normativa · 2026-10-22 · Versão STUDIO-ARCH 0.1 · Estado:** arquitetura/documentação; não implica rotas ou UI implementadas.

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
