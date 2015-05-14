# field-guide-tools
Tools for the field guide app project

To use this script from a browser, you will need to provide a symbolic
link from your webserver to this directory.

In Linux, this would be 

/var/www/http/field-guide-tools -> ~/git/field-guide-tools

or 

/var/www/field-guide-tools -> ~/git/field-guide-tools

To create these links, in a terminal type:

sudo ln -s ~/git/field-guide-tools/ /var/www/field-guide-tools

(note the first (target) path has a trailing slash (/), while the second
path (the link) does not.

On OS X, the link a command would be:

/Library/WebServer/Documents/field-guide-tools -> ~/git/field-guide-tools

sudo ln -s ~/git/field-guide-tools/ /Library/WebServer/Documents/field-guide-tools

(note the first (target) path has a trailing slash (/), while the second
path (the link) does not.


You can then use the script by pointing your browser to:

http://localhost/parse-description-file.html