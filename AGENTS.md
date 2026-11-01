# ACULTA — instruções para agentes

PROJECT-STATUS-INDEX-GUARD: 2026-10-21

Este arquivo define as regras gerais do repositório. Siga também o `AGENTS.md` mais próximo dos arquivos alterados. Em caso de conflito, prevalecem a instrução explícita mais recente do responsável e as regras de segurança abaixo. Consulte a documentação referenciada para detalhes; não replique aqui históricos ou planos.

## ESTADO DA SESSÃO

- Antes de trocar de sessão, encerrar uma fase ou transferir o trabalho para outro agente/ferramenta, registre um handoff atualizado em [`docs/operations/ESTADO-DA-SESSAO.md`](docs/operations/ESTADO-DA-SESSAO.md). O registro deve resumir objetivo e contexto, o que foi feito, arquivos alterados, verificações executadas e seus resultados, pendências/bloqueios e próximos passos.
- Inclua no handoff referências concretas para leitura (caminhos de arquivos e, quando útil, seções/linhas) que permitam à próxima sessão retomar do ponto correto. Não registre segredos ou dados sensíveis.
- Ao concluir ou transferir a tarefa, informe ao responsável o caminho desse handoff para que sirva de referência às próximas sessões. Atualize o registro existente em vez de criar históricos paralelos; preserve o contexto anterior quando ainda for necessário para retomar o trabalho.

## Git: trabalhar e publicar em `dev`

- Todas as alterações autorizadas devem ser feitas, commitadas e enviadas diretamente para `origin/dev`. Não crie branches, worktrees ou PRs, nem publique em outra branch, salvo instrução explícita posterior do responsável.
- Antes de editar, confira `git status --short --branch` e preserve alterações de outras pessoas. Trabalhe sobre `dev`; se o checkout estiver em outra branch ou houver trabalho conflitante, não o sobrescreva: alinhe o checkout com segurança antes de prosseguir.
- Faça commits pequenos e claros, contendo somente arquivos da sua tarefa. Depois, envie-os para `origin/dev` por fast-forward. Nunca use force push, reset destrutivo ou descarte alterações alheias.
- Se o remoto avançar ou o push for recusado, atualize e integre as mudanças sem sobrescrever trabalho; revise o resultado e tente novamente. Ao concluir, informe os commits enviados, validações executadas e qualquer bloqueio. Não diga que publicou se o push não foi confirmado.

## Escopo e segurança

- Altere somente o necessário para a tarefa. Preserve contratos existentes, trabalho paralelo e dados. Não faça merge, deploy ou alterações de produção sem autorização explícita.
- Drupal fica em `web/`; módulos customizados em `web/modules/custom/`; tema em `web/themes/custom/aculta420/`. Siga os padrões Drupal/PHP e os contratos dos módulos envolvidos.
- Nunca crie bancos de dados. Os bancos existentes são `aculta_runtime` e `aculta_simpletest`; o host `toca` não deve ser alterado. Não execute instalação, atualização/importação de configuração, migrações ou outras operações persistentes no Runtime sem autorização explícita.
- Nunca execute `pm:uninstall`, `DROP` ou outra ação destrutiva sem ordem explícita. Antes de qualquer operação de Runtime autorizada, confirme ambiente, URI e banco, leia `docs/architecture/environments.md` e verifique impacto e reversão. Prefira inspeções somente leitura.
- Não invente contratos ou altere ownership de rotas, entidades, permissões, workflow ou integrações sem verificar a documentação e o código atuais.

## Arquitetura e validação

- Suítes atuais: Marley `account:/marley`; Ganjalab `courses:/ganjalab`; WE SATIVA `wiki:/wesativa`; Green Stash `shop:/greenstash`; Culturas `eventos:/culturas`. `aculta_studio` é infraestrutura compartilhada, não uma raiz pública. `/studio` e `/studio/*` devem responder 404, sem alias ou redirect.
- Rotas administrativas e de integração existem somente no domínio principal e devem responder 404 em outros domínios. A experiência de organizadores em `/culturas` permanece separada.
- Preserve os módulos responsáveis e valide acesso no servidor, inclusive em escritas. Links entre domínios devem seguir os serviços e políticas de propósito já existentes.
- Antes de concluir, execute as verificações relevantes à mudança e registre os resultados. Não alegue testes ou validações que não executou.

## Referências

- Ambiente e procedimentos operacionais: `docs/architecture/environments.md` e `docs/operations/AGENTES-PROCEDIMENTO.md`.
- Contratos de domínio e rotas: ADR-038, `docs/portal/ADMIN-DOMAIN-POLICY.md` e documentação de arquitetura pertinente.
- Consulte README, ROADMAP, testes e instruções locais do módulo afetado para regras específicas.
