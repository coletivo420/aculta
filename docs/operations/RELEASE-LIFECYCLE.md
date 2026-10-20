# Ciclo de desenvolvimento, RC e Alpha

Revisado em 2026-10-20. Este documento define o fluxo vigente para todos os
módulos, submódulos e o tema. Ele substitui instruções anteriores que exigiam
`preRC`, visualização depois do RC, PR obrigatório por fase ou validação no
servidor de desenvolvimento antes de o deploy chegar a Alpha. Registros em
CHANGELOG e relatórios históricos preservam o que ocorreu sob as regras antigas.

## Estados e ambientes

| Estado | Significado | Ambiente e evidência |
| --- | --- | --- |
| **Dev** | Implementação ou revisão funcional ainda em andamento; código pode estar incompleto. | Checkout/branch de desenvolvimento. Validar automaticamente o que estiver implementado; não declarar a entrega pronta. |
| **RC** | Tudo necessário para a entrega está pronto antes do deploy: código completo, gates, testes automatizados, revisão de código e revisão visual/acessível pré-deploy aplicável. | Candidato aprovado para deploy em Alpha. Não deixar trabalho funcional, visual ou teste conhecido para “completar depois” do deploy. |
| **Alpha** | Status/etapa de deploy e ambiente do servidor de desenvolvimento. | Implantar o RC autorizado e validar a integração no servidor, configuração/runtime, rotas, logs, cache, permissões e comportamento aplicável. Registrar PASS/PARTIAL/FAIL e evidências por componente. |

Fluxo: **Dev → RC (pronto para deploy) → deploy/validação em Alpha → migração
para produção**. Se a validação Alpha encontrar defeito, voltar o componente a
Dev, corrigir, repetir as verificações afetadas e promover novo candidato. Alpha
validado é o gate para preparar a migração a produção; checks específicos de
produção, incluindo LiteSpeed/Cloudflare, são feitos depois do deploy produtivo.
Operações persistentes em produção continuam exigindo confirmação do ambiente,
inspeção de efeitos e autorização conforme os guardrails de ambiente.

### Critérios de RC

- Escopo de código da unidade concluído; nenhum item funcional obrigatório do
  roadmap permanece aberto.
- Testes automatizados, análise estática e gates aplicáveis passam; limites
  conhecidos ficam registrados sem serem apresentados como PASS.
- Revisão de código concluída.
- Revisão visual e acessível pré-deploy concluída quando houver interface;
  módulos sem interface registram que a revisão visual não se aplica.
- Dependências e rollback estão documentados. Um candidato pode ainda precisar
  de validação específica de servidor em Alpha.

Não existe estado `preRC` no fluxo vigente. `RC` é uma classificação do
candidato e não exige merge em `main` para desenvolvimento no branch autorizado.
O estado/versionamento informado no README raiz deve refletir a evidência atual;
um sufixo histórico no manifesto não transforma sozinho um componente em RC.

## Deploy e encerramento

- O responsável autoriza o deploy do candidato RC em Alpha; a classificação RC,
  sozinha, não executa nem autoriza operação persistente no Runtime.
- Antes de comandos que alteram ambiente, seguir `AGENTS.md` e
  `docs/architecture/environments.md`: confirmar host, checkout, branch,
  `git status`, Drush, URI e banco; inspecionar efeitos e recuperação.
- No Alpha, registrar versão/SHA, host/purpose, validações realizadas, resultado,
  pendências e rollback no changelog/roadmap dono. Não afirmar Runtime PASS com
  evidência apenas local.
- Após Alpha validado, preparar a migração do mesmo candidato para produção e
  executar a validação pós-deploy definida para produção. O agendamento e os
  comandos seguem os guardrails de ambiente e autorização do responsável.
- Desenvolvimento autorizado continua no branch que o responsável abriu. Fazer
  commits pequenos e atômicos; não abrir PR, trocar de branch, fazer merge ou
  criar tag sem pedido explícito. A regra prevalece sobre templates antigos que
  pedem PR próprio para cada fase.
- Atualizar a linha exata do componente em `README.md` no mesmo commit quando
  código, configuração, documentação de fase ou versão de componente mudar; rodar
  `php scripts/validate-project-status-index.php`.

## CSP: estado da decisão para o PDF.js

Em 2026-10-20 o responsável aprovou `drupal/csp` 2.2.4 em Report-Only no
candidato RC, com coleta/revisão de relatórios em Alpha e decisão de enforcement
posterior. O pacote já está em Composer; configuração exportável e módulo só
devem ser habilitados com enforcement desativado. A existência de um ensaio com
`worker-src 'self'` não equivale a uma auditoria de política estrita.

A opção upstream aprovada é `drupal/csp` 2.2.4, release estável compatível
com Drupal `^10.2 || ^11` e coberta pela política de avisos de segurança do
Drupal. O módulo suporta política Report-Only e enforced, integra a definição
das bibliotecas Drupal, e permite alteração por requisição. A implementação deve
usar APIs/documentação da versão realmente instalada. Nonce/hash podem remover
`'unsafe-inline'` e quebrar integrações; nonces precisam do placeholder do
Nonce Builder para coexistir com Dynamic Page Cache. Para PDF.js, auditar também
`worker-src` (com origem local autorizada) e as demais fontes observadas em
Report-Only. O responsável também aprovou `drupal/reporting` 2.2.0 para coleta
local de relatórios; o endpoint grava metadados técnicos no DBLog. Antes de
migrar o coletor a produção, revisar a retenção e a possibilidade de URLs/UA
conterem dados pessoais com o inventário LGPD. `report-sample` não está habilitado.

Portar CSP integralmente não é o caminho aprovado: o upstream 2.2.4 tem 69
arquivos no tag consultado, com 25 PHP de produção, 22 PHP de teste e 12 arquivos
YAML; cobre formulários, bibliotecas, eventos por requisição, nonce/hash e
handlers de relatório. O Core local 11.4.10 não contém implementação CSP no
código instalado; a change record upstream consultada está marcada Draft, então
não presumir que sua proposta esteja disponível nesta instalação. Um subscriber
próprio poderia emitir um header Report-Only, mas não substitui coleta, parsing,
API de alteração, integração de bibliotecas ou cobertura de testes. Esses números
são inventário de arquivos do upstream, não estimativa de LOC.

Fontes upstream consultadas em 2026-10-20:

- [Projeto Drupal CSP e releases](https://www.drupal.org/project/csp)
- [Alteração da política CSP](https://www.drupal.org/docs/extending-drupal/contributed-modules/contributed-module-documentation/content-security-policy/altering-a-sites-policy)
- [Projeto Reporting API Drupal](https://www.drupal.org/project/reporting)
- [Change record do Core sobre CSP (estado Draft)](https://www.drupal.org/node/3472498)
- [MDN: diretiva `worker-src`](https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/Content-Security-Policy/worker-src)

Enforcement segue pendente de decisão após análise dos relatórios Alpha.
