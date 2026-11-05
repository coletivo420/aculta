# ADR-049 — AJAX no ACULTA Studio (V2)

- **Status:** aceito pelo responsável em 2026-11-05.
- **Data:** 2026-11-05.
- **Relaciona-se com:** ADR-044 (decisão 8 e seção 3, mecanismo pendente, agora escolhido), ADR-045 (AJAX isolado em um módulo; preferir página completa sem ganho real), ADR-046 (sem jQuery por padrão), ADR-047 (menu), ADR-048 (layouts e popup de foto), `web/themes/custom/aculta420_v2/docs/studio-estrutura.md`.

## 1. Contexto

- O ADR-044 deixou em aberto qual mecanismo de AJAX a V2 usa: A (formulário sem AJAX), B (Fetch próprio com `<dialog>`), C (HTMX do core) ou D (`core/drupal.ajax`, que depende de jQuery).
- O ADR-045 pede que todo AJAX do tema passe por um único módulo JavaScript, e que páginas completas sejam preferidas quando AJAX não traz ganho real.
- O responsável pediu AJAX em todo o Studio.

## 2. Decisões

1. **Mecanismo: B, Fetch próprio com resposta aplicada em `<dialog>` ou na área da tela.** Sem jQuery e sem `core/drupal.ajax` no tema. Segue o padrão já usado pelo login da V2 (`multidomain-bar.js`).
2. **Módulo único.** Todo o AJAX do Studio fica em um só arquivo JavaScript do tema (`studio-ajax.js`, nome a confirmar na implementação), com `Drupal.behaviors` e `core/once`. Trocar o mecanismo no futuro fica restrito a esse arquivo (ADR-045, item 2).
3. **Escopo: ações e formulários.** Salvar, sortear, remover, enviar foto e os formulários de perfil e de conta são enviados por AJAX e não recarregam a página. A troca de tela continua com link comum, com página completa.
4. **Sem troca de seção por AJAX.** A sub-navegação de Minha conta e as abas das suítes são links comuns. Não há `history.pushState` nem atualização de URL por AJAX nesta fase.
5. **Popup de foto usa o mesmo mecanismo.** Envio de arquivo, sorteio e recorte passam pelo módulo do item 2, e não por `Drupal.ajax` dentro do `<dialog>`. Isso corrige a opção que estava como padrão no ADR-048, item 10, e na especificação 4.2.1.
6. **Sem JavaScript, funciona.** Todo formulário envia por POST comum e a página completa responde. O AJAX é melhoria progressiva.
7. **Comandos suportados.** O módulo trata um subconjunto de comandos do protocolo de resposta do Drupal: `insert`, `replaceWith`, `remove`, `redirect`, `update_build_id` e mensagens de status. Comando não suportado não é ignorado em silêncio: o módulo envia o formulário de novo por POST comum.
8. **Estados visíveis.**
   - Carregando: `aria-busy="true"` na área afetada, botão de envio desabilitado. Sem envio duplo.
   - Sucesso: mensagem de status em região `role="status"`. O foco fica na mensagem ou no próximo passo.
   - Erro de validação: foco no primeiro campo inválido, com `aria-invalid` e texto de erro associado.
   - Erro de rede: mensagem em `role="alert"`, e o formulário continua preenchido.
9. **Mesma origem.** Requisições só para o próprio host, como no login. Nenhuma requisição atravessa purpose (PAYMENT-DOMAIN-POLICY e CROSS-DOMAIN-REQUEST-POLICY).
10. **Sem cache de resposta.** Respostas de telas do Studio são privadas e não entram em cache público (ADR-048, 8).

## 3. Lista inicial de fluxos com AJAX

| Fluxo | Onde | Dono da regra |
| --- | --- | --- |
| Salvar perfil público (apelido, biografia, redes, visibilidade) | Meu Perfil | `aculta_user` |
| Salvar dados cadastrais, endereço, senha, e-mail e preferências | Minha conta | `aculta_portal` e módulos de conta |
| Enviar, sortear, remover e salvar foto | Popup de foto (Meu Perfil e área de conta) | `aculta_user` |
| Recortar foto (aplicar e cancelar) | Popup de recorte | `aculta_user` |
| Abrir e fechar o popup de foto | Avatar | tema (abertura) |

Fora desta lista, o formulário continua por POST comum até ser incluído por
decisão.

## 4. O que precisa mudar fora do tema

- **`aculta_user`:** os formulários de foto e de perfil público devolvem resposta
  de AJAX (comandos da lista do item 7) quando chegam com `_wrapper_format=drupal_ajax`
  ou com o cabeçalho de AJAX. Esse é o mesmo contrato que o login já usa.
- **`aculta_portal`:** formulários de conta seguem o mesmo contrato, por PR própria.
- **Gate:** o `validate-aculta-user-photo.php` passa a cobrir o fluxo com Fetch.
  A mudança é PR própria, com gate.

## 5. Consequências

- Nenhum jQuery novo entra no tema. O jQuery que o Core traz nas páginas do
  Portal continua sendo dívida registrada (ADR-045, item 6), sem mudança aqui.
- Há código próprio a manter: o protocolo mínimo do item 7. O risco de divergência
  com o Core é aceito e fica limitado ao módulo do item 2.
- O popup de foto deixa de depender de `core/drupal.dialog.ajax`, em linha com
  o item 10 do ADR-048.
- Em caso de migração para HTMX do core (ADR-044, opção C) ou para `Drupal.ajax`
  sem jQuery, o ponto de troca é o módulo do item 2.

## 6. Não-decisões

- Não define troca de seção por AJAX (item 4). Pode virar decisão futura com
  `history.pushState`.
- Não define o nome final do arquivo JavaScript.
- Não define a lista de comandos além do item 7; ampliar exige teste com o
  formulário que o usa.
- Não decide o Drupal 12 (ADR-045).

## 7. Validação

- Teste de cada fluxo da seção 3 com JavaScript ligado e desligado.
- Teclado: foco vai para a mensagem após sucesso e para o primeiro erro.
- Leitor de tela: `role="status"` e `role="alert"` anunciados.
- Comando não suportado cai no POST comum, sem perda de dados.
- Gates no Homelab. Capturas dos estados de carregamento, sucesso e erro em 320,
  390 e 1440 px, claro e escuro, fora do repositório (`$SUITE_OUT`).
- Status: **DEFERRED** no Homelab, sem execução nesta sessão.
