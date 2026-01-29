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
Access with: https://localhost:4443

## Logs
```
sudo docker compose logs -f
```

## Bash
```
sudo docker compose exec -it sfdownloader bash
```

## Example Docker Compose Config for Synology Container Manager

Access with: http://10.10.0.1:8085

Make sure to put same secret in MERCURE_JWT_SECRET, MERCURE_PUBLISHER_JWT_KEY & MERCURE_SUBSCRIBER_JWT_KEY

``` yaml
---
services:
  sfdownloader:
    image: thomastr/sfdownloader:latest
    container_name: sfdownloader-mercure
    restart: unless-stopped
    environment:
      APP_SECRET: !REPLACE_WITH_RANDOM_APP_SECRET!
      MERCURE_URL: http://sfdownloader/.well-known/mercure
      MERCURE_PUBLIC_URL: http://10.10.0.1:8085/.well-known/mercure
      MERCURE_JWT_SECRET: !MERCURE_JWT_SECRET!
      MERCURE_PUBLISHER_JWT_KEY: !MERCURE_JWT_SECRET!
      MERCURE_SUBSCRIBER_JWT_KEY: !MERCURE_JWT_SECRET!
      SERVER_NAME: :80
    volumes:
      - caddy_data:/data
      - caddy_config:/config
      - ./downloads:/app/downloads
    ports:
      - 8085:80

volumes:
  caddy_data:
  caddy_config:
```
