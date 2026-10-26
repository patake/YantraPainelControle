# Roteiro de testes

Este roteiro confere à mão o que o painel já faz (E0 a E7). Cada teste tem os passos e o resultado esperado. A suíte automática (seção 1) cobre as mesmas regras e mais casos de erro.

## 0. Preparação

1. Abra o Docker Desktop.
2. Na pasta `C:\YantraPainelControle`, suba o ambiente:
   ```
   docker compose up -d
   ```
3. Compile as telas:
   ```
   docker compose run --rm node npm run build
   ```
4. Recrie o banco com os dados de exemplo:
   ```
   docker compose exec -T app php artisan migrate:fresh --seed
   ```
5. Instale no celular um aplicativo autenticador, por exemplo o Microsoft Authenticator ou o Google Authenticator.

Cuidado: o passo 4 apaga todos os dados do banco de desenvolvimento.

Usuários de exemplo, todos com a senha `password`:

| E-mail | Papel |
|---|---|
| `admin@weega.com.br` | Administrador |
| `suporte@weega.com.br` | Suporte |
| `operador@hospital-exemplo.com.br` | Operador do Hospital Exemplo Norte |

Endereços:

| Endereço | O que abre |
|---|---|
| <http://localhost:8000> | Painel |
| <http://localhost:8000/api/docs> | Contrato da API do agente (Swagger) |
| <http://localhost:8081> | Arquivos do S3 local |

## 1. Suíte automática

1. Rode todos os testes:
   ```
   docker compose exec -T app php artisan test --compact
   ```
2. Resultado esperado: todos os testes passam. A suíte leva cerca de 1 minuto e meio.

## 2. Acesso (E1)

### 2.1 Primeiro login com cadastro do 2FA

1. Abra <http://localhost:8000> e entre com `admin@weega.com.br` e `password`.
2. Resultado esperado: o painel abre a tela de Segurança, com o aviso de que o 2FA é obrigatório.
3. Clique em **Cadastrar o 2FA**, leia o QR code com o aplicativo e digite o código.
4. Resultado esperado: o painel mostra os códigos de recuperação. Guarde um código para o teste 2.3.
5. Clique em **Continuar para o painel**.
6. Resultado esperado: a área Staff Weega abre na tela Início.

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
   docker compose exec -T app grep -o "http://localhost:8000/reset-password/[^ ]*" storage/logs/laravel.log | tail -1
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

1. Entre com `operador@hospital-exemplo.com.br` e cadastre o 2FA.
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
   docker compose exec -T app php artisan tinker --execute '$u = App\Models\User::where("email", "<e-mail>")->sole(); echo URL::temporarySignedRoute("invitation.show", $u->invited_at->copy()->addDays(7), ["user" => $u->id, "sent" => $u->invited_at->getTimestamp()]);'
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
2. Resultado esperado: o Swagger mostra as 12 rotas da API do agente. As rotas implementadas têm `x-yantra-status: implemented`.

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

Os comandos abaixo simulam o assistente de teclas do agente. Troque `<token>` pelo token do teste 4.2.

### 8.1 Combinação de teclas (R-EXA-11)

1. Grave uma combinação sem Ctrl, Alt ou Win:
   ```
   curl -s -X PUT http://localhost:8000/api/v1/agent/settings/hotkey -H "Authorization: Bearer <token>" -H "Accept: application/json" -H "Content-Type: application/json" -d '{"hotkey":"Shift+Y"}'
   ```
2. Resultado esperado: a resposta é 422. A mensagem pede Ctrl, Alt ou Win e uma tecla.
3. Grave a combinação em minúsculas:
   ```
   curl -s -X PUT http://localhost:8000/api/v1/agent/settings/hotkey -H "Authorization: Bearer <token>" -H "Accept: application/json" -H "Content-Type: application/json" -d '{"hotkey":"shift + ctrl + y"}'
   ```
4. Resultado esperado: a resposta traz `"hotkey":"Ctrl+Shift+Y"` e a versão nova dos parâmetros.
5. Abra o equipamento SALA-TESTE em **Equipamentos**.
6. Resultado esperado: o campo **Teclas da captura de tela** mostra `Ctrl+Shift+Y`.
7. Consulte os parâmetros (teste 6.2, passo 4).
8. Resultado esperado: a resposta traz `"hotkey":"Ctrl+Shift+Y"`.

### 8.2 Captura na conferência

1. Inicie um exame e envie um PDF (testes 7.1 e 7.2).
2. Envie uma imagem PNG como captura de tela. Troque `<exame>` pelo `exam_id`:
   ```
   curl -s -o /c/temp/captura.png https://placehold.co/800x450.png
   curl -s -X POST http://localhost:8000/api/v1/agent/exams/<exame>/files -H "Authorization: Bearer <token>" -H "Accept: application/json" -F kind=png -F sha256=$(sha256sum /c/temp/captura.png | cut -d' ' -f1) -F file=@/c/temp/captura.png
   ```
3. Resultado esperado: a resposta é 201. Na tela do exame, a lista de arquivos mostra o PDF e a captura.
4. Clique na captura.
5. Resultado esperado: o preview mostra a imagem. O PDF continua na lista para a gravação.
