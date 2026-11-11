# ADR-054/055 — ComponentLayout nos displays elegíveis — 2026-11-10

- **Objetivo:** migrar todos os displays Layout Builder compatíveis para UI Patterns ComponentLayout SDC.
- **Estado:** 22 displays usam `layout-section` em Sync e Runtime. Import parcial precedido por snapshot. Componentes, UUIDs, seção `content`, `allow_custom` e dados foram preservados; as exclusões `node.document.full` e `block_content.basic.default` continuam inalteradas.
- **Verificações:** Layouts 42 checks; batch Layout Builder 258 checks (configuração, construção de render arrays de entidades publicadas, paridade Runtime/Sync e acesso); SDC 41 componentes; PHPCS/lint do gate. Sem alterações em entidades. Token-budget gate segue FAIL por regras/catálogo global preexistentes; TODO V2 foi reduzido abaixo de 2.000 bytes.
- **Arquivos:** 20 displays Sync, CSV/JSON de inventário, gate batch, TODO/status/changelog/roadmap, ADR-054, P8 e plano técnico.
- **Pendências:** cache-hit continua `UNCACHEABLE`; aceite visual amplo, teclado, zoom, perfis e contraste seguem pendentes. Continuar Views, UI Styles Page/CKEditor 5, Icons, migração visual e RN-00–05.
- **Trabalho compartilhado:** preservar alterações locais em Eventos, Conta, Perfil e `web/tmp/`; não incluídas nesta entrega.
- **Próximo passo:** validar cache/perfis e avançar os pilotos UI Patterns Views e UI Styles Page sem importar drift global.

# UI Skins — extensão Sync e limpeza da chave antiga — 2026-11-10

- **Objetivo:** tornar UI Skins obrigatório no Config Sync e retirar a última chave de paleta antiga do Runtime.
- **Estado:** Sync registra `ui_skins`; gate exige extensão ativa no export. Runtime tem UI Skins 1.2.1 e descobriu cinco plugins, cada um com 20 scopes. Mapa e scopes ativos seguem ausentes; `aculta_purpose_palette` foi removida sem cópia após busca sem consumidores.
- **Decisões e alternativas descartadas:** sem dual-read, alias ou backup da configuração antiga, conforme instrução do responsável. Sem `config:import`/atualização não autorizados.
- **Cuidados:** preservar preferência individual de modo; cache varia por domínio/usuário e marca config do mapa.
- **Commits e arquivos:** `d4142969f`, `420141139`; Sync, gate UI Skins, ADR-054/055, plano, docs de gate/skins, roadmap, TODO e status.
- **Verificações:** UI Skins 127, extensões Sync 1753, resolver Unit 2/6 e PHPCS PASS; runtime descobriu 5×20 scopes; `drush cr` PASS. Token budget FAIL por sete pendências preexistentes. Browser DEFERRED sem mapa/scopes ativos; tradução pt-br falhou porque `translations://` não está configurado.
- **Pendências e bloqueios:** importar o mapa e os scopes UI Skins pelo fluxo autorizado; comparar Links em cache, modos e visual antes dos demais domínios.
- **Próximo passo:** executar o cutover de configuração autorizado e validar o piloto Links no tema V2.

# Inventário UI Suite — fallback funcional do Portal Button — 2026-11-10

- **Objetivo/estado:** corrigir classificação auditável de `aculta_portal_button`: o Render Element e seu template sem classes visuais são `FUNCTIONAL_NATIVE_EXCEPTION`; o override do tema compõe `aculta420_v2:button` e é `UI_SUITE_NATIVE`.
- **Decisões/cuidados:** sem comportamento visual legado ou configuração de fallback antiga. Preservar somente o fallback semântico funcional do Render API; não remover o contrato neutro do módulo.
- **Arquivos:** gerador `scripts/inventory-ui-suite-consumers.php`, CSV `docs/architecture/inventarios/ui-suite-consumidores-iniciais.csv`, plano vinculante e TODO do tema.
- **Verificações:** gerador executado (386 registros); classificação/consumidores das duas linhas inspecionados; `php -l` e `git diff --check` PASS. Browser/runtime do adapter não revalidado nesta microfase.
- **Pendências/próximo passo:** continuar migração dos demais templates e validar SDC/Render API em Runtime; mapa/scopes UI Skins ainda aguardam fluxo de import autorizado.
