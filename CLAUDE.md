# Yantra Painel (administrativo)

Esta máquina não tem PHP, Composer, Node nem PostgreSQL. Todo o ambiente roda em Docker (`docker-compose.yml`).

A documentação fica em `docs/`. Em cada mudança, atualize o `docs/CHANGELOG.md` (seção "Não publicado") e, quando a mudança cria uma função nova, o `docs/roteiro-de-testes.md`. Uma regra nova que não está na especificação entra na seção "Regras novas" da especificação, com o estado "Pendente".

Use o atalho `./dev` (Git Bash) ou `dev.bat` (cmd e PowerShell) para os comandos do Docker. Fora de um terminal, o `./dev` acrescenta o `-T` ao `exec`. Veja a lista com `./dev help`.

1. Suba o ambiente com `./dev up`.
2. Rode os comandos PHP no container `app`: `./dev artisan ...`, `./dev composer ...`, `./dev pint`. Qualquer outro comando vai direto para o `docker compose`, por exemplo `./dev exec -T app php -v`.
3. Acesse o painel em <http://localhost:8000>. O PostgreSQL 18 responde em localhost:5432 (banco `yantra_painel`, usuário `yantra`).
4. Use o disco `s3` do Laravel para os arquivos. Em produção, o disco aponta para o bucket da AWS (`sa-east-1`). Em desenvolvimento, aponta para o LocalStack em <http://localhost.localstack.cloud:4566> (bucket `yantra-painel`, criado por `docker/localstack/init-s3.sh`). Veja os arquivos no navegador em <http://localhost:8081> (s3manager).

Cuidado: o LocalStack community não confere permissões e não guarda os objetos depois de reiniciar. Teste no LocalStack só o fluxo de gravação e leitura. A regra de acesso privado só vale no S3 da AWS.

Os testes usam o banco PostgreSQL `yantra_painel_test`. Rode a suíte com `./dev test` (aceita `--filter=...` e caminhos). Rode o Pint com `./dev pint`: o atalho passa as pastas `app bootstrap/app.php config database routes tests`, porque o container não tem Git e a opção `--dirty` não funciona. Não passe a pasta `bootstrap` inteira, porque o Pint altera os arquivos de `bootstrap/cache`.

No container de desenvolvimento, o PHP-FPM roda como root (`docker/php/fpm-dev.conf`), igual ao artisan, à fila e ao agendador. Sem isso, um arquivo criado pelo artisan bloqueia o PHP-FPM com "Permission denied" no `laravel.log` e nas views compiladas.

Telas (Livewire 4 com Flux UI gratuito e Fortify):

1. As páginas ficam em `resources/views/pages/` como componentes de arquivo único (`pages::staff.clients.index`). A área Staff fica em `/staff` (administrador e suporte) e a área Cliente em `/app` (operador).
2. Compile as telas com `./dev assets`. Para recarga automática, use `./dev --profile dev up -d node`.
3. Escreva os textos das telas em português, dentro de `__()`. Os textos em inglês do kit têm tradução em `lang/pt_BR.json`.
4. O 2FA é obrigatório. Nos testes, use `User::factory()->withTwoFactor()`. O operador precisa de um cliente ativo: use `User::factory()->operatorWithClient()`.
5. O seeder cria `admin@weega.com.br`, `suporte@weega.com.br`, `operador@hospital-exemplo.com.br` e `gestor@hospital-exemplo.com.br`, todos com a senha `password`. O gestor é um usuário do hospital: usa a área Cliente e a tela Configurações (`User::factory()->managerWithClient()`). No primeiro login, cada um cadastra o 2FA.
6. Os modelos com o trait `Auditable` gravam a auditoria sozinhos. Para uma ação sem evento de modelo, como vincular clientes, chame `$model->writeAudit()`.

API do agente:

1. O contrato OpenAPI fica em `docs/openapi/agent-v1.yaml` e abre em <http://localhost:8000/api/docs>. Atualize o contrato junto com cada rota nova e troque `x-yantra-status` para `implemented`. Um teste confere esse campo.
2. O agente usa um token Sanctum do equipamento (`Device`), com a habilidade `agent`. As rotas autenticadas usam os middlewares `auth:sanctum`, `agent` e `agent.signed`.
3. As respostas são assinadas com Ed25519 sobre `<timestamp>.<corpo>`. Crie a chave de desenvolvimento com `php artisan yantra:signing-key --write`.
4. As regras de negócio têm códigos (R-ACE, R-LIC, R-EXA, R-SEG) na especificação do painel. Cite o código no comentário do código e no teste que confere a regra.

O bucket tem duas pastas: `previews/<cliente>/<exame>/` (apagada no fim do exame e, como segunda garantia, pela regra de ciclo de vida de 1 dia) e `agent-releases/<versão>/` (instaladores do agente).

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.5. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.
- Activate the `deploying-to-cloud` skill whenever deploying to Laravel Cloud, configuring Cloud environments or resources, using the Cloud CLI, or troubleshooting Cloud deployments.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This project uses PHPUnit. Create tests with `php artisan make:test --phpunit {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/phpunit` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

</laravel-boost-guidelines>
