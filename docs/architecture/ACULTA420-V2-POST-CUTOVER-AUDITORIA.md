# ACULTA420 V2 — Diagnóstico Pós-Corte (R0)

**Data da auditoria:** 2026-11-09  
**Branch:** `dev`  
**HEAD de auditoria:** `3cfc73cc1`  
**Ambiente:** Servidor de desenvolvimento (`*.aculta.toca.net.br`, HTTPS em 127.0.0.1)  
**Drupal:** 11.5.0-beta1 · **PHP:** 8.5.12 · **Drush:** 13.8.0.0  

---

## 1. Verificação do Corte P9 no Runtime

| Verificação | Estado Ativo | Config Sync | Resultado |
|---|---|---|---|
| **Tema Padrão** | `aculta420_v2` (versão 1.5.0) | `system.theme:default: aculta420_v2` | **CONFIRMADO** |
| **Shell Variant** | `v2` (`aculta_shell_variant: v2`) | `aculta_shell_variant: v2` | **CONFIRMADO** |
| **Regiões Declaradas** | 5 regiões (`institution`, `header`, `content`, `sidebar_first`, `footer`) | 5 regiões em `aculta420_v2.info.yml` | **CONFIRMADO** |
| **Blocos Ativos** | 36 blocos distribuídos nas 5 regiões | 36 arquivos `block.block.aculta420_v2_*` | **CONFIRMADO** |
| **Institution Bar** | Ativo na região `institution` (`aculta420_v2_institution_bar`) | **AUSENTE** no Config Sync (`block.block.aculta420_v2_institution_bar.yml`) | **LACUNA (Pacote R1)** |
| **Antigo motor de container** | Removido (`aculta_purpose_container`, `PurposeContainerLayout`) | Removido | **CONFIRMADO** |
| **Rollback do Shell** | Testado com `classic` + `drush cr`, respondeu 200 com 1 H1 e 1 `<main>` | Suportado via configuração | **CONFIRMADO** |

---

## 2. Reconciliação de Documentação Contraditória

1. **`ACULTA420-V2-P8-HOMOLOGACAO.md`**:
   - Seções anteriores ao corte foram devidamente atualizadas para refletir o estado de pós-corte P9 (cinco regiões ativas, motor de container removido, rollback testado).
2. **`docs/operations/ESTADO-DA-SESSAO.md`**:
   - Registrava dados antigos sobre o shell antes do corte. Mantido histórico recente de 3 entradas, reconciliando o corte P9 e as entregas em andamento.
3. **Fronteira Módulo x Tema**:
   - O relatório P8 registrava 64 falhas de fronteira pré-existentes. A implementação do elemento neutro `aculta_portal_button` eliminou 51 dessas ocorrências, restando 13 que estão em processo de tratamento seguro em R2.

---

## 3. Classificação dos Próximos Encaminhamentos

- **R1:** Exportar e versionar `config/sync/block.block.aculta420_v2_institution_bar.yml` e criar teste de integridade/reprodução.
- **R2:** Sanar as 13 violações de fronteira restantes (`aculta_forum`, `aculta_ui_library`, `aculta_editor`, `aculta_magazine`, etc.).
- **R3:** Fortalecer segurança e controle de acesso de `aculta_ui_library` (exigência estrita de papel administrador e domínio MAIN).
- **R4:** Integrar catálogo de blocos ao UI Patterns Library.
- **R5:** Homologar displays compatíveis com Layout Builder e documentar exclusões.
- **R6:** Reproduzir infraestrutura das landing pages nos dez purposes.
- **R7:** Concluir otimização de CSS/JS, resolver Stylelint e medir performance.
- **R8:** Matriz de homologação visual, multidomínio e cache cruzado.
- **R9:** Validação de reprodução da configuração e rollback.
