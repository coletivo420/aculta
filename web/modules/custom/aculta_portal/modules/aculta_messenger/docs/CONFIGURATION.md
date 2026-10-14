# Configuração e operação

Documento de referência. Dono: `aculta_messenger` no ACULTA Portal. Revisão:
2026-10-14.

## Princípios

- Não ativar automaticamente contrib, Views, blocos, campos ou configuração de
  terceiros por instalação do Messenger.
- Não criar config própria até identificar opções de integração que não sejam
  configuração já fornecida por Core/contrib.
- Config exportável pode conter nomes, tipos e políticas, nunca tokens, senha,
  credencial SMTP ou segredo. Segredos, se uma integração futura realmente
  exigir, seguem Drupal Key provider `env` e o contrato de secrets do ACULTA.
- A entrega usa a ferramenta de e-mail já instalada: Message Notify chama o
  serviço de mail do Drupal, que usa o Symfony Mailer/Mailer Plus existente.
  Não configurar SMTP ou outro transporte novo nem adicionar dependência para
  isso.
- Administração de Site Alert e comunicados permanece em `main`.
- A central pessoal e suas configurações ficam em `account`.

## Fase atual

`aculta_messenger.info.yml` declara dependências no ACULTA Portal e nos cinco
módulos integrados. Não há ainda arquivo de serviços, rotas, permissões ou
config próprio porque a fase não implementa runtime. `composer.json` e
`composer.lock` adicionam somente os cinco pacotes; detalhes e versões estão em
[INTEGRATIONS.md](INTEGRATIONS.md).

O código de Private Message confirma que inbox, threads, criação, contagem de
não lidas e bloqueio já existem. A instalação não concede as permissões de uso
às roles: a revisão da política de papéis deve preceder a disponibilização do
envio de membros.

## Checklist para implantação futura

1. Comparar configuração exportada dos cinco módulos e aprovar os componentes
   necessários; não fazer import/export automático.
2. Confirmar os purposes e hosts do ambiente pelos arquivos de arquitetura e
   gerar URLs com `DomainPurposeManager`.
3. Instalar primeiro em ambiente de teste com transporte de e-mail controlado.
4. Conferir configuração pendente (`config:status`) sem resolver drift alheio.
5. Verificar `updatedb:status`; não executar `updb`, `cim` ou `cex` sem análise
   do estado, efeitos e autorização operacional.
6. Confirmar cache rebuild, acesso como duas contas, rotas de MAIN/ACCOUNT e
   ausência de saída privada em outros purposes.
7. Registrar comandos e evidências no changelog da fase/PR, sem criar relatório
   normativo permanente de execução.
