## 0.3.0-dev.10 — subaba CEP na aba Pessoas do painel ACULTA — 2026-10-20

- Subaba **CEP** em `/painel-administrativo/aculta/cep`, dentro da aba Pessoas.

## 0.3.0-dev.9 — consulta de CEP no painel ACULTA — 2026-10-20

- Configuração em `/painel-administrativo/aculta/cep`, com item na aba ACULTA.
- Caminhos antigos `/painel-administrativo/configuracoes/aculta/...` respondem 404, sem redirecionamento.

## 0.3.0-dev.8 — ordem das fontes sem classe de design — 2026-10-19

- Tabela de fontes com `data-portal-ui="cep-sources"` e coluna de ordem com `role-weight` (marcador de arrastar do Core), no lugar de `cep-source-weight`. Sem mudança de comportamento.
- RUNTIME STATUS: DEFERRED.

## 0.3.0-dev.7 — base de CEP versionada no repositório — 2026-10-19

- A base do DNE em shards (872 arquivos, 1.388.509 CEPs) e o `manifest.json` passam a ser versionados em `data/cep/` (raiz do repositório, fora de `web/`). Decisão do responsável de publicar no repositório público, assumindo o risco de licença e a propriedade do conteúdo (`docs/portal/CEP-LOCAL.md`).
- Verificado: total de linhas dos shards igual ao `records` do manifesto; cópia idêntica à base do Runtime.
- Nenhum código novo. O Runtime segue lendo `private://aculta_portal_cep/shards`.
- RUNTIME STATUS: não aplicável (somente dados versionados).

## 0.3.0-dev.6 — configuração de Consulta de CEP: banco padrão e ordem das fontes — 2026-10-18

- Nova tela em Configurações > ACULTA Portal > Consulta de CEP (`aculta_portal_cep.settings`, permissão de configuração do site). Ela escolhe o banco de dados padrão (primeira fonte consultada) e a ordem das demais pela tabela de arrastar do Drupal (tabledrag, peso). Cada fonte pode ser ativada ou desativada.
- Fontes: base local de arquivos, ViaCEP e BrasilAPI. A consulta segue a ordem configurada e para na primeira fonte que acha o CEP. O padrão é o mesmo de antes (base local, ViaCEP, BrasilAPI).
- Validações: a fonte padrão precisa estar ativa e pelo menos uma fonte precisa estar ativa.
- Fonte desativada não é consultada nem verificada pelo cron, e o status mostra "Desativada na configuração".
- Padrões no código (`CepSources::DEFAULTS`) para o módulo já instalado antes desta configuração, que ainda não tem a configuração no banco.
- Contrib `cep_autocomplete` (removido): não tinha tela administrativa nem configuração gravada; só o endpoint e o cache fixo de 24 horas, que já foram reproduzidos aqui com outro prazo. Nada a portar.
- Verificado no Runtime de teste: formulário renderiza com a tabela de ordem; envio válido grava a ordem; padrão desativado e nenhuma fonte ativa são recusados; consulta de CEP responde com a base local desativada e com o ViaCEP desativado; status mostra fonte desativada; configuração restaurada ao padrão.

## 0.3.0-dev.5 — verificação das APIs de CEP diária (não a cada cron) — 2026-10-18

- `CepApiProbe::checkIfDue()`: consulta ViaCEP e BrasilAPI só quando a última verificação tem mais de 24 horas (`INTERVAL` 86400). O cron chama esse método, e não mais `check()`.
- Verificado no Runtime de teste pelo cron: com verificação de 1 hora, o cron não consulta; com 25 horas, consulta e grava o novo horário.

## 0.3.0-dev.4 — base local legível pelo processo web (status mostrava "sem base") — 2026-10-18

- Causa verificada: a base foi importada pelo usuário da CLI (`piradopirata`) com `file_chmod_directory 02770` e `file_chmod_file 0660` (settings.local.php). Os PHP-FPM do site rodam como outros usuários (`aculta`, `toca`, `bdtgn`, `www-data`), que não liam a pasta. O status então via a base como ausente.
- `CepLocalStore::makeReadable()`: ao final da importação e ao gravar o manifesto, a pasta fica `0755` e os arquivos `0644`. A base é dado público (DNE dos Correios) em `private://`, fora da web.
- Mensagem do status: quando a base não é encontrada, o detalhe agora também cita a leitura pelo processo web.
- Verificado: permissões do caminho inteiro legíveis; status das três linhas OK no Runtime de teste; reimportação de 1.388.509 CEPs.

## 0.3.0-dev.3 — status das consultas de CEP (API e base local) — 2026-10-18

- `CepApiProbe`: verifica ViaCEP e BrasilAPI com um CEP fixo (`01001000`, timeout de 5 s, mesmo endereço da consulta) e grava o resultado no State (`aculta_portal_cep.api_status`).
- `CepStatusCronHooks`: roda o probe no `hook_cron`. O status não consulta a rede.
- `CepLocalStore::writeManifest()` / `manifest()` / `shardCount()`: a importação (`aculta-portal-cep:import`) grava `private://aculta_portal_cep/manifest.json` com a versão (data da importação), a quantidade de CEPs e a origem.
- `CepStatusReport`: linhas do status (APIs e base local). Base até 90 dias é `ok`; de 91 a 180, `attention`; acima de 180 ou sem base, `error`. Resultado do cron com mais de 2 dias é `attention`.
- Ver também a seção "Consulta de CEP" do status do Portal (0.4.0-dev.148).

## 0.3.0-dev.2 — selects de cidade e UF travados após o CEP — 2026-10-18

- Cidade e UF vindos do CEP ficam desabilitados: não abrem nem mostram as outras opções. Um input hidden com o mesmo nome leva o valor no envio. Ao destravar (novo CEP), o hidden é removido.
- Teste de navegador: 30 verificações, incluindo os selects desabilitados e o valor no envio.
- RUNTIME STATUS: APLICADO no Runtime de teste; valor gravado conferido no banco.

## 0.3.0-dev.1 — campos do CEP bloqueados para edição — 2026-10-18

- Após a consulta do CEP, rua, bairro, cidade e UF vindos do banco ficam bloqueados (somente leitura; em select, a mudança é desfeita), visíveis para a pessoa. Campos que a busca não preencheu continuam editáveis. Número, S/N e complemento não são bloqueados. Digitar outro CEP desbloqueia e refaz o preenchimento.
- Classe `aculta-cep-locked` e `aria-readonly`/`aria-disabled` para o tema estilizar; o estilo visual do bloqueio é do ACULTA420 e não foi alterado nesta entrega.
- Teste de navegador: 26 verificações, incluindo o bloqueio e um CEP sem bairro na fonte (06550000).
- RUNTIME STATUS: APLICADO no Runtime de teste (cache rebuild).

## 0.2.1-dev.1 — troca de CEP em página com endereço salvo — 2026-10-18

- Bug: em página recarregada com endereço salvo, digitar outro CEP só preenchia o bairro vazio; cidade, UF e rua ficavam com o endereço antigo. Causa: a primeira busca da página era tratada como preenchimento de campos vazios.
- Correção: o CEP já salvo no formulário é a base da comparação. Qualquer CEP diferente troca o endereço inteiro; um formulário vazio continua sendo preenchido só nos campos vazios.
- Teste de navegador ampliado (20 verificações, incluindo página recarregada com CEP de outro estado).
- RUNTIME STATUS: APLICADO no Runtime de teste (cache rebuild); CEP 74711020 conferido.

## 0.2.0-dev.1 — base local de CEPs em arquivos — 2026-10-18

- Base local em arquivos privados, divididos por prefixo (`private://aculta_portal_cep/shards`), sem banco de dados. A consulta local tem prioridade; ViaCEP e BrasilAPI só são usadas quando o CEP não está na base.
- Comando Drush `aculta-portal-cep:import <tsv>`.
- Verificado no Runtime de teste: 1.388.509 CEPs importados (872 arquivos, ~92 MB); quatro CEPs conferidos contra a ViaCEP; teste de navegador do endereço 17/17.
- Licença: risco aceito pelo responsável em 2026-10-18 (ver docs/portal/CEP-LOCAL.md).
- RUNTIME STATUS: APLICADO no Runtime de teste (importação e cache rebuild).

# Changelog — aculta_portal_cep

## 0.1.0-dev.1 — porte do cep_autocomplete — 2026-10-18

- Porte do `drupal/cep_autocomplete` 1.0.0 para o submódulo: controlador de consulta (ViaCEP com fallback BrasilAPI, cache de 30 dias), limite de consultas por IP e biblioteca `cep-address`.
- Rota `aculta_portal_cep.lookup` em `/aculta_portal_cep/{cep}` (antes `/cep-autocomplete/viacep/{cep}`, do contrib). Chave `once` do JavaScript renomeada para `aculta-portal-cep`.
- Correção de porte: a checagem de limite comparava o nome da rota antigo do contrib; agora compara `aculta_portal_cep.lookup`.
- Não portado: `viacep.js` e o `ViaCepClient` do contrib (código morto ou conflitante; ver README).
- O `aculta_portal` passa a depender deste submódulo no lugar do `cep_autocomplete`.
- RUNTIME STATUS: pendente (ver a entrega do aculta_portal).
