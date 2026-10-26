<?php

namespace Tests\Feature\Http\Controllers;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

class ApiDocsControllerTest extends TestCase
{
    public function test_serves_the_openapi_contract_with_every_agent_route(): void
    {
        $response = $this->get(route('api.openapi'))->assertOk();

        $this->assertStringStartsWith('application/yaml', (string) $response->headers->get('Content-Type'));

        /** @var BinaryFileResponse $file */
        $file = $response->baseResponse;
        $spec = Yaml::parse((string) file_get_contents($file->getFile()->getPathname()));

        $this->assertSame('3.1.0', $spec['openapi']);
        $this->assertSame([
            '/public-key', '/ping', '/activation/{cnpj}', '/register', '/license', '/config',
            '/commands', '/commands/{command}/result', '/exams/{exam}/files', '/exams/{exam}/data',
            '/updates/latest',
        ], array_keys($spec['paths']));
    }

    public function test_marks_only_the_routes_that_exist_as_implemented(): void
    {
        $spec = Yaml::parseFile(base_path('docs/openapi/agent-v1.yaml'));
        $routes = collect(app('router')->getRoutes()->getRoutes())->map->uri();

        foreach ($spec['paths'] as $path => $operations) {
            foreach ($operations as $operation) {
                $uri = 'api/v1/agent'.$path;
                $implemented = $operation['x-yantra-status'] === 'implemented';

                $this->assertSame($implemented, $routes->contains($uri), "Estado errado no contrato para {$path}.");
            }
        }
    }

    public function test_renders_the_documentation_page(): void
    {
        $this->get(route('api.docs'))
            ->assertOk()
            ->assertSee(json_encode(route('api.openapi')), false);
    }
}
