# Frente paralela de QA do Marley/CGU - 21/11/2026

**Base congelada:** PR #394, commit \`08372bad37a44b38c420a3905c36544d9240af9e\`.
**Branch exclusiva:** \`test/forum-marley-boundaries-20261121\`.
**Política:** não editar \`dev\`, \`feat/forum-paridade-discourse\`, runtime, configurações ou classes em que o agente local está trabalhando.

## Contexto verificado

O agente CLI mantém na PR #394 as implementações de enquetes nativas, \`ForumPostMover\`, formulários de movimentação, autorização de backend e tipagem do CGU frontend. Em execução paralela, não há segurança para alterar \`aculta_forum.services.yml\`, \`aculta_forum.routing.yml\`, \`aculta_cgu/src/Controller/CguForumMarleyController.php\` ou outras classes de produção da mesma frente.

Esta branch acrescenta **somente arquivos novos**: dois conjuntos de testes Unit com matriz de permissões de CGU e controles de tópico no Marley, mais este registro. Não altera código funcional.

## Aceite coberto por estes testes

- Permissões comunitárias \`moderar denuncias do forum\`, \`review aculta forum reports\`, \`moderar denuncias cgu: forum\` concedem acesso **somente** ao propósito Fórum.
- Permissões Wiki/Revista não autorizam casos do Fórum; controle editorial de tópicos não concede acesso automático aos relatos do CGU.
- Escopo CGU transversal contém todos os purposes conhecidos, com ordem estável.
- Controle de tópico exige usuário autenticado, permissão \`moderate aculta forum topics\`, bundle \`aculta_forum_topic\`, \`field_domain_source\` resolvido como \`forum\` e Entity Access no domínio de origem.
- Conteúdo privado, de Wiki, Revista, LMS, sem permissão, sem entidade ou com domínio irresolúvel é negado.
- Sem autorização, o builder não produz links de mutação do tópico.

## Trabalho que deliberadamente NÃO será feito aqui

Enquetes, ForumPostMover, split/merge, novos serviços, alterações de rota, migradores de dados, mudanças no Runtime, revisão da role \`moderador\`, formulários CGU ou de moderação, alterações nas classes de produção e commits na PR #394.

## Para o agente local incorporar sem conflito

1. Quando a implementação funcional estabilizar, fazer \`git fetch origin\` e inspecionar o diff desta branch com \`git diff origin/feat/forum-paridade-discourse...origin/test/forum-marley-boundaries-20261121\`.
2. Executar os dois arquivos novos via PHPUnit no worktree isolado, corrigindo eventuais falhas causadas por alterações posteriores dos contratos.
3. Incorporar **apenas o commit de testes** por cherry-pick quando compatível, ou copiar os três arquivos novos. Não rebasear a branch principal da PR a partir desta branch de QA.
4. Continuar os gates do projeto (PHPStan nível 10, PHPUnit Unit/Kernel, funcional, navegador, acesso por domínio/Group, auditoria, sem regressão Growroom).

**Limite:** mocks Unit validam a política em nível de serviço, mas não homologam Form API real, CSRF, multiusuário ou banco. Esses gates permanecem obrigatórios no Homelab.
