FROM debian:trixie-slim

# Let the container know that there is no tty
ENV DEBIAN_FRONTEND=noninteractive
ENV php_conf=/etc/php/8.4/fpm/php.ini
ENV fpm_conf=/etc/php/8.4/fpm/php-fpm.conf
ENV fpm_www_conf=/etc/php/8.4/fpm/pool.d/www.conf
ENV VERSION_CENTRIFUGO=6.3.1
ENV ARCH_CENTRIFUGO linux_amd64
#ENV ARCH_CENTRIFUGO=linux_arm64

# https://getcomposer.org/doc/03-cli.md#composer-allow-superuser
ENV COMPOSER_ALLOW_SUPERUSER=1

# Install packages
RUN buildDeps='apt-transport-https curl gpg git lsb-release' \
    && deps='ca-certificates ffmpeg gnupg2 dirmngr lsb-release nano openssl python3-full python3-brotli python3-certifi python3-mutagen python3-pip python3-pkg-resources python3-pycryptodome python3-requests python3-urllib3 python3-websockets python3-setuptools python3-wheel aria2 rtmpdump unzip wget zip' \
    && set -x \
    && apt-get update \
    && apt-get install --no-install-recommends $buildDeps --no-install-suggests -q -y $deps \
    && curl -sSLo /usr/share/keyrings/deb.sury.org-php.gpg https://packages.sury.org/php/apt.gpg \
    && sh -c 'echo "deb [signed-by=/usr/share/keyrings/deb.sury.org-php.gpg] https://packages.sury.org/php/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/php.list' \
    && apt-get update \
    && apt-get install --no-install-recommends --no-install-suggests -q -y \
            mc \
            nginx \
            php8.4-fpm \
            php8.4-cli \
            php8.4-bcmath \
            php8.4-common \
            php8.4-opcache \
            php8.4-readline \
            php8.4-mbstring \
            php8.4-curl \
            php8.4-zip \
            php8.4-intl \
            php8.4-xml \
    && mkdir -p /run/php \
    && pip3 install --no-build-isolation supervisor --break-system-packages \
    && pip3 install --no-build-isolation git+https://github.com/coderanger/supervisor-stdout --break-system-packages \
    && echo "#!/bin/sh\nexit 0" > /usr/sbin/policy-rc.d \
    && rm -rf /etc/nginx/sites-available/default \
    && sed -i -e "s/;cgi.fix_pathinfo=1/cgi.fix_pathinfo=0/g" ${php_conf} \
    && sed -i -e "s/memory_limit\s*=\s*.*/memory_limit = 256M/g" ${php_conf} \
    && sed -i -e "s/upload_max_filesize\s*=\s*2M/upload_max_filesize = 100M/g" ${php_conf} \
    && sed -i -e "s/post_max_size\s*=\s*8M/post_max_size = 100M/g" ${php_conf} \
    && sed -i -e "s/variables_order = \"GPCS\"/variables_order = \"EGPCS\"/g" ${php_conf} \
    && sed -i -e "s/;daemonize\s*=\s*yes/daemonize = no/g" ${fpm_conf} \
    && sed -i -e "s/error_log\s*= \/var\/log\/php8.4-fpm.log/error_log = \/proc\/self\/fd\/2/g" ${fpm_conf} \
    && sed -i -e "s/;catch_workers_output\s*=\s*yes/catch_workers_output = yes/g" ${fpm_www_conf} \
    && sed -i -e "s/pm.max_children = 5/pm.max_children = 4/g" ${fpm_www_conf} \
    && sed -i -e "s/pm.start_servers = 2/pm.start_servers = 3/g" ${fpm_www_conf} \
    && sed -i -e "s/pm.min_spare_servers = 1/pm.min_spare_servers = 2/g" ${fpm_www_conf} \
    && sed -i -e "s/pm.max_spare_servers = 3/pm.max_spare_servers = 4/g" ${fpm_www_conf} \
    && sed -i -e "s/pm.max_requests = 500/pm.max_requests = 200/g" ${fpm_www_conf} \
    && sed -i -e "s/^;clear_env = no$/clear_env = no/" ${fpm_www_conf}

# Install Composer
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
    && php -r "unlink('composer-setup.php');"

#Install centrifugo
RUN curl -L https://github.com/centrifugal/centrifugo/releases/download/v${VERSION_CENTRIFUGO}/centrifugo_${VERSION_CENTRIFUGO}_${ARCH_CENTRIFUGO}.tar.gz | tar xvz -C /tmp \
    && cp /tmp/centrifugo /usr/bin/centrifugo \
    && rm -rf /tmp/centrifugo

# Install ytdlp
RUN curl -L https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp -o /usr/local/bin/yt-dlp \
    && chmod a+rx /usr/local/bin/yt-dlp

 # Install app
COPY . /var/www/symfony-downloader

RUN cd /var/www/symfony-downloader \
    && composer install --no-cache --prefer-dist \
    && bin/console asset-map:compile \
    && chown www-data:www-data -R /var/www \
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
EXPOSE 8001

CMD ["/start.sh"]
