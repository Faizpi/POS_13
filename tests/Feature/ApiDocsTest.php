<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiDocsTest extends TestCase
{
    public function test_api_docs_page_and_json_are_available(): void
    {
        $this->get(route('api.docs'))
            ->assertOk()
            ->assertSee('Hibiscus Efsya POS')
            ->assertSee('API Documentation');

        $response = $this->get(route('api.docs.json'))
            ->assertOk()
            ->assertJsonPath('openapi', '3.0.0')
            ->assertJsonPath('info.title', 'Hibiscus Efsya POS API');

        $this->assertArrayHasKey('/login', $response->json('paths'));
        $this->assertArrayHasKey('/penjualan', $response->json('paths'));
    }

    public function test_postman_collection_download_is_available(): void
    {
        $this->get(route('api.docs.download.postman'))
            ->assertOk()
            ->assertHeader('content-type', 'application/json')
            ->assertHeader('content-disposition', 'attachment; filename="hibiscusefsya-postman.json"')
            ->assertJsonPath('info.name', 'Hibiscus Efsya POS API v1');
    }
}
