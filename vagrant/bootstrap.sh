#!/usr/bin/env bash

# Vagrant VM provision script

provisioningStartTime=`date +%s`
echo ""
echo "Starting Vagrant provision script"

## Pre-requesites
# Since we have 512mb RAM it's recommended to enable swap before we run any command.
/vagrant/vagrant/scripts/swap.sh

# All environments should be running in one timezone, use Europe/Moscow as default.
sudo bash -c 'echo "Europe/Moscow" > /etc/timezone'
sudo dpkg-reconfigure -f noninteractive tzdata

# /vagrant directory is a synced folder (see config.vm.synced_folder in Vagrantfile)
# Let's store all important info in this driectory.
cd /vagrant

# First of all, upgrade system packages.
sudo apt-get update
sudo apt-get dist-upgrade -y

# Add required repositories
sudo LC_ALL=C.UTF-8 add-apt-repository ppa:ondrej/php -y
sudo apt-add-repository ppa:rwky/redis -y
sudo apt-get update

## Install core packages.
sudo apt-get install software-properties-common python-software-properties -y
sudo apt-get install unzip -y
sudo apt-get install git-core -y
sudo apt-get install nginx -y
sudo apt-get install memcached -y

# PHP 7.1 is our primary PHP version.
sudo apt-get install php7.1-fpm -y
sudo apt-get install php7.1-dev -y

# PHP's extensions.
sudo apt-get install php7.1-curl -y
sudo apt-get install php7.1-memcached -y
sudo apt-get install php7.1-mbstring -y
sudo apt-get install php7.1-xml -y
sudo apt-get install php7.1-zip -y

# Additional extensions.
sudo pecl channel-update pecl.php.net

# PHPUnit for testing our code.
wget https://phar.phpunit.de/phpunit.phar
chmod +x phpunit.phar
sudo mv phpunit.phar /usr/local/bin/phpunit

# Composer is our dependency manager.
curl -sS https://getcomposer.org/installer | sudo php -- --install-dir=/usr/local/bin --filename=composer

# php-cs-fixer is used to fix PHP code styles in our sources before commit.
wget http://get.sensiolabs.org/php-cs-fixer.phar -O php-cs-fixer
sudo chmod a+x php-cs-fixer
sudo mv php-cs-fixer /usr/local/bin/php-cs-fixer

# As of June 9, 2016 there's a bug on sensiolabs.org:
# instead of installing stable release of php-cs-fixer
# it installs 2.0-DEV version.
# Use php-cs-fixer selfupdate to install stable release.
# https://github.com/FriendsOfPHP/PHP-CS-Fixer/issues/1925#issuecomment-224208657
php-cs-fixer selfupdate

## Configs
# Add nginx vhosts.
sudo rm /etc/nginx/sites-enabled/default
sudo rm /etc/nginx/sites-available/default
sudo ln -s /vagrant/vagrant/configs/nginx-vhosts.conf /etc/nginx/sites-enabled/vhosts.conf

# Add some php.ini tweak.
sudo ln -s /vagrant/vagrant/configs/php.ini /etc/php/7.1/fpm/conf.d/00-php.ini
sudo ln -s /vagrant/vagrant/configs/php.ini /etc/php/7.1/cli/conf.d/00-php.ini


# Export some paths to $PATH env variable.
echo 'export PATH="$PATH:/usr/local/bin"' >> ~/.bashrc
echo 'export PATH="$PATH:$HOME/.composer/vendor/bin"' >> ~/.bashrc
source ~/.bashrc

## Finish
# Cleanup unused packages.
sudo apt-get autoremove -y

# Restart services.
sudo service nginx restart
sudo service php7.1-fpm restart

# Ok, we're ready.
provisioningEndTime=`date +%s`
provisioningRunTime=$((provisioningEndTime-provisioningStartTime))
provisioningMinutes=$((provisioningRunTime/60))
echo ""
echo "Provisioned in $provisioningMinutes minutes"