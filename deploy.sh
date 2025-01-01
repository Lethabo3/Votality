#!/bin/bash

# Set working directory
cd /home/votalik6n1q7/public_html

# Log start
echo "Deployment started at $(date)" >> /home/votalik6n1q7/deployment.log

# Fetch latest changes
git fetch origin main

# Reset to match origin/main
git reset --hard origin/main

# Log the result
echo "Deployment completed at $(date)" >> /home/votalik6n1q7/deployment.log