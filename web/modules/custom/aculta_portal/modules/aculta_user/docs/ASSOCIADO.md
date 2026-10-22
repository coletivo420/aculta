# Associado: situação, pedido de adesão e concessão pelo administrador

Decisão do responsável (2026-10-21): associado **não é papel**. É uma situação da conta, com data de adesão e status.
Há pedido de adesão, feito pela própria pessoa. Só o administrador concede, suspende, desliga ou recusa.

## Situações

| Situação | Significado | Como chega |
| --- | --- | --- |
| (sem situação) | Nunca pediu adesão | Padrão |
| `pendente` | Pedido em análise | A pessoa pede em `/associado` |
| `convidado` | Convite do administrador, aguardando aceite | Administrador convida; a pessoa aceita ou recusa em `/associado` |
| `ativo` | Associado | Administrador adiciona (`ativo`, sem convite) ou a pessoa aceita o convite |
| `suspenso` | Adesão suspensa | Administrador define `suspenso` |
| `desligado` | Encerrada | Administrador define `desligado`; a pessoa pode pedir de novo |

- **Data de adesão:** gravada na primeira ativação e não muda depois (suspensão e volta mantêm a data original).
- **Recusa:** administrador recusa um pedido pendente; a linha é removida. A pessoa recusa um convite; a linha é removida.
- **Convite:** só o administrador convida. Quem já é ativo ou suspenso não recebe convite. O convite não concede nada antes do aceite; o aceite grava a data de adesão e garante o perfil.
- **Autor e motivo:** cada alteração grava quem alterou (`concedido_por`) e o motivo (`motivo`). Não há histórico de
  alterações além da última; o log do Drupal registra cada decisão (`aculta_user`).

## Quem pode o quê

- **Pessoa com e-mail confirmado:** pedir adesão em `/associado`. O pedido não concede nenhum acesso.
- **Administrador** (`administer aculta associados`): adicionar, convidar, suspender, desligar e recusar pedido, em
  `/painel-administrativo/associados/{user}/situacao`; ver o relatório em `/painel-administrativo/associados/relatorio`.
- **Exportação com RG completo:** somente `export aculta associados` (administrador).
- Nenhum papel concede a situação. Nenhuma checagem de acesso usa o nome "associado" como papel.

## Dados

- A entidade `aculta_associado` guarda a situação. Ela não substitui o perfil.
- Ativar garante o perfil de tipo `associado` (dados como RG e órgão emissor ficam nele, cifrados pelo módulo de documentos).
- O tipo de perfil `associado` não tem restrição por papel (`roles` vazio), porque o papel saiu.

## Migração

- `aculta_user_update_10005`: adiciona situação, data de adesão, motivo e alteração. Contas que já tinham a marca
  passam a "ativo", com a data de adesão igual à data da marca.
- Configuração: papel `associado` removido, com suas ações; cadeia de herança sem `associado` (`lms_teacher` herda de
  `email_confirmed`). O gate `scripts/validate-role-hierarchy.php` confere a cadeia decidida.

## Verificação (servidor de testes, 2026-10-21)

- Pedido pela conta de teste: situação `pendente`.
- Administrador ativa: situação `ativo`, data de adesão gravada, perfil `associado` garantido.
- Pessoa vê "Você é associado desde DATA".
- Administrador suspende: situação `suspenso`; pessoa vê "suspensa"; não é mais associada.
- Conta comum recebe 403 na tela administrativa; a pessoa não consegue se conceder situação.
- Páginas (`/associado`, `/atividade/conquistas`, `/configuracoes/privacidade`, relatório e situação) respondem 200 com
  os temas aculta420 e Olivero.
- Teste de Kernel dedicado não foi executado: o `aculta_user` depende de serviços do `aculta_portal` (chaves, CAPTCHA,
  OAuth, Commerce), e a montagem do container de teste não fecha com módulos mínimos. Pendência registrada em `PENDENCIAS.md`.

### Categorias de usuário

Associado e estudante são categorias representadas por tipos de perfil independentes. Uma mesma conta pode manter os dois perfis; a confirmação real do e-mail ou a matrícula em curso ou turma garante o perfil `estudante`, e a situação ativa de associado garante o perfil `associado`. A atualização `aculta_user_update_10011()` migra contas do antigo papel `estudante` para o perfil e remove o papel legado.
