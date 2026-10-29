# Plano arquitetural de incorporação seletiva OpenCulturas 3.1.5 → ACULTA

**Aprovação:** 2026-10-29. **Estado:** planejamento e auditoria estática; NÃO constitui implementação/homologação. **Execução:** frente arquitetural ARQ-01 a ARQ-09, separada das frentes de UI `OCS-F/A` e `THEME-MF`. Branch existente `refactor/opencultures-codigo-visual`, PR #299; sem criar outra PR, merge ou force-push.

## Fontes e evidências

- [Auditoria reproduzível OpenCulturas 3.1.5](AUDITORIA-OPENCULTURAS-3.1.5-2026-10-29.md), pacote original SHA-256 `2cc0a34293e3cd7ccff633d07d61844afc1cec752d506a955c49a319fcbbb406`; **42 Views de instalação (7 desabilitadas), 9 taxonomias, 3 workflows, 3 facetas, 5 flags, 19 tipos de parágrafo**. Não repetir as contagens anteriormente erradas.
- Destino: estado real do HEAD na PR #299, confrontado com os roadmaps. **Não assumir homologação Runtime com base em arquivos, plano, testes escritos ou benchmarks sintéticos.**
- Decisões vigentes: [DECISOES-RESPONSAVEL-2026-10-28.md](DECISOES-RESPONSAVEL-2026-10-28.md); não criar papel organizador; vínculos por moderação; destinatários de denúncias por purpose; UGC centraliza fila; licença de mídia opcional; Turnstile sem Honeypot; core daterange + recorrência ACULTA; mapa via `aculta_maps` e provedores aprovados.
- Preservar `aculta_index` como infraestrutura de projeção derivada e relacionamento e `aculta_studio` como plataforma de apresentação; Green Stash é área **integrada** do Studio, sem shell separado; não confundir Conta, Perfil público e organização. Mobile-first é exigência de **TODO ACULTA420**, mas é responsabilidade da frente de tema, não escopo ARQ.

## Diagnóstico e opções

O OpenCulturas possui configurações de Search API/Views/Facets, fluxos de perfis/organizações, flags/ECA e conteúdo estruturado cultural. O ACULTA já possui serviços, entidades, workflow, domínio/purpose e módulos especializados. **Portar contratos, comportamentos e modelos mínimos, nunca distribuição inteira, YAML/UUID, entidades duplicadas, ECA em massa ou tema upstream.** Verificar atualizações recentes do ramo antes de cada fase.

| Fase | Responsável principal | Implementação incremental | Aceite mínimo |
| --- | --- | --- | --- |
| **ARQ-01 / P0** | `aculta_events` e acesso/Maps | formalizar matriz de visibilidade de `aculta_event_location`, precisão aproximada/exata e grants; inspecionar `LocationCatalogue::busca()` (usa `accessCheck(FALSE)` sem filtragem por `access('view')` no estado auditado); corrigir listagens/mapas/APIs sem divulgar endereço privado | Unit/Kernel/Functional de anônimo, dono, não dono, múltiplos purposes, precisão, cache e render; ausência de vazamento |
| **ARQ-02 / P0** | `aculta_events` + `aculta_calendar` | medir volume de `EventCatalogue::busca()`, expansão de RRULE e `usort()` em PHP; desenhar projeção consultável das próximas ocorrências, paginação por consulta, limites e invalidação incremental, sem duplicar fonte da verdade nem trocar biblioteca/recorrência aprovada | benchmarks representativos com SLO declarado, 10k/100k quando viável; resultados/paginação equivalentes, cancelados, overrides, timezones e ACL |
| **ARQ-03 / P0** | `aculta_index` + `aculta_events` + Search API | mapear busca pública e API do Index, verificar módulos realmente ativados; projetar eventos/locais sem contornar grants, construir catálogo cultural unificado e filtros por entidade/purpose; avaliar Facets depois de necessidade comprovada | indexação/rebuild/remoção, acesso contextual, fontes múltiplas, consistência, performance e cache; não recriar o Index ou importar View Search upstream |
| **ARQ-04 / P1** | `aculta_events` + `aculta_maps` | estudo de campos aditivos: atendimento presencial/online/híbrido, virtual, idade, status, informações de segurança, abertura de portas, horário estruturado com exceções e acessibilidade; mapear migração de `field_horario` texto livre sem perda | schema/migração reversível, compatibilidade de leitura, forms, filtros e testes; não instalar Office Hours/Smart Date sem decisão |
| **ARQ-05 / P1** | `aculta_user` + organizações e moderação | vínculo verificado entre conta, Perfil, organização e conteúdo; reivindicação por pedido e decisão de moderação, padrão fechado; acesso por `content_editor` escopado à organização, sem papel novo | testes A/B, escopo, revogação, auditoria, CSRF e concorrência; nenhum conteúdo reivindicado por padrão |
| **ARQ-06 / P1** | futuro `aculta_ugc` + Fórum/Wiki/Revista/Eventos | fila única de denúncias com adapters, destinação por purpose em configuração, transição de filas existentes após inventário e migração contável; notificações por serviços atuais, sem cópia de processos ECA | migração com antes/depois, idempotência, permissões, privacidade, fila sem duplicidade, testes Kernel/Functional; confirmar se módulo existe realmente |
| **ARQ-07 / P1** | Flags + `aculta_index` + Studio | contratos de salvos multiplataforma, lista própria por UID e relações/contexto para recomendações transparentes; não criar estado salvo paralelo ao Flag | acesso entre duas contas, invalidação e cache por UID, habilitação opcional, paginação; não produzir recomendações artificiais |
| **ARQ-08 / P2** | `aculta_magazine`, `aculta_image`, owners editoriais | modelar referências de imprensa, patrocinadores/apoiadores autorizados, teaser editorial opcional e metadados legíveis de downloads; consumidores SDC já existentes | autorização de logomarca, pipeline Media/Image, acessibilidade, grants, cache, migração não destrutiva |
| **ARQ-09 / gate** | integração + QA | matriz de paridade, segurança cross-purpose, API/backfill, performance, migrations, observabilidade, documentação e evidências de testes Runtime | não declarar PASS com DEFERRED; não iniciar rollout funcional sem gates anteriores |

### Sequenciamento, dependências e prioridades

1. ARQ-01 é pré-requisito de publicação/descoberta geográfica ampliada em ARQ-03; ARQ-02 pode começar por benchmark/leitura sem aguardar ARQ-01, mas publicação pública de projeções exige ACL.
2. ARQ-03 **reutiliza** `aculta_index`, seu registry/provider/fingerprint/gerações e contratos de acesso. Antes de benchmark/ativação, revisar pendências operacionais do índice e seu status atual; **não realizar rebuild/import em Runtime sem autorização**.
3. ARQ-05 fundamenta escopos de organização e transições; ARQ-06 centraliza denúncias; ARQ-07 depende de grants/flags verificados. ARQ-04 pode prosseguir em desenho e testes aditivos, não converter campos cegamente.
4. ARQ-08 só evolui quando há consumidores e autorização de marcas; ARQ-09 exige evidências. Execução visual OCS e mobile-first THEME-MF podem continuar independentemente, mediante coordenação de arquivos compartilhados.

### Ownership e disciplina de PR

- Frente arquitetural: `src/`, APIs, formulários, services, entidades, políticas, tests e configurações necessárias de cada módulo **apenas com ownership aprovado**. Frente B mantém `web/themes/custom/aculta420/**`, SDC/Twig/CSS/stories e Views reservadas; para mudanças compartilhadas registrar `Pede-B` ou handoff e não editar sem combinar.
- Um commit por microfase, `git status` antes de `git add`, indexação limitada a paths próprios, fetch/rebase seguro, push fast-forward na **PR #299**. Não abrir branch/PR; não fazer merge/force. Se outros agentes mexerem no mesmo arquivo, parar e coordenar.
- Evitar instalação de dependências ou configuração em produção, alteração de schema, `drush cim` e migração destrutiva sem evidência, testes e autorização apropriada. Evoluir Dev → RC → Alpha conforme ciclo do projeto.
- Em cada fase atualizar roadmap e relatório: **IMPLEMENTADO**, **PARCIAL**, **PLANEJADO**, **BLOQUEADO**, **DEFERRED**, logs e commit SHA. Adapters e relações por owner, cache contexts/tags/max-age, ACL/Domain e segurança de conteúdo são gates inegociáveis.

## O que não portar

Não copiar todo o Profile OpenCulturas, Views/configuration YAML upstream, ECA generalizado, Paragraphs como modelo obrigatório, Smart Date/smart_date_recur, papéis genéricos de organizador, tema ou shell de administração, infraestrutura de mapas duplicada, licença obrigatória de Media nem breadcrumb alternativo. Não inventar funcionalidades de IA/polígonos sem prova no pacote 3.1.5.

**Status:** plano registrado. Nenhuma fase ARQ marcada concluída por este documento.
