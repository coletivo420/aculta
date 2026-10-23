> **SUPERSEDED (2026-10-23).** Este plano de migração para `aculta_associado_id` está cancelado. Por decisão do responsável, `aculta_id_cpf` é o módulo oficial de CPF/CNPJ; `aculta_associado_id` será descontinuado e removido após verificação de Runtime (ver `docs/portal/CPF-CONSOLIDACAO-E-CHECKOUT.md`, seção 4). Os dados de associação pertencem ao domínio de usuários/perfis. O texto abaixo é histórico e não representa a arquitetura vigente.

# ACULTA Associado ID — escopo e migração

## Identidade e finalidade

O submódulo `aculta_associado_id` (`web/modules/custom/aculta_portal/modules/aculta_associado_id`)
orquestra os dados identificadores e cadastrais dos associados necessários para
apoiar a elaboração de atas, relatórios e o cumprimento das obrigações legais da
associação. A finalidade define quais dados podem ser tratados; nenhum campo ou
uso jurídico deve ser presumido sem decisão do responsável.

A arquitetura separa: `aculta_associado_id` contém serviços de domínio e
persistência específica; `aculta_portal` coordena formulários, rotas, access,
cache e a integração; ACULTA420 apresenta apenas o contrato neutro. Drupal
Profile/User continuam fontes de verdade, sem armazenamento paralelo.

O escopo efetivamente implementado hoje é CPF/CNPJ: validação, normalização,
formatação, cifra e fingerprint no perfil participante. Atas, relatórios e a
orquestração dos demais dados ainda não estão implementados.

## Compatibilidade da substituição

O nome anterior da extensão era `aculta_id_cpf` (o diretório presente no Git).
A extensão antiga está preservada como compatibilidade transitória; o Portal
prefere o serviço novo e recorre ao antigo durante a migração. O Runtime local
está com `aculta_id_cpf` habilitado e schema `10001`.

Identificadores criptográficos mantidos deliberadamente:

- Key ID `aculta_id_cpf_fingerprint` e variável
  `ACULTA_ID_CPF_FINGERPRINT_KEY`;
- separador HMAC `aculta_id_cpf:v1`;
- chave de cifra `aculta_document_encryption_key` e variável
  `ACULTA_DOCUMENT_ENCRYPTION_KEY`.

Eles não são nomes da extensão; renomeá-los mudaria a capacidade de ler os
valores cifrados ou comparar os índices já gravados. Não foram alterados.

### Plano de migração — desinstalação e reinstalação controlada

Nenhuma operação no banco foi executada. Antes da mudança no Runtime, ensaiar em
cópia isolada com estas verificações:

1. Backup/cópia íntegra e inventário de config, fields e schema sem exibir PII.
2. Disponibilidade das mesmas chaves de cifra e HMAC; fixtures usam somente
   valores sintéticos e chave de teste.
3. Desinstalação antiga e instalação nova no clone, observando a remoção e
   recriação de configuração/campos. Não presumir preservação automática.
4. Legibilidade de documentos cifrados e igualdade de fingerprints após
   reinstalar, incluindo cenários sem documento, duplicidade e rollback.
5. Comparação de estado antes/depois e gates registrados sem dados pessoais.
6. Aprovação posterior para etapa operacional separada com backup, comandos,
   janela e rollback.

A troca ainda não tem evidência de ensaio de preservação em cópia. Logo, Runtime
e produção permanecem sem migração e a extensão antiga não deve ser removida.
**RUNTIME STATUS: DEFERRED.**

## Evidência histórica

Os resultados abaixo referem-se à implementação original `aculta_id_cpf` e à
rodada de 2026-10-12; não validam a instalação/migração do novo machine name.

| Verificação histórica | Resultado |
| --- | --- |
| Runner PHP de domínio | PASS: 248 verificações |
| PHPUnit Unit | PASS: 34 testes, 63 asserções |
| Lint PHP e gate Drupal 11+ | PASS: 583 checks |
| Habilitação/cache local | PASS; não prova migração |
| Servidor remoto de testes | DEFERRED |

Nenhum CPF/CNPJ real foi usado nos ensaios documentados. Consulte o changelog do
submódulo para o histórico detalhado da implementação original.

## Referências

- [README do submódulo](../../web/modules/custom/aculta_portal/modules/aculta_associado_id/README.md)
- [Changelog do Portal](../../web/modules/custom/aculta_portal/CHANGELOG.md)
- [Manual Receita/Serpro do DV de CNPJ](https://www.gov.br/receitafederal/pt-br/centrais-de-conteudo/publicacoes/documentos-tecnicos/cnpj/manual-dv-cnpj.pdf)
