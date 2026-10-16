# Roadmap do aculta_lms e visualizador PDF global

Revisado em 2026-10-16. A implementação segue fases independentes, com autorização e critérios próprios. `RUNTIME STATUS: DEFERRED` até homologação explícita no Runtime canônico.

## Princípios e estado inicial

`aculta_lms` será opcional, integrador e educacional. Group mantém cursos/memberships; Drupal LMS mantém lições/atividades/respostas/status/notas; Media/File mantém arquivos; `aculta_messenger` controla comunicações; `aculta_user`/`aculta_game` controlam identidade/conquistas. PDF é serviço compartilhado, não feature exclusiva LMS. O inventário está em [LMS-GROUP.md](LMS-GROUP.md), contratos em [LMS-INTEGRATION-CONTRACTS.md](../portal/LMS-INTEGRATION-CONTRACTS.md), segurança em [SECURITY-ACCESS-FILES-MATRIX.md](../portal/SECURITY-ACCESS-FILES-MATRIX.md) e PDF em [PDF-VIEWER-INTEGRATION.md](../portal/PDF-VIEWER-INTEGRATION.md).

Bloqueios técnicos: PDFPreview 1.2 e ImageMagick 5.0 não resolvem juntos via Composer; PDF 1.3 usa referências `.js`, incompatíveis com a distribuição PDF.js `.mjs` moderna; acesso/cache de viewer e miniaturas privadas requer prova; H5P segue beta e precisa de auditoria de segurança; PHP 8.5 e ferramentas de imagem precisam ser validados em ambiente correspondente.

## Fases e gates

| Fase | Escopo e dependências | Critério de aceite | Bloqueio/rollback |
| --- | --- | --- | --- |
| F0 — Documentação, inventário e auditoria | Fontes, versões, Composer, acesso, contratos, roadmap e revisão upstream | Documentos normativos atualizados; evidência PASS/FAIL/DEFERRED; nenhum estado Drupal alterado | `git diff --check`; commits documentais separados; rollback pelo revert dos commits |
| F1 — Fundação `aculta_lms` | Módulo opcional, DI, contratos públicos e testes de instalação; sem mover serviços LMS ativos no Portal | Instalação em site de teste, sem mudança automática de dados/config, sem storage paralelo; gates Drupal 11.3+ | Não ativar no Runtime antes de target/URI/config confirmados; desinstalação preserva dados LMS |
| F2 — PDF.js e formatter global | `drupal/pdf` 1.3 e PDF.js 6.4.299 pinados localmente; adapter ESM e formatter global do Media `document` no Portal, sem usar o formatter legado | Unit 5/5 PASS; browser smoke isolado PASS (PDF.js + worker local, amostra oficial 14 páginas). Teste do display Drupal Runtime DEFERRED; F3 integrou Media sem provar arquivo privado/turma | Sem patch direto em contrib. PDF público usa viewer; arquivos não PDF e PDFs sem viewer mantêm File link sujeito a access. Rollback remove formatter do display e serviço/asset após retirar uso |
| F3 — PDFPreview e integração Media | Formatter global no display Media `document`; preview opcional, ferramenta/binários, storage/cache de derivados | Formatter local integra PDF.js a PDFs públicos e mantém links Core; geração de thumbnail e prova de acesso privado dependem de ferramentas e Runtime compatíveis | Integração do display entregue, operação Runtime DEFERRED. PDFPreview 1.2 requer ImageMagick 3.7/4.x e ImageMagick 5.x segue sem suporte estável; binários locais ausentes. Não aplicar policy PDF/PS nem migrar arquivos nesta fase. |
| F4 — Consolidar Drupal LMS | Atualizar LMS/Group controladamente, rotas, papéis e provisionamento piloto | Composer audit/dry-run, config/schema update, conteúdo piloto reconciliado, regressões e permissões aprovadas | Não consolidar scripts antes de escolher fonte canônica. Backup e retorno de código/config; nunca deletar conteúdo como rollback |
| F5 — Catálogo | SDC ACULTA420 sobre `courses_catalog` e Views existentes | Anônimo/aluno vê somente cursos elegíveis; access/cache e teclado validado; sem JS essencial | Reverte presenter/SDC/config da view; manter rota catálogo anterior |
| F6 — Página de curso e sala | Overview, índice de lições, leitura/atividade, viewer compartilhado | Form/rota nativa LMS continua; membership e acesso de atividade testados | Sem alterar entidades LMS; desligar componente e voltar render padrão |
| F7 — Turmas e memberships | LMS Classes conforme existência/metadata real, educadores/alunos, isolamento | Usuário A/B, educador A/B, acesso admin e autocadastro/política aprovados | Não habilitar autoenrollment até política; rollback de grants/config em janela controlada |
| F8 — Avaliação objetiva | Answer plugins LMS, tentativas, scores e feedback | Cálculo repetível no servidor, regra documentada, audit trail e sem score externo confiado | Remover plugin somente após confirmar atividades referenciadas; preservar respostas existentes |
| F9 — Webform Score e entregas | Webform Score, LMS File Upload, storage privado | Identidade/autoria e resultados integrados sem gradebook duplicado; revogação de membership corta arquivos | Dependente de APIs Composer e privacidade; desabilitar formulário/integração mantendo submissions e arquivos |
| F10 — H5P | Auditoria de segurança/compatibilidade e integração de conteúdo | Bibliotecas, permissões, uploads, CSP/isolamento e acesso aprovados; sem nota oficial até API confiável | H5P Drupal 11 é beta nas fontes revisadas; ativação só por decisão. Rollback despublica embed sem apagar conteúdo |
| F11 — Certificados | Entity Print após conclusão LMS aprovada | emissão/autorização, verificação, reemissão, revogação, auditoria, privacidade e template testados | Definir registro imutável/derivado antes de codar. Reverter emissão sem apagar auditoria/artefatos válidos |
| F12 — Dashboards | SDC ACULTA420 sobre LMS services/Views | dados próprios para estudante; aggregate limitado à turma para educador; sem cache cruzado | Reverter presenters/Views e manter rotas LMS |
| F13 — Notificações educacionais | Eventos LMS → `aculta_messenger` / Message Notify | dedupe, access atual, preferências, e-mail mínimo e fila testados | Desabilitar subscriber/producer; Message permanece fonte e não apagar comunicações existentes |
| F14 — Conexões | `FriendshipManagerInterface` e Messenger existentes | ações e notificações usam amizade atual sem duplicar estado/permissão | Reverter adapter; amizade continua em `aculta_user` |
| F15 — Integrações de conteúdo | Gallery, Magazine, Wiki, Forum e eventos quando estabilizados | referência de entidades e acesso preservados em todos os purposes | Só após roadmap/PRs dos submódulos estabilizados; remover links sem deletar conteúdo |
| F16 — Hardening | performance, segurança, privacidade, acessibilidade, cache e tamanho/timeout PDF | zero Critical/High; matriz completa e gates PASS | rollback por componente/flag/config, sem downgrade destrutivo de entidades |
| RC — Homologação integrada | testes no Homelab, browser, config sync, documento e gate de release | checklist/testes completos, Runtime PASS, versão e rollback anotados | Nenhum deploy/merge automático; interromper RC se access/cache, segurança ou banco divergir |
| F17 — Google Classroom (opcional e última) | Após RC aprovado e nova decisão; publicar somente link para atividade ACULTA | OAuth/scopes, consentimento e link para fonte LMS aprovados | Nenhum pacote/token/rota/storage nesta linha; desabilitar integração sem afetar LMS |

## Estado de fase

**F1 — Fundação `aculta_lms`: entregue na PR #286, versão `0.1.0-dev.1`.** O submódulo opcional e o contrato
DI de conclusão aprovada estão implementados. Testes Unit (2/2) e Kernel isolado (1/1) passam; o módulo não foi
habilitado no Runtime e não tem produtor ou consumidor educacional ativo (**RUNTIME STATUS: DEFERRED**). A fase
F2 e integração do display F3 estão no código da PR #286. O teste de runtime do display, entrega privada
protegida e geração de miniatura estão **DEFERRED**; ImageMagick está ausente no checkout e Hostinger não
foi verificada. A próxima atividade é resolver a operação segura de preview e estabelecer um endpoint de
leitura privada com validação no Runtime antes de habilitar documentos educacionais privados.

## Ordem, PR e status

Cada fase é uma unidade pequena, commitada e descrita em PR própria conforme a regra do repositório. Este roadmap não autoriza execução de código, instalação de dependências, importação de curso, atualização persistente, merge ou deploy; cada fase futura precisa de decisão e PR própria.

Antes de operação Homelab: registrar `hostname`, `pwd`, branch/estado Git e Drush; confirmar site URI, banco, estado de configuração e possibilidade de recuperação.

## Rollback transversal

Prévia de mudanças: `composer require --dry-run`, `composer validate`, `composer audit`, diff de configuração e entidades, inventário de arquivos e estratégia de restauração. Toda atualização de schema/config/estado deve ter backup verificável e caminho de retorno. Rollback de código não remove dados de Group/LMS/File/Media/Message; não usar downgrade que perca respostas, notas ou memberships. Mudanças de grants podem cortar acesso, por isso validar usuário A/B e preservar access antes de liberar.

## Referências funcionais

- [ADR-005](../decisions/ADR-005-lms-integration.md)
- [LMS e Group](LMS-GROUP.md)
- [Contratos de integração](../portal/LMS-INTEGRATION-CONTRACTS.md)
- [PDF compartilhado](../portal/PDF-VIEWER-INTEGRATION.md)
- [Matriz de segurança](../portal/SECURITY-ACCESS-FILES-MATRIX.md)
- [LMS Drupal.org](https://www.drupal.org/project/lms)
- [Opigno Catalogue](https://www.drupal.org/files/Opigno-Catalogue.png), [Training Page](https://www.drupal.org/files/Opigno-Training-Page.png), [Dashboard](https://www.drupal.org/files/Opigno-Dashboard.png): referências visuais/funcionais; não copiar CSS/templates. O [repositório Opigno](https://github.com/Craftmasters/opigno) declara GPL-2.0, e a página do [Opigno LMS Community](https://www.drupal.org/project/opigno_lms) informa licença igual ao Core; qualquer reaproveitamento de código exige confirmar a licença do arquivo/versão exata e preservar avisos/atribuição.
