<?php

namespace Tests\Unit;

use App\RoundRobin\Purelymail\Services\Addresses;
use Tests\TestCase;

class ProfileEmailsTest extends TestCase
{
    public function testSignInMustBeOnOurDomains()
    {
        $this->assertNull(Addresses::signInProblem('kabir.israni@roundrobin.in'));
        $this->assertNull(Addresses::signInProblem('Info@RoundRobin.in'));
        $this->assertNotNull(Addresses::signInProblem('kabirisrani04work@gmail.com'));
        $this->assertNotNull(Addresses::signInProblem(''));
    }
}
