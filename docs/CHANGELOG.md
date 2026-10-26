# CHANGELOG

Este arquivo registra as mudanças do Yantra Painel (administrativo e API do agente). O formato segue o [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/). As etapas (E0 a E9) são as do [Plano do Agente Yantra](https://claude.ai/artifact/6tFWcGJUTSMYdythF65BgK). Os códigos de regra (R-ACE, R-LIC, R-EXA, R-SEG) são os da [especificação do painel](https://claude.ai/artifact/NsD8esQXyYemLH9ZZ4RNBA).

## [Não publicado]

### Adicionado

- Pasta `docs/` com este CHANGELOG, o roteiro de testes e o contrato OpenAPI.
- Senha com no mínimo 12 caracteres (R-ACE-8).
- A recuperação de senha mostra a mesma mensagem para qualquer e-mail (R-ACE-9).
- Ciclo do exame (E6): o operador inicia o exame, consulta os pacientes, grava o laudo no ERP e conclui ou cancela o exame.
- Rotas `GET /api/v1/agent/commands` e `POST /api/v1/agent/commands/{id}/result`. O painel entrega cada comando uma vez e pede uma nova busca a cada 5 segundos com um exame aberto, ou a cada 30 segundos sem exame.
- Rota `POST /api/v1/agent/exams/{id}/files` para o preview do exame no S3, com o SHA-256 conferido (R-EXA-6, R-SEG-6).
- A senha do Tasy vai só no comando criptografado. O painel apaga a senha quando recebe o resultado ou quando o comando expira.
- O painel apaga os previews no fim do exame e a cada hora apaga os previews com mais de 24 horas (R-EXA-7).
- Tarefa `commands:expire`, a cada minuto: um início expirado põe o exame em falha, e uma gravação expirada devolve o exame para revisão.
- Telas do operador: Novo exame, tela do exame com o preview e Histórico.
- Convite do usuário novo, válido por 7 dias (R-ACE-13). Um convite novo invalida o link anterior. A lista de usuários mostra o convite pendente e o botão **Reenviar convite**.
- Histórico das versões do exame (R-EXA-10). Cada gravação e cada importação guardam uma cópia da versão. A tela **Diferenças da versão** mostra os SQLs linha a linha, as leituras e os dados alterados.
- O administrador restaura uma versão antiga. A restauração cria uma versão nova, e o histórico nunca perde versões.
- Captura de tela (E7): rota `PUT /api/v1/agent/settings/hotkey`, em que o assistente do agente grava a combinação de teclas aceita pelo Windows.
- A combinação de teclas tem Ctrl, Alt ou Win e uma tecla (R-EXA-11). O painel grava a combinação no formato canônico, por exemplo `Ctrl+Shift+Y`.
- A conferência do exame mostra a captura de tela (PNG) ao lado dos PDFs.
- A combinação padrão da captura de tela é `Ctrl+Y` (R-EXA-11). Só o sistema Yantra Screen Capture (YO SRC) usa a combinação: nos outros sistemas, os parâmetros trazem `hotkey` null, e a rota `PUT /settings/hotkey` responde 409.
- Papel **Gestor** (R-ACE-14): usuário do hospital que usa a área Cliente, como o operador. A tela **Configurações** da área Cliente é só do gestor: ele troca a combinação de teclas dos equipamentos YO SRC dos clientes vinculados.
- O seeder cria o gestor `gestor@hospital-exemplo.com.br` e o equipamento YO SRC `RECEPCAO-01`.
- Comando `./dev clock`: compara a hora do container com a hora da internet. O código do 2FA depende da hora.
- Assistente de primeiro acesso em `/primeiro-acesso` (R-ACE-15), com 4 passos: senha nova, 2FA com os códigos de recuperação, tema e "Tudo pronto". Antes do fim do assistente, nenhuma tela do painel abre. O convidado começa no passo 2, porque já criou a senha no convite. Depois que o administrador reinicia o 2FA, o usuário volta ao passo 2.
- Recarregar o assistente mantém o QR code já lido pelo celular.
- Porta serial (E8): o cadastro do exame tem o card **Regras da porta serial**, com o campo e a regra regex de cada dado. As regras entram no histórico das versões do exame.
- O painel aceita só a sintaxe que o motor RE2 do agente aceita (R-EXA-12). O valor extraído é o primeiro grupo entre parênteses ou, sem grupo, o trecho inteiro.
- Card **Testar as regras da porta serial**: o usuário cola um conteúdo recebido e vê o valor de cada regra, sem gravar nada.
- Rota `POST /api/v1/agent/exams/{id}/data`: o agente envia os dados extraídos. Um envio novo junta os campos aos campos já recebidos.
- Rota `PUT /api/v1/agent/settings/serial-port`: o assistente da porta COM do agente grava no painel a porta que achou, o nome do adaptador e o resultado do teste de comunicação. Sem a rota, o próximo `GET /config` trocaria a porta escolhida no computador pela porta antiga do painel.
- Só o sistema de porta serial (YO HUB) usa a porta COM (R-EXA-13). Nos outros sistemas, a rota responde 409, e os parâmetros trazem `serial_port` null. A tela do equipamento mostra a porta, o adaptador e o resultado do teste só no YO HUB. Uma troca manual da porta apaga o adaptador e o teste da porta anterior.
- A tela do exame mostra o card **Dados da porta serial**. Um dado preenche a informação complementar com o mesmo marcador, se o operador ainda não mudou o valor.
- Atalhos `dev` (Git Bash) e `dev.bat` (cmd e PowerShell) para os comandos do Docker: `up`, `artisan`, `test`, `pint`, `assets`, `psql`, `fresh` e outros. Veja a lista com `./dev help`.
- Teste automático do cadastro do 2FA com um código TOTP real.

### Alterado

- O primeiro cadastro do 2FA saiu da tela de Segurança e foi para o assistente de primeiro acesso. A tela de Segurança não mostra mais o botão "Continuar para o painel".
- O contrato OpenAPI mudou de `resources/openapi/` para `docs/openapi/`. O endereço `/api/docs` continua o mesmo.
- O usuário novo cria a senha pelo link do convite (7 dias), e não mais pelo link de troca de senha (60 minutos).
- As regras R-ACE-7 a R-EXA-11 da seção 5.1 da especificação foram confirmadas em 26/10/2026.
- O roteiro de testes e o `CLAUDE.md` usam os atalhos `dev`.

### Corrigido

- Segurança: numa tela já aberta, as ações do Livewire não conferiam de novo se o usuário estava ativo, se tinha o papel certo e se tinha o 2FA. Um usuário desativado continuava a usar a tela até recarregar a página. Agora o Livewire reaplica esses 3 middlewares em cada ação.
- Nas telas de login, 2FA, convite e senha, o botão Entrar ficava ativo e sem o ícone de carregamento depois do clique. Agora o botão fica desativado e mostra o ícone até a página seguinte abrir.
- No ambiente de desenvolvimento, o PHP-FPM não gravava o `laravel.log` nem as views compiladas ("Permission denied"). O artisan criava esses arquivos como root, e o PHP-FPM rodava como `www-data`. Agora o PHP-FPM do container de desenvolvimento roda como root. O erro aparecia na tela Aparência e impedia a confirmação do código do 2FA.

## E5 · Exames, conexão do ERP e parametrização — 26/10/2026

Commit [`662ea77`](https://github.com/patake/YantraPainelControle/commit/662ea77).

### Adicionado

- Cadastro de exames no modelo do Yantra Cardio 3.0: 5 SQLs, leituras, pasta de destino e opções de impressão. Cada gravação cria uma versão nova. Só o administrador altera (R-EXA-3).
- Importação dos arquivos `EXAME.json` e `PARAMETROS.json` do Yantra Cardio 3.0.
- Conexão do ERP por cliente, com a senha criptografada, e o botão de teste da conexão.
- Parametrização do equipamento: pasta das capturas, credencial de rede, teclas, porta serial e exames habilitados.
- Rota `GET /api/v1/agent/config` com os parâmetros assinados. A rota responde 304 quando o agente já tem a versão atual.
- Um equipamento bloqueado ou com o trial expirado recebe 403 nas rotas de trabalho.

## E4 · Atualização automática — 26/10/2026

Commit [`99bfa02`](https://github.com/patake/YantraPainelControle/commit/99bfa02).

### Adicionado

- Tela Versões do agente: envio do instalador `.exe` (até 200 MB) para o S3, em `agent-releases/<versão>/`.
- Canais piloto e estável. O administrador promove uma versão do canal piloto para o estável.
- Canal de atualização por equipamento.
- Rota `GET /api/v1/agent/updates/latest` com o link assinado do S3, válido por 10 minutos.

## E3 · Licenciamento por sistema — 26/10/2026

Commit [`842ab53`](https://github.com/patake/YantraPainelControle/commit/842ab53).

### Adicionado

- Pacotes de licenças por cliente e sistema. O pacote não fica menor que o uso atual (R-LIC-9).
- Tela Sistemas e licenças, com o nome e a cor de cada sistema.
- Rota `GET /api/v1/agent/license` com o estado da licença assinado.

## E2 · Identidade do equipamento e registro — 25/10/2026

Commit [`5371a90`](https://github.com/patake/YantraPainelControle/commit/5371a90).

### Adicionado

- Rota `GET /api/v1/agent/activation/{cnpj}`: sistemas e licenças livres do cliente.
- Rota `POST /api/v1/agent/register`: registro em trial de 7 dias e token do equipamento.
- Limite de 10 tentativas por hora por IP na ativação (R-LIC-8).
- Reinstalação no mesmo cliente com token novo (R-LIC-7) e registro de suspeita de clone (R-LIC-6).
- Tela de equipamentos com ativação e bloqueio pela Staff Weega (R-LIC-4).
- Expiração automática dos trials a cada 5 minutos, no serviço `scheduler` (R-LIC-3).

## E1 · Base do administrativo — 25/10/2026

Commit [`4715c57`](https://github.com/patake/YantraPainelControle/commit/4715c57).

### Adicionado

- Livewire 4, Flux UI e Fortify, com as telas do kit Livewire do Laravel 13 em português.
- 2FA obrigatório para todos (R-ACE-5). Depois de 5 códigos errados, o login fica bloqueado por 15 minutos (R-ACE-7).
- Usuário inativo e operador sem cliente ativo não entram (R-ACE-6).
- Área Staff Weega (`/staff`) e área Cliente (`/app`).
- Cadastros de clientes (com validação do CNPJ) e de usuários, com o vínculo do operador aos clientes.
- Auditoria automática com valor antigo e valor novo, sem segredos (R-SEG-4 e R-SEG-5).
- Telas de auditoria e de requisições da API.

## E0 · Fundação e contrato da API — 25/10/2026

Commits [`11d6ec5`](https://github.com/patake/YantraPainelControle/commit/11d6ec5), [`ca78c9f`](https://github.com/patake/YantraPainelControle/commit/ca78c9f), [`ccf30a6`](https://github.com/patake/YantraPainelControle/commit/ccf30a6) e [`8a805fe`](https://github.com/patake/YantraPainelControle/commit/8a805fe).

### Adicionado

- Projeto Laravel 13 com PHP 8.5.12 no Docker: PHP-FPM, filas, nginx e PostgreSQL 18.
- S3 local com o LocalStack e o navegador de arquivos em `http://localhost:8081`.
- Modelo de dados com 17 tabelas.
- Token do equipamento (Sanctum) e assinatura Ed25519 das respostas da API do agente (R-SEG-1 e R-SEG-2).
- Log de cada requisição da API (R-SEG-3).
- Contrato OpenAPI 3.1 com as 11 rotas da API do agente, em `/api/docs`.
