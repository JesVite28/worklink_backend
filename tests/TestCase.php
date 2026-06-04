<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * @var \App\Models\User|null
     */
    protected $user;

    /**
     * Proxy helper for JSON POST requests added by Laravel.
     *
     * @param mixed $uri
     * @param array $data
     * @param array $headers
     * @param int $options
     * @return \Illuminate\Testing\TestResponse
     */
    public function postJson($uri, array $data = [], array $headers = [], $options = 0)
    {
        return parent::postJson($uri, $data, $headers, $options);
    }

}
