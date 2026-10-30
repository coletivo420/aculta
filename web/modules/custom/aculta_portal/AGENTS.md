# Portal — regras locais

## ADR-038 - caminho aprovado para as Suites (implementação futura)

Marley `conta.aculta.*/marley`; Ganjalab `cursos.aculta.*/ganjalab`; WE SATIVA `wiki.aculta.*/wesativa`; Green Stash `loja.aculta.*/greenstash`; Culturas `eventos.aculta.*/culturas`. `/studio` e todo `/studio/*` serão removidos e responderão 404, sem aliases/redirects. `aculta_studio` permanece framework compartilhado. A raiz de `conta` será entrada de Conta, fora de `/studio`; Conta e Perfil permanecem separados. As referências de rota anteriores neste arquivo descrevem o estado pré-migração e não são destino para implementação nova. Consulte ADR-038.


Herde `AGENTS.md` da raiz. Consulte `docs/AGENT-POLICIES.md` **somente nas seções relevantes** e os contratos `docs/portal/GUARDRAILS.md` e `docs/portal/DRUPAL-11-STANDARDS.md` quando a alteração exigir.

- Core/contrib são a fonte de verdade; preserve a fronteira Core/contrib → Portal → contrato neutro → tema.
- Endereço e CEP: use exclusivamente o contrato central em `AccountAddressForm` e `aculta_portal_cep`. O país é sempre Brasil (`BR`), sem seletor de país em formulário algum. Validação no servidor obrigatória (`country_code = BR`). Preserve número, S/N e complemento no CEP. Ver ADR-034 e `docs/portal/ACCOUNT-ADDRESS.md`.
- Valide DI, Entity API, access, render/cacheability e gates do escopo.
- Para rotas/domínios, consulte `docs/architecture/AGENT-ROUTES.md` e `docs/architecture/ROUTES-SITEMAP.md`.
- **Administração e integrações somente no MAIN:** `/painel-administrativo/**` e `/integracoes/**` respondem somente no domínio principal; em subdomínios, 404 (nunca 302). Não renderize toolbar, links contextuais ou ações administrativas em subdomínio. Política: `docs/portal/ADMIN-DOMAIN-POLICY.md`; gate: `php scripts/validate-admin-routes-main-domain.php`.
- Regras adicionais de submódulos só se aplicam ao submódulo alterado.

- Antes de criar ou editar captura de endereço, leia `docs/decisions/ADR-033-endereco-busca-e-locais.md` e siga `docs/portal/ACCOUNT-ADDRESS.md`: reutilize o adaptador central e CEP para endereço completo; Brasil padrão, sem seletor.
- Busca por logradouro é exclusiva de Events/Maps e não está implementada. Preserve o comportamento de Conta/checkout. Para Activity, integre Local de Events em vez de manter campos postais avulsos.

## Decisão de produto — ACULTA Studio Culturas

## ACULTA Studio Culturas — decisão de produto e domínio (2026-10-30)

O nome aprovado para a experiência de organizadores é **ACULTA Studio Culturas**. A rota planejada é `/culturas` no purpose `eventos` (host eventos configurado pelo DomainPurposeManager); a entrada geral do Studio em `account` poderá apenas apresentar um link para essa experiência.

- A sessão administrativa técnica continua no domínio principal, em `/painel-administrativo/eventos`, restrita a administradores. Configuração global de permissões, auditoria e operações administrativas permanecem ali.
- ACULTA Studio Culturas é a experiência de organizadores: composição de fluxos do owner `aculta_events`, para criar e gerir Eventos e seus componentes (Datas e Locais), usando Form API, Entity Access e moderação existentes. Não cria entidade, formulário de persistência, permissão ou catálogo duplicado.
- A autorização para criar/editar/publicar Eventos e cadastrar Locais deve ser configurável pela administração de Eventos, com `content_editor` como padrão inicial. A configuração não concede acesso sozinha: permissões efetivas e access handlers do servidor continuam obrigatórios; criar, editar e publicar são capacidades distintas.
- Escritas só são aceitas no purpose `eventos` através de rotas canônicas da experiência. O domínio `account` pode linkar para ela, mas não pode fazer POST nem gravar conteúdo de Eventos. Não introduzir proxy de escrita, alias de rota ou redirect de compatibilidade.
- As telas públicas de Eventos continuam no purpose `eventos`. Outros domínios podem compor exibição somente leitura de Eventos publicados e acessíveis, sempre com link canônico de Eventos e sem expor Eventos privados do LMS.
- Esta decisão substitui qualquer texto anterior que diga que todo trabalho editorial de Eventos só pode ocorrer no MAIN ou que o purpose `eventos` é exclusivamente público. O painel de administração permanece no MAIN; a área do organizador é a exceção explícita e separada em ACULTA Studio Culturas.
- Estado: decisão e planejamento documental; implementação, autorização por domínio, navegação e homologação ainda precisam ser concluídas.

## Contrato futuro: Suites do ACULTA Studio por domínio (ADR-038)

Cada Suite responde em seu próprio domínio/purpose e sob o caminho-base abaixo. O prefixo público `/studio` e todas as rotas `/studio/*` serão retirados e responderão 404, sem alias ou redirect. `aculta_studio` permanece como infraestrutura compartilhada de composição e registry, não como raiz de URL.

| Suite | Purpose | Host de produção | Host de teste | Caminho-base |
| --- | --- | --- | --- | --- |
| Marley | `account` | `conta.aculta.org` | `conta.aculta.toca.net.br` | `/marley` |
| Ganjalab | `courses` | `cursos.aculta.org` | `cursos.aculta.toca.net.br` | `/ganjalab` |
| WE SATIVA | `wiki` | `wiki.aculta.org` | `wiki.aculta.toca.net.br` | `/wesativa` |
| Green Stash | `shop` | `loja.aculta.org` | `loja.aculta.toca.net.br` | `/greenstash` |
| Culturas | `eventos` | `eventos.aculta.org` | `eventos.aculta.toca.net.br` | `/culturas` |

Manter Conta e Perfil como experiências diferentes, fora das Suites. A raiz de `conta` passa a uma página de entrada da Conta com navegação para Marley e links absolutos às outras Suites; não aponta internamente para `/studio`. A Conta legada continua em `/conta-legada`. Planejar caminhos `/conta/*`, `/perfil/*` e `/salvos` para absorver as rotas pessoais atuais, preservando owners e sem misturar dados.

Regras de implementação:
- Rotas declaram o purpose correto e validam usuário, módulo owner e permissão no servidor. Purpose incorreto responde 404, inclusive POST. Links cross-domain são absolutos via `DomainPurposeManager`; não enviar POST/proxy de escrita entre purposes.
- Preservar route IDs e owners sempre que possível. Rotas de negócio podem ser declaradas pelo módulo owner e ter path/purpose ajustados conforme a Suite responsável.
- Novas Suites escolhem host/purpose e slug próprio antes de declarar rotas; não introduzir `/studio/{suite}`.
- Hosts de Conta, Cursos, Loja e Eventos já constam nos registros atuais. Wiki ainda usa `wiki420.aculta.org` e `wiki420.aculta.toca.net.br`; migração para `wiki.aculta.*` depende de config Deployer/Domain, DNS, TLS/vhost e homologação.
- Atualizar `PurposeFrontPathProcessor`, `FrontPathPolicy`, subscriber de propósito, navegação e testes para a raiz de cada purpose e as rotas da Suite. Os cinco caminhos da matriz são rotas válidas apenas no purpose respectivo.
- Remover todas as rotas `/studio` e `/studio/*` após mover destinos. Sem redirects legados; canonical e links apontam diretamente aos novos hosts/caminhos.
- Migrar links internos/externos, login/retorno, e-mail, notificações, sitemap, canonical, cache e testes. A troca de URL não transfere ownership de entidades, formulários, ACL ou workflows.

Estado em 2026-10-30: decisão e plano aprovados; nenhum corte de rotas, configuração de domínio, DNS ou validação de Runtime é declarado por este documento.

Ao revisar rotas, trate ADR-038 como decisão vigente e consulte `docs/architecture/ROUTES-SITEMAP.md`.
