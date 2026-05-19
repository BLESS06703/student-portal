#!/bin/bash
echo "Starting Student Portal..."
killall mariadbd 2>/dev/null
killall php 2>/dev/null
sleep 1
mariadbd-safe &
sleep 3
php -S 0.0.0.0:8080 -t /data/data/com.termux/files/usr/share/apache2/default-site/htdocs/ &
sleep 1
IP=$(ifconfig wlan0 2>/dev/null | grep "inet " | awk '{print $2}')
echo ""
echo "================================="
echo "  Student Portal is Running!"
echo "  Local: http://localhost:8080"
if [ -n "$IP" ]; then
    echo "  Network: http://$IP:8080"
fi
echo "================================="
