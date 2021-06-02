# Dockerized symfony youtube downloader

Personal playground for technologies like docker, websockets, stimulus

## Build
```
docker build -t sfdownloader .
```

## Config

Place centrifugo config.json file elswere and reference path to this file in docker run, 
adjust ```allowed_origins```if you use other ip/port than 127.0.0.1:8080 

```
{
    "v3_use_offset": true,
    "anonymous": true,
    "token_hmac_secret_key": "<SAME RANDOM SECRET AS IN CENTRIFUGO_SECRET IN env>",
    "api_key": "<SAME RANDOM SECRET AS IN CENTRIFUGO_API_KEY IN env>",
    "allowed_origins": [
        "http://127.0.0.1:8080"
    ]
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

## Run
```
docker run -d --rm  \
           -p 8080:80 \
           -p 8010:8000 \
           -v ~/projects/symfony-downloader/docker/centrifugo:/etc/centrifugo/ \
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
