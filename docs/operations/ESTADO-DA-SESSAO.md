# ADR-054/055 — RN-04 publicado e RN-05 parcial — 2026-11-11

- **Objetivo:** concluir a renomeação funcional `purpose` → `site_key` e revalidar o Runtime.
- **Estado:** classes, serviços, rotas, opções, State, sessão e a condição de bloco (`aculta_domain_site`/`site_key`) migrados sem aliases internos; 23 configs de bloco migradas no Sync e no install. PHPStan 0; unit 3287 PASS; Portal 1234 PASS; boundary 7888 PASS; browser index 151; admin routes e aliases PASS; Firefox ESR 70/70 nos dez domínios após `drush cr`.
- **Decisões e alternativas descartadas:** aceitar as versões do remoto nos conflitos do wiki e reaplicar a renomeação; manter o guard de relations com `@phpstan-ignore`; mesclar as entradas novas do baseline em vez de regenerá-lo.
- **Cuidados:** o import parcial autorizado das 22 configs de bloco foi executado após snapshot; o site responde 200 e o smoke 70/70 foi repetido. O login autenticado segue bloqueado (DT-S01); rotas públicas são `private/no-cache` e `UNCACHEABLE`.
- **Commits e arquivos:** `0c34d22cc`, `164608d1d`, `8e4816826` publicados; Portal, gates, baseline, inventário de contratos e ADRs.
- **Verificações:** PHPStan 0, unit 3287, Portal 1234, boundary 7888, browser index 151, admin routes, aliases, root policy e status index PASS; smoke Firefox 70/70 pós-import.
- **Pendências e bloqueios:** Kernel/Functional, sessão autenticada (DT-S01), cache-hit e aceite humano.
- **Próximo passo:** importar a configuração autorizada, rodar Kernel/Functional e concluir RN-05.

# PHPStan — rodada 18 aculta_mailer — 2026-11-11

- **Objetivo:** zerar a dívida PHPStan de todo o módulo aculta_mailer.
- **Estado:** src, .install e testes medidos em zero erros; baseline sem entradas do módulo. SMTP usa a fábrica pública Symfony mantendo TLS configurado.
- **Decisões e alternativas descartadas:** narrowing de entidades concretas; helper valida FieldStorageDefinition; nenhum ignore PHPStan. Removido teste tautológico do TTL.
- **Cuidados:** credenciais SMTP continuam resolvidas pelo SecretProvider; porta 465 usa smtps e verify_peer é encaminhado pela Dsn. Nenhum envio externo.
- **Commits e arquivos:** base `0210e5c86`; código, testes e serviço de aculta_mailer, `phpstan-baseline.neon`, relatório de dívida, TODO/status e este handoff. Hash final consta no commit da rodada.
- **Verificações:** PHPStan módulo/global `[OK] No errors`; Unit 166/348 PASS; PHP lint 29 arquivos, YAML serviços e status index PASS; baseline global 3564. Unit global 3287/17594 com 4 falhas fora do escopo (Forum, Game, Index) e 3 deprecações. Token budget FAIL por 7 violações globais preexistentes.
- **Pendências e bloqueios:** migração de consumidores `DomainPurposeManager` para `DomainSiteManager` ausente de `origin/dev`; browser/SMTP externo seguem como próximos passos do componente.
- **Próximo passo:** retomar rodada PHPStan 17 da aculta_wiki após a migração chegar a origin/dev.

# Dependências contrib — atualização com compatibilidade — 2026-11-11

- **Objetivo:** atualizar os oito projetos Composer solicitados dentro das compatibilidades do Drupal Core atual.
- **Estado:** field_group 4.1.0-beta1, freelinking 5.0.0-alpha3, media_duplicates 3.0.0-alpha1, photoswipe 5.0.14, private_message 4.0.0-alpha5, social_api 4.1.0-beta1 e social_auth 4.2.0-beta2 resolvidos no lockfile. CKEditor Plugin Pack permanece 1.5.6: 2.0.0-beta1 exige Core 12, incompatível com o Core 11.5.0-beta1 exigido pelo projeto.
- **Decisões e alternativas descartadas:** constraints permitem evolução sem travar patch/minor; pré-releases explicitamente habilitados quando necessários. Não atualizar Core para satisfazer CKEditor.
- **Cuidados:** composer update também atualizou dependências transitivas Symfony/Doctrine e reaplicou patch de social_api. Nenhuma atualização de banco ou Runtime.
- **Commits e arquivos:** sem commit; `composer.json` e `composer.lock`.
- **Verificações:** `composer validate --no-check-publish` válido com avisos de constraints exatas preexistentes. Composer update dos sete pacotes bem-sucedido. Gate Runtime e testes funcionais não executados; sem ordem para escrita no Runtime. Composer apontou advisories de segurança em um pacote, sem detalhamento nesta rodada.
- **Pendências e bloqueios:** CKEditor Plugin Pack 2.x aguarda atualização do Core para 12. Workspace possui alterações preexistentes extensas e branch divergente do origin; publicação não realizada para evitar misturar ou reescrever trabalho alheio.
- **Próximo passo:** revisar e integrar este diff em branch sincronizada; repetir CKEditor quando Core 12 passar a ser requisito do projeto.
