# This is pretty much just copied from the CouchDB wiki and will setup CouchDB from the latest SVN:HEAD
# see http://wiki.apache.org/couchdb/Getting_started_with_Amazon_EC2

# All I did was adding php5-cli + php5-curl

sudo apt-get update && sudo apt-get -y upgrade
sudo apt-get install -y erlang libmozjs-dev libicu-dev libcurl4-gnutls-dev make subversion automake autoconf libtool help2man
sudo apt-get install -y php5-cli php5-curl
svn checkout http://svn.apache.org/repos/asf/couchdb/trunk couchdb
cd couchdb
./bootstrap && ./configure && make && sudo make install
sudo adduser --system --home /usr/local/var/lib/couchdb --no-create-home --shell /bin/bash --group --gecos 'CouchDB account' couchdb
sudo chown -R couchdb.couchdb /usr/local/var/{lib,log}/couchdb
sudo -i -u couchdb couchdb 