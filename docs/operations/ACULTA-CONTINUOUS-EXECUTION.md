# Execução contínua ACULTA — PR #298

**Branch:** `chore/aculta-m0-integridade`  
**HEAD retomado:** `d55c4301e7ffde6022fd6c7b3651df1de927e619`  
**HEAD estável:** `86cdbb0fe`
**Estado:** PARTIAL — P0 H5P concluído; auditoria M-0 e frentes M-1 a M-5 em andamento.
**Runtime:** `toca`, Drupal 11.4.10, PHP 8.4.27, URI padrão `http://default`, banco `aculta_runtime`. Consultas somente leitura; nenhum write foi executado.

## Ponto de retomada

1. Prosseguir PHPCS pelos arquivos tocados restantes; formatter, widget e teste focal H5P agora passam sem renomear classes H5P persistidas.
2. Prosseguir M-1: auditar `aculta_user`, Portal e CEP; testar consentimento, identidade pública e integração de checkout em banco isolado.
3. Executar os gates administrativos e de configuração com bootstrap e URIs explícitas.
4. Completar inventário Runtime read-only, Config Sync e auditoria individual dos demais componentes.

## Evidência desta retomada

- `6daa7542f test(h5p): repair course access kernel contract`: formatter de acesso e test harness usam o plugin Group real. PHPCS Drupal/DrupalPractice, PHP lint e diff check passam nos dois arquivos H5P alterados.
- Kernel isolado H5P: 3 testes/134 assertions PASS; 41 deprecações Core/contrib. Foram validados Group membership, educador, estudante, usuário sem vínculo, referência de recurso e revogação; nenhum serviço de produto fictício foi adicionado.
- PHPCS Drupal/DrupalPractice passou nos três arquivos H5P alterados após corrigir o widget e documentar duas exceções pontuais para os nomes de classe `aculta_H5P*`, preservados por contrato de plugin/cache.
- Gate Domain administrativo: `php vendor/drush/drush/drush.php --uri=http://aculta.toca.net.br php:script scripts/validate-admin-domain-policy.php` PASS (3498 checks). O bootstrap usou o checkout compartilhado `main`; isso valida o Runtime/código atual, não o diff da PR.
- Commits do agente 2 revisados e cherry-picked: `6483dddbe` Deployer profile scope, `81b03581a` teste Kernel de filtro editorial e `85db52888` reconciliação documental dos módulos sob seu ownership.
- Deployer suite: PASS após tornar neutra a descrição de menu do Portal que acoplava nome de módulo opcional; builds de ensaio continuaram em `/tmp`, e `DEP-0003` permanece bloqueador operacional de páginas públicas do host de teste.
- Editor Kernel, com MariaDB dedicado e código da branch: 7 testes/90 assertions PASS; 3 deprecações. O teste prova que `revista_editorial` aplica `filter_html` e remove `<script>`/atributo de evento.
- Links: status YAML atualizado para `0.4.0-dev.32`, manifesto e changelog alinhados; índice de 41 componentes regenerado e PASS.
- CPF/CNPJ (`aculta_id_cpf`): `AccountDocumentStore` agora possui restrição SQL única em `profile__field_documento_indice`; update `10002` verifica duplicatas com contagem agregada e falha sem imprimir fingerprints; o hook de instalação aplica o contrato em instalações novas. Unit 47/107, runner de domínio 248 checks e Kernel MariaDB 2/5 PASS. `updatedb` não foi executado no Runtime.

## Status por frente

| Frente | Estado | Próxima ação |
|---|---|---|
| P0 PHPCS | Parcial | Arquivos H5P alterados passam; widget legado conserva violações preexistentes e precisa de correção focal após analisar contratos |
| P0 H5P | PASS focal | Kernel isolado 3/134; suíte integral do módulo não repetida nesta etapa |
| P0 admin Domain | PASS no checkout compartilhado | 3498 checks; repetir/validar contra a branch quando runner bootstrapped estiver disponível |
| M-0 inventário individual | Parcial | Inventário 41 itens preservado; faltam Runtime/Config Sync completo e evidência por componente |
| M-1 a M-5 | Em execução | Auditar User, Portal e CEP; a garantia fiscal de unicidade está em código, aguardando validação isolada do update e autorização para Runtime |

## Restrições / deferred

- Nenhuma escrita no Runtime; sem migração, importação de config ou processamento de filas.
- Operações financeiras externas, deploy, merge e remoção de adaptadores permanecem fora da autorização.
- Mudanças que alterem ownership de dados, membership persistida ou política de produto serão isoladas e submetidas ao responsável quando houver decisão necessária.

## Atividade do agente 2

- Ownership concedido exclusivamente: Melhor Envio, Deployer, Patcher, Sitemap, QR Code, Links, Activity e Editor. Commits incorporados: `6483dddbe`, `81b03581a`, `85db52888`.
- Agente não operou no Runtime. Os diretórios devem permanecer sob esse ownership em novas correções até coordenação explícita.
