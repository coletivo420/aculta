# Decisões — ACULTA Fórum

Data: 2026-10-15. Fonte: decisões explícitas do responsável nesta sessão.
Atualização do responsável: marca **Growroom420**, prefixo **`growroom420.aculta`**.
O acervo de origem continua identificado como Growroom nas fontes históricas.
Este documento governa o projeto futuro; não descreve alterações já aplicadas.

## Aprovado

| ID | Decisão | Consequência |
| --- | --- | --- |
| F-D01 | Reconstruir o Growroom usando o Wayback, sem backup próprio disponível | Medir cobertura e preservar lacunas; não prometer conteúdo não arquivado |
| F-D02 | Prefixo público `growroom420` em lugar de `forum` | Deployer, Domain/aliases, infraestrutura e URLs serão atualizados por ambiente |
| F-D03 | Leitura comunitária pública; postagem por cadastrados | Permissões nativas e regras atuais de participação |
| F-D04 | Discussões do LMS reservadas a matriculados e equipe | Acesso, busca, cache e notificações dependem do contexto do curso |
| F-D05 | Acervo fechado e nova conversa vinculada | A postagem atual não modifica a sequência histórica |
| F-D06 | Contas históricas bloqueadas com papel Usuários legados do fórum | Preservação de `uid` de autoria sem login ativo |
| F-D07 | Nomes individuais e memorial coletivo | Cada autor mantém nome e conteúdo; o link público comum é `/u/lendashistoricas` |
| F-D08 | Sem página individual pública de legado | Não gerar `/u/<apelido>`, subpáginas do memorial ou `/user/<uid>` público para essas contas |
| F-D09 | Avatar especial com o sistema atual | Acervo/File/Image e caixas existentes, sem biblioteca de avatar paralela |
| F-D10 | Reutilizar galerias do Portal | Respeitar a publicação atual na Revista; referências a partir do Fórum |
| F-D11 | Loja integrada por discussões e dúvidas | Dados de pedidos e contato pessoal não viram conteúdo público |
| F-D12 | Abrir projetos opcionais `aculta_forum` e `aculta_game` | Documentação agora; runtime por fases posteriores |
| F-D13 | Participação no Fórum (criar, editar o próprio conteúdo, comentar e denunciar) exige e-mail confirmado (papel `email_confirmed`); leitura e seguir/salvar ficam para contas cadastradas | Ver `EMAIL-CONFIRMATION-POLICY.md`, seção 10. Lacuna: conteúdo histórico importado não passa por essa regra |

"Memorial" significa memória da comunidade; não afirma falecimento das pessoas.
O bloqueio é técnico e não constitui punição, expulsão ou avaliação da pessoa.

## Diretrizes de implementação aprovadas pelo plano

Forum/Node/Comment/Taxonomy preservam tópicos, respostas e estrutura. O Portal
prepara URLs, acesso e cache; ACULTA420 apresenta contratos neutros. Não criar
novas entidades Topic/Reply, outro Messenger, outra galeria ou outro checkout.

Matrícula não será convertida em um papel global de curso. Amizade não concede
acesso a conteúdo reservado. Importação não concede conquistas contemporâneas.

## Resultado operacional da F1 — 2026-10-15

- O host de teste `forum.aculta.toca.net.br` foi aposentado sem redirect: HTTP e
  HTTPS respondem 404. O DNS A e o certificado válidos permanecem no Homelab
  para manter essa resposta controlada; novas referências usam
  `growroom420.aculta.toca.net.br`.
- `forum.aculta.org` não tinha registro DNS na zona Cloudflare de produção. O novo
  `growroom420.aculta.org` permanece como destino mapeado no Deployer, mas não
  recebeu DNS: o TLS direto ao IP de origem Hostinger falhou, então o hostname
  precisa ser cadastrado e validado na origem antes de publicar o registro.
- No teste, `/sitemap.xml` responde 200 e não lista FORUM. Isso segue a política
  já instalada em `PurposeNodeUrlGenerator::INDEXABLE_PURPOSES`, que exclui
  FORUM e ACCOUNT. A verificação global `sitemap --env=test` ainda falha porque
  LINKS responde 404; a F1 não alterou LINKS.

## Propostas técnicas a comprovar

- Manter purpose `forum` e os IDs Domain existentes ao trocar hostname/aliases.
- Usar Migrate API, identificadores estáveis de origem e importação repetível.
- Reservar `lendashistoricas` antes da resolução genérica `/u/{apelido}` e impedir
  que o cadastro comum ocupe esse apelido. Inspecionar colisões antes de alterar dados.
- Fazer todos os links públicos de autor legado apontarem à mesma rota do memorial,
  preservando o nome individual como texto, inclusive nos comentários e nas Views.
- Reutilizar a pilha Message para novas respostas, com um único dono da entrega.

## Decisões posteriores, sem bloqueio da documentação

| Tema | Recomendação para a fase correspondente |
| --- | --- |
| Host antigo após a mudança | Definir política explícita antes de desligar; não introduzir redirect genérico ou replay de POST |
| Capturas adicionais | Usar a captura indicada como referência; inventariar anteriores e decidir o corte temporal antes da importação integral |
| Pessoa atual que reivindica autoria histórica | Processo verificável próprio; não vincular por coincidência de nome |
| Galeria aberta diretamente no Fórum | Nova decisão sobre D13 do `aculta_gallery`; inicialmente usar referências para a Revista |
| Curso versus turma | Validar o contexto das atividades reutilizadas antes de definir chave e alcance da discussão |

Decisões deste projeto não alteram retroativamente o funcionamento das extensões
instaladas. Cada aplicação exige código/configuração, evidência e registro no PR.
