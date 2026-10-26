# Roteiro de testes

Este roteiro confere à mão o que o painel já faz (E0 a E8). Cada teste tem os passos e o resultado esperado. A suíte automática (seção 1) cobre as mesmas regras e mais casos de erro.

## 0. Preparação

Os comandos deste roteiro usam o atalho `dev`, na raiz do projeto. O atalho chama o `docker compose` com o container certo. Para ver todos os comandos, rode `./dev help`.

| Terminal | Como chamar |
|---|---|
| Git Bash | `./dev <comando>` |
| cmd | `dev <comando>` |
| PowerShell | `.\dev <comando>` |

Os testes com `curl` usam o Git Bash.

1. Abra o Docker Desktop.
2. Na pasta `C:\YantraPainelControle`, suba o ambiente:
   ```
   ./dev up
   ```
3. Compile as telas:
   ```
   ./dev assets
   ```

Cuidado: o passo 4 apaga todos os dados do banco de desenvolvimento.

4. Recrie o banco com os dados de exemplo:
   ```
   ./dev fresh
   ```
5. Instale no celular um aplicativo autenticador, por exemplo o Microsoft Authenticator ou o Google Authenticator.

Usuários de exemplo, todos com a senha `password`:

| E-mail | Papel |
|---|---|
| `admin@weega.com.br` | Administrador |
| `suporte@weega.com.br` | Suporte |
| `operador@hospital-exemplo.com.br` | Operador do Hospital Exemplo Norte |
| `gestor@hospital-exemplo.com.br` | Gestor do Hospital Exemplo Norte |

Endereços:

| Endereço | O que abre |
|---|---|
| <http://localhost:8000> | Painel |
| <http://localhost:8000/api/docs> | Contrato da API do agente (Swagger) |
| <http://localhost:8081> | Arquivos do S3 local |

## 1. Suíte automática

1. Rode todos os testes:
   ```
   ./dev test
   ```
2. Resultado esperado: todos os testes passam. A suíte leva cerca de 1 minuto e meio.

## 2. Acesso (E1)

### 2.1 Assistente de primeiro acesso (R-ACE-15)

1. Abra <http://localhost:8000> e entre com `admin@weega.com.br` e `password`.
2. Resultado esperado: o botão **Entrar** fica desativado e mostra o ícone de carregamento. Depois, o painel abre o assistente **Primeiro acesso** no passo 1 (Senha).
3. Abra <http://localhost:8000/staff>.
4. Resultado esperado: o painel volta para o assistente. Antes do fim do assistente, nenhuma tela do painel abre.
5. Digite `password` como senha nova.
6. Resultado esperado: o painel recusa a senha, porque ela tem menos de 12 caracteres.
7. Crie uma senha com 12 caracteres ou mais e clique em **Salvar e continuar**.
8. Resultado esperado: o passo 1 fica com o sinal de concluído. O passo 2 mostra o QR code e a chave manual.

Cuidado: o código do aplicativo depende da hora do celular e da hora do servidor. Deixe a data e a hora do celular e do Windows no modo automático. Antes do passo 9, rode `./dev clock`: a hora do container e a hora da internet precisam ser iguais. Com 3 horas de diferença, por exemplo, nenhum código vale.

9. Leia o QR code com o aplicativo autenticador. Recarregue a página.
10. Resultado esperado: o QR code continua o mesmo. O código do aplicativo continua válido.
11. Digite o código e clique em **Confirmar código**.
12. Resultado esperado: o painel mostra os códigos de recuperação. Guarde um código para o teste 2.3.
13. Clique em **Já guardei os códigos**.
14. Resultado esperado: o passo 3 mostra as opções **Claro**, **Escuro** e **Sistema**. O tema muda na hora.
15. Escolha um tema e clique em **Confirmar o tema**.
16. Resultado esperado: o passo 4 mostra **Tudo pronto**.
17. Clique em **Acessar o painel**.
18. Resultado esperado: a área Staff Weega abre na tela Início.

Um usuário convidado (teste 3.6) cria a senha no convite. Para esse usuário, o assistente começa no passo 2.

### 2.2 Login com 2FA

1. Saia pelo menu do usuário (canto inferior esquerdo) e entre de novo.
2. Resultado esperado: depois da senha, o painel pede o código do celular.
3. Digite o código.
4. Resultado esperado: a tela Início abre.

### 2.3 Código de recuperação e bloqueio

1. Saia e entre de novo. Na tela do código, clique em **entrar com um código de recuperação** e use o código guardado.
2. Resultado esperado: o login funciona. O mesmo código não funciona uma segunda vez.
3. Saia e entre de novo. Digite um código errado 6 vezes.
4. Resultado esperado: na sexta tentativa, o painel recusa por excesso de tentativas. O bloqueio dura 15 minutos.

### 2.4 Recuperação de senha

1. Na tela de login, clique em **Esqueci a senha** e informe `admin@weega.com.br`.
2. Resultado esperado: a mensagem diz que, se o e-mail existe, o link chega em poucos minutos.
3. Repita com `ninguem@exemplo.com`.
4. Resultado esperado: a mesma mensagem.
5. O e-mail de desenvolvimento vai para o log. Leia o link com:
   ```
   ./dev exec -T app grep -o "http://localhost:8000/reset-password/[^ ]*" storage/logs/laravel.log | tail -1
   ```
6. Abra o link e tente a senha `curta123456` (11 caracteres).
7. Resultado esperado: o painel recusa a senha. Uma senha com 12 caracteres ou mais é aceita.

### 2.5 Tema

1. Abra o menu do usuário e escolha **Claro**, **Escuro** e **Sistema**.
2. Resultado esperado: o painel muda de tema na hora e mantém a escolha depois de recarregar a página.

## 3. Cadastros da Staff Weega (E1)

### 3.1 Clientes

1. Como administrador, abra **Clientes** e clique em **Novo cliente**.
2. Digite o CNPJ `11.222.333/0001-82`.
3. Resultado esperado: o painel recusa o CNPJ inválido.
4. Troque para `11.222.333/0001-81`, preencha os nomes e salve.
5. Resultado esperado: o cliente aparece na lista. A busca por `11.222` encontra o cliente.

### 3.2 Usuários e vínculo

1. Abra **Usuários** e clique em **Novo usuário**. Escolha o papel Operador e salve sem marcar um cliente.
2. Resultado esperado: o painel exige pelo menos um cliente.
3. Marque o cliente criado no teste 3.1 e salve.
4. Resultado esperado: o usuário aparece na lista com o 2FA pendente e o selo **Convite pendente**. O e-mail do convite vai para o log. Use o teste 3.6 para abrir o link.

### 3.3 Permissões do suporte

1. Saia e entre com `suporte@weega.com.br`. Cadastre o 2FA.
2. Resultado esperado: o suporte vê Clientes e Usuários, mas não vê os botões de criar e editar. O endereço `/staff/clientes/novo` mostra o erro 403.

### 3.4 Área do operador

1. Entre com `operador@hospital-exemplo.com.br` e complete o assistente de primeiro acesso (teste 2.1).
2. Resultado esperado: a área Cliente mostra só os equipamentos do Hospital Exemplo Norte. O endereço `/staff` mostra o erro 403.
3. Como administrador, desative o Hospital Exemplo Norte (Clientes › Editar dados).
4. Resultado esperado: o operador não consegue mais entrar.

### 3.5 Auditoria

1. Como administrador, abra **Log e auditoria**.
2. Resultado esperado: cada criação e alteração dos testes anteriores aparece com o usuário, a data e o IP. Em **Ver valores**, o painel mostra o valor antigo e o valor novo. Senhas aparecem só como `[alterado]`.

### 3.6 Convite do usuário novo (R-ACE-13)

O e-mail de desenvolvimento vai para o log, com o texto codificado. O comando abaixo monta o mesmo link do convite pendente.

1. Gere o link do convite. Troque `<e-mail>` pelo e-mail do usuário do teste 3.2:
   ```
   ./dev artisan tinker --execute '$u = App\Models\User::where("email", "<e-mail>")->sole(); echo URL::temporarySignedRoute("invitation.show", $u->invited_at->copy()->addDays(7), ["user" => $u->id, "sent" => $u->invited_at->getTimestamp()]);'
   ```
2. Abra o link numa janela anônima.
3. Resultado esperado: a tela **Criar a senha** mostra o nome e o e-mail do usuário.
4. Tente a senha `curta123456` (11 caracteres).
5. Resultado esperado: o painel recusa a senha.
6. Crie uma senha com 12 caracteres ou mais.
7. Resultado esperado: o login abre com a mensagem para entrar e cadastrar o 2FA. O selo **Convite pendente** sai da lista de usuários.
8. Abra o link de novo.
9. Resultado esperado: a tela mostra **Convite inválido**. O link vale uma vez.
10. Crie outro usuário. Na lista, clique em **Reenviar convite** e gere o link de novo (passo 1).
11. Resultado esperado: o link novo abre a tela **Criar a senha**. O link anterior mostra **Convite inválido**.

## 4. Licenças e equipamentos (E2 e E3)

### 4.1 Pacote de licenças

1. Abra o cliente do teste 3.1, aba **Licenças**. Adicione um pacote do sistema YC_PRT com quantidade 1.
2. Resultado esperado: o pacote aparece com 0 licenças em uso.

### 4.2 Ativação e registro pela API

Os comandos abaixo simulam o assistente de ativação do agente. Use o Git Bash.

1. Consulte as licenças livres:
   ```
   curl -s http://localhost:8000/api/v1/agent/activation/11222333000181
   ```
2. Resultado esperado: o sistema YC_PRT aparece com `"available":1`.
3. Registre um equipamento:
   ```
   curl -s -X POST http://localhost:8000/api/v1/agent/register -H "Accept: application/json" -H "Content-Type: application/json" -d '{"cnpj":"11222333000181","system":"YC_PRT","signature":"aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa","hostname":"SALA-TESTE"}'
   ```
4. Resultado esperado: a resposta traz `device_id`, `token` e `"status":"trial"`. Guarde o token.
5. Repita o registro com outra assinatura (troque os `a` por `b`).
6. Resultado esperado: o erro 409 diz que todas as licenças estão em uso.

### 4.3 Rotas do equipamento

Troque `<token>` pelo token do teste 4.2.

1. Teste o token e a licença:
   ```
   curl -s -i http://localhost:8000/api/v1/agent/ping -H "Authorization: Bearer <token>" -H "Accept: application/json"
   curl -s http://localhost:8000/api/v1/agent/license -H "Authorization: Bearer <token>" -H "Accept: application/json"
   ```
2. Resultado esperado: as respostas trazem os cabeçalhos `X-Yantra-Signature`, `X-Yantra-Timestamp` e `X-Yantra-Key-Id`. A licença mostra `"status":"trial"`.

### 4.4 Decisão da Staff Weega

1. Abra **Equipamentos**.
2. Resultado esperado: o SALA-TESTE aparece em trial. O menu mostra a quantidade de instalações que aguardam decisão.
3. Clique em **Bloquear** e consulte a licença de novo (teste 4.3).
4. Resultado esperado: a licença mostra `"status":"blocked"` e a mensagem da tela de bloqueio.
5. Clique em **Ativar**.
6. Resultado esperado: o equipamento fica ativo.

## 5. Versões do agente (E4)

1. Crie um arquivo de teste `C:\temp\YantraAgente-Setup.exe` com qualquer conteúdo.
2. Como administrador, abra **Versões do agente**. Publique a versão `2.1.0` no canal Piloto com esse arquivo.
3. Resultado esperado: a versão aparece na lista. O arquivo aparece em <http://localhost:8081>, na pasta `agent-releases/2.1.0/`.
4. Em **Equipamentos**, clique no canal do SALA-TESTE para mudar para piloto.
5. Consulte a atualização:
   ```
   curl -s "http://localhost:8000/api/v1/agent/updates/latest?current=2.0.0" -H "Authorization: Bearer <token>" -H "Accept: application/json"
   ```
6. Resultado esperado: a resposta traz a versão 2.1.0 e um `download_url`. O link baixa o arquivo durante 10 minutos.

Cuidado: o S3 local apaga os arquivos quando o container reinicia.

## 6. Exames e parâmetros (E5)

### 6.1 Importação do Yantra Cardio 3.0

1. Abra **Exames** e clique em **Importar do Yantra Cardio 3.0**.
2. Escolha o cliente do teste 3.1 e os arquivos de `C:\YANTRA-CARDIO\Queries\Eletro`.
3. Resultado esperado: o exame Eletro abre com os 5 SQLs, 6 leituras e a pasta `T:\Yantra\Eletrocardiogograma`.
4. Importe também a pasta `Teste`.
5. Resultado esperado: o exame Teste é importado. Esse modelo usa outro formato de arquivo, e o painel aceita os dois formatos.

### 6.2 Conexão do ERP e parametrização

1. No cliente, aba **Conexão do ERP**, preencha os dados e salve.
2. Resultado esperado: depois de salvar, o campo da senha fica vazio, com o aviso de que a senha está gravada.
3. Abra o equipamento SALA-TESTE (clique no nome em **Equipamentos**). Marque o exame Eletro e salve.
4. Consulte os parâmetros:
   ```
   curl -s -i http://localhost:8000/api/v1/agent/config -H "Authorization: Bearer <token>" -H "Accept: application/json"
   ```
5. Resultado esperado: a resposta traz o exame Eletro, os SQLs, a conexão e o cabeçalho `ETag` com a versão.
6. Repita a consulta com o cabeçalho `-H 'If-None-Match: "<versão>"'`.
7. Resultado esperado: a resposta é 304, sem corpo.

### 6.4 Versões do exame (R-EXA-10)

1. Como administrador, abra o exame Eletro (teste 6.1).
2. Resultado esperado: o card **Versões** mostra a versão atual com o selo **Atual**.
3. Mude uma linha do SQL de consulta e adicione uma leitura. Clique em **Salvar versão**.
4. Resultado esperado: o card **Versões** mostra uma versão nova, com a data e o seu nome.
5. Clique em **Ver diferenças** na versão nova.
6. Resultado esperado: a linha removida aparece em vermelho, com `−`. A linha nova aparece em verde, com `+`. A tabela de leituras mostra a leitura nova.
7. Volte e clique em **Ver diferenças** na versão anterior. Clique em **Restaurar a versão** e confirme.
8. Resultado esperado: o painel cria uma versão nova com o conteúdo da versão restaurada. A coluna Origem diz **Restaurada da versão** e o número da versão. As versões anteriores continuam no histórico.
9. Entre como `suporte@weega.com.br` e abra as diferenças de uma versão.
10. Resultado esperado: o suporte vê as diferenças, mas não vê o botão **Restaurar**.

### 6.3 Contrato da API

1. Abra <http://localhost:8000/api/docs>.
2. Resultado esperado: o Swagger mostra as 12 rotas da API do agente. Todas as rotas têm `x-yantra-status: implemented`.

## 7. Ciclo do exame (E6)

Os comandos abaixo simulam o agente do SALA-TESTE. Use o Git Bash. Troque `<token>` pelo token do teste 4.2.

Antes de começar:

1. Confira que o SALA-TESTE está ativo (teste 4.4) e tem o exame Eletro marcado (teste 6.2).
2. Entre como o operador criado no teste 3.2. Esse operador está vinculado ao cliente do teste 3.1.

### 7.1 Início do exame

1. Na área Cliente, clique em **Novo exame**. Escolha o SALA-TESTE e o exame Eletro. Clique em **Iniciar exame**.
2. Resultado esperado: a tela do exame abre com o status **Iniciando**.
3. Busque os comandos do equipamento:
   ```
   curl -s http://localhost:8000/api/v1/agent/commands -H "Authorization: Bearer <token>" -H "Accept: application/json"
   ```
4. Resultado esperado: a resposta traz um comando `start_exam` e `"next_poll_seconds":5`. Guarde o `id` do comando e o `exam_id`.
5. Repita a busca.
6. Resultado esperado: a lista vem vazia. O painel entrega cada comando uma vez.
7. Envie o resultado do comando. Troque `<comando>` pelo `id` do passo 4:
   ```
   curl -s -i -X POST http://localhost:8000/api/v1/agent/commands/<comando>/result -H "Authorization: Bearer <token>" -H "Accept: application/json" -H "Content-Type: application/json" -d '{"success":true,"data":{"temp_folder":"C:\capturas\Eletro\1"}}'
   ```
8. Resultado esperado: a resposta é 204. Em até 5 segundos, a tela do exame mostra o status **Capturando**.
9. Envie o mesmo resultado de novo.
10. Resultado esperado: a resposta é 409.

Cuidado: o comando de início expira em 5 minutos. Depois disso, o resultado recebe 410 e o exame fica com falha.

### 7.2 Envio do preview

1. Envie um PDF de teste. Troque `<exame>` pelo `exam_id` do teste 7.1:
   ```
   echo "%PDF-1.7 teste" > /c/temp/ecg.pdf
   curl -s -X POST http://localhost:8000/api/v1/agent/exams/<exame>/files -H "Authorization: Bearer <token>" -H "Accept: application/json" -F kind=pdf -F sha256=$(sha256sum /c/temp/ecg.pdf | cut -d' ' -f1) -F file=@/c/temp/ecg.pdf
   ```
2. Resultado esperado: a resposta é 201 com o `id` do arquivo. A tela do exame mostra o preview e o status **Em conferência**. O arquivo aparece em <http://localhost:8081>, na pasta `previews/`.
3. Repita o envio com `-F sha256=` seguido de 64 zeros.
4. Resultado esperado: a resposta é 422. O painel recusa um arquivo com o SHA-256 diferente.

### 7.3 Consulta de pacientes

1. Na tela do exame, preencha os filtros e clique em **Consultar no Tasy**.
2. Busque os comandos (teste 7.1, passo 3).
3. Resultado esperado: a resposta traz um comando `query_patients` com os valores dos filtros.
4. Envie o resultado da consulta:
   ```
   curl -s -X POST http://localhost:8000/api/v1/agent/commands/<comando>/result -H "Authorization: Bearer <token>" -H "Accept: application/json" -H "Content-Type: application/json" -d '{"success":true,"data":{"columns":["PACIENTE","ATENDIMENTO"],"rows":[{"PACIENTE":"PACIENTE EXEMPLO 1","ATENDIMENTO":"4815162"}]}}'
   ```
5. Resultado esperado: a tela do exame mostra a tabela com o paciente.

### 7.4 Gravação do laudo

1. Na tela do exame, escolha o paciente e marque o arquivo. Digite o usuário e a senha do Tasy. Clique em **Gravar laudo**.
2. Resultado esperado: o status muda para **Gravando**. O campo da senha fica vazio.
3. Busque os comandos.
4. Resultado esperado: o comando `record_report` traz o paciente, os arquivos e a senha do Tasy. No banco, a coluna `payload` fica criptografada.
5. Envie o resultado da gravação. Troque `<arquivo>` pelo `id` do teste 7.2:
   ```
   curl -s -X POST http://localhost:8000/api/v1/agent/commands/<comando>/result -H "Authorization: Bearer <token>" -H "Accept: application/json" -H "Content-Type: application/json" -d '{"success":true,"data":{"reports":[{"file_id":"<arquivo>","final_path":"T:\Yantra\ECG-1.pdf","erp_report_id":88421}]}}'
   ```
6. Resultado esperado: o exame fica **Concluído**. O preview some do S3. O comando não guarda mais a senha.
7. Abra **Histórico**.
8. Resultado esperado: o exame aparece com o paciente. Um exame de outro cliente não aparece.

### 7.5 Cancelamento

1. Inicie outro exame (teste 7.1, passos 1 a 8) e envie um preview (teste 7.2).
2. Na tela do exame, clique em **Cancelar exame**.
3. Resultado esperado: o exame fica **Cancelado** e o preview some do S3. A busca de comandos traz um comando `cancel_exam`.

## 8. Captura de tela (E7)

Os comandos abaixo simulam o assistente de teclas do agente. Só o sistema Yantra Screen Capture (YO SRC) usa a combinação de teclas (R-EXA-11).

### 8.1 Combinação de teclas pelo agente (R-EXA-11)

1. Grave uma combinação com o token do SALA-TESTE (teste 4.2), que é do sistema YC_PRT:
   ```
   curl -s -X PUT http://localhost:8000/api/v1/agent/settings/hotkey -H "Authorization: Bearer <token>" -H "Accept: application/json" -H "Content-Type: application/json" -d '{"hotkey":"Ctrl+Y"}'
   ```
2. Resultado esperado: a resposta é 409. O sistema do equipamento não usa a captura de tela.
3. No cliente do teste 3.1, aba **Licenças**, adicione um pacote do sistema YO_SRC com quantidade 1.
4. Registre um equipamento YO SRC:
   ```
   curl -s -X POST http://localhost:8000/api/v1/agent/register -H "Accept: application/json" -H "Content-Type: application/json" -d '{"cnpj":"11222333000181","system":"YO_SRC","signature":"cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc","hostname":"RECEPCAO-TESTE"}'
   ```
5. Resultado esperado: a resposta traz o `token`. Nos passos abaixo, troque `<token-src>` por esse token.
6. Grave uma combinação sem Ctrl, Alt ou Win:
   ```
   curl -s -X PUT http://localhost:8000/api/v1/agent/settings/hotkey -H "Authorization: Bearer <token-src>" -H "Accept: application/json" -H "Content-Type: application/json" -d '{"hotkey":"Shift+Y"}'
   ```
7. Resultado esperado: a resposta é 422. A mensagem pede Ctrl, Alt ou Win e uma tecla.
8. Grave a combinação em minúsculas:
   ```
   curl -s -X PUT http://localhost:8000/api/v1/agent/settings/hotkey -H "Authorization: Bearer <token-src>" -H "Accept: application/json" -H "Content-Type: application/json" -d '{"hotkey":"shift + ctrl + y"}'
   ```
9. Resultado esperado: a resposta traz `"hotkey":"Ctrl+Shift+Y"` e a versão nova dos parâmetros.
10. Abra o equipamento RECEPCAO-TESTE em **Equipamentos**.
11. Resultado esperado: o campo **Teclas da captura de tela** mostra `Ctrl+Shift+Y`.
12. Consulte os parâmetros do SALA-TESTE (teste 6.2, passo 4).
13. Resultado esperado: a resposta traz `"hotkey":null`.

### 8.2 Teclas pelo gestor do hospital (R-ACE-14)

Só o sistema Yantra Screen Capture (YO SRC) usa a combinação de teclas. O seeder cria o equipamento `RECEPCAO-01` com esse sistema.

1. Entre com `gestor@hospital-exemplo.com.br` e complete o assistente de primeiro acesso (teste 2.1).
2. Resultado esperado: a área Cliente abre. O menu mostra **Gestão › Configurações**.
3. Abra **Configurações**.
4. Resultado esperado: a lista mostra só o `RECEPCAO-01`, com `Ctrl+Y`. Os equipamentos do sistema de impressora (`CARDIO-SALA-02`) não aparecem.
5. Digite `Shift+Y` e clique em **Salvar**.
6. Resultado esperado: o painel recusa a combinação.
7. Digite `alt + f9` e clique em **Salvar**.
8. Resultado esperado: o painel grava `Alt+F9`.
9. Entre com `operador@hospital-exemplo.com.br` e abra <http://localhost:8000/app/configuracoes>.
10. Resultado esperado: o painel mostra o erro 403. O operador não vê o menu **Configurações**.
11. Como administrador, abra o equipamento `CARDIO-SALA-02` em **Equipamentos**.
12. Resultado esperado: a parametrização não mostra o campo **Teclas da captura de tela**, porque o sistema não é o YO SRC.

### 8.3 Captura na conferência

1. Inicie um exame e envie um PDF (testes 7.1 e 7.2).
2. Envie uma imagem PNG como captura de tela. Troque `<exame>` pelo `exam_id`:
   ```
   curl -s -o /c/temp/captura.png https://placehold.co/800x450.png
   curl -s -X POST http://localhost:8000/api/v1/agent/exams/<exame>/files -H "Authorization: Bearer <token>" -H "Accept: application/json" -F kind=png -F sha256=$(sha256sum /c/temp/captura.png | cut -d' ' -f1) -F file=@/c/temp/captura.png
   ```
3. Resultado esperado: a resposta é 201. Na tela do exame, a lista de arquivos mostra o PDF e a captura.
4. Clique na captura.
5. Resultado esperado: o preview mostra a imagem. O PDF continua na lista para a gravação.

## 9. Porta serial (E8)

### 9.1 Regras e teste das regras (R-EXA-12)

1. Como administrador, abra o exame Eletro (teste 6.1).
2. No card **Regras da porta serial**, clique em **Adicionar regra**. Digite o campo `FC` e a regra `FC:\s*(\d+)`.
3. Adicione a regra `PA` com `PA:\s*(\d+/\d+)`.
4. Adicione a regra `X` com `X(?=:)` e clique em **Salvar versão**.
5. Resultado esperado: o painel recusa a regra `X`, porque o motor RE2 do agente não aceita lookahead.
6. Remova a regra `X` e salve.
7. Resultado esperado: o card **Versões** mostra uma versão nova. **Ver diferenças** mostra as 2 regras novas.
8. No card **Testar as regras da porta serial**, cole o conteúdo:
   ```
   PACIENTE: TESTE
   FC: 72 bpm
   PA: 120/80 mmHg
   ```
9. Resultado esperado: a tabela mostra `FC` = `72` e `PA` = `120/80`. O teste não grava nada.
10. Consulte os parâmetros do SALA-TESTE (teste 6.2, passo 4).
11. Resultado esperado: o exame Eletro traz `serial_rules` com as 2 regras.

### 9.2 Dados da porta serial no exame

1. Inicie um exame Eletro no SALA-TESTE (teste 7.1).
2. Envie os dados extraídos. Troque `<exame>` pelo `exam_id`:
   ```
   curl -s -i -X POST http://localhost:8000/api/v1/agent/exams/<exame>/data -H "Authorization: Bearer <token>" -H "Accept: application/json" -H "Content-Type: application/json" -d '{"fields":{"FC":"72","PA":"120/80"}}'
   ```
3. Resultado esperado: a resposta é 204. Em até 5 segundos, a tela do exame mostra o card **Dados da porta serial** com `FC` e `PA`, e o status **Em conferência**.
4. Envie `{"fields":{"fc":"72"}}`.
5. Resultado esperado: a resposta é 422. O nome do campo usa letras maiúsculas, números e "_".
6. Se o exame tem uma informação complementar com o marcador `FC`, confira o campo.
7. Resultado esperado: o campo mostra `72`. Um valor que o operador digitou antes não muda.
