# ADR-035 — ACULTA Studio Culturas para organizadores de Eventos

- **Status:** Aceita; rota inicial implementada, autorização por operação e homologação pendentes.
- **Data:** 2026-10-30.
- **Decisores:** responsável pelo produto.
- **Contexto:** A administração técnica e a experiência cotidiana de organizadores precisam de escopos distintos. Eventos, Datas e Locais já têm ownership no módulo `aculta_events`; o Studio é uma camada de experiência, não um segundo owner.
- **Decisão:** A experiência de organizadores chama-se **ACULTA Studio Culturas** e usa a rota canônica `/culturas` no purpose `eventos`, no host de Eventos configurado pelo DomainPurposeManager. A ADR-038 substituiu o prefixo `/studio/culturas`. O painel administrativo fica no domínio principal em `/painel-administrativo/eventos`, restrito a administradores, incluindo configuração dos papéis/capacidades de organizadores.
- **Autorização:** `content_editor` é o padrão inicial configurável para criação. Criar, editar e publicar são capacidades distintas; configuração não substitui permissões Drupal, Entity Access, access handlers ou moderação verificados no servidor.
- **Domínios:** A área Studio Culturas pode executar escritas somente no purpose `eventos`. A raiz de Conta pode oferecer link absoluto para Culturas via `DomainPurposeManager`, sem POST/proxy de escrita. Não criar aliases temporários, redirects ou persistência paralela.
- **Ownership:** `aculta_events` continua dono das entidades e operações. Datas são geridas dentro do Evento; Locais são escolhidos/criados em contexto do Evento. A interface Studio apenas compõe rotas e formulários existentes.
- **Exibição cruzada:** Outros domínios podem exibir apenas Eventos publicados e acessíveis em componentes somente leitura, com canonical do purpose `eventos`; Eventos privados do LMS permanecem inacessíveis.
- **Estado:** A rota `/culturas`, dashboard inicial, fluxos de Evento/Data/Local e configuração de papéis estão no código. A validação completa das permissões de escrita por operação, Dialog/AJAX, acessibilidade e jornada no Runtime permanece pendente; não declarar a Suite homologada.

## Consequências

- O purpose `eventos` deixa de ser exclusivamente público: contém também a experiência autenticada de organizadores, separada da administração privilegiada no MAIN.
- A política global “todo editorial apenas no MAIN” deve ser interpretada como regra do painel administrativo; ACULTA Studio Culturas é uma exceção explícita, com isolamento no purpose `eventos`.
- Os módulos Studio não devem duplicar CRUD, permissões, armazenamento nem lifecycle do Events.


## Atualização de rota pela ADR-038

ACULTA Studio Culturas continua sendo o nome da Suite; `/culturas` responde no purpose `eventos`, cujo host é configurado por DomainPurposeManager. `/studio/culturas` foi removida e responde 404. A validação de permissões e homologação dos fluxos seguem pendentes.
