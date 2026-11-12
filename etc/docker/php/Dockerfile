# Git 2.56.1 fixes the November 2026 Git security advisories. Debian's older
# packages are still affected; build the verified upstream release on the same
# base as the runtime so its HTTPS helper uses compatible shared libraries.
FROM php:8.5-cli AS git-build
RUN apt-get update \
    && apt-get install --no-install-recommends --yes \
        build-essential ca-certificates curl libcurl4-openssl-dev libexpat1-dev \
        libpcre2-dev libssl-dev perl xz-utils zlib1g-dev \
    && curl --fail --silent --show-error --location \
        https://www.kernel.org/pub/software/scm/git/git-2.56.1.tar.xz --output /tmp/git.tar.xz \
    && echo 'f0b0bf89264b9bd2e1c640363e7529643989da76281600473c50745cd34a607f  /tmp/git.tar.xz' | sha256sum --check --strict \
    && tar --extract --xz --file /tmp/git.tar.xz --directory /tmp \
    && make -C /tmp/git-2.56.1 -j2 prefix=/usr/local \
        NO_RUST=YesPlease NO_GETTEXT=YesPlease NO_TCLTK=YesPlease USE_LIBPCRE2=YesPlease all \
    && make -C /tmp/git-2.56.1 prefix=/usr/local \
        NO_RUST=YesPlease NO_GETTEXT=YesPlease NO_TCLTK=YesPlease USE_LIBPCRE2=YesPlease \
        DESTDIR=/opt/git install

FROM php:8.5-cli

COPY --from=git-build /opt/git/usr/local/ /usr/local/

# Fail closed if an upstream tag or local override supplies an affected interpreter.
RUN php -r 'exit(PHP_MAJOR_VERSION === 8 && PHP_MINOR_VERSION === 5 && version_compare(PHP_VERSION, "8.5.12", ">=") ? 0 : 1);'

COPY --from=composer:2.8.12 /usr/bin/composer /usr/local/bin/composer

# Set working directory
WORKDIR /var/www

# Install dependencies
RUN apt-get update \
    && apt-get install -y --allow-downgrades \
        --allow-remove-essential --allow-change-held-packages \
        ca-certificates \
        wget \
        curl \
        ca-certificates curl libexpat1 libpcre2-8-0 openssh-client perl zlib1g \
        python3 \
        unzip \
        locales \
        libxml2-dev \
        libssl-dev \
        libpq-dev \
        libzip-dev \
        libicu-dev \
    && echo "en_US.UTF-8 UTF-8" >> /etc/locale.gen \
    && locale-gen en_US.UTF-8 \
    && dpkg-reconfigure --frontend=noninteractive locales \
    && python3 -c 'import ssl; assert not any(v == "emSign ECC Root CA - G3" for cert in ssl.create_default_context().get_ca_certs() for rdn in cert["subject"] for k, v in rdn if k == "commonName")'

# Install extensions
RUN docker-php-ext-configure intl \
    && docker-php-ext-install intl \
    && docker-php-ext-install pcntl \
    && docker-php-ext-install pdo \
    && docker-php-ext-install pdo_mysql \
    && docker-php-ext-install pdo_pgsql \
    && docker-php-ext-install simplexml \
    && docker-php-ext-install zip \
    && docker-php-ext-install bcmath \
    && docker-php-ext-install sockets \
    && docker-php-ext-install posix \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug

# Clear cache
RUN apt-get -y autoremove && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# The gate runs as the checkout owner; restrictive host umasks must not hide public PHP settings.
COPY --chmod=0644 ./local.ini /usr/local/etc/php/conf.d/local.ini

# Root-owned proof that release commands are executing in the canonical image.
RUN install -d -o root -g root -m 0755 /usr/local/share/fight-common \
    && printf '%s\n' 'fight-common-release-php-8.5-v1' \
        > /usr/local/share/fight-common/release-runtime-v1 \
    && chown root:root /usr/local/share/fight-common/release-runtime-v1 \
    && chmod 0444 /usr/local/share/fight-common/release-runtime-v1

# Preserve the distro executable path without retaining its affected Git package.
# A package that reintroduces /usr/bin/git makes this layer fail closed.
RUN ln -s /usr/local/bin/git /usr/bin/git \
    && test "$(git --version)" = 'git version 2.56.1' \
    && test "$(/usr/bin/git --version)" = 'git version 2.56.1' \
    && test "$(readlink /usr/bin/git)" = /usr/local/bin/git \
    && test -x "$(git --exec-path)/git-remote-https"
