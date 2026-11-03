# Changes to upstream FreeScout files

Each line: file, what changed, and why no hook could do it. Keep this list short; check it after every upstream merge.

| File | Change | Why |
|---|---|---|
| `config/app.php` | Registers `App\RoundRobin\Purelymail\PurelymailServiceProvider` and `App\RoundRobin\Brand\BrandServiceProvider` | Our code ships in the image (not `/data/Modules`), so it must be registered as an app provider |
