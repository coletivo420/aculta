# Regras específicas — consulta sob demanda

Estas regras foram realocadas do arquivo histórico de instruções; aplique somente no escopo correspondente. O `AGENTS.md` raiz continua obrigatório.

## Separação de Pessoas e Configurações > Pessoas (decisão do responsável, 2026-10-17)

- `/painel-administrativo/pessoas`: administração de usuários individuais (lista, cadastro, edição, perfis e OAuth/Social auth).
- `/painel-administrativo/configuracoes/pessoas`: ferramentas de administração de contas: papéis (`papeis`), permissões
  por papel (`permissoes`), configuração de papel (`configuracoes-de-papel`), campos de perfil (`campos-de-perfil`),
  desbloqueio de sobrecarga (`desbloqueio-sobrecarga`), opções da conta (`contas`), CAPTCHA, acordos e controle de sobrecarga.
- Só a árvore de configuração e as ferramentas listadas são traduzidas (`AdministrativeRouteSubscriber`). Caminhos antigos
  respondem 404, sem redirecionamento.

## Aceite único (regra permanente)

- Existe **um único formulário de aceite** (`AcultaUserAgreementAcceptForm`), para Termos de Uso e Política de Privacidade.
- **Não criar novos formulários de aceite**, checkbox de aceite fora do formulário único nem tipo de aceite novo no ledger (`aculta_user_agreement`). O ledger grava só `aculta_user_agreement_terms`.
- Toda função do portal que trate dado pessoal ou dependa de consentimento precisa estar descrita na Política de Privacidade e nos Termos de Uso publicados. O aceite único cobre esse texto.
- Função que exige escolha separada do titular não entra no portal sem decisão do responsável sobre a base legal.
- Regra, pendências e exceções: `docs/portal/ACEITE-UNICO.md`. Gate: `php scripts/validate-single-acceptance.php`.

## Links de acesso único: 24 horas (regra permanente)

- Todo link de acesso único do projeto (confirmação, troca de e-mail, recuperação de senha, acesso de cadastro) vale **24 horas**, em todos os módulos. Nenhum prazo maior, nenhum `isExpired()` com retorno fixo.
- Pedido novo de link substitui o anterior: o conteúdo guardado do pedido antigo é cancelado, e a cifra é gerada de novo.
- Reenvio manual não reaproveita link de uso único: pede ao dono do link um pedido novo. A única exceção é a retentativa e o failover automáticos da mesma mensagem.
- Regra, inventário e exceção: `docs/portal/LINKS-DE-USO-UNICO.md`. Gate: `php scripts/validate-single-use-links.php`.
