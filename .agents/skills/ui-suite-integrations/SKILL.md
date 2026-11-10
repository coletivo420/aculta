---
name: ui-suite-integrations
description: Coordena tarefas que atravessam integrações UI Suite no ACULTA e identifica dependências, estado e riscos sem duplicar Skills especializadas.
---

# Coordenação das integrações UI Suite

Use quando uma mudança cruza mais de uma integração de apresentação Drupal, ou quando a decisão envolve blocos, Layout Builder, Views, fields, bibliotecas, segurança ou tecnologia UI Suite opcional. Para tarefa focada, carregue somente a Skill especializada (`sdc`, `ui-patterns`, `ui-styles`, `ui-icons`, `css`).

## Roteamento

- Criar contrato e template de componente: `sdc`.
- Publicar SDC em bloco/layout/field/formatter/Views/Library: `ui-patterns`.
- Opções de classe reutilizáveis: `ui-styles`.
- Pack/seleção/renderização de ícone: `ui-icons`.
- Tokens, purpose e tema: `css` e `aculta-data-portal-ui`.
- Preview real de blocos ACULTA, rotas/permissões e SEC-01: consulte matriz do ecossistema e gates de `aculta-gates-por-escopo`.

## Governança

Antes de recomendar API, separe `composer.json`, lock, filesystem, `core.extension.yml` e runtime. Este checkout pode não conter `web/core`, `web/modules/contrib` ou `vendor`. Confirme machine names, classes e versões na instalação alvo. Não instale, habilite, migre displays nem altere Composer/configuração/runtime por inferência.

Preserve Layout Builder, Views, os layouts existentes, ADR-054, as cinco regiões do tema e o roteamento multidomínio/purposes. Avalie permissões, cache contexts/tags/max-age, traduções, revisões, prévias e carregamento condicional de assets. Não duplique funcionalidade entre SDC, UI Patterns Library e ACULTA UI Library.

## Tecnologias opcionais

- **UI Patterns Field Group:** pacote `2.0.0-beta2` no lock, não habilitado no export. O projeto não está coberto pela política de avisos de segurança Drupal; não habilite nesta tarefa.
- **UI Styles Page/Library:** extensões opcionais não habilitadas no export. Confirme machine name e versão no código instalado.
- **UI Icons:** packs Core do tema existem, módulo contrib e integrações não foram comprovados. Preserve Phosphor/Fluent e não crie seletor paralelo.
- **UI Skins:** não instalado. Pode sobrepor modos e variáveis CSS ao `data-theme`, purposes e tokens ACULTA. Não migrar o tema.
- **UI Examples:** não instalado. Exemplos declarativos/YAML e páginas demonstrativas não são a mesma coisa que stories de componente ou preview real.
- **Display Builder:** não instalado. Projeto em beta no momento da pesquisa, com potencial de substituir/migrar Layout Builder, displays de Views e entity displays. Não instalar nem migrar; avaliar maturidade, compatibilidade e ADR-054 antes de qualquer proposta.
- **SDC Devel:** habilitado no export; confirme runtime e uso de previews conforme a instalação.

## Bibliotecas e segurança

UI Patterns Library cataloga todos os SDCs e stories, inclusive internos e editoriais. Não ocultar componentes para resolver performance. ACULTA UI Library (`aculta_ui_library`) cataloga blocos reais e suas prévias administrativas, em domínio MAIN, com rotas/permissões próprias. Preview sintético não equivale a execução real do bloco.

SEC-01: o controller observado usa `AccessResultInterface::isAllowed()` antes de `build()` e o teste-fonte cobre allowed/forbidden/neutral. O status de sessão mais recente registra o PHPUnit 9/9 PASS; esta auditoria não o repetiu. O README do módulo ainda afirma incorretamente que a falha está pendente. A correção funcional aparenta presente; reconcilie essa documentação e valide novamente em execução antes de declarar nova homologação. Não alterar a implementação funcional nesta tarefa.

## Referência

Leia `docs/operations/AUDITORIA-SKILLS-DRUPAL-2026-11.md` para a matriz e `docs/operations/TESTES-SKILLS-UI-SUITE.md` para avaliações. Não carregue essas referências em tarefa focal sem necessidade.
