# 3rd Party Components

The Password Check Extension uses the 3rd party components listed below to
provide password strength checking. They are open source covered under the
MIT license. Please see their respective folders for further detail.

Component         Github repo               Commit
----------------- ------------------------- --------------------------
polyfill-mbstring symfony/polyfill-mbstring 766ee47e6565 27 Feb 2020
zxcvbn-php        bjeavons/zxcvbn-php       d5ebb2651bab 15 Sep 2018 

*Notes:*

- The component files were copied directly (not installed via Composer).
- Only the files contained from the specified commit are present.
- An older commit of zxcvbn-php was used to retain compatibility with
  PHP 5.6.

