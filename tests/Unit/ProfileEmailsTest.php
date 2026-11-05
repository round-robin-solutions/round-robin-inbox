<?php

namespace Tests\Unit;

use App\RoundRobin\Purelymail\Services\Addresses;
use App\User;
use Tests\TestCase;

class ProfileEmailsTest extends TestCase
{
    public function testRoundRobinEmailIsTheSignInEmailOnOurDomain()
    {
        $user = new User(['email' => 'Kabir.Israni@roundrobin.in']);
        $user->id = 3;
        $this->assertTrue(Addresses::workIsSignIn($user));
        $this->assertSame('kabir.israni@roundrobin.in', Addresses::work($user));
    }

    public function testWorkProblemOnlyRejectsOtherDomains()
    {
        $this->assertNull(Addresses::workProblem(''));
        $this->assertNull(Addresses::workProblem('a@roundrobin.in'));
        $this->assertNotNull(Addresses::workProblem('a@gmail.com'));
    }
}
