# Dockerized symfony youtube downloader

Personal playground for technologies like docker, websockets, stimulus

## Build
```
docker build -t sfdownloader .
```

## Run
```
docker run -d --rm  \
           -p 8080:80 \
           -p 8010:8000 \
           -v ~/projects/symfony-downloader/docker/centrifugo:/etc/centrifugo/ \
           -v ~/Downloads/sf-test:/var/www/symfony-downloader/var/downloads \
           --env-file ~/projects/symfony-downloader/.env.local \
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
Do **not use localhost**, as you must adjust cors in ```/etc/centrifugo/config.json```
