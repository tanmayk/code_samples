
WP Migrate Extend
---------------------------

This is a custom module developed to extend Wordpress Migration (https://www.drupal.org/project/wordpress_migrate) module.


Requirement
-------------

After configuring Wordpress Migration module it fetches the blogs & create Drupal nodes as expected. The field values
also works fine based on mapping done.

But we wanted to extract the first image from wordpress content & add it in Image field in Drupal.


Implementation
----------------

We extended the classes provided by Wordpress Migration module & extracted image from content while migration process.
Extracted image is mapped to field_image in Drupal & removed from actual content.
