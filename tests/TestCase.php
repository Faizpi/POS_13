<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Support\BuildsTransactionFixtures;

abstract class TestCase extends BaseTestCase
{
    use BuildsTransactionFixtures;
}
