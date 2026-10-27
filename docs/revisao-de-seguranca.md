# Revisão de segurança (E9)

Data: 27/10/2026. Escopo: o Yantra Painel (este repositório) e o Yantra Agente (`C:\YantraAgente`). A revisão leu o código, rodou os auditores de dependências e criou testes automáticos de ataque. Não houve teste de invasão externo, porque a produção na AWS ainda não existe.

## 1. Resultado por tipo de ataque

| Ataque | Onde foi conferido | Resultado | Teste automático |
|---|---|---|---|
| SQL injection no painel | Todas as consultas. As 3 com SQL bruto usam variável de ligação ou texto fixo. Buscas com `' OR 1=1 --`, `DROP TABLE`, `%`, `_` e `\` | Sem falha | `SecurityAttacksTest` |
| SQL injection no Tasy pelo agente | O agente liga os valores do operador e do paciente como variáveis (`:y1`). Só os parâmetros internos, definidos pela Staff, entram como texto | Sem falha de injeção. Achada uma falha de integridade (item 2.1) | `TestTheOperatorValuesDoNotChangeThePatient` |
| XSS guardado | Nome do cliente, do usuário, do equipamento, do paciente (ERP), do arquivo (agente) e dados da porta serial. As telas usam `{{ }}`. O único `{!! !!}` é o QR code do 2FA, gerado pelo Fortify | Sem falha | `SecurityAttacksTest` |
| Injeção de código e comandos | Sem `eval`, `unserialize`, `exec`, `Process` ou `include` com dado do usuário no painel. O agente não monta comando do Windows com dado externo | Sem falha | — |
| Arquivo disfarçado | Envio do agente: PNG como `pdf`, PHP como `pdf`, HTML como texto, SVG como `png` | Falha corrigida (item 2.2) | `SecurityAttacksTest` |
| Travessia de pasta | Painel: o nome no S3 é um UUID. Agente: o nome do paciente passa pelo `SafeName` | Falha menor corrigida (item 2.5) | `TestFinalPathNamesAndNumbersTheFile` |
| Acesso a outro cliente (IDOR) | Exame, arquivos do laudo, equipamentos do gestor, comandos e arquivos do agente | Sem falha | Testes de cada tela e de cada rota |
| Escalada de papel | Suporte não cria nem altera usuários (política `UserPolicy`). Operador não abre a área Staff | Sem falha | `UsersTest`, `SecurityAttacksTest` |
| Token do agente × sessão do painel | O token do equipamento não abre o painel. A sessão de um usuário não usa a API do agente | Sem falha | `SecurityAttacksTest` |
| Força bruta no login | 5 tentativas por minuto por e-mail e IP. 2FA obrigatório: 5 códigos errados bloqueiam por 15 minutos | Sem falha | `SecurityAttacksTest`, `TwoFactorTest` |
| Excesso de pedidos de senha | O Laravel só limitava o mesmo e-mail | Falha corrigida (item 2.3) | `SecurityAttacksTest` |
| Escalada local no agente | API local, pastas do serviço, atualização | 2 falhas corrigidas (itens 2.6 e 2.7) | `config_test.go`, roteiro 10.1 e 10.2 |
| Sigilo em trânsito | HTTPS, HSTS e assinatura Ed25519 das respostas | Falha corrigida (item 2.8) | `client_test.go`, `SecurityHeadersTest` |
| SSRF e XXE | O painel não chama URLs informadas pelo usuário e não lê XML | Não se aplica | — |
| Dependências | `composer audit`, `npm audit` e `govulncheck`: nenhuma falha (o `golang.org/x/sys` foi para a 0.44, item 3.2) | Sem falha | — |

## 2. Falhas corrigidas

1. **Laudo no paciente errado.** O navegador mandava marcadores fora das Informações complementares, por exemplo `NR_ATENDIMENTO`. O agente trocava o dado do paciente, e o laudo ia para outro prontuário. Agora o painel só envia as Informações complementares do exame, e o agente não deixa um valor do operador trocar um dado do paciente.
2. **Arquivo de outro tipo.** O painel aceitava qualquer tipo permitido para qualquer `kind`, por exemplo um PNG como `pdf`. Agora o conteúdo precisa ser do tipo informado.
3. **E-mails de senha em massa.** "Esqueci a senha" agora aceita 5 pedidos por minuto por IP. O limite continua com o cache de rotas.
4. **IP do balanceador.** Atrás do balanceador da AWS, o limite de ativações por IP valeria para todos os clientes juntos. A variável `TRUSTED_PROXIES` corrige isso.
5. **Nome reservado do Windows.** Um paciente chamado `CON`, `NUL` ou `COM1` virava um dispositivo do Windows na pasta final. Agora o nome recebe `_` na frente.
6. **Configuração do serviço pela API local.** Qualquer usuário do Windows lê o token local e trocava o painel, a chave, o assinante da atualização ou o SumatraPDF. Com isso, rodava um programa como SYSTEM. Agora a API local muda só os campos da impressora.
7. **Pastas do serviço.** Os usuários criavam arquivos nas pastas do serviço, por exemplo o instalador baixado. Agora só o SYSTEM e os administradores gravam nessas pastas.
8. **HTTP sem TLS.** O agente aceitava `http://` para um painel fora do computador. Agora o HTTPS é obrigatório. O painel manda HSTS e os cabeçalhos de segurança em todas as respostas.

## 3. Pontos que pedem decisão

1. **Ativação só com o CNPJ.** O CNPJ é público. Qualquer pessoa com o instalador registrava equipamentos falsos no cliente e ocupava as licenças em trial. **Aprovado e feito em 27/10/2026:** a ativação pede também o código de ativação do cliente (R-LIC-11).
2. **`golang.org/x/sys` 0.33.** A falha GO-2026-5024 estava numa função que o agente não chama. **Aprovado e feito em 27/10/2026:** o agente usa a 0.44, e o `govulncheck` não acha mais falhas.
3. **Pacotes abandonados.** O `laravel-lang` usa os pacotes `dragon-code/*`, marcados como abandonados, sem falha conhecida. Acompanhe uma versão nova do `laravel-lang`.
4. **Teste de invasão.** Depois da produção na AWS, faça um teste de invasão externo (OWASP ZAP ou uma empresa), com o painel no endereço real.

## 4. Como repetir

1. Painel: `./dev test --filter=SecurityAttacksTest` e `./dev composer audit`.
2. Telas: `docker compose run --rm --no-deps node npm audit --omit=dev`.
3. Agente: `cd service && go test ./... && go run golang.org/x/vuln/cmd/govulncheck@latest ./...`.
4. Testes manuais: seção 10 dos roteiros do painel e do agente.
