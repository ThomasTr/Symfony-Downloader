# Dockerized symfony youtube downloader

Personal playground for technologies like docker, websockets, stimulus

Im Aktuellen bei Docker Hub veröffentlichten Container ist das yt-dlp binary zu alt.
Es kommt der Fehler: unable to download video data: HTTP Error 403: Forbidden
Abhilfe: Im container eine neue Version von yt-dlp herunterladen:

```
sudo docker exec -it sfdownloader /bin/bash
cd /usr/local/bin
wget https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp
chmod +x yt-dlp
```

## Local dev

### Install centrifugo
Download centrifugo_x.x.x_darwin_arm64.tar.gz from https://github.com/centrifugal/centrifugo/releases. Place it in bin folder

### Config

Generate centrifugo config in root folder:
```
bin/centrifugo genconfig
```

Adjust ```allowed_origins```if you use other ip/port than 127.0.0.1:8080

```
{
    "client": {
        "allow_anonymous_connect_without_token": true,
        "allowed_origins": [
            "http://sfdownloader.test"
        ],
        "insecure": true,
        "insecure_skip_token_signature_verify": true,
        "token": {
            "hmac_secret_key": "<SAME RANDOM SECRET AS IN CENTRIFUGO_SECRET IN env>"
        }
    },
    "admin": {
        "enabled": true,
        "password": "<WITH THIS KEY YOU CAN LOGIN IN ADMIN PANEL>",
        "secret": "<WITH THIS KEY YOU CAN LOGIN IN ADMIN PANEL>"
    },
    "http_api": {
        "key": "<SAME RANDOM SECRET AS IN CENTRIFUGO_API_KEY IN env>"
    },
    "http_server": {
        "port": "8001"
    }
}
```

Place env file elswere and reference path to this file in docker run --env-file.
Generate random tokens e.g. with ```openssl rand -hex 32``` and place them in both config.json and env file.
Adjust WEBSOCKET_URL if you use other port than 8010

CENTRIFUGO_API_KEY should be the same value as option http_api.key in your Centrifugo config file.
CENTRIFUGO_SECRET should be the same value as option client.token.hmac_secret_key in your Centrifugo config file.

```
APP_ENV=prod
APP_SECRET=GENERATE_RANDOM_SECRET

CENTRIFUGO_API_KEY=<SAME RANDOM SECRET AS IN api_key IN config.json>
CENTRIFUGO_SECRET=<SAME RANDOM SECRET AS IN token_hmac_secret_key IN config.json>

DOWNLOAD_PATH=/var/www/symfony-downloader/var/downloads
WEBSOCKET_URL=localhost:8010
```

### Run centrifugo local
```
bin/centrifugo
```

## Build
```
docker build --no-cache --progress plain -t sfdownloader .
```

## Run local
```
docker run -d --rm \
           -p 8080:80 \
           -p 8001:8001 \
           -v ~/Downloads:/var/www/symfony-downloader/var/downloads \
           -e APP_ENV=dev \
           -e APP_SECRET=$(openssl rand -hex 32) \
           -e API_ENDPOINT_CENTRIFUGO=http://localhost:8001/api \
           -e WEBSOCKET_URL=localhost:8001 \
           -e YT_DLP_PATH=/usr/local/bin/yt-dlp \
           -e FFMPEG_PATH=/usr/bin/ffmpeg \
           -e CENTRIFUGO_ADMIN_ENABLED=true \
           -e CENTRIFUGO_ADMIN_PASSWORD=s3cr3t \
           -e CENTRIFUGO_ADMIN_SECRET=$(openssl rand -hex 32) \
           -e CENTRIFUGO_CLIENT_ALLOWED_ORIGINS=http://localhost:8080 \
           -e CENTRIFUGO_CLIENT_ALLOW_ANONYMOUS_CONNECT_WITHOUT_TOKEN=true \
           -e CENTRIFUGO_CLIENT_INSECURE=true \
           -e CENTRIFUGO_CLIENT_INSECURE_SKIP_TOKEN_SIGNATURE_VERIFY=true \
           -e CENTRIFUGO_CLIENT_TOKEN_HMAC_SECRET_KEY=$(openssl rand -hex 32) \
           -e CENTRIFUGO_HTTP_API_KEY=$(openssl rand -hex 32) \
           -e CENTRIFUGO_HTTP_SERVER_PORT=8001 \
           -e CENTRIFUGO_LOG_LEVEL=debug \
           --name sfdownloader \
           sfdownloader:latest
```
Adjust allowed origins in ```docker/centrifugo/config.json``` & WEBSOCKET_URL in env file

## Console
```
docker exec -it sfdownloader bash
```

## Logs
```
docker logs sfdownloader -f
```

## Use it
Run it with http://127.0.0.1:8080/
If you **not use localhost**, you must adjust cors in centrifugo config.json file.

## Use local built docker image in synology nas when same architecture
Export file to archive when build with the same architecture
```
docker save sfdownloader | gzip > sfdownloader.tar.gz
```
Import archive on synology at docker/image 

##Alternatively build docker image direct on synology
Log in via ssh, chdir to `/volume1/docker` clone via
```
git clone git@github.com:ThomasTr/Symfony-Downloader.git sfdownloader
```
build:
```
cd sfdownloader
sudo docker build --no-cache -t sfdownloader .
```
Image is then available in docker images frontend.

## Settings for Synology NAS

### Ports
| local port | container port | type |
|------------|----------------|------|
| 8081       | 80             | tcp  |
| 8001       | 8001           | tcp  |

### Volumes
|Folder|Mount Point|
|------|-----------|
|docker/sfdownloader/downloads|/var/www/symfony-downloader/var/downloads|

### Env Variables
| key                                                     | value                                                                                        |
|---------------------------------------------------------|----------------------------------------------------------------------------------------------|
| APP_ENV                                                 | dev                                                                                          |
| APP_SECRET                                              | random-secret-for-symfony, is now generated during docker build                              |
| CENTRIFUGO_API_ENDPOINT                                 | http://10.10.0.1:8001/api                                                                    |
| DOWNLOAD_PATH                                           | defaults to /var/www/symfony-downloader/var/downloads in container, can be exposed via mount |
| WEBSOCKET_URL                                           | 10.10.0.1:8001                                                                               |
| YT_DLP_PATH                                             | absolute path to yt-dlp binary: /usr/local/bin/yt-dlp                                        |
| FFMPEG_PATH                                             | absolute path to ffmpg binary: /usr/bin/ffmpeg                                               |
| CENTRIFUGO_ADMIN_ENABLED                                | true                                                                                         |
| CENTRIFUGO_ADMIN_PASSWORD                               | secure password to access centrifugo admin area                                              |
| CENTRIFUGO_ADMIN_SECRET                                 | This is the secret key for the authentication token used after successful login.             |
| CENTRIFUGO_CLIENT_ALLOWED_ORIGINS                       | http://sfdownloader.test                                                                     |
| CENTRIFUGO_CLIENT_ALLOW_ANONYMOUS_CONNECT_WITHOUT_TOKEN | true                                                                                         |
| CENTRIFUGO_CLIENT_INSECURE                              | true                                                                                         |
| CENTRIFUGO_CLIENT_INSECURE_SKIP_TOKEN_SIGNATURE_VERIFY  | true                                                                                         |
| CENTRIFUGO_CLIENT_TOKEN_HMAC_SECRET_KEY                 | random secret                                                                                |
| CENTRIFUGO_HTTP_API_KEY                                 | random secret                                                                                |
| CENTRIFUGO_HTTP_SERVER_PORT                             | 8001                                                                                         |
