# Plataforma de formulários Webform

Dono: ACULTA Portal. Revisão técnica: 2026-10-18.

Referência para configurar formulários editáveis, etapas, submissões e arquivos
com o Drupal Webform. A configuração exportada em `config/sync`, o Composer e
os arquivos de ambiente são as fontes de verdade; este documento define os
limites de uso e as decisões necessárias para ampliar a plataforma.

## Direção recomendada

Usar **Webform + Webform UI** como construtor geral de formulários. O editor
visual permite criar campos, organizar layouts, configurar condições,
confirmações, handlers e formulários em várias páginas sem implementar outro
construtor no Portal. Webform cobre texto, e-mail, telefone, datas, números,
checkboxes, radios, selects, opções “Outro”, Likert, tabelas, termos e upload
gerenciado, entre outros elementos.

Usar Webform para contato, manifestação de interesse, pesquisa, inscrição,
avaliação sem nota oficial do LMS, pedidos de informação e coleta documental.
Atividades avaliadas, tentativas e notas de cursos permanecem no LMS. Não
converter submissões Webform em notas LMS automaticamente.

Webform e Webform UI já estão habilitados na configuração versionada; portanto,
a decisão de plataforma não exige adicionar outro construtor nem dependência.
CTools não é o construtor de formulários: sua API de wizard não oferece a UI de
criação para editores. O Webform já possui a navegação em etapas necessária.
CTools permanece por funções atuais não relacionadas à construção de
formulários (opções do bloco Views de próximas atividades e condição de grupo
da integração H5P); veja [`CTOOLS.md`](../../web/modules/custom/aculta_portal/docs/CTOOLS.md).

## Inventário versionado

Confira a configuração e as permissões nos arquivos indicados antes de qualquer
alteração. Não inferir o estado ativo de um Runtime apenas pelo Config Sync.

| Área | Evidência no repositório | O que a plataforma já oferece / ponto a resolver |
| --- | --- | --- |
| Webform e Webform UI | `composer.lock`; `config/sync/core.extension.yml` | Versão travada 6.3.1; `webform`, `webform_ui` e `webform_score` constam habilitados na configuração versionada. A UI de construção já pertence ao pacote Webform. |
| Formulários públicos | `config/sync/webform.webform.aculta_contact.yml`, `webform.webform.aculta_participation.yml` | Dois formulários publicados para anônimos e autenticados. São de página única e não têm elementos de arquivo. `aculta_participation` já usa campo `checkboxes`; contato envia notificação institucional por e-mail. |
| Tipos de campo | `web/modules/contrib/webform/src/Plugin/WebformElement/` | O pacote instalado contém elementos de seleção única/múltipla, “Outro”, tabelas, Likert e upload gerenciado. A disponibilidade do elemento depende das permissões/configuração do Webform e do armazenamento privado. |
| Respostas e relatórios | `config/sync/views.view.webform_submissions.yml` | Há View de submissões com displays protegidos por permissões. O papel editor não recebe permissões Webform em `config/sync/user.role.content_editor.yml`; não conceder acesso amplo às respostas como efeito colateral de permitir criar formulários. |
| Spam | `config/sync/captcha.settings.yml`, pontos `captcha.captcha_point.webform_submission_*` e `turnstile.settings.yml` | CAPTCHA/Turnstile está configurado globalmente e os dois formulários existentes têm pontos explícitos. Verificar o widget e o envio real em cada novo formulário público; não criar exceções sem atualizar a política anti-bot. |
| Upload privado | `config/sync/webform.settings.yml`, `web/sites/default/settings.local.php` | Webform desabilita uploads públicos e redireciona downloads privados sem acesso. O arquivo de ambiente local configura `file_private_path` fora do webroot. Confirmar configuração equivalente, permissões e backup na produção antes de publicar upload. |
| Segurança e versão | `composer.lock`, `web/modules/contrib/webform/webform.info.yml` | Webform 6.3.1 corresponde ao lock e às correções de segurança publicadas para a série 6.3. Manter atualização de segurança monitorada antes de abrir uploads a visitantes. |
| Add-ons Webform | `web/modules/contrib/webform/modules/`, `config/sync/core.extension.yml` | O código do pacote contém add-ons como Templates, Access, Attachment, Cards e export/import; eles não constam habilitados na configuração versionada. `Webform Attachment` cria/anexa arquivos gerados; não é requisito para receber arquivos enviados, que usa o elemento gerenciado do Webform. Avaliar add-ons somente com caso de uso e revisão de compatibilidade/segurança. |

O snapshot deve ser refeito a partir de `composer.lock`, `config/sync` e do
Runtime explicitamente confirmado antes de uma configuração. As submissões são
dados de conteúdo no banco, não arquivos de configuração exportados.

## Modelo de edição e acesso

Separar três tarefas que o Webform permite controlar independentemente:

1. **Criar e editar a estrutura do formulário.** Planejar um papel específico
   para editores autorizados. Evitar entregar `administer webform`, edição de
   source/Twig/CSS/JavaScript ou administração global de submissões. O acesso a
   elementos Twig, código e handlers remotos pode expor dados ou executar
   comportamento privilegiado.
2. **Ler e tratar respostas.** Dar acesso por formulário e por equipe, no painel
   Access de cada Webform. Não conceder `view any webform submission`,
   `administer webform submission`, edição, exclusão ou exportação global a um
   papel editorial genérico. Respostas podem conter dados pessoais e anexos.
3. **Enviar respostas.** A publicação anônima é uma decisão por formulário.
   Coletar somente os dados necessários, explicar o uso, limitar tentativas
   quando aplicável e revisar consentimento e retenção antes da publicação.

O Webform oferece permissões globais e controles de acesso por formulário.
Definir os papéis concretos e testar usuários A/B antes de liberar para editores.
O role `content_editor` não possui permissão Webform explícita no YAML atual.
Se muitas equipes precisarem de acesso reutilizável a conjuntos diferentes de
formulários, avaliar o add-on `webform_access`; o controle de acesso padrão por
formulário é suficiente para começar.

## Etapas, respostas e tipos de campo

- Usar elementos nativos, agrupamentos, páginas de wizard, indicador de
  progresso, revisão e confirmação. Dividir formulários longos em etapas com
  títulos claros; tornar campos obrigatórios somente quando necessários.
- Usar `checkboxes` quando mais de uma resposta pode ser marcada e `radios` ou
  select de opção única quando as alternativas se excluem. Usar variantes
  “Outro” quando respostas abertas forem legítimas, evitando campos livres sem
  necessidade.
- Usar Likert/tabela para perguntas matriciais curtas e acessíveis; em mobile,
  evitar grades largas. Testar teclado, leitor de tela, mensagens de erro e
  progresso nas larguras móveis do tema.
- Definir se o formulário aceita resposta anônima, uma ou várias submissões,
  edição posterior, rascunhos e prazo de retenção. Não ativar autofill ou
  rascunho persistente sem revisar exposição de respostas compartilhadas e
  dispositivo público.
- As duas configurações Webform existentes desativam a captura do endereço IP,
  mas o padrão global exportado não faz isso. Recomenda-se desativar a coleta de
  IP por padrão e reabilitá-la somente quando houver finalidade documentada.
- A confirmação deve informar que o envio foi recebido, sem ecoar dados
  pessoais. Preferir notificação com informação mínima e link administrativo
  protegido; não enviar arquivos anexados por e-mail como padrão.

## Política para arquivos

O Webform possui elementos de arquivo gerenciado. Usar o esquema **privado**
para documentos enviados por usuários. Nunca habilitar destino público para
uploads de visitantes. A proteção depende da autorização da submissão e do
serviço de arquivos privados do Drupal; testar URL direta, conta sem permissão,
submissão removida e revogação de acesso.

Antes de publicar um campo de arquivo, definir por formulário e por elemento:

- finalidade e quem pode baixar o documento;
- extensões/MIME estritamente necessárias, quantidade de arquivos e tamanho
  máximo;
- limite coerente com `upload_max_filesize`, `post_max_size` e limites do proxy
  tanto no Apache do Homelab como no LiteSpeed da produção;
- retenção, exclusão, backup, proteção antimalware disponível e resposta a
  arquivo suspeito;
- comportamento de submissão incompleta, arquivo temporário e remoção da
  resposta.

Os valores globais de tamanho do Webform estão vazios na configuração exportada;
não usar limites PHP implícitos como política de produto. A lista padrão inclui
formatos como HTML, SVG e arquivos compactados, então cada campo deve declarar
uma allowlist estreita adequada à finalidade; não liberar a lista global sem
analisar cada caso.

**Imagens seguem regra própria do ACULTA:** todo arquivo de imagem do site passa
por `aculta_image`, com `UploadPolicy` e o fluxo de ingestão aprovado. Não
ativar upload de imagens Webform pelo elemento genérico até existir uma
integração que aplique essa política. Para a primeira entrega, recomendar
documentos privados não executáveis, com allowlist curta, e excluir SVG/HTML,
arquivos compactados e executáveis salvo revisão específica.

## Configuração e publicação

Tratar a definição do Webform como configuração Drupal versionada:

1. Criar/editar o formulário na UI de desenvolvimento ou teste já confirmado.
2. Revisar elementos, access, handlers, exportação, mensagem de confirmação,
   retenção e comportamento de upload.
3. Exportar a configuração YAML para `config/sync`, revisar o diff e submetê-la
   em commit. Nunca transportar dados de submissão junto da configuração.
4. Importar por procedimento de release após conferir ambiente, UUID, efeitos e
   plano de recuperação; não editar formulários diretamente em produção como
   fluxo habitual.
5. Validar a página pública, envio e confirmação; testar permissões de criação,
   leitura, exportação e download com papéis diferentes.

Não instalar `webform_config_ignore` para mascarar mudanças de configuração em
produção. Isso faria a UI deixar de ser rastreável no fluxo versionado do
projeto.

## Segurança e manutenção

Webform 6.3.1 corrigiu um conjunto coordenado de vulnerabilidades, inclusive
falhas de controle de acesso em submissões e uploads e uma vulnerabilidade
crítica de execução remota sob configurações específicas. O lock atual já está
nessa versão. Conferir avisos de segurança do Drupal e atualizar para releases
corrigidas antes de habilitar novos recursos de upload, anexos ou formatos
customizados.

O mecanismo atual de Turnstile é a proteção anti-bot de formulários públicos.
Honeypot pode ser avaliado como camada complementar de baixo atrito se spam
justificar uma dependência; não é necessário substituir o CAPTCHA atual nem
ativar proteção indiscriminada em todos os formulários do site.

## Decisões pendentes antes de ampliar o uso

- papéis autorizados a criar/editar formulários e equipes autorizadas a ler ou
  exportar respostas;
- categorias de dados aceitas, aviso/consentimento e prazo de retenção por tipo
  de formulário;
- limites de tamanho, formatos e volume de arquivos por finalidade;
- suporte a imagem em formulário, condicionado à integração com `aculta_image`;
- se rascunhos e retomada de formulários longos serão permitidos para pessoas
  anônimas ou somente para contas autenticadas.

## Referências

- [Webform: recursos, elementos, wizard, submissões e controle de acesso](https://www.drupal.org/docs/contributed-modules/webform/webform-features)
- [Webform UI: interface administrativa para construir formulários](https://www.drupal.org/docs/contributed-modules/webform/webform-sub-modules)
- [Projeto e releases do Webform](https://www.drupal.org/project/webform)
- [Release Webform 6.3.1](https://www.drupal.org/project/webform/releases/6.3.1)
- [Aviso SA-CONTRIB-2026-175 (execução remota)](https://www.drupal.org/sa-contrib-2026-175)
- [Honeypot: mitigação de spam](https://www.drupal.org/project/honeypot)
- [Drupal: gerenciamento e publicação de configuração](https://www.drupal.org/docs/administering-a-drupal-site/configuration-management/managing-your-sites-configuration)
- [PSA-2016-003: upload não confiável em arquivos públicos](https://www.drupal.org/psa-2016-003)
- [Política anti-bot do ACULTA](../portal/TURNSTILE.md)
- [Política de arquivos e acesso do LMS](../portal/SECURITY-ACCESS-FILES-MATRIX.md)
- [Política do pipeline de imagens ACULTA](../../web/modules/custom/aculta_portal/modules/aculta_image/README.md)
