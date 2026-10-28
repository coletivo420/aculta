# Procedimento de trabalho dos agentes

A REGRA GERAL de AGENTS.md prevalece sobre qualquer item abaixo.

Vale para todos os agentes (Local 1, Local 2, Local 3) e para qualquer sessão. Em caso
de dúvida ou de ordem que não cabe aqui, parar e perguntar ao responsável. Não
interpretar.

## 1. Uma PR, um branch
- Todo trabalho entra como commit no branch chore/aculta-m0-integridade (PR #298).
- Não criar branch de frente, não abrir PR, não criar PR nova sem ordem explícita.
- Branches locais temporárias existem só até o commit ser integrado. Não são destino
  final e não são publicadas como tal.
- Publicação sempre por fast-forward. Nunca force push. Nunca reescrever histórico
  publicado.
- Antes de publicar: git fetch, e conferir que o head remoto é ancestral do commit que
  será publicado.

## 2. O servidor de desenvolvimento é o checkout servido
- O servidor de desenvolvimento é o checkout /home/piradopirata/projetos/aculta, em HEAD
  destacado, sempre no head da PR.
- Não editar arquivos rastreados no checkout servido. Toda mudança entra por commit na
  PR e chega ao servidor por git checkout --detach <head>.
- Arquivos não rastreados no checkout (node_modules, package*.json e o widget não
  versionado) não são removidos, movidos nem adicionados ao git sem ordem explícita.
- Antes de trocar o head: git status, e conferir que os caminhos não rastreados não
  conflitam com o novo head. Se houver conflito: parar e relatar.
- Worktrees servem para leitura e teste temporário. Não são o destino final.
- Ao trocar o head, rodar o gatilho de banco da seção 3 e confirmar HTTP 200 na página
  principal.

## 3. Bancos: só os existentes, com gatilho por head
Bancos permitidos:
- aculta_runtime: banco do servidor de desenvolvimento.
- aculta_simpletest: testes virtuais dos agentes, com prefixos por agente.
- toca: fora do escopo do procedimento. Dono não verificado. Não tocar sem ordem
  explícita do responsável.

Proibido, sem exceção:
- Criar banco novo.
- site:install em banco que tenha tabelas.
- Alterar grants.

Gatilho de banco do aculta_runtime (a cada troca de head do checkout servido):
1. Somente leitura: drush updatedb:status e drush config:status (comparando com o
   config/sync do head). Registrar a saída literal.
2. Se houver atualização pendente ou diferença de configuração, e somente nesse caso:
   a) snapshot antes de qualquer comando: mysqldump --single-transaction do
      aculta_runtime, com SHA-256 e caminho registrados;
   b) drush updatedb, em seguida;
   c) drush config:import somente se o diff for exatamente o que o head espera, vindo
      do config/sync. Diff inesperado: parar e relatar.
3. drush cr. Limpeza de cache é permitida nesse gatilho e deve ser registrada como
   escrita de cache.
4. Verificar: página principal e loja respondem HTTP 200.
5. Registrar tudo na seção 5.

Sempre com ordem explícita do responsável:
- pm:uninstall de qualquer módulo.
- DROP DATABASE ou DROP TABLE.
- Qualquer escrita em aculta_runtime fora do gatilho acima (inclusive criar conteúdo de
  teste).
- Qualquer config:import que não venha do config/sync do head.

Conexão administrativa: usar o caminho autorizado pelo responsável (sudo -u mysql
mariadb). Não ler credenciais em secrets/. Não colar hash ou senha em relatório.

Antes de qualquer ação em banco: confirmar o nome e a contagem de tabelas. Se não
bater com o registro, parar.

## 4. Gates antes de qualquer publicação
- Lista fixa, no head que será publicado: git diff --check; php -l nos PHP alterados;
  phpcs com zero erros e zero avisos; testes JS do que foi alterado; PHPUnit do que foi
  alterado, rodado a partir do próprio head, sem bootstrap temporário; gates do tema
  (sdc-schemas, design-foundations e teste, browser-validators, module-theme-boundary,
  foundation, shell-contract e teste).
- Gate vermelho: não publicar. Registrar a saída literal e parar.
- Documentação pura (sem código) não exige gate de tema, mas exige git diff --check.

## 5. Registro
- Toda ação que altera estado (publicação, troca de head, gatilho de banco, exclusão,
  janela) entra em docs/operations/ACULTA-CONTINUOUS-EXECUTION.md, como commit de docs
  na PR.
- Separar o que foi VERIFICADO pelo agente do que foi RELATADO pelo responsável.
- Quando não for possível verificar, escrever "não verificado". Nunca completar com
  suposição.

## 6. Proibido sem ordem explícita do responsável
- Merge na main.
- Exclusão de branch, de banco ou de tabela.
- Alteração de secrets/ ou de credencial.
- Abertura ou fechamento de PR.
- Tag de versão de estado não validado.

## 7. Relatório
- Sempre: SHAs antes e depois, arquivos alterados, saídas literais dos comandos, HEAD do
  checkout servido, resultado do gatilho de banco e o que ficou pendente.
- Se algo não estava na ordem, dizer antes de fazer, não depois.
