# Política anti-regressão do Portal

Motivo: correções já aceitas pelo responsável voltaram a quebrar em rodadas seguintes (login sem redirecionamento
após uma mudança de AJAX; avatar do sistema removível; crop abrindo atrás do popup). Sem registro e sem verificação,
o próximo ajuste em área vizinha desfaz a correção anterior. Esta política torna cada comportamento aceito um
invariante verificável.

## Regras

1. **Invariante registrado.** Todo comportamento que o responsável confirmou como correto (ou corrigiu após relato)
   vira um invariante: uma linha no índice abaixo, com o caso correspondente em `docs/portal/TESTES-NAVEGADOR.md`
   (passos e resultado esperado).
2. **Ler antes de tocar.** Antes de alterar uma área, ler os invariantes do índice que a tocam. Uma mudança que
   contraria um invariante só entra com decisão explícita do responsável, registrada no próprio índice.
3. **Correção registra o caso.** O PR que corrige um relato inclui o caso no roteiro de testes e a linha no índice,
   no mesmo commit ou em commit separado do mesmo branch.
4. **Verificação mínima.** Cada invariante é verificado por pelo menos um destes meios, nomeado no índice: gate
   (`scripts/validate-*.php`), requisição HTTP com status/`Location` esperados, teste jsdom, ou passo de navegador
   executado pelo agente de testes. "Verificado por leitura" não basta para invariante de comportamento.
5. **Teste antes do merge.** Mudança com invariante afetado só vai para merge depois de rodar os casos afetados no
   servidor de testes. Caso não executado é dito como não executado, sem marcar como aprovado.
6. **Não remover caso para passar.** Um caso falhando não é retirado do roteiro para liberar o PR. O caso só sai
   com decisão do responsável, com o motivo no índice.
7. **Mudança de branch não apaga regressão.** Correção em branch separado é registrada no índice do branch de
   origem e na `main` quando for integrada. O servidor de testes serve um checkout só: dizer qual branch está em uso.

## Índice de invariantes

| ID | Área | Invariante | Verificação | Caso |
|---|---|---|---|---|
| INV-001 | Login | Login correto sai de `/entrar` e segue a política de redirecionamento (entrada direta → conta; `destination` principal → principal; `destination` da conta → rota da conta). Senha errada mostra mensagem e fica no formulário. | HTTP: 303 + `Location`; 200 com mensagem | TESTES-NAVEGADOR seção O |
| INV-002 | Login | O formulário `user_login_form` não recebe o AJAX de salvamento da Minha conta (`FormHooks::accountAjaxSubmit`). | Revisão + INV-001 | TESTES-NAVEGADOR seção O |
| INV-FOTO-1 | Foto | Avatar do sistema não tem botão "Remover" (campo de envio com `#aculta_acervo`; `withoutRemoveForAcervo`). | Gate `validate-aculta-user-photo.php` + HTTP | TESTES-NAVEGADOR seção P1 |
| INV-FOTO-2 | Foto | "Sortear outro avatar" só existe com foto do acervo. | Gate + HTTP | Seção P1, P3 |
| INV-FOTO-3 | Foto | Sem link "Recortar foto"; o recorte abre só logo após o envio (`photo-flow.js`). | Gate | Seção P2, P5 |
| INV-FOTO-4 | Foto | Recorte só do arquivo recém-enviado (temporário, do próprio usuário); foto salva recusada. | Gate + HTTP | Seção P5 |
| INV-FOTO-5 | Foto | Uma única caixa de recorte no modal (achatamento limpa o `#pre_render`). | Gate + Chromium | Seção N / P2 |
| INV-FOTO-6 | Foto | Aplicar recorte troca a foto da conta e recarrega o painel; sem reabrir o popup (sem loop). | Gate + Chromium | Seção P2 |
| INV-FOTO-7 | Foto | Popup sem nome/link da foto atual; "Remover foto" só em foto personalizada e sorteia do acervo. | Gate + HTTP | Seção P4 |
| INV-FOTO-8 | Foto | Sortear atualiza os avatares por AJAX; fechar o modal depois do sorteio recarrega a página. | Gate + Chromium | Seção P7 |

Os invariantes de foto (INV-FOTO-1 a 7) estão na tabela acima; as regras completas e o motivo de cada uma estão em `web/modules/custom/aculta_portal/modules/aculta_user/docs/FOTO-PERFIL.md`.

## Como aplicar na revisão

- Diff que toca área com invariante: conferir a linha do índice e o caso correspondente.
- Invariante violado ou sem verificação é achado de revisão, não nota.
