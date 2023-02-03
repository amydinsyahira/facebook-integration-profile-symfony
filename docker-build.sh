#!/bin/bash

name="fb-integration-profile-symfony"

docker build -t  "$name":latest -f ./Dockerfile .