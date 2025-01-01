#!/bin/bash

# Log the deployment start time
echo "Deployment started at $(date)" >> /home/votalik6n1q7/deployment.log

# Change to your website directory
cd /home/votalik6n1q7/public_html

# Pull the latest changes
git pull origin main >> /home/votalik6n1q7/deployment.log 2>&1

# Log the deployment completion
echo "Deployment completed at $(date)" >> /home/votalik6n1q7/deployment.log