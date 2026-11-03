# Round Robin Inbox

Round Robin Solutions' fork of [FreeScout](https://github.com/freescout-help-desk/freescout), running at https://inbox.roundrobin.in. It is the team inbox and the one place to manage every Round Robin mail account. [Purelymail](https://purelymail.com) is the mail host and the source of truth, reached through its API (https://news.purelymail.com/api/index.html).

Licence: AGPL-3.0, like FreeScout. This repository is the source for the running service.

## Layout

| Path | What |
|---|---|
| `app/RoundRobin/Purelymail/` | Purelymail client, accounts, one password (dashboard password = mail password), Login & password page, Gmail-like aliases |
| `app/RoundRobin/Brand/` | Round Robin look (hooks only). `tools/build_recolor.py` regenerates `public/rr/brand/css/recolor.css` from FreeScout's CSS bundle |
| `public/rr/` | Our static files (fonts, icons, CSS, JS) |
| `app/RoundRobin/PATCHES.md` | Every change to an upstream file, with the reason |
| `Containerfile` | Image: nfrastack's FreeScout runtime with this tree |

Rule: new code goes in `app/RoundRobin/` or `public/rr/`. An upstream file is changed only where FreeScout has no hook, and each change is listed in `PATCHES.md`. Upstream never ships a folder with these names, so merges don't conflict there.

## Branches and versions

- `main`: upstream release tag plus our commits. Releases are tagged `<upstream tag>-rr.<n>`, for example `1.8.249-rr.1`.
- Remote `upstream` = freescout-help-desk/freescout. Release tags come from its `dist` branch, which includes `vendor/`.
- `dist` and `master` in this fork mirror upstream and are never committed to.

## Taking an upstream release

```bash
git fetch upstream --tags
git switch -c upstream/1.8.250 main
git merge --no-ff 1.8.250            # merge, never rebase: nothing is force-pushed
git diff 1.8.250..HEAD --stat -- . ':!app/RoundRobin' ':!public/rr'   # our core patch, keep it small
```

Then resolve any conflict (behaviour choices go to Rahul), open a PR, let CI test, build and boot the image on a scratch database, merge, tag and deploy. Security releases are taken the same day; others weekly. Where we patch only because a hook is missing, offer the hook upstream; once it is accepted, our patch becomes a hook in `app/RoundRobin/`.

## Database changes

Each blue-green colour runs `artisan migrate` on start while the other is still live, so migrations are additive only: new tables or nullable columns. Removing or renaming anything takes two releases.

## Build and deploy

The image builds on RR CI to `ghcr.io/round-robin-solutions/round-robin-inbox` (linux/amd64 and linux/arm64). Production compose pins a tag, and `rr-rollout inbox` deploys it with no downtime. Host files and the runbook are kept outside this repository.

Secrets never live here. The Purelymail API token is a file mounted at `/run/secrets/purelymail_token`.
