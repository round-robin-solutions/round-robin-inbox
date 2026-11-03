# Round Robin Inbox (inbox.roundrobin.in): nfrastack's FreeScout runtime with this fork's code.
# nfrastack's image copies /container/data/freescout/install to the webroot on every start, then runs migrations,
# so swapping that folder is all a fork needs. Their init, scheduler, nginx and env handling stay unchanged.
# Base pinned by digest (multi-arch: linux/amd64 and linux/arm64). See ROUNDROBIN.md.
ARG BASE_IMAGE=docker.io/nfrastack/freescout:2.2.23@sha256:1b22ddbc4f044ad838a885d091980554f9e9c477a006106bb3bca23f9728e5e9
FROM ${BASE_IMAGE}

ARG RR_REVISION=unknown
ARG RR_VERSION=dev

COPY . /container/data/freescout/install.rr
RUN set -e; \
    cd /container/data/freescout; \
    rm -rf install; \
    mv install.rr install; \
    cd install; \
    sed -i "s/env('APP_LOG', 'daily')/env('APP_LOG', 'single')/" config/app.php; \
    mkdir -p vendor/natxet/cssmin/src vendor/rap2hpoutre/laravel-log-viewer/src/controllers; \
    composer install --ignore-platform-reqs --no-interaction --no-progress; \
    php artisan freescout:build; \
    rm -rf .editorconfig .env.travis .git* .htaccess .travis.yml tests Containerfile .dockerignore; \
    printf '%s %s\n' "${RR_VERSION}" "${RR_REVISION}" > .rr-version

LABEL \
    org.opencontainers.image.title="Round Robin Inbox" \
    org.opencontainers.image.description="FreeScout fork with Purelymail account management (Round Robin Solutions)" \
    org.opencontainers.image.source="https://github.com/round-robin-solutions/round-robin-inbox" \
    org.opencontainers.image.licenses="AGPL-3.0" \
    org.opencontainers.image.revision="${RR_REVISION}" \
    org.opencontainers.image.version="${RR_VERSION}"
