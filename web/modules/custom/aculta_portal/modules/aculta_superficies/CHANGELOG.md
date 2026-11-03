## 0.1.0-dev.18 — maiúsculas viram minúsculas no endereço e no slug

- Slug e subdomínio convertidos para minúsculas antes da validação e do salvamento.
- Textos de ajuda do modal atualizados.
- Testes: DEFERRED (sem vendor/ nem Runtime). Lint dos PHP executado.

## 0.1.0-dev.17 — Nome do Site, status de DNS e validação de endereço

- Lista: sai a coluna Caminho em vigor e a linha do Principal. Entram Módulo ACULTA (somente leitura) e Status do DNS. Superfície passa a ser Nome do Site.
- Status do DNS: em diretório, "Modo Diretório, Não Aplicável"; em subdomínio, consulta o DNS (CNAME para o principal ou IP em comum), com cache de 5 minutos.
- Orientação de DNS embaixo da tabela, com o host principal vindo do Domain.
- Modal: título vira Nome do Site. Endereço e slug recusam caracteres inválidos e dizem quais são e por que.
- Serviço novo: DnsStatusChecker (aculta_superficies.services.yml).
- Testes: DEFERRED (sem vendor/ nem Runtime). Lint dos PHP executado.

## 0.1.0-dev.10 — subdomínio editável no modal (grava no Domain)

- Modo subdomínio: campo com o endereço completo da superfície, gravado na entidade Domain do purpose. O principal continua somente leitura.
- Troca de endereço exige confirmação explícita, valida formato (sem https://, sem barra) e impede repetir um endereço de outro Domain.
- Testes: DEFERRED (sem vendor/ nem Runtime). Lint dos PHP executado.

## 0.1.0-dev.9 — edição em modal e seletor de modo por AJAX

- Coluna Ações com botão Editar em cada linha; a edição abre em modal do Drupal (`/painel-administrativo/aculta/caminhos/{purpose}/editar`, só MAIN).
- Classe `SurfaceEditForm`: título, ativação e modo. Ao mudar o modo por AJAX, o campo de caminho muda: diretório mostra o slug editável; subdomínio mostra o endereço vindo do Domain (somente leitura); desativado não mostra campo.
- Salvar fecha o modal e volta à lista; erro de validação reabre o modal com a mensagem.
- Testes: DEFERRED (sem vendor/ nem Runtime). Lint dos PHP executado.

## 0.1.0-dev.8 — validação no Runtime e Kernel de superfícies

- Kernel de rotas executado: 2 testes e 22 asserções PASS; fixture passou a declarar os módulos e schemas necessários ao boot real do Portal.
- Matriz HTTP 3.0 executada antes e depois de `leitura_ativa` no Runtime Homelab; resultados registrados em `docs/architecture/TESTES-3.0-ROTAS-SUPERFICIES.md`.
- A validação autenticada da interface administrativa e a resolução de `/slug` no MAIN continuam DEFERRED.

## 0.1.0-dev.7 — caminhos em /painel-administrativo/aculta/caminhos e tabela somente leitura

- Formulário movido de `/painel-administrativo/superficies` para `/painel-administrativo/aculta/caminhos` (só MAIN, permissão `administrar superficies`). O caminho antigo deixa de existir, sem alias: o submódulo não está em produção.
- Tabela somente leitura com superfície, modo, caminho público (host vindo do Domain), ativação e caminho em vigor. Caminho de diretório só aparece como em vigor com `leitura_ativa` ligado.
- Link no menu ACULTA (`aculta_portal.admin`), sem alterar outros menus.
- `SurfaceRoutesStaticTest::testFormularioEhSoMain` confere caminho, purpose e permissão.
- Testes: DEFERRED (sem vendor/ nem Runtime). Gate `validate-admin-routes-main-domain.php` executado.

## 0.1.0-dev.6 — aviso de deploy sem citar a ferramenta

- Mensagem do formulário ao mudar modo ou slug não cita o nome da ferramenta de deploy. A fronteira (`boundaries`) exige isso: consumidores não referenciam a ferramenta.
- Testes: DEFERRED.

## 0.1.0-dev.5 — teste estático das rotas (3.0) e comando de sugestão planejado

- `SurfaceRoutesStaticTest`: lê os YAML de Wiki, Studio e Commerce e confere os caminhos novos e a ausência dos antigos. Roda sem subir o Drupal.
- Comando de sugestão no deployer: não incluído, porque o módulo está em RC. Decisão pendente do responsável.
- Testes: DEFERRED (sem vendor/ nem Runtime). O teste Kernel com HTTP real segue DEFERRED.

## 0.1.0-dev.4 — aviso de correção de deploy ao mudar modo ou slug

- O formulário avisa quando uma superfície muda de modo ou slug e indica o registro de correção de deploy (canonical e sitemap) a criar com a CLI do aculta_deployer. O Portal não grava no registro.
- Testes: DEFERRED.

## 0.1.0-dev.3 — estado real das fases e documentação

- Fase 3.1 (mapa único), 3.2 (flag autoritativa), 3.3 (constantes de purpose), 3.4 parcial (configuração, schema, leitor, interruptor desligado), 3.5 parcial (regra de diretório na validação), 3.6 (formulário no MAIN), 3.7 parcial (YAML exportado).
- Não implementado: subscriber da 3.4, URLs de diretório da 3.5, conferência de importação da 3.7. Dependem de teste de rota no Runtime.
- Testes: escritos, DEFERRED.

## 0.1.0-dev.2 — formulário de superfícies no MAIN (Fase 3.6)

- `SuperficiesForm` em `/painel-administrativo/superficies`, permissão `administrar superficies`, rota só no MAIN.
- Salva `aculta_portal.superficies` com validação do `SurfaceConfigReader`. Não altera rotas nesta versão.
- Testes e Runtime: DEFERRED.

# Changelog: ACULTA Superfícies

## 0.1.0-dev.1 — esqueleto e planejamento

- Pasta do submódulo criada com `info.yml`, `README.md` e este changelog.
- Nenhum código PHP, rota, formulário ou configuração foi escrito nesta versão.
- Decisões registradas no plano:
  - resolvedor no núcleo `aculta_portal`, não neste submódulo;
  - modo diretório por camada própria (não `path_prefix` experimental do Domain);
  - flag `_aculta_domain_purpose_authoritative` lida pelo resolvedor;
  - cinco mapas de purpose unificados em uma configuração (Fase 3.1).
- Testes: **DEFERRED**. Sem acesso ao Runtime.
- Versão de referência: MINOR do `aculta_portal` quando houver implementação. Confirmar na classificação de versionamento antes de marcar a versão final.
## 0.1.0-dev.11 — descrição de host sem ambiente fixo

- Substituída a referência de produção na descrição do campo de host por um exemplo neutro; o valor exibido continua vindo da entidade Domain.
- Gate de endereços de ambiente passa sem ocorrências no submódulo.

## 0.1.0-dev.12 — corrige formulário AJAX no PHP 8.4

- Renomeada a propriedade de configuração injetada para não colidir com a propriedade herdada de `FormBase`, permitindo renderizar o modal de edição.
- A troca AJAX de modo ignora a validação de persistência até o envio pelo botão Salvar, permitindo estados intermediários sem erro.

## 0.1.0-dev.13 — garante fragmento vazio válido no modo desativado

- O wrapper do caminho mantém um elemento renderizável quando nenhum campo de caminho deve aparecer.

## 0.1.0-dev.14 — preserva dependências entre callbacks AJAX

- Removido `readonly` das dependências injetadas no formulário para que o objeto seja restaurado corretamente entre requisições AJAX do Form API.

## 0.1.0-dev.15 — restaura dependências do formulário AJAX

- As dependências do formulário passaram a `protected`, compatíveis com a desserialização de formulários do `FormBase` entre requisições AJAX.

## 0.1.0-dev.16 — invalida cache antigo do formulário

- O identificador do formulário foi atualizado para impedir que callbacks AJAX reutilizem instâncias serializadas antes da correção de injeção.

