# ACULTA Magazine — Roadmap de realinhamento arquitetural

**MAG 0.5-R · 2026-10-22 · Planejamento, sem alteração funcional ou homologação.**

Este documento substitui a antiga sequência linear que condicionava a criação do Studio ao fechamento do Magazine. A migração `article`/`blog` → `revista`, já executada no Runtime de teste, permanece documentada em `web/modules/custom/aculta_portal/modules/aculta_magazine/docs/MIGRATION-ARTICLE-BLOG-TO-REVISTA.md` e no CHANGELOG. Não repetir migração, apagar dados ou importar Config Sync global.

## Produto e fronteiras

- **Magazine:** dono do bundle `revista`, campos, classificações, validação editorial, SEO, URLs, referências, configurações e workflow de conteúdo; oferece Forms, ações e adaptadores autorizados para consumo.
- **ACULTA Studio:** plataforma transversal; **GanjaLab** é experiência editorial e de moderação; lista `Meu trabalho`, navegação, estados UI, feedback, composição e jornadas. Não cria storage, grants ou workflow paralelos. O Magazine não cria segunda área editorial.
- **ACULTA420:** aparência exclusiva por Core SDC + UI Suite, tokens, versões light/dark, responsividade e cabeçalho multidomínio preservado. Não portar templates/CSS legados que não sigam esse padrão.
- **Drupal Core:** Entity/Revision Access, Content Moderation, Workflows, Form API, Views, CKEditor 5 e render/cache são fontes de verdade.
- **aculta_user:** identidade, perfil público, cartão autor/coautor e política compartilhada de primeira participação. Não duplicar regras de confiança no Magazine.
- **aculta_forum:** comentários contextuais `field_aculta_discussion` no Node `revista`, sem tópico público automático; ForumPost revisionável é entidade do fórum público, não substituição implícita dos Comments da Revista.
- **aculta_image / aculta_gallery / Portal uploads:** Media, conversão, alt text, uploads e galerias. Usar a política compartilhada apenas onde exista consumidor real. Não inventar biblioteca ou upload paralelo.
- **aculta_document_viewer / aculta_pdf_viewer:** visualizadores reutilizados onde houver documento editorial permitido.
- **aculta_messenger:** notificações, destinatários e entrega; Magazine emite eventos/contratos de publicação, não engine própria.
- **aculta_statistics:** métricas editoriais quando contratos de coleta estiverem disponíveis. Não duplicar tracking.
- **aculta_portal:** Domain/purpose, URLs canônicas, serviços globais e políticas técnicas.

## Estado constatado na branch

**Implementado/código:** bundle `revista` único; hooks de formulário, autoria e discussão; serviço de apresentação `PostPresentation`; Views administrativas e por sessão; metatags e Pathauto; galeria via CKEditor/Entity Embed; coautoria via `node_co_authors`. Migração dos 16 Nodes/revisões e 26 aliases no Runtime de teste registrada; não equivale a deploy de produção.

**Parcial / exigir prova:** Content Moderation e transições por papel, permissões de coautores e revisões não-default, envio e moderação de Comment contextual, preview real de rascunho, URLs entre account/magazine, Config Sync com drift concorrente, comportamento mobile do formulário autenticado, cache de perfil público e SEO/canonical em páginas acessíveis.

**Observações de código:** `RevistaFormHooks` só recolhe conjunto fixo de campos, sem contrato formal de grupos e sem prova de erro/foco nos detalhes; `PostPresentation` monta links absolutos para compartilhamento e consulta perfil/caches, exigindo teste de Domain, privacidade, render-cache e XSS; `ArticleAuthorsHooks` só atua em Node publicado e view mode full no purpose magazine. São alvos de auditoria, não defeitos comprovados.

## Roadmap versionado — microfases

| Fase | Dono | Entrega | Dependência / gate |
| --- | --- | --- | --- |
| **MAG 0.5-A** | Magazine | Auditoria comparativa de código, Config Sync, roles, fields, displays, indexes, SEO, services e dependências; matriz implementado/planejado/DEFERRED; atualizar README e contratos | Não importar sync global nem mudar Runtime |
| **MAG 0.5-B** | Magazine | Consolidar contratos editoriais para Studio: operações criar/editar, formulários Core, ações por transição, rotas canônicas, cache/access/revision; revisar `node_co_authors` | Studio E-B.2-T para listagens seguras; Drupal Content Moderation |
| **MAG 0.5-C** | Magazine | Workflow editorial revista: rascunho, revisão, publicado, rejeitado/arquivado **somente conforme estados reais**; ações autorizadas, concorrência e trilha de revisão | Grants de entidade/revisão, configuração efetiva e fixtures; não conceder publish por papel implícito |
| **MAG 0.5-D** | Magazine | Form API progressivo: título, resumo e corpo primeiro; secundários sob details; erros abrem seções e focam campos; confirmação real de save, prevenção de perda de dados | Studio E-C/E-D e ACULTA420 SDC/UI Suite; sem CSS/JS de design paralelo |
| **MAG 0.5-E** | Magazine | Prévia fiel e privada das revisões autorizadas, canonical/alias/purpose, Metatag, Open Graph, classificação, cards de autoria e compartilhamento | Studio integra interface; Portal Domain; User privacidade; teste de cache anônimo/autenticado |
| **MAG 0.5-F** | Magazine + Forum | Discussão editorial contextual, moderação e first-discussion policy; eventos após publicação real sem vazamento de rascunhos | Forum Comment adapter + User; workflow ForumPost público NÃO migra automaticamente para Comment |
| **MAG 0.5-G** | Magazine + Media owners | Integração editorial de imagem/capa/galeria, alt, embeds autorizados e opções de documentos, sem duplicar mídia | Image/Gallery/Portal upload policy/viewers; fixture de anexo privado |
| **MAG 0.5-H** | Magazine + Messenger/Statistics | Eventos editoriais idempotentes de publicação/aprovação, notificações e métricas, consumidos somente por contratos estáveis | Messenger e Statistics; não criar duplicação local |
| **MAG 0.5-I** | Magazine | Higiene de configuração, consultas Views, Search API existente, SEO/categorias e documentação; tratar drift seletivamente | Fórum/workflow e mudanças concorrentes; backup e validação antes de import |
| **MAG 0.5-R** | Magazine + Studio + Tema | Unit/Kernel/Functional/browser: 2 autores, coautor, editor, moderador, anônimo, revisões, cache quente, Domain, 320–1440px, WCAG 2.2 AA, dark/light, teclado/no-JS/AJAX e benchmarks | Todos os contratos consumidores reais e fixtures aprovadas |
| **MAG 0.5-RC** | Responsável | Consolidar changelog, notas, rollback, decisão de RC | Nenhum gate DEFERRED crítico; não publicar nem mesclar automaticamente |

### Sequência de implementação independente

Começar agora por **0.5-A**, seguida de **0.5-B** (auditoria de access, revisões e providers) e itens internos de **0.5-E** (SEO, autoria e URL) que não exigem nova UI. Preparar testes de **0.5-C** sem ativar transições. Também revisar em **0.5-I** a configuração sob ownership do Magazine, sem importar configuração global.

### Sequência bloqueada ou coordenada

- **Studio E-B.2-T → E-B.3 → E-C/E-D:** UX `Meu trabalho`, cartões, editor e transições visíveis. Magazine fornece backend; Studio monta experiência.
- **ACULTA420:** novos componentes visuais exigem SDC/UI Suite e consumidor real; nenhuma substituição por CSS do Magazine.
- **Forum/User:** homologação de comentários e primeira moderação; manter Comment contextual mesmo com ForumPost revisionável no fórum público.
- **Gallery/Image/Portal:** upload, publicação contextual, Media privada, otimização e visualizadores; verificar políticas antes de criar widget.
- **Messenger/Statistics:** aguardar contrato de consumo real antes de enviar mensagens ou coletar métricas.
- **Config Sync:** coordenar dependências do workflow do Fórum, Domain por ambiente e demais alterações paralelas antes de importar; não forçar import global.

## Critérios e segurança

1. Preservar produção, conteúdo, NID, UUID, revisões, aliases, mídia e referências. Migração legada concluída não se repete.
2. Consultas com `accessCheck(TRUE)`; Entity Access e Revision Access por item, inclusive coautores; respeitar cache contexts/tags/max-age.
3. Um draft ou revisão pendente não aparece em listagens públicas, metatags públicas, contadores, notificações ou resultados sem autorização.
4. Reusar Form API, workflows e transições reais do dono; Studio não salva/publica diretamente por acesso presumido.
5. Separar contas `email_confirmed`, autores/coautores, editor, moderador, visitante e roles cruzados; simular duas contas e cache quente.
6. Preservar Institution Bar e Domain Header; tema controla layout. Mobile-first, SDC/UI Suite, 320–1440px, dark/light, reduced motion, teclado, leitor de tela e sem JS.
7. Cada microfase: commit exclusivo na branch/PR autorizada; README, CHANGELOG, roadmap, testes e evidências. Atualizar status do componente apenas após verificação real.
8. Homelab: confirmar branch/worktree, banco, URI, ambiente e backups antes de qualquer comando persistente. Distinguir PASS/FAIL/PARTIAL/DEFERRED. Não alegar Runtime com teste estático.

## Referências

- `docs/architecture/ACULTA-STUDIO-EXPERIENCE-PLATFORM.md`
- `web/modules/custom/aculta_portal/modules/aculta_studio/docs/ROADMAP.md`
- `docs/portal/STUDIO-REVISTA-EDITOR-VISUAL.md`
- `docs/architecture/ACULTA-SHARED-MODERATION-AND-KERNEL-POLICY.md`
- `web/modules/custom/aculta_portal/modules/aculta_forum/docs/ROADMAP.md`
- `docs/operations/DEBT-REGISTER.md`
- `web/modules/custom/aculta_portal/modules/aculta_magazine/docs/README.md`
