#!/bin/bash

name="fb-integration-profile-symfony"
port=8001

if docker ps --format '{{.Names}}' | grep "$name"; then
  docker stop "$name" && docker rm "$name"
fi

docker run --name "$name" --restart always -dp 127.0.0.1:"$port":80 "$name":latest

sleep 5
docker logs "$name"