# Changes to upstream FreeScout files

Each line: file, what changed, and why no hook could do it. Keep this list short; check it after every upstream merge.

| File | Change | Why |
|---|---|---|
| `config/app.php` | Registers `App\RoundRobin\Purelymail\PurelymailServiceProvider` and `App\RoundRobin\Brand\BrandServiceProvider` | Our code ships in the image (not `/data/Modules`), so it must be registered as an app provider |
| `config/modules.php` | Module cache key carries a hash of `.rr-version` | Blue and green share `/data/storage` during a rollout. With one key, green loaded blue's cached module list, which named a module only blue had, and failed to start (2026-11-03 cutover, caught by the health check) |
