<?php

namespace App\RoundRobin\Purelymail;

use Illuminate\Support\ServiceProvider;

class PurelymailServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->mergeConfigFrom(__DIR__.'/Config/config.php', 'purelymail');
        if (!$this->app->routesAreCached()) {
            require __DIR__.'/Http/routes.php';
        }
        $this->loadViewsFrom(__DIR__.'/Resources/views', 'purelymail');

        // One password: the dashboard password is the Purelymail email password (sign-in, change, reset).
        $this->app['router']->pushMiddlewareToGroup('web', \App\RoundRobin\Purelymail\Http\Middleware\EmailPassword::class);

        // Manage menu: link under "Mailboxes", admins only.
        \Eventy::addAction('menu.manage.after_mailboxes', function () {
            if (auth()->user() && auth()->user()->isAdmin()) {
                echo '<li class="'.\App\Misc\Helper::menuSelectedHtml('purelymail').'"><a href="'
                    .route('purelymail.index').'">'.__('Create inbox (Purelymail)').'</a></li>';
            }
        });
        // FreeScout's own "New Mailbox" button: put the address on Purelymail and connect it straight away.
        \App\Mailbox::created(function (\App\Mailbox $mailbox) {
            if (app()->runningInConsole() || !\App\RoundRobin\Purelymail\Services\Provisioner::eligible($mailbox)) {
                return;
            }
            try {
                $password = app(\App\RoundRobin\Purelymail\Services\Provisioner::class)->provision($mailbox);
                \Session::flash('flash_warning', $password
                    ? __(':address was created on Purelymail and connected. Webmail password (shown once, save it in the team password manager): :password', ['address' => $mailbox->email, 'password' => $password])
                    : __(':address is connected to its Purelymail inbox.', ['address' => $mailbox->email]));
            } catch (\Exception $e) {
                \Helper::logException($e, '[Purelymail] auto-provision '.$mailbox->email);
                \Session::flash('flash_error', __('Could not set :address up on Purelymail: :error. Use Manage » Create inbox (Purelymail) to retry.', ['address' => $mailbox->email, 'error' => $e->getMessage()]));
            }
        });

        // Profile sidebar: "Login & password" (personal email for resets; admins can reset others).
        \Eventy::addAction('user.profile.menu.after_profile', function ($user) {
            $me = auth()->user();
            if ($me && ($me->isAdmin() || $me->id === $user->id)) {
                $active = \Route::currentRouteName() === 'purelymail.login' ? ' class="active"' : '';
                echo '<li'.$active.'><a href="'.route('purelymail.login', ['id' => $user->id]).'"><i class="glyphicon glyphicon-lock"></i> '
                    .e(__('Login & password')).'</a></li>';
            }
        });

        \Eventy::addFilter('menu.selected', function ($menu) {
            $menu['purelymail'] = ['purelymail.index'];

            return $menu;
        });
    }

    public function register()
    {
    }
}
