# ACULTA Studio Ganjalab / ACULTA420 — Fase 0.8-B: Componentes transversais e navegação

**Versão:** G420-UX 0.8-B.0-dev.1 · **Data:** 2026-10-22 · **Estado:** planejamento, não implementado.
**Dependência:** [auditoria cruzada 0.8-A](GANJALAB-CROSS-VISUAL-AUDIT.md) · [gates 0.8](GANJALAB-VISUAL-QUALITY-GATES.md).

## Objetivo e regra de construção
Uniformizar contextos, abas e feedback em **ACULTA Studio Ganjalab, ACULTA Studio Marley, Revista, Wiki, Fórum, Galeria e Minha Conta**. A arquitetura segue **reutilizar componente existente → estender variante/slot → criar SDC transversal se necessário → adaptador de domínio apenas quando indispensável**. A fase começa com análise de consumidores reais no Runtime, não criação automática de três SDCs.

## Matriz de candidatos
| Prioridade | Padrão pretendido | Componentes/recursos a avaliar primeiro | Consumidores | Dados/slots neutros |
| --- | --- | --- | --- | --- |
| B1 P0 | `context-header` | shell atual, `patterns/content-section`, títulos/breadcrumb Core/Bootstrap | ACULTA Studio Ganjalab Sala, Revista, Wiki, ACULTA Studio Marley | `breadcrumb` renderizável, `title`, `subtitle`, `back_link`, `actions` |
| B2 P0 | `context-tabs` | tabs Core, Bootstrap 5, navegação AJAX atual do `aculta_studio` | ACULTA Studio Marley, Turmas, Fórum, Wiki, Conta | `items` renderizáveis já autorizados, active/current, `aria-label` |
| B3 P0 | `feedback-state` / `empty-state` | mensagens Core, `feedback/messenger-state`, `content/institutional-notice` | LMS, Loja, Revista, Wiki, Messenger | tipo semântico, `title`, `message`, ações renderizáveis |
| B4 P1 | `context-layout` | grid/section do tema, Bootstrap e shell multidomínio | Leitor LMS, Wiki, Revista, Fórum | `sidebar`, `main`, `aside` slots |

Evitar o nome `feedback-state` se já houver padrão suficiente e diferente de `messenger-state`. A abstração deve diferenciar estado técnico (carregando/erro) de estado pedagógico (concluído) sem derivar informações no tema.

## Contrato visual B1 — Context Header
**Mobile:** breadcrumb resumido ou link acessível “Voltar ao curso” (não ambos redundantes); título que pode quebrar linha; ações secundárias colapsam quando necessário. **Desktop:** contexto à esquerda, ações à direita, mesma hierarquia sem mudança de semântica. Links reais fornecidos pelos providers. Não impor uma seta de voltar dependente de histórico do navegador quando rota canônica estiver disponível.

```text
[← Voltar ao curso]                  [ações]
Curso / Módulo 1
História e contexto
Descrição curta opcional
```

Reutilização: Aula, artigo da Revista, verbete Wiki, tópico de Fórum e formulários ACULTA Studio Marley.

## Contrato visual B2 — Context Tabs
Base em anchors reais e `aria-current` (não apenas botões JS). Padrão de abas visual responsivo: em 320px rolagem horizontal acessível ou menu alternativo, indicadores de overflow, ordem previsível e foco visível. **Não criar sistema de AJAX genérico dentro do SDC**. Módulos donos de AJAX continuam responsáveis por pushState/popstate, fallback, estados e erros; o tema só estiliza render arrays de tabs. Não trocar semântica indiscriminadamente para `role=tab` quando os links navegam entre páginas diferentes.

```text
[Mural] [Discussões] [Atividades] [Participantes →]
```

Reutilização: Turma ACULTA Studio Ganjalab, navegação do Studio/ACULTA Studio Marley, categorias Wiki/Fórum e Minha Conta.

## Contrato visual B3 — Feedback e estados
Estados globais aprovados como **apresentação**, nunca resultado de operação presumido: vazio, erro, restrito, carregando e sucesso **confirmado no backend**. Prover `title` e `description` acessíveis, região `role=status` ou `alert` quando apropriado, CTA de retry/volta somente se rota/ação real existir. Estado de submissão H5P/LMS é fornecido pelo provider, não calculado por ícone no JS.

```text
[Nenhum material disponível]
Ainda não há materiais autorizados nesta aula.
[Voltar à aula] (somente com rota real)
```

Reutilização: biblioteca, carrinho vazio, artigos sem itens, wiki sem resultados, conversa vazia.

## Arquitetura técnica esperada
- **Provider/Controller (Portal, LMS, Magazine, Wiki, Forum):** dados, acesso, cache tags/contexts, URL real e semântica de estados.
- **Presenter/render array:** props tipadas e slots renderizáveis preservando cache e attachments; não atribuir acesso por CSS.
- **ACULTA420:** Twig/SDC com tokens semânticos + Bootstrap 5, sem EntityManager, `\Drupal::service`, queries nem CSS duplicado por aplicação.
- **UI Patterns:** `.story.yml` para cenário padrão, mobile, vazio/erro e light/dark; fixtures sem dados reais.
- **LMS/H5P/PDF:** manter IDs e players providos; nenhum novo storage, middleware de nota ou visualizador.

## Sequência curta de execução futura
| Fatia | Entrega | Evidência/gate |
| --- | --- | --- |
| B0 | confirmar HTML/SDCs efetivamente usados por 2+ consumidores reais | inventário de template, menu, cache e props |
| B1 | adaptar header existente ou criar SDC contextual mínimo | Sala + Revista/Wiki, 320/1280px |
| B2 | tabs responsivas por links reais | Turma + ACULTA Studio Marley/Fórum; no-JS e back |
| B3 | estados padronizados usando notices/Core antes de novo SDC | LMS + outro consumidor, aria-live e erro |
| B4 | harmonizar layout contextual sem duplicar shell | largura reader, sidebar, 320–1440px |
| B5 | registrar docs/stories/testes no catálogo | WCAG 2.2 AA, dark/light, ausência de regressão |

**Política de commits:** uma fatia funcional por commit + documentação e story associados; não implantar componentes vazios. Critério de aceite exige pelo menos **um uso real** no ACULTA Studio Ganjalab e uma **aplicação real adicional** ou justificativa escrita para especialização isolada.

## Limitações desta versão
Plano não corresponde a componente instalado ou homologado. Nenhum arquivo funcional do tema, da navegação ou do `aculta_lms` foi alterado. A auditoria 0.8-A ainda requer inspeção Runtime dos consumidores, CSS, acessibilidade e stories antes de marcar o gate como concluído.

## Regra não negociável — shell multidomínio (decisão 2026-10-22)

Preservar **Institution Bar + Domain Header atuais em todo o portal**. O `context-header` planejado em B1 é um subcabeçalho contextual **dentro do conteúdo**, sem trocar nem replicar o cabeçalho multidomínio. Pode evoluir visualmente o shell real (mobile-first, contraste, menus e dark/light), porém com ownership e resolução de purpose existentes. Abas e estados também se encaixam abaixo do shell; nenhuma tela de Sala, Studio, Wiki, Fórum ou Conta pode montar outro header global. Regressão do shell em qualquer purpose impede aprovação da fase.
