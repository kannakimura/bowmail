<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

// Laravel 11以降はBaseTestCase内でcreateApplication()が実装済みのため
// CreatesApplicationトレイトやcreateApplication()の明示的な定義は不要
abstract class TestCase extends BaseTestCase
{
    //
}
