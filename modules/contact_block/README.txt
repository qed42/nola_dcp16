INTRODUCTION
------------
The Contact Block module provides you with contact forms in a block. It extends
Drupal core's Contact module which provides the forms.

 * Visit the module's project page:
   https://drupal.org/project/contact_block

REQUIREMENTS
------------
This module requires the following modules:

* Contact module (Drupal core)

INSTALLATION
------------
Install as you would normally install a contributed Drupal module. See:
  https://www.drupal.org/documentation/install/modules-themes/modules-8
  for further information.

CONFIGURATION
-------------
Add a Contact block to a block region.
 - Home > Administration > Structure > Block layout
 - Click 'Place block' of the region you want to place a contact block in.
 - Search for 'Contact block' in the listed blocks and click 'Place block'.
 - Select the 'Contact form' you want to add.
 - Save the block.

The personal contact form is build to be used on a pages that 'know' about the
user. The user 'To' address is found using the user ID in the URL. No personal
contact form is displayed if the user ID is not in the URL.
For developers: The personal contact form is only loaded if the path contains
the 'user' placeholder. For example in /user/{user}.

The contact forms of Contact module remain functional at their URL. Use custom
code or an other module to deny access to these pages.

MAINTAINERS
-----------
Current maintainers:
 * Erik Stielstra (Sutharsan) https://www.drupal.org/user/73854

This project has been sponsored by:
 * Wizzlern
   The Drupal trainers.
