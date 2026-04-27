<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Tells RefreshDatabase to pass --drop-types, needed for PostgreSQL ENUM cleanup between test runs. */
    protected bool $dropTypes = true;
}
