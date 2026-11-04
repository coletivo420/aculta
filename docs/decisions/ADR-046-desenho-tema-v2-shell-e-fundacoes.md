# ADR-046 — Desenho do tema ACULTA420 V2: base visual V1, UI Styles e revisão a partir do shell

- **Status:** aceito pelo responsável em 2026-11-04.
- **Data:** 2026-11-04.
- **Relaciona-se com:** ADR-044 (CTools e AJAX), ADR-045 (compatibilidade com Drupal 12), `docs/architecture/ARQUITETURA-ACULTA420-V2.md`, `docs/portal/COMPONENT-DESIGN-SYSTEM.md`, `web/themes/custom/aculta420/docs/architecture.md`.

## 1. Contexto

- A V2 (`web/themes/custom/aculta420_v2`) é Bootstrap-free, sem jQuery por padrão e sem Vite. Tem 15 componentes SDC com schema estrito.
- A V1 (`web/themes/custom/aculta420`) usa Bootstrap 5, Vite e Node, e é citada como canônica nos documentos do Portal.
- O desenho proposto em `DESENHO-TEMA-V2` (rascunho de 2026-11-04) apontou o conflito entre as duas bases e as fases de implementação.

## 2. Decisões

1. **A V1 é usada só como base visual da V2.** Os valores de identidade (cores de marca, tipografia, logos, proporções e tom dos componentes) vêm da V1. A **estrutura** (SDC, tokens semânticos, shell, CSS sem Bootstrap) é da V2. Nenhum código de Bootstrap, Vite ou Node entra na V2.
2. **A V1 deixa de receber funcionalidades novas.** Ela permanece como referência visual até a V2 cobrir os mesmos casos de uso. Os documentos do Portal que a citam como canônica são atualizados em PR próprio.
3. **Chave neutra de purpose no contrato do Portal.** O `domain_presentation` passa a incluir uma chave neutra do purpose atual (ex.: `shop`, `wiki`). O tema usa essa chave para aplicar `data-purpose` no `<html>` e ajustar tokens de destaque. O tema não conhece hostnames nem regras de domínio. A mudança no Portal é feita em PR própria, com gate de contrato.
4. **UI Styles é adotado na V2.** Os estilos de apresentação de componentes são declarados como plugins do UI Styles, sem classes Bootstrap. A compatibilidade com Drupal 12 é verificada antes da adoção definitiva, conforme ADR-045.
5. **Revisão completa da V2, começando pelo shell e pelas fundações.** Todos os componentes, templates e CSS existentes são revisados contra este ADR. Componentes que não atendem o contrato são refeitos. A ordem é: shell e fundações primeiro, depois componentes, padrões e templates de página.

## 3. Fases

**Fase 1 — Shell e fundações** (início imediato):
- Tokens: revisar `tokens.css` com os valores de identidade da V1; manter tokens semânticos e aliases legados apenas onde ainda são usados.
- Fundações: tipografia, espaçamento, raios, foco, movimento reduzido, zoom 200% e largura mínima de 320 px.
- Shell: barra institucional, cabeçalho, navegação móvel, rodapé e regiões do `page.html.twig`.
- Chave de purpose no contrato do Portal (PR própria no Portal, aprovada).
- UI Styles: instalação e primeiro estilo de componente no shell, validando a compatibilidade com Drupal 11.5.

**Fase 2 — Componentes existentes**: revisar os 15 componentes contra o contrato (schema, acessibilidade, tokens, sem regra de negócio).

**Fase 3 — Componentes faltantes**: `avatar`, `author-box`, `breadcrumb`, `tabs`, `accordion`, `dialog`, `form-field`, `progress`, `data-table`, `empty-state` e `price`.

**Fase 4 — Templates por tipo de conteúdo**: evento, revista, wiki, fórum, aula do LMS, FAQ, avisos e produto da loja.

**Fase 5 — Formulários e autenticação**, depois **LMS e loja**.

## 4. Regras de trabalho

- Cada fase termina em PR própria, com `RUNTIME STATUS: DEFERRED` quando não testada no servidor de desenvolvimento.
- Mudança no tema só com autorização explícita, que este ADR registra para a V2 (decisão 5).
- Mudança de contrato no Portal (decisão 3) é PR separada do tema.
- Nenhuma parte nova usa Bootstrap, jQuery ou `core/drupal.dialog.ajax` sem registro em ADR.
- Versão: revisões do shell e fundações são MINOR (`1.2.0-dev.N`), com a marcação em `aculta420_v2.info.yml`, `CHANGELOG.md` e `docs/roadmap.md`.

## 5. Não-decisões

- Não define a data de migração da V1 para desativação.
- Não define a estratégia final de AJAX (ver ADR-044 e ADR-045).
- Não define a data da migração para Drupal 12.

## 6. Validação

- Gates do tema (SDC, fundações, shell, validadores de navegador) no servidor de desenvolvimento (Drupal 11.5).
- Gate de contrato no Portal para a chave de purpose.
- Teste visual em Firefox, em claro e escuro, de 320 px a desktop, com zoom e teclado.
- Teste das páginas do Portal também com o tema Olivero (CLAUDE.md).
