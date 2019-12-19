# SS13 Media Server
This is a mess of code designed to serve data and media files to Space Station 13 servers
with jukeboxes.

## Prerequisites

* PHP >= 7.3
* git
* https://git.nexisonline.net/N3X15/ss13-media-converter (on your PC)

**HIGHLY RECOMMENDED:**
* git large file support (git lfs)

## Installation

We only support Linux.  There is something wrong with you if you use Windows or Mac for a production server.

```shell
# Clone the repository to your server. Replace /var/www/media.example.tld with wherever you want it.
# WORKS BEST ON ITS OWN SUBDOMAIN!
git clone https://github.com/N3X15/ss13-media.git /var/www/media.example.tld
# Create cache and files directories
mkdir /var/www/media.example.tld/{cache,files}
# Set the server process as the owner
chown -R www-data:www-data /var/www/media.example.tld

cd /var/www/media.example.tld
# Make config:
cp config.dist.php config.php
# Change to taste:
$EDITOR config.php

# You may want to tweak things a bit.

# OPTIONAL - To rename htdocs/ to public/:
mv htdocs/ public/
# OPTIONAL - To move everything in the htdocs/ folder into the root (useful if you're in a subdirectory, like ss13.moe/media):
mv htdocs/* ./
# Remember to update config:
$EDITOR config.php
```

Now upload your files from the media converter using upload.sh.

## Maintenance

### Adding a playlist

1. Add playlist ID to playlists.json and media converter config
1. Sync with media converter (media converter's `upload.sh`)
1. Clear cache/ directory (`rm -rfv cache/*`)

### Removing a playlist
1. Remove playlist ID from playlists ID, and remove from media converter
1. Sync with media converter (media converter's `upload.sh`)
1. Clear cache/ directory (`rm -rfv cache/*`)

### Updating Media Server
First, back up everything, you're going to need to restore all the PHP/JS/CSS files to their previous states.

```shell
cd /path/to/media.server.tld
# Restore all the PHP files to the originally checked-out state
sudo -u www-data git reset --hard
# Fetch changes from N3X15
sudo -u www-data git fetch origin --all --prune
# Check out all the files from the new changes
sudo -u www-data git pull origin master
# Clear cache
rm -v cache/*
```
