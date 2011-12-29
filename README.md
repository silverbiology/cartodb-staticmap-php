# What is CartoDB Static Map? #

CartoDB Static Map is a PHP library that is used along with ImageMagick to merge individual tiles into one png image.

It was built to make it easier for people to tell download a single image or for generate a series of images through scripting or command line interfaces.

# How to install #

Download the most recent copy from github and unzip it to a folder on your server.  
Go to the staticmap folder and chmod 777 cache to give the class write permission to generate temporary tiles.

Requirements: PHP & Imagemagick

If you do not have image magic install you can download the latest version via:
yum install imagemagik
apt-get install imagemagick