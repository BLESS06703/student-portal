#!/bin/bash
echo "Pushing to Railway..."
git add .
git commit -m "Railway deployment" --allow-empty
git push origin main
