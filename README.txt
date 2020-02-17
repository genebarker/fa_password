# Password Check Extension for FA

Adds the following features to FrontAccounting (FA) to enhance password
security:

- verify new passwords are different than last few ones
- verify new passwords meet minimun strength requirements
  (uses zxcvbn)
- require password change when too old
- on repeated password failures, disable user login for set time
- provide mechanism for admin to adjust settings for above features

