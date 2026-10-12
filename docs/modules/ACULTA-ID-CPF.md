# ACULTA ID CPF — primeira entrega

Solicitação do responsável em 2026-10-12: iniciar `aculta_id_cpf` como submódulo
do `aculta_portal`, a partir da especificação técnica preparada na conversa.
Essa solicitação prioriza a primeira entrega sem reordenar as demais fases do
roadmap ou fechar as dívidas existentes.

## Escopo, versões e fonte de verdade

- Caminho: `web/modules/custom/aculta_portal/modules/aculta_id_cpf`.
- Submódulo opt-in, versão inicial `0.1.0-dev.1`, classificação MINOR.
- Base do trabalho: `main` em `368ec263189d670d459e385b068d7bb188af95b3`.
- Portal permanece `0.3.0-dev.16`: nenhum código runtime do módulo pai foi
  alterado. A próxima marcação do Portal depende da homologação correspondente;
  não foi criada release ou tag para estado ainda não homologado.
- Lock: Core 11.4.8, Webform 6.3.1, login_emailusername 3.0.1, Key 1.22.0,
  Profile 1.14.0 e PHPUnit 11.5.57. O lock não equivale ao ambiente instalado.
- Checkout, OAuth, CAPTCHA, Domain, e-mail, tema e dados existentes não são
  alterados por esta entrega.

Os serviços puros não coletam nem gravam dados. Os elementos Form API entregam
valores canônicos ao chamador, que ainda precisa implementar proteção contra
cache, armazenamento em claro e exibição não autorizada. Validade de DV não
comprova titularidade, emissão ou situação cadastral.

## Pesquisa e adaptação ao repositório

A especificação original previa integração com login do Core e repositório
privado. O site já possui `login_emailusername`, Social Auth, confirmação de
e-mail e `PortalFormCallbacks::validateLoginAuthentication`, responsável por
preservar a resposta genérica de contas bloqueadas. Dados pessoais pertencem a
Profile, contas a User, e segredos a Key/environment. A entrega inicial não
introduz outro storage ou resolvedor de autenticação.

Antes da persistência, verificar o suporte real de Core/contrib a criptografia
de campos e índices exclusivos. Se for necessário índice privado/criptografia
complementar, documentar a lacuna e manter User/Profile como fonte de verdade,
sem espelhamento acidental. Campos CPF/CNPJ completos não podem ser strings
expostas em entidades públicas. Login por CPF precisa manter o wrapper atual,
flood, confirmação, CAPTCHA e Social Auth, com contadores compartilhados por uid.

Plugins de Form API usam `#[FormElement]`; validação usa callback
`aculta_id_cpf.element_validation:validate` com serviços explicitamente
injetados. Esse contrato foi verificado no change record 3548821 e nos arquivos
Textfield, FormBuilder e FormValidator da tag Core 11.4.8. Core/contrib/vendor
não estavam instalados neste checkout; por isso a resolução no Drupal completo
permanece pendente. Não há declaração de prontidão para Drupal 12/13.

O relatório P10-R citado por AGENTS.md foi consultado no commit histórico
`9c95420`, conforme indicado no DEBT-REGISTER; não foi recriado no working tree.

## Evidência de execução

Validação local em container de desenvolvimento, não no servidor de testes
Homelab. PHP CLI 8.3.6; PHPUnit 11.5.57; Node v24.19.0.

| Verificação | Resultado |
| --- | --- |
| Runner PHP independente do Drupal | PASS: 248 verificações |
| PHPUnit de domínio | PASS: 34 testes, 63 asserções |
| Máscaras JavaScript em harness Node | PASS: 8 testes |
| Lint PHP | PASS: 13 arquivos |
| Gate Drupal 11+ do Portal | PASS: 583 verificações |
| git diff --check | PASS |
| Kernel / descoberta de plugins / Form API completo | DEFERRED |
| Composer validate/audit, Drush cr e status de config/update | DEFERRED: dependências/runtime ausentes |
| Homelab, MariaDB, PHP 8.5 e navegador real | DEFERRED |

Os testes Node exercitam handlers reais em harness, mas não comprovam o
comportamento de todos os navegadores. Os testes de Kernel foram adicionados
para descoberta, resolução dos callbacks, valores aninhados, entradas vazias e
controle CR/LF; não foram executados nem declarados PASS.

Os testes PHP usam vetores públicos ilustrativos e o exemplo alfanumérico
oficial `12ABC34501DE35`. O runner também altera cada DV dos vetores válidos e
verifica a rejeição, sem consultar cadastros reais. Nenhum pacote Composer foi
adicionado e nenhum segredo foi utilizado.

## Próximas entregas

1. Homologar a primeira entrega com dependências instaladas, PHP 8.5, Kernel,
   cache rebuild e smoke do Portal no Homelab.
2. Implementar campos PF/PJ e armazenamento privado, autorização, reautenticação
   e índices de unicidade transacionais. Testar concorrência e rollback em
   MariaDB de teste antes de coletar documentos.
3. Integrar login por CPF ao pipeline existente, sem contador separado para
   CPF/e-mail; revisar logs, recuperação de senha e enumeração.
4. Criar integração opcional Webform, com modos validate_only, link_user e
   capture_encrypted. Proteger rascunhos, tokens, e-mails, exportações e handlers
   antes de habilitar coleta.

Nenhum item futuro está marcado como implementado. A PR desta entrega deve
permanecer em draft com **RUNTIME STATUS: DEFERRED** até homologação.

## Rollback

Como o submódulo não está habilitado no sync e não possui schema/update hooks,
remover/reverter a alteração de código reverte esta entrega. Se tiver sido
habilitado em homologação, desabilitar/desinstalar somente após verificar que
nenhum formulário depende dos elementos e reconstruir o cache pelo fluxo do
projeto. Não há migração de dados ou ação de produção nesta entrega.

## Fontes oficiais

- [Form API CallableResolver, Drupal 11.3](https://www.drupal.org/node/3548821).
- [Textfield, Core 11.4.8](https://github.com/drupal/drupal/blob/11.4.8/core/lib/Drupal/Core/Render/Element/Textfield.php).
- [FormValidator, Core 11.4.8](https://github.com/drupal/drupal/blob/11.4.8/core/lib/Drupal/Core/Form/FormValidator.php).
- [Atributo FormElement](https://api.drupal.org/api/drupal/core!lib!Drupal!Core!Render!Attribute!FormElement.php/class/FormElement/11.x).
- [Manual Receita/Serpro do DV de CNPJ](https://www.gov.br/receitafederal/pt-br/centrais-de-conteudo/publicacoes/documentos-tecnicos/cnpj/manual-dv-cnpj.pdf).

Pesquisa de reaproveitamento da especificação: bissolli/validador-cpf-cnpj,
geekcom/validator-docs e theuves/cpf. Nenhum arquivo desses projetos foi copiado.
O módulo mantém implementação própria pequena e serviços independentes;
normalize/validate não apagam letras do CNPJ nem caracteres arbitrários.
