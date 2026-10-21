# Inventário de tratamento de dados pessoais (LGPD)

Versão 1.0, 20/10/2026. Base: código e configuração do repositório (`web/modules/custom/aculta_portal`, `web/themes/custom/aculta420`),
e dados publicados em `docs/institution/IDENTIDADE-LEGAL.md`.

Controlador: Associação Cultural Antiproibicionista (CNPJ 68.238.467/0001-08). Servidores de hospedagem e banco de dados no Brasil. Canal de privacidade: faleconosco@aculta.org.
Encarregado (DPO): **pessoa jurídica, a designar pelo responsável** (razão social pendente), atendido pelo canal faleconosco@aculta.org.

## Princípios que valem para todo módulo

- **Checkout ativo na produção.** Pagamentos de apoios, doações e compras passam pelo Mercado Pago.
- **Servidores no Brasil** para o site e o banco de dados.

- **O Portal não processa pagamentos.** Todo pagamento é processado pelo Mercado Pago. A conta ACULTA não armazena nem exibe
  cartões, métodos salvos ou histórico de pagamentos pessoais (regra permanente em `AGENTS.md`).
- **CPF e CNPJ**: coletados quando necessários a cursos e à condição de associado. Em claro, o documento só existe no
  momento da coleta e, quando exigido pelo pagamento, no envio ao Mercado Pago. No banco, ficam cifrados
  (`aculta_associado_id`, `DocumentCipher`) e com impressão digital pseudonimizada, para unicidade (`aculta_id_cpf`,
  `DocumentFingerprint`, chave `ACULTA_ID_CPF_FINGERPRINT_KEY`). Impressão digital é dado pessoal pseudonimizado, não anonimizado.
- **Endereços de e-mail** no registro de envios: só como identificador criptográfico (`aculta_mailer`).
- **Segredos** (chaves de cifra, tokens, senhas) ficam no arquivo de segredos do servidor, fora do repositório.

## Operadores e terceiros

| Prestador | Função | Dados enviados | Observação |
|---|---|---|---|
| Mercado Pago | Processamento de pagamentos | Dados de pagamento, identificação do pagador (CPF quando exigido), valor, referência | Trata dados em seu próprio ambiente. Cartão nunca passa pelo site. |
| Brevo | Envio transacional por API e eventos de entrega | Endereço do destinatário, assunto e corpo da mensagem, dados de entrega | Recebe eventos de volta pelo webhook (token de portador). |
| Hostinger | Hospedagem e envio SMTP | Mesmo conteúdo do envio SMTP | Caixa de monitoramento de devoluções e DMARC. |
| SMTP2GO | Contingência de envio | Mesmo conteúdo do envio por esse transporte | Usado só quando os demais falham. |
| Cloudflare Turnstile | Antirrobô em formulários | Token de desafio e dados técnicos (pode incluir IP) | Ativo quando há chaves; contingência local sem chaves. |
| Google | Fontes (Inter, Oswald); conexão opcional de conta (OAuth) | Endereço IP ao carregar fontes; dados básicos da conta Google se o usuário optar | Sem acesso a Gmail, Drive, Agenda ou Contatos. |
| ViaCEP | Consulta de endereço por CEP (`aculta_portal_cep`) | CEP digitado | Consulta pública; não envia nome nem CPF. |

## Inventário por módulo

Legenda de base legal (LGPD, art. 7º e 11): **C** consentimento (art. 7º, I); **E** execução de contrato (art. 7º, V); **O** obrigação legal (art. 7º, II); **L** legítimo interesse (art. 7º, IX); **S** dado sensível, com consentimento específico (art. 11, I). Enquadramento por uso, na política de privacidade (seção Bases legais por uso do site), é a referência publicada.

| Módulo | Dados pessoais tratados | Finalidade | Base (a validar) | Retenção | Prestadores |
|---|---|---|---|---|---|
| `aculta_portal` (conta, formulários) | Nome de usuário, e-mail, hash de senha, dados de Contato e Faça Parte | Acesso à conta; resposta a manifestações | E, L | Conta enquanto existir; submissões pelo prazo necessário | Hosting; Turnstile |
| `aculta_user` | Apelido, nome, sobrenome, WhatsApp, cidade, UF, data de nascimento, biografia, foto, visibilidade pública | Perfil privado (contrato); perfil público só se a pessoa optar | E; C (público) | Enquanto a conta existir | — |
| `aculta_id_cpf` | Impressão digital pseudonimizada de CPF/CNPJ | Unicidade de documento (sem exibir o número) | E, O | Enquanto o vínculo existir | — |
| `aculta_associado_id` | CPF/CNPJ cifrado; documentos cifrados (`ACULTA_DOCUMENT_ENCRYPTION_KEY`) | Condição de associado | E, O | Pelo período de vínculo e obrigações legais | — |
| `aculta_pagamentos_mercadopago` | Referências de pedido, valores, estados; identificação do pagador enviada ao Mercado Pago | Apoios e compras | E, O | Pelo período exigido para registro fiscal e contábil | Mercado Pago |
| `aculta_donation` | Valor de apoio, dedicatórias (nome da pessoa homenageada), recorrência | Apoios | C, E | Conforme pagamento | Mercado Pago |
| `aculta_portal_cep` | CEP consultado | Preenchimento de endereço | E | A validar (confirmar se o CEP é gravado no endereço da pessoa) | ViaCEP |
| `aculta_messenger` | Mensagens internas, remetente, avatar, avisos e destinatários | Comunicação e avisos da conta | E | A validar (prazo de retenção das mensagens internas) | — |
| `aculta_forum` e `aculta_forum_lms` (Growroom420) | Autoria, apelido, conteúdo publicado; conteúdo político pode ser dado sensível | Discussões | C; S | Enquanto o conteúdo existir | — |
| `aculta_magazine` | Nome, apelido, WhatsApp, data de publicação, perfil público | Revista e autoria | C | Enquanto o conteúdo existir | — |
| `aculta_links` | Quando habilitado: ID do destino Drupal e contagem agregada diária de GETs de redirects curtos; fila transitória guarda ID do destino e instante UTC, sem identificadores do visitante | Exibir ao proprietário o volume aproximado de acessos ao seu destino curto | L (a validar antes da ativação) | Desligado por padrão; agregados por 90 dias; fila removida ao processar, eventos fora do prazo descartados ao processar | — |
| `aculta_qrcode` | Autor (UID), título administrativo, destino do código QR dinâmico e histórico de revisões; contagem diária por código entra na 0.6.0, sem IP nem user agent | Redirecionamento de QR dinâmico e registro administrativo | A validar | A validar (retenção do histórico de revisões) | — |
| `aculta_game` (conquistas) | Nome, sobrenome, apelido, WhatsApp, imagem, perfil público | Conquistas e reconhecimento | C | Enquanto a conta existir | — |
| `aculta_lms` e `aculta_lms_h5p` | Nomes em cursos e em conteúdos interativos; progresso e avaliações; nome, curso, carga horária, conclusão, emissão, estado e código opaco de certificados | Cursos e acompanhamento; verificação pública por código compartilhado no certificado | E; O (registro acadêmico, quando exigido), a validar | Progresso e avaliações pelo período do curso; certificados permanecem verificáveis após exclusão da conta por decisão do responsável, com snapshot identificável e estado/reemissões preservados | — |
| `aculta_mailer` | E-mail do destinatário (só HMAC no registro); conteúdo cifrado só em falha | Envio e registro de mensagens transacionais | E | Registro: 90 dias (configurável, 30 a 365). Conteúdo de falha: até aceitação ou entrega; links de uso único em 24 horas | Brevo; Hostinger; SMTP2GO |
| `aculta_image`, `aculta_pdf_viewer`, `aculta_gallery` | Arquivos enviados (podem conter imagens de pessoas) | Acervo e visualização | C, L | Enquanto o arquivo estiver publicado ou em uso | — |
| `csp` + `reporting` (observação CSP do PDF.js) | URL da página, URL/recurso bloqueado, diretiva, metadados do navegador/User-Agent; sem trecho inline (`report-sample` não habilitado) | Diagnóstico de compatibilidade e segurança da política CSP em Alpha; DBLog local recebe os relatórios | L (a validar) | DBLog/Watchdog; prazo de retenção precisa ser definido e limitado antes da migração a produção | — |
| `aculta_deployer`, `aculta_patcher`, `aculta_portal_sitemap` | Nenhum dado pessoal (ferramentas de operação e índices) | — | — | — | — |
| `aculta420` (tema) | Nenhum dado pessoal; só apresentação | — | — | — | Google Fonts |

## Pendências para fechar o inventário

1. Encarregado (DPO, pessoa jurídica): designar e publicar a razão social.
2. Transferência internacional: confirmar com cada prestador (Brevo, Cloudflare, Google) o país de tratamento e as cláusulas contratuais. Mercado Pago: confirmar o mesmo para o checkout ativo na produção.
3. Wiki: definir a base legal e a retenção quando a wiki for ativada.
4. Retenção de dados de associado e de pagamento: confirmar os prazos fiscais e de vínculo.
5. Processo de atendimento aos direitos do titular (art. 18) e de comunicação de incidentes (art. 48): a definir pelo responsável.
