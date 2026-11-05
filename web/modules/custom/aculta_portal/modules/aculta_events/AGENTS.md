# Eventos — ACULTA Studio Culturas

Decisão de produto e domínio movida do AGENTS do Portal (2026-11-05). Rotas: `docs/architecture/ROUTES-SITEMAP.md`.

## Decisão (2026-10-30)

O nome aprovado para a experiência de organizadores é **ACULTA Studio Culturas**. `/culturas` e os fluxos iniciais estão declarados no purpose `eventos`; o host e links absolutos vêm do `DomainPurposeManager`. A entrada de Conta pode apenas linkar, sem escrita cross-domain. A jornada não está homologada até validar permissões por operação e rotas auxiliares.

- A sessão administrativa técnica continua no domínio principal, em `/painel-administrativo/eventos`, restrita a administradores. Configuração global de permissões, auditoria e operações administrativas permanecem ali.
- ACULTA Studio Culturas é a experiência de organizadores: composição de fluxos do owner `aculta_events`, para criar e gerir Eventos e seus componentes (Datas e Locais), usando Form API, Entity Access e moderação existentes. Não cria entidade, formulário de persistência, permissão ou catálogo duplicado.
- A autorização para criar/editar/publicar Eventos e cadastrar Locais deve ser configurável pela administração de Eventos, com `content_editor` como padrão inicial. A configuração não concede acesso sozinha: permissões efetivas e access handlers do servidor continuam obrigatórios; criar, editar e publicar são capacidades distintas.
- Escritas só são aceitas no purpose `eventos` através de rotas canônicas da experiência. O domínio `account` pode linkar para ela, mas não pode fazer POST nem gravar conteúdo de Eventos. Não introduzir proxy de escrita, alias de rota ou redirect de compatibilidade.
- As telas públicas de Eventos continuam no purpose `eventos`. Outros domínios podem compor exibição somente leitura de Eventos publicados e acessíveis, sempre com link canônico de Eventos e sem expor Eventos privados do LMS.
- Esta decisão substitui qualquer texto anterior que diga que todo trabalho editorial de Eventos só pode ocorrer no MAIN ou que o purpose `eventos` é exclusivamente público. O painel de administração permanece no MAIN; a área do organizador é a exceção explícita e separada em ACULTA Studio Culturas.
- A implementação atual é parcial: dashboard, formulários de Evento/Data/Local e configuração de papéis existem em `aculta_events`. Não declarar a experiência homologada antes de validar purpose, autorização, acessibilidade, navegação e submissões no Runtime.

