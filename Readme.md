# Dockerized symfony youtube downloader

## Local dev

## Build
```
docker compose --progress plain build --pull --no-cache
```

## Run local
```
HTTP_PORT=8001 HTTPS_PORT=4443 HTTP3_PORT=4443 docker compose up --wait
```
Adjust allowed origins in ```docker/centrifugo/config.json``` & WEBSOCKET_URL in env file

## Logs
```
sudo docker compose logs -f
```

## Bash
```
sudo docker compose exec -it sfdownloader bash
```
