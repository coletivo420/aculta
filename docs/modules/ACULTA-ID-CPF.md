# ACULTA ID CPF — serviços e decisões de integração

Solicitação do responsável em 2026-10-12: iniciar `aculta_id_cpf` como
submódulo do `aculta_portal`. Decisão atual: o submódulo fornece serviços de
domínio; campos PF/PJ e máscara serão implementados manualmente no Portal.

## Escopo e arquitetura

- Caminho: `web/modules/custom/aculta_portal/modules/aculta_id_cpf`.
- Versão atual de desenvolvimento: `0.2.0-dev.2`; Drupal 11.3+ e PHP 8.4.
- O serviço valida CPF e CNPJ numérico/alfanumérico, normaliza entradas com
  pontuação completa ou sem pontuação, preserva zeros iniciais e rejeita
  caracteres inválidos. Usa strings para não perder zeros/letras.
- O serviço `aculta_id_cpf.fingerprint` gera HMAC-SHA-256 determinístico, com
  separação por versão/tipo, usando chave do Drupal Key (`aculta_id_cpf_fingerprint`)
  de pelo menos 32 bytes. A chave não fica em Configuration Sync/Git. O resultado
  continua sendo dado pessoal pseudonimizado e requer proteção.
- Persistência: `field_documento` (cifrado) e `field_documento_indice`
  (fingerprint) no perfil participante, privados do perfil.
- Unicidade entre contas: checagem antes da gravação, sem garantia transacional
  (campo de entidade não tem índice único). Ver README, "Limite conhecido".
- Não implementa autenticação por CPF nem integração Webform.

O Portal já integra `login_emailusername`, Social Auth, confirmação de e-mail e
`PortalFormCallbacks::validateLoginAuthentication`, que mantém resposta genérica
para contas bloqueadas. User/Profile permanecem fontes de verdade de conta e
perfil; Key/environment guarda segredos. Não introduzir armazenamento ou
resolvedor de autenticação paralelo sem documentar a lacuna em Core/contrib.
Validade dos dígitos verificadores não comprova identidade ou situação cadastral.

## Segurança para as próximas fases

Antes de coletar documentos, implementar e testar proteção contra cache de
formulário/rebuild, armazenamento em claro, rascunhos, exportações, logs e
handlers externos; definir retenção, autorização de leitura, reautenticação e
unicidade transacional concorrente. CPF/CNPJ completos não podem ficar expostos
em entidades públicas. Login por CPF deve preservar flood, CAPTCHA, confirmação
de e-mail, Social Auth e respostas contra enumeração, usando contadores
compartilhados por uid.

## Homologação já registrada

A rodada foi executada em checkout local que simula o servidor de testes, não
em `*.aculta.toca.net.br`. Ambiente: PHP CLI/FPM 8.4.26, Composer 2.8.8,
Drupal 11.4.8, MariaDB 11.8.6, PHPUnit 11.5.57, Node 22.23.3, Playwright
1.64.0 e Chromium 154.0.8037.92. O navegador usou alias local em `127.0.0.1`.

| Verificação registrada | Resultado |
| --- | --- |
| Composer validate/audit; lock | PASS; sem `composer update` |
| Runner PHP de domínio | PASS: 248 verificações |
| PHPUnit Unit | PASS: 34 testes, 63 asserções |
| Lint PHP e gate Drupal 11+ | PASS: 583 checks |
| Drush enable e cache rebuild local | PASS; sem mudança em config |
| Gates CAPTCHA e Conta | PASS: 15 e 1233 checks |
| Kernel em MariaDB isolado | PASS histórico: 5 testes, 8 asserções dos elementos removidos |
| Chromium local | PASS histórico para máscara, clipboard, AJAX e seleção; máscara removida |
| Gate Portal commerce/security | FAIL em pendência DT-O11: `Fake runtime credentials were not persisted` |
| Servidor remoto de testes | DEFERRED: rodada local não comprova execução remota |

Os testes Kernel e Chromium acima são evidência histórica da implementação de
campos/máscara, removida por decisão de escopo. Não representam funcionalidades
atuais do módulo. Nenhum documento real foi usado ou gravado. O gate DT-O11
permanece pendente separado; nenhum segredo foi exposto ou alterado. Status:
**RUNTIME STATUS: DEFERRED**, sem release ou tag.

### Validação adicional do HMAC (0.1.0-dev.3)

No checkout atual, PHP 8.3.6 foi a única CLI disponível. Runner de domínio
(248 checks), smoke isolado do fingerprint (canonicalização, vetor HMAC,
documento inválido e chave curta), lint PHP, YAML parse, `git diff --check` e
gate Drupal 11+ (583 checks) passaram. PHPUnit não foi executado porque este
checkout não contém `vendor/`; PHP 8.4, Core/Key instalados e runtime Drupal
precisam validar a nova integração antes de release. Essa evidência não altera
os resultados históricos PHP 8.4.26 acima.

## Próximas etapas

1. Implementar manualmente no `aculta_portal` os campos PF/PJ e máscara usando
   os serviços deste submódulo.
2. Integrar o fingerprint à fonte de verdade Profile/User e definir
   armazenamento protegido, autorização, retenção e unicidade concorrente;
   testar concorrência e rollback em MariaDB de teste. Rotação da chave exige
   recálculo controlado dos fingerprints existentes.
3. Integrar login por CPF ao pipeline atual e testar flood, CAPTCHA, confirmação,
   Social Auth e enumeração.
4. Avaliar Webform após definir proteção de rascunhos, exportações, notificações
   e handlers.

## Referências

- [Manual Receita/Serpro do DV de CNPJ](https://www.gov.br/receitafederal/pt-br/centrais-de-conteudo/publicacoes/documentos-tecnicos/cnpj/manual-dv-cnpj.pdf).
- Pesquisa: bissolli/validador-cpf-cnpj, geekcom/validator-docs e theuves/cpf.
  Nenhum arquivo foi copiado; a implementação é própria.

