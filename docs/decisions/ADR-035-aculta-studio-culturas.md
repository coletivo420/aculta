# ADR-035 — ACULTA Studio Culturas para organizadores de Eventos

- **Status:** Aceita; código parcial presente na PR #299; homologação e validação Runtime pendentes.
- **Data:** 2026-10-30.
- **Decisores:** responsável pelo produto.
- **Contexto:** A administração técnica e a experiência cotidiana de organizadores precisam de escopos distintos. Eventos, Datas e Locais já têm ownership no módulo `aculta_events`; o Studio é uma camada de experiência, não um segundo owner.
- **Decisão:** A experiência de organizadores chama-se **ACULTA Studio Culturas** e tem rota canônica `/culturas` no purpose `eventos`, no host de Eventos. A rota está declarada no código da PR #299; presença no código não comprova ativação no Runtime. A ADR-038 define o caminho da Suite por domínio; `/studio/culturas` não será rota canônica. O painel administrativo fica no domínio principal em `/painel-administrativo/eventos`, restrito a administradores, incluindo configuração dos papéis/capacidades de organizadores.
- **Autorização:** `content_editor` é o padrão inicial configurável para criação. Criar, editar e publicar são capacidades distintas; configuração não substitui permissões Drupal, Entity Access, access handlers ou moderação verificados no servidor.
- **Domínios:** A área Studio Culturas pode executar escritas somente no purpose `eventos`. A raiz de Conta pode oferecer link absoluto para Culturas via `DomainPurposeManager`, sem POST/proxy de escrita. Não criar aliases temporários, redirects ou persistência paralela.
- **Ownership:** `aculta_events` continua dono das entidades e operações. Datas são geridas dentro do Evento; Locais são escolhidos/criados em contexto do Evento. A interface Studio apenas compõe rotas e formulários existentes.
- **Exibição cruzada:** Outros domínios podem exibir apenas Eventos publicados e acessíveis em componentes somente leitura, com canonical do purpose `eventos`; Eventos privados do LMS permanecem inacessíveis.
- **Estado:** Esta ADR aprova a direção. O HEAD auditado contém dashboard inicial, rotas e fluxos de Evento/Data/Local e configuração de papéis. Testes no HEAD, autorização/domínio Runtime, acessibilidade, acabamento de experiência e homologação ainda não estão concluídos.

## Consequências

- O purpose `eventos` deixa de ser exclusivamente público: contém também a experiência autenticada de organizadores, separada da administração privilegiada no MAIN.
- A política global “todo editorial apenas no MAIN” deve ser interpretada como regra do painel administrativo; ACULTA Studio Culturas é uma exceção explícita, com isolamento no purpose `eventos`.
- Os módulos Studio não devem duplicar CRUD, permissões, armazenamento nem lifecycle do Events.


## Atualização de rota pela ADR-038

ACULTA Studio Culturas continua sendo o nome da Suite; o caminho aprovado é `/culturas` em `eventos.aculta.*`. Dashboard e fluxos editoriais iniciais estão declarados na PR, mas sua ativação depende de validação no Runtime. `/studio/culturas` não é rota-alvo; não criar alias ou redirect. Homologação permanece pendente.
