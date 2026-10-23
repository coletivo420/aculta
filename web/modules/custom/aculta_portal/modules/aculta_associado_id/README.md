> **DESCONTINUADO (2026-10-23).** Não é mais o módulo de identidade fiscal. O módulo oficial é `aculta_id_cpf`. Este diretório permanece somente até a verificação de Runtime exigida em `docs/portal/CPF-CONSOLIDACAO-E-CHECKOUT.md`, seção 4. Não habilitar, não instalar e não referenciar em código novo. O conteúdo abaixo é histórico.

# ACULTA Associado ID

Submódulo opcional do `aculta_portal`, baseline Drupal **11.3+**. Versão atual:
**0.3.0-dev.1**. É o domínio responsável por orquestrar dados identificadores e
cadastrais dos associados, para apoiar a elaboração de atas, relatórios e o
cumprimento das obrigações legais da associação. O escopo futuro deve manter os
dados na fonte de verdade Drupal apropriada e aplicar minimização, acesso,
retenção e proteção compatíveis com cada finalidade.

**Escopo implementado nesta versão:** validação, normalização, formatação,
cifra, fingerprint e persistência do CPF/CNPJ associados ao perfil participante.
A orquestração de outros dados dos associados e a geração de atas/relatórios
não estão implementadas. Não presumir campos legais, dados ou finalidades além
dos aprovados pelo responsável.

O `aculta_portal` mantém rotas, formulários, access, cache e a integração neutra;
o `aculta_associado_id` implementa serviços de domínio/dados; ACULTA420 mantém
somente apresentação. Não criar banco paralelo nem mover regra funcional para o
tema.

**RUNTIME STATUS: DEFERRED** — módulo novo ainda não foi ativado nem migrado no
Runtime. A extensão legada `aculta_id_cpf` permanece intacta enquanto se prepara
e valida a substituição controlada em cópia isolada.

## Capacidades atuais

- Validação PHP de CPF e CNPJ numérico/alfanumérico, normalização estrita,
  formatação e ocultação do documento.
- Serviços DI `aculta_associado_id.*`, incluindo `account_document`.
- Cifra libsodium e índice HMAC do documento no perfil participante.
- Sem dependência Composer nova. O submódulo é opcional e não é habilitado por
  `config/sync/core.extension.yml`.

### Compatibilidade criptográfica

Embora o machine name novo seja `aculta_associado_id`, os identificadores abaixo
permanecem estáveis para preservar dados existentes:

- Drupal Key ID: `aculta_id_cpf_fingerprint`.
- Variável de ambiente: `ACULTA_ID_CPF_FINGERPRINT_KEY`.
- Separador HMAC: `aculta_id_cpf:v1`.
- Chave de cifra: `aculta_document_encryption_key` / `ACULTA_DOCUMENT_ENCRYPTION_KEY`.

Não renomear, rotacionar ou substituir essas chaves/protocolos como parte da
mudança de machine name. Troca invalida a leitura ou a comparação de dados
existentes. Segredos permanecem fora do Git e da configuração exportada.

## Contrato com o Portal

O Portal injeta `AccountDocumentInterface`, prefere
`aculta_associado_id.account_document` e aceita temporariamente
`aculta_id_cpf.account_document` durante a migração. O módulo não implementa
campos, widgets, máscaras JavaScript, formulários de conta nem login por CPF.

## Migração do machine name

A mudança não é uma renomeação de pasta inofensiva: Drupal registra a extensão e
o schema pelo machine name, e remove configuração pertencente a uma extensão na
desinstalação. A extensão antiga está ativa no Runtime com schema `10001`.
Nenhum comando de desinstalação, instalação, update ou rebuild foi executado.

Procedimento obrigatório antes de migrar o Runtime:

1. Criar uma cópia isolada e íntegra do banco/arquivos do Runtime conforme o
   procedimento operacional aprovado; nunca usar dados reais em testes criados.
2. Confirmar a disponibilidade das mesmas chaves de cifra e HMAC fora do banco.
   Criar uma chave sintética apenas para fixtures se não houver segredo de teste.
3. Inventariar configuração, campos, schema `10001`, perfis de teste e estado dos
   documentos sem imprimir valores pessoais nem segredos.
4. Ensaiar desinstalação do módulo antigo e instalação do novo na cópia. Verificar
   se campos/configuração necessários foram removidos, retidos ou recriados; não
   assumir que uninstall/reinstall preserva dados.
5. Confirmar que registros cifrados continuam legíveis e os fingerprints
   recalculados são idênticos usando as chaves e o separador HMAC estáveis;
   testar também perfil sem documento, duplicidade e falha/rollback.
6. Executar os gates e validações funcionais no clone, comparar os campos/config
   antes e depois e documentar evidências sem dados pessoais.
7. Só depois de aprovação do responsável, preparar PR/etapa operacional separada
   com backup, janela, comandos exatos e rollback. Não migrar produção como parte
   desta mudança de código.

**Bloqueio atual:** não há ainda evidência de ensaio em cópia demonstrando que a
sequência preserva os dados de Profile e as configurações. Portanto, o módulo
novo não deve ser ativado no Runtime e o antigo não deve ser removido.

## Testes e gates

```sh
php web/modules/custom/aculta_portal/modules/aculta_associado_id/tests/run.php
vendor/bin/phpunit -c web/core/phpunit.xml.dist web/modules/custom/aculta_portal/modules/aculta_associado_id/tests/src/Unit
php scripts/validate-aculta-portal-drupal11.php
```

A evidência histórica da implementação original está em
[ACULTA-ASSOCIADO-ID.md](../../../../../../docs/modules/ACULTA-ASSOCIADO-ID.md).

## Próximas entregas

1. Validar a migração em cópia isolada conforme procedimento acima.
2. Remover a extensão de compatibilidade antiga após migração aprovada.
3. Levantar com a associação quais dados de associados são necessários por
   finalidade antes de ampliar coleta ou persistência.
4. Projetar, em fases aprovadas, dados/documentos para atas e relatórios com
   minimização, permissões, trilha de auditoria, retenção e exportação segura.

Não adicionar tabela paralela ou resolvedor de login sem registrar a lacuna nas
APIs Core/contrib e obter decisão arquitetural.
