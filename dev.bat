@echo off
:: Atalho para os comandos do Docker do Yantra Painel (cmd e PowerShell)
:: Uso: dev <comando> [argumentos]

SETLOCAL
SET COMPOSE=docker compose -f docker-compose.yml
SET PINT_PATHS=app bootstrap/app.php config database routes tests

:: ARGS recebe os argumentos depois do comando. O %%ARGS:*%1=%% mantem o "=" de opcoes como --filter=Login.
SET ARGS=%*
IF NOT "%~1"=="" CALL SET ARGS=%%ARGS:*%1=%%

IF "%~1"=="" GOTO help

IF "%~1"=="up"       GOTO up
IF "%~1"=="up:build" GOTO up_build
IF "%~1"=="build"    GOTO build
IF "%~1"=="down"     GOTO down
IF "%~1"=="restart"  GOTO restart
IF "%~1"=="ps"       GOTO ps
IF "%~1"=="logs"     GOTO logs
IF "%~1"=="shell"    GOTO shell
IF "%~1"=="artisan"  GOTO artisan
IF "%~1"=="tinker"   GOTO tinker
IF "%~1"=="composer" GOTO composer
IF "%~1"=="test"     GOTO test
IF "%~1"=="pint"     GOTO pint
IF "%~1"=="npm"      GOTO npm
IF "%~1"=="assets"   GOTO assets
IF "%~1"=="psql"     GOTO psql
IF "%~1"=="migrate"  GOTO migrate
IF "%~1"=="fresh"    GOTO fresh
IF "%~1"=="opcache"  GOTO opcache
IF "%~1"=="help"     GOTO help

:: Passthrough: qualquer outro subcomando docker compose
%COMPOSE% %*
GOTO end

:up
%COMPOSE% up -d
GOTO end

:up_build
%COMPOSE% build --no-cache %ARGS%
%COMPOSE% up -d
GOTO end

:build
%COMPOSE% build %ARGS%
GOTO end

:down
%COMPOSE% down
GOTO end

:restart
%COMPOSE% restart %ARGS%
GOTO end

:ps
%COMPOSE% ps
GOTO end

:logs
%COMPOSE% logs -f %ARGS%
GOTO end

:shell
:: A imagem e Alpine: tem sh, nao tem bash.
%COMPOSE% exec app sh
GOTO end

:artisan
%COMPOSE% exec app php artisan %ARGS%
GOTO end

:tinker
%COMPOSE% exec app php artisan tinker
GOTO end

:composer
%COMPOSE% exec app composer %ARGS%
GOTO end

:test
%COMPOSE% exec app php artisan test --compact %ARGS%
GOTO end

:pint
%COMPOSE% exec app vendor/bin/pint %PINT_PATHS% --format agent %ARGS%
GOTO end

:npm
%COMPOSE% run --rm node npm %ARGS%
GOTO end

:assets
%COMPOSE% run --rm node npm run build
GOTO end

:psql
%COMPOSE% exec db psql -U yantra -d yantra_painel %ARGS%
GOTO end

:migrate
%COMPOSE% exec app php artisan migrate %ARGS%
GOTO end

:fresh
%COMPOSE% exec app php artisan migrate:fresh --seed
GOTO end

:opcache
%COMPOSE% kill -s SIGUSR2 app
echo OPcache do app recarregado.
GOTO end

:help
echo.
echo Uso: dev ^<comando^> [argumentos]
echo.
echo Comandos gerais:
echo   up              Sobe todos os containers em background
echo   up:build        Rebuild sem cache e sobe os containers
echo   build [servico] Build com cache (opcional: nome do servico)
echo   down            Para e remove os containers
echo   restart [serv.] Reinicia os containers
echo   ps              Lista os containers em execucao
echo   logs [servico]  Exibe os logs (todos ou de um servico)
echo.
echo PHP / Laravel (container app):
echo   shell           Abre o shell (sh) no app
echo   artisan ^<cmd^>   Executa php artisan ^<cmd^>
echo   tinker          Abre o tinker
echo   composer ^<cmd^>  Executa composer ^<cmd^>
echo   test [args]     Executa php artisan test --compact (ex.: dev test --filter=Login)
echo   pint            Formata o codigo com o Pint
echo.
echo Telas (container node):
echo   npm ^<cmd^>       Executa npm ^<cmd^>
echo   assets          Compila as telas (npm run build)
echo.
echo Banco de dados:
echo   psql [args]     Abre o psql no banco yantra_painel
echo   migrate         Executa as migrations
echo   fresh           Recria o banco com os dados de exemplo (apaga todos os dados)
echo.
echo Performance:
echo   opcache         Recarrega o OPcache do php-fpm
echo.
echo Qualquer outro comando vai direto para o docker compose (ex.: dev exec app php -v).
echo.
echo Exemplos:
echo   dev up
echo   dev artisan route:list --path=api
echo   dev test tests/Feature/Auth
echo   dev logs app
echo.

:end
ENDLOCAL
