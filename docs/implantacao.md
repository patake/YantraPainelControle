# Implantação do Yantra Painel na AWS (rascunho)

Estado: **rascunho para revisão**. Este documento propõe a produção do painel na AWS, região `sa-east-1` (São Paulo). Nenhum recurso foi criado ainda. Confirme as decisões da seção 6 antes de criar a conta e os recursos.

## 1. Arquitetura proposta

```text
Agentes (hospitais) e navegadores
            │ HTTPS 443
            ▼
  Route 53  ─►  Application Load Balancer (certificado ACM)
                      │ HTTP 80, só a partir do balanceador
                      ▼
              EC2 (Docker Compose): web (nginx), app (PHP-FPM), queue, scheduler
                      │                         │
                      ▼                         ▼
          RDS PostgreSQL 18              S3 yantra-painel (privado, SSE-S3)
          (backup automático)            previews/ (1 dia) e agent-releases/
                      │
                      ▼
              CloudWatch (logs, métricas e alarmes) ─► SNS (e-mail da Weega)
```

| Recurso | Proposta | Motivo |
|---|---|---|
| Servidor | 1 EC2 `t3.small` (2 vCPU, 2 GB), Amazon Linux 2023, Docker Compose | O volume é baixo: poucos hospitais, um agente a cada 5 ou 30 segundos. Usa a mesma imagem do desenvolvimento |
| Balanceador | Application Load Balancer com certificado gratuito do ACM | HTTPS sem renovação manual. Permite trocar o servidor sem mudar o DNS |
| Banco | RDS PostgreSQL 18, `db.t4g.micro`, 20 GB gp3, criptografia ligada | Backup automático e atualização de segurança pela AWS |
| Arquivos | Bucket S3 privado `yantra-painel`, SSE-S3, sem acesso público | Decisão de 2026-10-25. O ciclo de vida apaga `previews/` em 1 dia |
| E-mail | Amazon SES em `sa-east-1` | Convites e recuperação de senha |
| Segredos | AWS Systems Manager Parameter Store (SecureString) | A chave do Laravel, a senha do banco e a chave Ed25519 não ficam no disco do servidor nem no Git |
| Acesso ao S3 | Perfil IAM da instância EC2 | Sem chave de acesso fixa no `.env` |
| Monitoramento | CloudWatch Logs, métricas e alarmes, com aviso por SNS | Seção 4 |

Custo estimado por mês, sem impostos: EC2 cerca de US$ 20, ALB cerca de US$ 22, RDS cerca de US$ 17, S3, SES e CloudWatch abaixo de US$ 10. Total aproximado: US$ 70. Confira os preços na calculadora da AWS antes de decidir.

## 2. Variáveis de ambiente da produção

| Variável | Valor | Observação |
|---|---|---|
| `APP_ENV` | `production` | |
| `APP_DEBUG` | `false` | Com `true`, um erro mostra o código e as variáveis na tela |
| `APP_URL` | `https://painel.<domínio>` | |
| `APP_KEY` | Parameter Store | Gere uma vez com `php artisan key:generate --show`. Uma chave nova invalida as senhas cifradas do ERP e da rede |
| `TRUSTED_PROXIES` | `*` | O balanceador termina o HTTPS (revisão de segurança da E9) |
| `SESSION_SECURE_COOKIE` | `true` | O cookie da sessão só vai por HTTPS |
| `SESSION_ENCRYPT` | `true` | |
| `DB_*` | Endereço do RDS e usuário próprio do painel | Use SSL: `DB_SSLMODE=require` |
| `FILESYSTEM_DISK` | `s3` | |
| `AWS_BUCKET`, `AWS_DEFAULT_REGION` | `yantra-painel`, `sa-east-1` | Sem `AWS_ACCESS_KEY_ID`, sem `AWS_ENDPOINT`: o perfil IAM da instância dá o acesso |
| `MAIL_MAILER` | `ses` | |
| `LOG_CHANNEL` | `stderr` | O Docker envia o log ao CloudWatch |
| `YANTRA_SIGNING_KEY_ID`, `YANTRA_SIGNING_SECRET_KEY` | Parameter Store | Chave Ed25519 nova, só da produção (`php artisan yantra:signing-key`) |

### 2.1 Instalador do agente

Cuidado: um agente instalado sem o endereço e a chave da produção fala com o painel de desenvolvimento.

1. Com o painel no ar, abra `https://painel.<domínio>/api/v1/agent/public-key` e copie `public_key` e `key_id`.
2. No repositório do agente, gere o instalador com `-PanelUrl https://painel.<domínio>/api/v1/agent -PanelPublicKey <public_key> -PanelKeyId <key_id>` (README do agente).
3. Publique o instalador na área Staff, em **Versões do agente**.

### 2.2 Imagens de produção

O arquivo `docker/production/Dockerfile` gera duas imagens a partir da raiz do projeto:

| Imagem | Conteúdo |
|---|---|
| `yantra-painel-app` | PHP-FPM 8.5 com o código, as dependências sem as de desenvolvimento, as telas compiladas e o OPcache. Roda como `www-data`. Na partida, cria o cache da configuração, das rotas, dos eventos e das views |
| `yantra-painel-web` | nginx com `public/` e as telas compiladas. Envia o PHP ao container `app` |

1. Gere as imagens:

   ```bash
   docker build -f docker/production/Dockerfile --target app -t yantra-painel-app:<versão> .
   docker build -f docker/production/Dockerfile --target web -t yantra-painel-web:<versão> .
   ```

2. Envie as imagens ao Amazon ECR, ou gere as imagens na própria EC2.
3. Na EC2, grave o `.env.production` com as variáveis da seção 2.
4. Suba os serviços: `YANTRA_APP_IMAGE=yantra-painel-app:<versão> YANTRA_WEB_IMAGE=yantra-painel-web:<versão> docker compose -f docker-compose.prod.yml up -d`.
5. Rode as migrations: `docker compose -f docker-compose.prod.yml exec app php artisan migrate --force`.

Em 2026-10-27, as duas imagens foram geradas e testadas no Docker Desktop, ligadas ao banco de desenvolvimento: `/up`, o login, a API do agente e as telas compiladas responderam 200. A página de erro não mostrou detalhes internos, e o processo rodou como `www-data`.

## 3. Passos da implantação

1. Crie a conta da AWS da Weega e ative o MFA no usuário raiz.
2. Registre o domínio no Route 53, ou aponte o domínio atual para o Route 53.
3. Peça o certificado no ACM para `painel.<domínio>`, com validação por DNS.
4. Crie a VPC com duas sub-redes públicas (balanceador) e duas sub-redes privadas (EC2 e RDS).
5. Crie o RDS PostgreSQL 18 nas sub-redes privadas, com backup de 14 dias e proteção contra exclusão.
6. Crie o bucket S3 com o bloqueio de acesso público, a SSE-S3 e a regra de ciclo de vida de `previews/` (1 dia).
7. Crie o perfil IAM da EC2: leitura e gravação no bucket, leitura dos parâmetros `/yantra/*` e envio de logs ao CloudWatch.
8. Grave os segredos no Parameter Store (seção 2).
9. Crie a EC2 na sub-rede privada. Instale o Docker e o agente do CloudWatch. O acesso ao servidor fica pelo Session Manager, sem porta SSH aberta.
10. Gere as imagens de produção e suba os serviços `web`, `app`, `queue` e `scheduler` (seção 2.2).
11. Rode `php artisan migrate --force` no container `app` e `php artisan db:seed --class=SystemSeeder --force`.
12. Crie o balanceador com o certificado, o redirecionamento de HTTP para HTTPS e a verificação de saúde em `/up`.
13. Confirme o domínio no SES e saia da sandbox do SES.
14. Crie o primeiro administrador. Troque a senha e configure o 2FA no primeiro acesso.
15. Rode o roteiro de testes do painel em produção, com um equipamento de teste.

## 4. Monitoramento e alertas

| Alarme | Condição | Ação |
|---|---|---|
| Painel fora do ar | Verificação de saúde do balanceador falha por 2 minutos | E-mail à Weega |
| Erros 5xx | Mais de 10 respostas 5xx em 5 minutos | E-mail |
| Agentes sem contato | Nenhuma chamada a `/api/v1/agent/*` em 15 minutos, em horário comercial | E-mail |
| Banco | CPU acima de 80% por 15 minutos ou espaço livre abaixo de 2 GB | E-mail |
| Servidor | CPU acima de 80% por 15 minutos ou disco acima de 80% | E-mail |
| Fila | Jobs com falha (`failed_jobs`) | E-mail diário |

O log do painel vai ao CloudWatch Logs, com retenção de 90 dias. O painel guarda o log das chamadas da API e a auditoria no banco (R-SEG-3).

## 5. Pendências antes da produção

1. **Backup dos arquivos.** O S3 guarda só os previews (apagados em 1 dia) e os instaladores. Ligue o versionamento do bucket para `agent-releases/`.
2. **Teste de restauração.** Restaure um backup do RDS numa instância de teste, uma vez antes do piloto e depois a cada 6 meses.
3. **Instalador do agente.** Gere o instalador de produção depois que o painel estiver no ar (seção 2.1).

## 6. Decisões para a Weega

1. **Servidor:** EC2 com Docker Compose (proposta) ou ECS Fargate. O Fargate dispensa a manutenção do servidor, mas custa mais e muda a implantação.
2. **Domínio:** qual domínio o painel usa, por exemplo `painel.yantra.com.br`.
3. **Alta disponibilidade:** RDS em uma zona (proposta, cerca de US$ 17) ou Multi-AZ (cerca do dobro). Com uma zona, uma falha da zona deixa o painel fora do ar até a restauração. Os agentes continuam a funcionar sem o painel por até 7 dias (R-LIC).
4. **E-mail de alerta:** qual endereço recebe os alarmes.
