# Dockerized symfony youtube downloader

Personal playground for technologies like docker, websockets, stimulus

## Build
```
docker build -t sfdownloader .
```

## Run
```
docker run -d --rm  -p 8080:80 -p 8010:8000 --name sfdownloader -v ~/Downloads/sf-test:/var/www/symfony-downloader/var/downloads sfdownloader:latest
```
At the moment the ports are fixed to 8010 & 8080 as they are configured at different places

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
