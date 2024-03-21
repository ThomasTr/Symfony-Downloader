FROM debian:bookworm-slim

# Let the container know that there is no tty
ENV DEBIAN_FRONTEND noninteractive
ENV php_conf /etc/php/8.2/fpm/php.ini
ENV fpm_conf /etc/php/8.2/fpm/php-fpm.conf
ENV fpm_www_conf /etc/php/8.2/fpm/pool.d/www.conf
ENV COMPOSER_VERSION 2.7.2
ENV CENTRIFUGO_VERSION 5.3.0
#ENV CENTRIFUGO_ARCH linux_amd64
ENV CENTRIFUGO_ARCH linux_arm64

# https://getcomposer.org/doc/03-cli.md#composer-allow-superuser
ENV COMPOSER_ALLOW_SUPERUSER=1

# Install Basic Requirements
RUN buildDeps='apt-transport-https curl gpg git lsb-release wget' \
    && deps='ca-certificates ffmpeg gnupg2 dirmngr lsb-release nano python3-full python3-brotli python3-certifi python3-mutagen python3-pip python3-pkg-resources python3-pycryptodome python3-requests python3-urllib3 python3-websockets aria2 rtmpdump unzip zip' \
    && set -x \
    && apt-get update \
    && apt-get install --no-install-recommends $buildDeps --no-install-suggests -q -y $deps \
    && curl -sSLo /usr/share/keyrings/deb.sury.org-php.gpg https://packages.sury.org/php/apt.gpg \
    && sh -c 'echo "deb [signed-by=/usr/share/keyrings/deb.sury.org-php.gpg] https://packages.sury.org/php/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/php.list' \
    && apt-get update \
    && apt-get install --no-install-recommends --no-install-suggests -q -y \
            mc \
            nginx \
            php8.2-fpm \
            php8.2-cli \
            php8.2-bcmath \
            php8.2-common \
            php8.2-opcache \
            php8.2-readline \
            php8.2-mbstring \
            php8.2-curl \
            php8.2-zip \
            php8.2-intl \
            php8.2-xml \
    && mkdir -p /run/php \
    && pip3 install wheel --break-system-packages\
    && pip3 install supervisor --break-system-packages\
    && pip3 install git+https://github.com/coderanger/supervisor-stdout  --break-system-packages\
    && echo "#!/bin/sh\nexit 0" > /usr/sbin/policy-rc.d \
    && rm -rf /etc/nginx/sites-available/default \
    && sed -i -e "s/;cgi.fix_pathinfo=1/cgi.fix_pathinfo=0/g" ${php_conf} \
    && sed -i -e "s/memory_limit\s*=\s*.*/memory_limit = 256M/g" ${php_conf} \
    && sed -i -e "s/upload_max_filesize\s*=\s*2M/upload_max_filesize = 100M/g" ${php_conf} \
    && sed -i -e "s/post_max_size\s*=\s*8M/post_max_size = 100M/g" ${php_conf} \
    && sed -i -e "s/variables_order = \"GPCS\"/variables_order = \"EGPCS\"/g" ${php_conf} \
    && sed -i -e "s/;daemonize\s*=\s*yes/daemonize = no/g" ${fpm_conf} \
    && sed -i -e "s/error_log\s*= \/var\/log\/php8.1-fpm.log/error_log = \/proc\/self\/fd\/2/g" ${fpm_conf} \
    && sed -i -e "s/;catch_workers_output\s*=\s*yes/catch_workers_output = yes/g" ${fpm_www_conf} \
    && sed -i -e "s/pm.max_children = 5/pm.max_children = 4/g" ${fpm_www_conf} \
    && sed -i -e "s/pm.start_servers = 2/pm.start_servers = 3/g" ${fpm_www_conf} \
    && sed -i -e "s/pm.min_spare_servers = 1/pm.min_spare_servers = 2/g" ${fpm_www_conf} \
    && sed -i -e "s/pm.max_spare_servers = 3/pm.max_spare_servers = 4/g" ${fpm_www_conf} \
    && sed -i -e "s/pm.max_requests = 500/pm.max_requests = 200/g" ${fpm_www_conf} \
    && sed -i -e "s/^;clear_env = no$/clear_env = no/" ${fpm_www_conf} \
    # Install Composer
    && curl -o /tmp/composer-setup.php https://getcomposer.org/installer \
    && curl -o /tmp/composer-setup.sig https://composer.github.io/installer.sig \
    && php -r "if (hash('SHA384', file_get_contents('/tmp/composer-setup.php')) !== trim(file_get_contents('/tmp/composer-setup.sig'))) { unlink('/tmp/composer-setup.php'); echo 'Invalid installer' . PHP_EOL; exit(1); }" \
    && php /tmp/composer-setup.php --no-ansi --install-dir=/usr/local/bin --filename=composer --version=${COMPOSER_VERSION} \
    && rm -rf /tmp/composer-setup.php \
    && rm -rf /tmp/composer-setup.sig \
    # Install Centrifugo
    && curl -L https://github.com/centrifugal/centrifugo/releases/download/v${CENTRIFUGO_VERSION}/centrifugo_${CENTRIFUGO_VERSION}_${CENTRIFUGO_ARCH}.tar.gz | tar xvz -C /tmp \
    && cp /tmp/centrifugo /usr/bin/centrifugo \
    && rm -rf /tmp/centrifugo \
    && mkdir /etc/centrifugo \
    # Install yt-dlp
    && curl -L https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp -o /usr/local/bin/yt-dlp \
    && chmod a+rx /usr/local/bin/yt-dlp \
    # Install app
    && git clone https://github.com/ThomasTr/Symfony-Downloader.git /var/www/symfony-downloader \
    && cd /var/www/symfony-downloader \
    && composer install --no-cache --prefer-dist \
#    && mkdir /var/www/symfony-downloader/var/downloads \
    && chown www-data:www-data -R /var/www/symfony-downloader \
    # Clean up
    && rm -rf /tmp/pear \
    && apt-get purge -y --auto-remove $buildDeps \
    && apt-get clean \
    && apt-get autoremove \
    && rm -rf /usr/local/bin/composer \
    && rm -rf /var/lib/apt/lists/*

# Supervisor config
COPY docker/supervisord/supervisord.conf /etc/supervisord.conf

# Override nginx's default config
COPY docker/nginx/default.conf /etc/nginx/sites-available/default

# Copy Scripts
COPY docker/start.sh /start.sh

EXPOSE 80
EXPOSE 8000

CMD ["/start.sh"]
