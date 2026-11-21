# Frente paralela de QA do Marley/CGU - 21/11/2026

**Base congelada:** PR #394, commit `08372bad37a44b38c420a3905c36544d9240af9e`.
**Branch exclusiva:** `test/forum-marley-boundaries-20261121`.
**Política:** não editar `dev`, `feat/forum-paridade-discourse`, runtime, configurações ou classes em que o agente local está trabalhando.

## Contexto verificado

O agente CLI mantém na PR #394 as implementações de enquetes nativas, `ForumPostMover`, formulários de movimentação, autorização de backend e tipagem do CGU frontend. Em execução paralela, não há segurança para alterar `aculta_forum.services.yml`, `aculta_forum.routing.yml`, `aculta_cgu/src/Controller/CguForumMarleyController.php` ou outras classes de produção da mesma frente.

Esta branch acrescenta **somente arquivos novos**: duas matrizes Unit, uma suíte Kernel com banco de teste e papéis reais, uma suíte Browser Playwright opt-in (config independente, especificação, exemplo de fixture e README), mais este registro. Não altera código funcional nem configurações do portal.

## Aceite coberto por estes testes

- Permissões comunitárias `moderar denuncias do forum`, `review aculta forum reports`, `moderar denuncias cgu: forum` concedem acesso **somente** ao propósito Fórum.
- Permissões Wiki/Revista não autorizam casos do Fórum; controle editorial de tópicos não concede acesso automático aos relatos do CGU.
- Escopo CGU transversal contém todos os purposes conhecidos, com ordem estável.
- Controle de tópico exige usuário autenticado, permissão `moderate aculta forum topics`, bundle `aculta_forum_topic`, `field_domain_source` resolvido como `forum` e Entity Access no domínio de origem.
- Conteúdo privado, de Wiki, Revista, LMS, sem permissão, sem entidade ou com domínio irresolúvel é negado.
- Sem autorização, o builder não produz links de mutação do tópico.

## Trabalho que deliberadamente NÃO será feito aqui

Enquetes, ForumPostMover, split/merge, novos serviços, alterações de rota, migradores de dados, mudanças no Runtime, revisão da role `moderador`, formulários CGU ou de moderação, alterações nas classes de produção e commits na PR #394.

## Para o agente local incorporar sem conflito

1. Quando a implementação funcional estabilizar, fazer `git fetch origin` e inspecionar o diff desta branch com `git diff origin/feat/forum-paridade-discourse...origin/test/forum-marley-boundaries-20261121`.
2. Executar Unit e Kernel em banco de testes, em worktree isolado. Rodar Playwright somente com fixtures de papéis combinados criadas em instalação descartável. Asserções de HTML/ACL devem ser revistas após alterações do agente.
3. Incorporar os commits de QA por cherry-pick quando compatíveis, ou copiar exclusivamente os arquivos de testes e documentação. Não rebasear a branch principal da PR a partir desta branch de QA.
4. Continuar os gates do projeto (PHPStan nível 10, PHPUnit Unit/Kernel, funcional, navegador, acesso por domínio/Group, auditoria, sem regressão Growroom).

## Ampliação Kernel e Browser (21/11/2026)

**Novos testes Kernel** (`tests/src/Kernel/CguMarleyCombinedRolesDecisionKernelTest.php`):

- Entidades e tabelas reais em banco temporário: fila CGU paginada por purpose; papéis `forum`, `wiki`, `revista`, `topic_operator` e suas combinações.
- Tópicos publicados e não publicados; denúncia de conteúdo privado não concede leitura automaticamente.
- Mesma entidade com relatórios etiquetados em propósitos distintos: contadores, listagem e decisões não se misturam.
- Troca `pendente -> em_analise/procedente/improcedente` com `loadUnchanged()`, lock de decisão e proteção contra substituição de decisão final por formulário antigo.
- Nenhuma decisão CGU é uma mudança de publicação do nó. Registros de decisão preservam parecer e UID do moderador; a fila não exibe UID do denunciante.

**Novo Browser Playwright** em `tests/browser/qa-marley/` (config próprio):

- Rotas da Conta com moderador de Fórum, sem permissão, moderador Wiki/Revista e combinações Fórum+Wiki, Fórum+Revista e Revista+Wiki.
- Sem herdar acesso administrativo, sem ler relatório de outro purpose, sem abrir relatório restrito de Fórum ou expor título privado/identidade do denunciante.
- Link de tópico autorizado apontando ao domínio Fórum, enquanto a fila permanece no domínio da Conta.
- Form API real no navegador, proteção CSRF e GET pós-POST inválido para confirmar ausência de mutação mesmo quando o Drupal devolve HTTP 200.
- Decisão efetiva via navegador **somente** em relatório descartável e com `ACULTA_QA_MARLEY_ALLOW_MUTATION=yes`, verificando persistência e que o alvo permanece publicado.

As credenciais, cookies, IDs de casos e títulos sintéticos são fornecidos por fixture **local não versionada**, baseada em `tests/browser/qa-marley/fixture.example.json`. Nenhum browser pode iniciar mutações em Runtime compartilhado.

### Comandos de aceite no worktree isolado

```bash
vendor/bin/phpunit \
  web/modules/custom/aculta_portal/modules/aculta_cgu/tests/src/Unit/ModerationScopeMarleyMatrixTest.php \
  web/modules/custom/aculta_portal/modules/aculta_forum/tests/src/Unit/ForumMarleyTopicIsolationMatrixTest.php

vendor/bin/phpunit \
  web/modules/custom/aculta_portal/modules/aculta_cgu/tests/src/Kernel/CguMarleyCombinedRolesDecisionKernelTest.php

cd tests/browser
ACULTA_QA_MARLEY_FIXTURE=/caminho/seguro/marley-fixture.json \
  npx playwright test --config qa-marley/playwright.marley.config.mjs
```

**Observação:** comandos propostos, ainda não executados nem homologados. Para teste browser com escrita, utilizar exclusivamente ambiente e banco descartáveis e seguir o README da suíte.

### Critérios de aceite complementares

| ID | Caso | Situação na entrega |
| --- | --- | --- |
| QA-MARLEY-K1 | Fórum+Wiki só vê denúncias dos dois purposes | Código Kernel, PASS pendente |
| QA-MARLEY-K2 | Combo de papéis não concede escopo de terceiro purpose | Código Kernel, PASS pendente |
| QA-MARLEY-K3 | Mesmo alvo em purposes diferentes não mistura contagem e decisão | Código Kernel, PASS pendente |
| QA-MARLEY-K4 | Rejeição de status final sobrescrito e lock concorrente | Código Kernel, PASS pendente |
| QA-MARLEY-K5 | Conteúdo despublicado não herda Entity Access por ser denunciado | Código Kernel, PASS pendente |
| QA-MARLEY-B1 | Acesso de todos os perfis e combos via HTTP | Código Browser, fixture/execução pendentes |
| QA-MARLEY-B2 | 403/404 de outros purposes e alvos restritos | Código Browser, fixture/execução pendentes |
| QA-MARLEY-B3 | Sem PII na fila e com link ao domínio Fórum | Código Browser, fixture/execução pendentes |
| QA-MARLEY-B4 | GET de formulário, POST com CSRF inválido e ausência de mutação | Código Browser, fixture/execução pendentes |
| QA-MARLEY-B5 | Decisão real só em banco descartável, alvo ainda publicado | Código Browser, execução autorizada pendente |

**Limite:** Unit e Kernel não substituem Browser/Functional. O pacote agora contém suítes executáveis, mas não há comprovação de PASS, screenshots ou Runtime nesta sessão. Combinações de grants devem ser conferidas contra a configuração final que o agente local consolidar no código.

