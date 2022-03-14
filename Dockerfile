FROM debian:bullseye-slim

LABEL maintainer="Colin Wilson colin@wyveo.com"

# Let the container know that there is no tty
ENV DEBIAN_FRONTEND noninteractive
ENV NGINX_VERSION 1.21.6-1~bullseye
ENV php_conf /etc/php/8.1/fpm/php.ini
ENV fpm_conf /etc/php/8.1/fpm/pool.d/www.conf
ENV COMPOSER_VERSION 2.2.7
ENV CENTRIFUGO_VERSION 2.8.6

# Install Basic Requirements
RUN buildDeps='apt-transport-https apt-utils autoconf curl gcc git libc-dev make pkg-config wget zlib1g-dev' \
    && deps='ca-certificates ffmpeg gnupg2 dirmngr lsb-release nano python3-pip python-is-python3 python-setuptools rtmpdump unzip zip' \
    && set -x \
    && apt-get update \
    && apt-get install --no-install-recommends $buildDeps --no-install-suggests -q -y $deps \
    && \
    NGINX_GPGKEY=573BFD6B3D8FBC641079A6ABABF5BD827BD9BF62; \
	  found=''; \
	  for server in \
		  ha.pool.sks-keyservers.net \
		  hkp://keyserver.ubuntu.com:80 \
		  hkp://p80.pool.sks-keyservers.net:80 \
		  pgp.mit.edu \
	  ; do \
		  echo "Fetching GPG key $NGINX_GPGKEY from $server"; \
		  apt-key adv --batch --keyserver "$server" --keyserver-options timeout=10 --recv-keys "$NGINX_GPGKEY" && found=yes && break; \
	  done; \
    test -z "$found" && echo >&2 "error: failed to fetch GPG key $NGINX_GPGKEY" && exit 1; \
    echo "deb http://nginx.org/packages/mainline/debian/ bullseye nginx" >> /etc/apt/sources.list \
    && wget -O /etc/apt/trusted.gpg.d/php.gpg https://packages.sury.org/php/apt.gpg \
    && echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/php.list \
    && curl -fsSL https://deb.nodesource.com/setup_17.x | bash - \
    && curl -sS https://dl.yarnpkg.com/debian/pubkey.gpg | apt-key add - \
    && echo "deb https://dl.yarnpkg.com/debian/ stable main" | tee /etc/apt/sources.list.d/yarn.list \
    && apt-get update \
    && apt-get install --no-install-recommends --no-install-suggests -q -y \
            nginx=${NGINX_VERSION} \
            php8.1-fpm \
            php8.1-cli \
            php8.1-bcmath \
            php8.1-common \
            php8.1-opcache \
            php8.1-readline \
            php8.1-mbstring \
            php8.1-curl \
            php8.1-zip \
            php8.1-intl \
            php8.1-xml \
            nodejs \
            yarn \
    && mkdir -p /run/php \
    && pip3 install wheel \
    && pip3 install supervisor \
    && pip3 install git+https://github.com/coderanger/supervisor-stdout \
    && echo "#!/bin/sh\nexit 0" > /usr/sbin/policy-rc.d \
    && rm -rf /etc/nginx/conf.d/default.conf \
    && sed -i -e "s/;cgi.fix_pathinfo=1/cgi.fix_pathinfo=0/g" ${php_conf} \
    && sed -i -e "s/memory_limit\s*=\s*.*/memory_limit = 256M/g" ${php_conf} \
    && sed -i -e "s/upload_max_filesize\s*=\s*2M/upload_max_filesize = 100M/g" ${php_conf} \
    && sed -i -e "s/post_max_size\s*=\s*8M/post_max_size = 100M/g" ${php_conf} \
    && sed -i -e "s/variables_order = \"GPCS\"/variables_order = \"EGPCS\"/g" ${php_conf} \
    && sed -i -e "s/;daemonize\s*=\s*yes/daemonize = no/g" /etc/php/8.1/fpm/php-fpm.conf \
    && sed -i -e "s/error_log\s*= \/var\/log\/php8.1-fpm.log/error_log = \/proc\/self\/fd\/2/g" /etc/php/8.1/fpm/php-fpm.conf \
    && sed -i -e "s/;catch_workers_output\s*=\s*yes/catch_workers_output = yes/g" ${fpm_conf} \
    && sed -i -e "s/pm.max_children = 5/pm.max_children = 4/g" ${fpm_conf} \
    && sed -i -e "s/pm.start_servers = 2/pm.start_servers = 3/g" ${fpm_conf} \
    && sed -i -e "s/pm.min_spare_servers = 1/pm.min_spare_servers = 2/g" ${fpm_conf} \
    && sed -i -e "s/pm.max_spare_servers = 3/pm.max_spare_servers = 4/g" ${fpm_conf} \
    && sed -i -e "s/pm.max_requests = 500/pm.max_requests = 200/g" ${fpm_conf} \
    && sed -i -e "s/www-data/nginx/g" ${fpm_conf} \
    && sed -i -e "s/^;clear_env = no$/clear_env = no/" ${fpm_conf} \
    # Install Composer
    && curl -o /tmp/composer-setup.php https://getcomposer.org/installer \
    && curl -o /tmp/composer-setup.sig https://composer.github.io/installer.sig \
    && php -r "if (hash('SHA384', file_get_contents('/tmp/composer-setup.php')) !== trim(file_get_contents('/tmp/composer-setup.sig'))) { unlink('/tmp/composer-setup.php'); echo 'Invalid installer' . PHP_EOL; exit(1); }" \
    && php /tmp/composer-setup.php --no-ansi --install-dir=/usr/local/bin --filename=composer --version=${COMPOSER_VERSION} \
    && rm -rf /tmp/composer-setup.php \
    && rm -rf /tmp/composer-setup.sig \
    # Install Centrifugo
    && wget -q https://github.com/centrifugal/centrifugo/releases/download/v${CENTRIFUGO_VERSION}/centrifugo_${CENTRIFUGO_VERSION}_linux_amd64.tar.gz -O- | tar xvz -C /tmp \
    && cp /tmp/centrifugo /usr/bin/centrifugo \
    && rm -rf /tmp/centrifugo \
    && mkdir /etc/centrifugo \
    # Install yt-dl
    && curl -L https://yt-dl.org/downloads/latest/youtube-dl -o /usr/local/bin/youtube-dl \
    && chmod a+rx /usr/local/bin/youtube-dl \
    # Install app
    && git clone https://github.com/ThomasTr/Symfony-Downloader.git /var/www/symfony-downloader \
    && cd /var/www/symfony-downloader \
    && composer install \
    && yarn install \
    && yarn build \
    && mkdir /var/www/symfony-downloader/var/downloads \
    && chown nginx:nginx -R /var/www/symfony-downloader \
    # Clean up
    && rm -rf /tmp/pear \
    && apt-get purge -y --auto-remove $buildDeps nodejs yarn \
    && apt-get clean \
    && apt-get autoremove \
    && rm -rf /usr/local/bin/composer \
    && rm -rf /var/lib/apt/lists/* \
    && rm -rf /var/www/symfony-downloader/node_modules

# Supervisor config
COPY docker/supervisord/supervisord.conf /etc/supervisord.conf

# Override nginx's default config
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf

# Copy Scripts
COPY docker/start.sh /start.sh

EXPOSE 80
EXPOSE 8000

CMD ["/start.sh"]
