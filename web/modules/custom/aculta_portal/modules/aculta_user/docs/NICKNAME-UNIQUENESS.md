# Unicidade transacional do apelido

## Estado da análise — 2026-10-26

### Política confirmada pelo responsável

- Apelido identifica uma conta/UID: UIDs distintos não podem compartilhar o apelido atual; perfis de papéis do mesmo UID exibem o mesmo apelido.
- Ao trocar o apelido, o anterior fica livre para outra conta imediatamente. Não se criam aliases/redirects de perfil; referências internas usam UID.
- `-gr` não é permitido como sufixo de apelido de conta. O marcador é apenas nome de exibição dos autores Growroom em conteúdo do Fórum; o acervo legado não cria User/Profile nem rota individual.
- Colisões de nome automático podem gerar `nome#2`, cujo slug público é `nome-2`. A pessoa também pode informar `#N` manualmente se o slug correspondente estiver livre; formulários não acrescentam sufixos automaticamente. `nome-2` e `nome#2` são a mesma identidade pública.

`NicknamePolicy` faz o preflight amigável nos campos `field_nickname` dos perfis de papel. A garantia concorrente usa a constraint da projeção SQL owner-managed descrita abaixo; somente o preflight não seria suficiente. Este parágrafo substitui a análise inicial de corrida, preservada no histórico desta documentação.

A tabela Field API `profile__field_nickname` não contém `uid`. Uma chave SQL única simples sobre `field_nickname_value` impediria também perfis distintos do mesmo usuário de compartilhar o apelido. O campo é translatable e revisionável, então a garantia depende ainda da semântica de linhas atuais, idiomas, perfis legados e de futuras alterações de schema. Não foi criada uma constraint direta porque ela não representa exatamente o contrato vigente.

## Preflight read-only do Homelab

Antes das consultas, Drush confirmou Drupal 11.4.10/PHP 8.4.27, host `toca`, URI MAIN `https://aculta.toca.net.br`, banco `aculta_runtime`, root `/home/piradopirata/projetos/aculta/web` e configuração em `/home/piradopirata/projetos/aculta/config/sync`.

Consultas agregadas, sem selecionar ou imprimir apelidos:

- `profile__field_nickname`: 14 linhas ativas em 14 entidades; nenhuma linha `deleted`.
- Nenhum grupo duplicado case-insensitive entre entidades de diferentes UIDs.
- Nenhuma duplicação do mesmo apelido entre perfis de um mesmo UID.
- A tabela não possui índice único sobre o valor.

Esse snapshot não comprova ausência de duplicatas em outros ambientes nem resolve a corrida entre gravações. Nenhuma escrita ou atualização foi executada.

## Opções para fechar a corrida

1. **Lock por apelido no ciclo de persistência do Profile:** um hook central adquire lock distribuído pelo apelido normalizado, reconsulta sob lock excluindo o UID atual, mantém o lock até insert/update e libera no post-save. Deve tratar falhas antes do post-save por expiração curta e retornar erro genérico em conflito. Não cria armazenamento paralelo, mas precisa cobrir todos os caminhos de escrita.
2. **Índice derivado de apelidos por UID:** tabela owner-managed que reserva cada apelido normalizado para um UID, permitindo vários perfis da mesma conta. Hooks transacionais mantêm a projeção; conflito de outra conta é rejeitado. Exige rebuild idempotente e auditoria de duplicatas antes da criação.

### Arquitetura selecionada pelo responsável

O responsável selecionou a opção 2. Profile permanece a fonte de verdade e `aculta_user` mantém uma projeção owner-managed por UID com `uid` único e slug normalizado único. Perfis de papéis do mesmo UID compartilham a reivindicação; ao renomear, a atualização do novo slug e a liberação do anterior ocorrem no mesmo caminho transacional. A projeção pode ser reconstruída a partir de Profiles; não guarda histórico nem cria aliases/redirects. Referências internas continuam usando UID.

A projeção deve indexar o slug canônico (`#N` e `-N` convergem para `-N`) e falhar fechada se o rebuild encontrar apelidos diferentes entre Profiles ativos do mesmo UID, colisões entre UIDs ou apelidos atuais terminados em `-gr`; reportar apenas contagens agregadas, sem expor apelidos. Cobertura Kernel/MariaDB: dois processos confirmam slug livre antes de inserir; uma claim vence e a outra é rejeitada (7 testes/55 assertions no total). Também há cobertura de rename/liberação, Profiles múltiplos do UID, rebuild idempotente e conflitos. Falta simular rollback de falha de persistência e validar o update 10013 em instalação efêmera. Não executar update no Runtime sem autorização operacional específica.

## Reversão

A opção de lock não altera schema nem dados; rollback é reverter o commit e remover o hook/testes. Para o índice derivado, interromper a escrita antes do rebuild, preservar backup da tabela e confirmar consistência dos UIDs; rollback deve remover hooks e projeção somente depois de comparar novamente com `field_nickname`. Nenhum plano autoriza alterar o Runtime atual.


### Implementação em código — dev.83

- Schema owner-managed: `aculta_user_nickname(uid PRIMARY KEY, slug UNIQUE)`. O campo de Profile continua sendo a fonte; a tabela guarda somente a claim atual por UID. Não guarda aliases ou histórico.
- `ProfileNicknameRegistryHooks` chama `NicknameRegistry` no `entity_presave`/`entity_delete`. O Core SQL content storage envolve os hooks e a escrita de Profile na mesma transação. Um rename atualiza a linha por UID, sincroniza todos os Profiles de papel e deixa o slug anterior disponível na mesma transação. A chave SQL única resolve corridas entre UIDs; o preflight de Form API dá mensagem amigável antes do save.
- `PublicProfileController` resolve o slug na tabela, depois carrega o usuário e o Profile principal. Referências internas seguem por UID. `aculta_user_update_10013()` cria a tabela se necessário e executa rebuild idempotente. A validação é feita antes de limpar/repopular a projeção; em conflito, somente contagens agregadas são reportadas e os registros existentes não são substituídos.
- O Kernel `NicknameRegistryKernelTest` passou **7 testes / 55 assertions** em MariaDB dedicado. Inclui dois processos que observam a ausência do slug antes de inserir e disputam a constraint; uma inserção vence e outra recebe conflito. Também cobre rename/liberação imediata, chave SQL única, rebuild idempotente e fail-closed, Profiles do mesmo UID e sincronização transacional. A cobertura SQLite histórica não substitui MariaDB. Faltam teste de rollback quando save falha e instalação efêmera do update 10013. Nenhum update foi executado em Runtime.
