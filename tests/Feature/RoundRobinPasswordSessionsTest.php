<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\RoundRobin\Purelymail\Services\EmailPassword;
use App\RoundRobin\Purelymail\Services\PurelymailApi;
use App\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * FreeScout 1.8.250 ends a person's other sessions when their password hash changes (AuthenticateSession).
 * These cover the fork's one-password flows (Http/Middleware/EmailPassword, Http/Controllers/LoginController)
 * on top of that: Purelymail and the dashboard stay in step, and other sessions end only when the password
 * really changed. Purelymail is faked; nothing here reaches the network.
 */
class RoundRobinPasswordSessionsTest extends TestCase
{
    private $api;
    private $passwords;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
        \Option::set('mail_driver', 'log');

        $this->api = new class() extends PurelymailApi {
            public $calls = [];
            public $fail = false;

            public function __construct()
            {
            }

            public function users()
            {
                return [];
            }

            public function setPassword($address, $password)
            {
                if ($this->fail) {
                    throw new \RuntimeException('Purelymail setPassword failed (test)');
                }
                $this->calls[] = [$address, $password];
            }
        };
        $this->passwords = new class($this->api) extends EmailPassword {
            public $verdict = true;

            public function backed($email)
            {
                return true;
            }

            public function check($email, $password)
            {
                return $this->verdict;
            }
        };
        $this->app->instance(PurelymailApi::class, $this->api);
        $this->app->instance(EmailPassword::class, $this->passwords);
    }

    private function person($password, $role = User::ROLE_USER)
    {
        return factory(User::class)->create([
            'email'    => 'rr.test.'.strtolower(Str::random(10)).'@roundrobin.in',
            'password' => Hash::make($password),
            'role'     => $role,
            'status'   => User::STATUS_ACTIVE,
        ]);
    }

    /** A session on another device that started while $hash was the stored password hash. */
    private function otherSession(User $user, $hash)
    {
        return $this->actingAs($user->fresh())
            ->withSession(['password_hash' => $hash])
            ->get(route('users.profile', ['id' => $user->id]));
    }

    public function testForgotPasswordResetSetsPurelymailAndEndsOtherSessions()
    {
        $user = $this->person('Old-pass-1234');
        $old = $user->password;
        $token = \Password::broker()->createToken($user);

        $this->post('/password/reset', [
            'token'                 => $token,
            'email'                 => $user->email,
            'password'              => 'New-pass-5678',
            'password_confirmation' => 'New-pass-5678',
        ])->assertStatus(302);

        $this->assertSame([[$user->email, 'New-pass-5678']], $this->api->calls);
        $this->assertTrue(Hash::check('New-pass-5678', $user->fresh()->password));
        $this->otherSession($user, $old)->assertRedirect(route('login'));
    }

    public function testForgotPasswordResetPutsOldPasswordBackWhenPurelymailFails()
    {
        $user = $this->person('Old-pass-1234');
        $old = $user->password;
        $token = \Password::broker()->createToken($user);
        $this->api->fail = true;

        $this->post('/password/reset', [
            'token'                 => $token,
            'email'                 => $user->email,
            'password'              => 'New-pass-5678',
            'password_confirmation' => 'New-pass-5678',
        ])->assertRedirect(route('login'));

        $this->assertSame($old, $user->fresh()->password);
        $this->otherSession($user, $old)->assertStatus(200);
    }

    public function testAdminResetEndsThePersonsSessionsAndKeepsTheAdmins()
    {
        $admin = $this->person('Admin-pass-1234', User::ROLE_ADMIN);
        $user = $this->person('Old-pass-1234');
        $old = $user->password;

        $this->actingAs($admin)
            ->withSession(['password_hash' => $admin->password])
            ->post(route('purelymail.login.reset', ['id' => $user->id]), [])
            ->assertRedirect(route('purelymail.login', ['id' => $user->id]));

        $this->assertCount(1, $this->api->calls);
        $this->assertSame($user->email, $this->api->calls[0][0]);
        $this->assertTrue(Hash::check($this->api->calls[0][1], $user->fresh()->password));
        $this->get(route('users.profile', ['id' => $admin->id]))->assertStatus(200);
        $this->otherSession($user, $old)->assertRedirect(route('login'));
    }

    public function testSignInWithTheSamePasswordKeepsOtherSessions()
    {
        $user = $this->person('Same-pass-1234');
        $old = $user->password;

        $this->post('/'.trim(config('app.login_path'), '/'), ['email' => $user->email, 'password' => 'Same-pass-1234'])
            ->assertStatus(302);

        $this->assertSame($old, $user->fresh()->password);
        $this->otherSession($user, $old)->assertStatus(200);
    }

    public function testSignInWithAPasswordChangedInWebmailEndsOtherSessions()
    {
        $user = $this->person('Old-pass-1234');
        $old = $user->password;

        $this->post('/'.trim(config('app.login_path'), '/'), ['email' => $user->email, 'password' => 'Webmail-pass-5678'])
            ->assertStatus(302);

        $this->assertTrue(Hash::check('Webmail-pass-5678', $user->fresh()->password));
        $this->otherSession($user, $old)->assertRedirect(route('login'));
    }

    public function testProfileChangeSetsPurelymailKeepsThisSessionAndEndsOthers()
    {
        $user = $this->person('Old-pass-1234');
        $old = $user->password;

        $this->actingAs($user)
            ->withSession(['password_hash' => $old])
            ->post(route('users.password.save', ['id' => $user->id]), [
                'password_current'      => 'Old-pass-1234',
                'password'              => 'New-pass-5678',
                'password_confirmation' => 'New-pass-5678',
            ])->assertRedirect(route('users.profile', ['id' => $user->id]));

        $this->assertSame([[$user->email, 'New-pass-5678']], $this->api->calls);
        $this->get(route('users.profile', ['id' => $user->id]))->assertStatus(200);
        $this->otherSession($user, $old)->assertRedirect(route('login'));
    }
}
