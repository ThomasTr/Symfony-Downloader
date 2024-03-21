# Dockerized symfony youtube downloader

Personal playground for technologies like docker, websockets, stimulus

## Config

Generate centrifugo config in root folder:
```
bin/centrifugo genconfig
```
 
Adjust ```allowed_origins```if you use other ip/port than 127.0.0.1:8080 

```
{
    "port": 8001
    "allow_anonymous_connect_without_token": true,
    "token_hmac_secret_key": "<SAME RANDOM SECRET AS IN CENTRIFUGO_SECRET IN env>",
    "admin": true,
    "admin_password": "<WITH THIS KEY YOU CAN LOGIN IN ADMIN PANEL>",
    "admin_secret": "71e55876-5178-4f54-963b-796fb49387ca",
    "api_key": "<SAME RANDOM SECRET AS IN CENTRIFUGO_API_KEY IN env>",
    "allowed_origins": [
        "http://127.0.0.1:8080"
    ],
}
```

Place env file elswere and reference path to this file in docker run --env-file.
Generate random tokens e.g. with ```openssl rand -hex 32``` and place them in both config.json and env file.
Adjust WEBSOCKET_URL if you use other port than 8010

```
APP_ENV=prod
APP_SECRET=GENERATE_RANDOM_SECRET

CENTRIFUGO_API_KEY=<SAME RANDOM SECRET AS IN api_key IN config.json>
CENTRIFUGO_SECRET=<SAME RANDOM SECRET AS IN token_hmac_secret_key IN config.json>

DOWNLOAD_PATH=/var/www/symfony-downloader/var/downloads
WEBSOCKET_URL=localhost:8010
```

## Build
```
docker build -t sfdownloader .
```

## Run
```
docker run -d --rm  \
           -p 8080:80 \
           -p 8010:8000 \
           -v ~/projects/symfony-downloader/docker/centrifugo:/etc/centrifugo \
           -v ~/Downloads/sf-test:/var/www/symfony-downloader/var/downloads \
           --env-file ~/projects/symfony-downloader/.env.docker \
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
| 8010       | 8000           | tcp  |

### Volumes
|Folder|Mount Point|
|------|-----------|
|docker/sfdownloader/config/centrifugo|/etc/centrifugo|
|docker/sfdownloader/downloads|/var/www/symfony-downloader/var/downloads|

### Env Variables
| key                     | value                                                     |
|-------------------------|-----------------------------------------------------------|
| APP_ENV                 | dev                                                       |
| APP_SECRET              | random-secret-for-symfony                                 |
| CENTRIFUGO_API_KEY      | same-key-as-in-centrifugo-config-in-api_key               |
| CENTRIFUGO_API_ENDPOINT | http://synology-ip:8000/api                               |
| CENTRIFUGO_SECRET       | same-key-as-in-centrifugo-config-in-token_hmac_secret_key |
| DOWNLOAD_PATH           | /var/www/symfony-downloader/var/downloads                 |
| WEBSOCKET_URL           | synology-ip:8010                                          |
