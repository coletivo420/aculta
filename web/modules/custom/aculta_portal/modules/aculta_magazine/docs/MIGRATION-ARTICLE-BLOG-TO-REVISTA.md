# Registro histórico: migração para `revista`

Este documento registra a migração já executada no Runtime de teste em
2026-10-21. **Não é um runbook e não contém comandos reutilizáveis.** O migrador
e seu comando Drush foram removidos após a conclusão, conforme decisão de
produto. Uma futura importação de dados `article`/`blog` exige auditoria,
planejamento e implementação específicos, com autorização para o ambiente.

## Resultado registrado

A operação autorizada converteu 16 Nodes (11 `article`, 5 `blog`) em `revista`,
preservando NID, UUID e VID original, além de 26 aliases. O inventário anterior
à operação registrou uma revisão por Node e alias, nenhum Comment hospedado e
nenhuma referência de entrada detectada. Após a migração, os bundles antigos
foram aposentados.

Uma verificação posterior identificou que 12 publicações legítimas precisavam
recuperar o estado publicado do workflow. A Entity API criou uma revisão
publicada para cada uma; os quatro Nodes de teste permaneceram rascunhos.

Depois, os quatro Nodes de teste foram removidos em operação separada,
autorizada para aquele Runtime, com inventário e backup. Nenhum Media, arquivo
ou entidade compartilhada foi removido. Os backups permanecem privados no
Runtime e não pertencem ao repositório.

## Limites deste registro

Os números e resultados acima descrevem somente o Runtime e a execução de
2026-10-21. Eles não autorizam alterações em outra instalação. Não há mais
comando ou serviço neste módulo que converta bundles antigos; uma nova migração
exige desenho e revisão próprios.
