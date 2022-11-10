#!/bin/bash

# Add trail line and remove previous records of this host
tail -c1 /usr/etc/host/hosts | read -r _ || echo >> /usr/etc/host/hosts
sed -i "/$1/d" /usr/etc/host/hosts

# Add WEB host record
echo "#HOSTS OF DOCKER $1" >> /usr/etc/host/hosts
WEB_HOST_IP=$(ping $2 -c 1  | grep "PING $2 " | awk '{print $3}' | cut -d"(" -f2 | cut -d")" -f1 )
echo $WEB_HOST_IP $1 >> /usr/etc/host/hosts

echo $WEB_HOST_IP

# Add intermediate hosts 
arr=$(echo $3 | tr " " "\n")

for host in $arr
do
    hostIP=$(ping $host -c 1  | grep "PING $host " | awk '{print $3}' | cut -d"(" -f2 | cut -d")" -f1 )
    echo $hostIP $1.$host >> /usr/etc/host/hosts
    echo $hostIP
done
