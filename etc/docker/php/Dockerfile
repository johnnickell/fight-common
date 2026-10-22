FROM php:8.5-cli

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
        git \
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

COPY ./local.ini /usr/local/etc/php/conf.d/local.ini

# Root-owned proof that release commands are executing in the canonical image.
RUN install -d -o root -g root -m 0755 /usr/local/share/fight-common \
    && printf '%s\n' 'fight-common-release-php-8.5-v1' \
        > /usr/local/share/fight-common/release-runtime-v1 \
    && chown root:root /usr/local/share/fight-common/release-runtime-v1 \
    && chmod 0444 /usr/local/share/fight-common/release-runtime-v1
