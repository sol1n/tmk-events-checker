# -*- mode: ruby -*-
# vi: set ft=ruby :

# All Vagrant configuration is done below. The "2" in Vagrant.configure
# configures the configuration version (we support older styles for
# backwards compatibility). Please don't change it unless you know what
# you're doing.

Vagrant.configure(2) do |config|
  config.vm.box = "ubuntu/trusty64"
  config.vm.network "private_network", ip: "192.168.33.15"
  config.vm.hostname = "tmk-visits.local"
  config.vm.network "forwarded_port", guest: 80, host: 8080

  config.hostsupdater.aliases = []

  unless Vagrant.has_plugin?("vagrant-hostsupdater")
    puts 'vagrant-hostsupdater is not installed!'
    puts 'To install the plugin, run:'
    puts 'vagrant plugin install vagrant-hostsupdater'
    exit
  end

  unless Vagrant.has_plugin?("vagrant-vbguest")
    puts 'vagrant-vbguest is not installed!'
    puts 'To install the plugin, run:'
    puts 'vagrant plugin install vagrant-vbguest'
    exit
  end

  config.vm.synced_folder ".", "/vagrant", type: "virtualbox", id: "vagrant-root",
    owner: "vagrant",
    group: "www-data",
    mount_options: ["dmode=777,fmode=777"]

  config.vm.provider "virtualbox" do |vb|
    vb.memory = "512"
  end

  config.vm.provision :shell, path: "vagrant/bootstrap.sh"
  config.vm.provision :shell, path: "vagrant/startup.sh",
    run: "always"
end
