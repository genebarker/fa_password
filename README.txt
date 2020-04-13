# Password Check Extension for FA

Adds the following features to FrontAccounting (FA) to enhance password
security:

- Verify new passwords are different than last few ones;
- Verify new passwords meet minimun strength requirements using [zxcvbn][1];
- Require password change when too old;
- Require password change when admin updates it;
- On repeated password failures, disable user login for set time; and
- Add Password Security Setup option to adjust settings for above features.

![Password Security Setup Screenshot](screenshot.png)

*Design Notes:*

- This extension uses the 3rd party components [zxcvbn-php][2] and
  [polyfill-mbstring][3]. Please see the [README](vendor/README.txt) for
  details.
- This extension is designed to work with the 'fixes' branch fork of
  the [FA repository][4]
- The fork is used so that the extension can provide feedback to users
  regarding login failures and provide a means to keep password history
  (not possible using FA's hook_authenticate method alone).
- When extension is activated, existing users are required to update their
  password on the next login.
- When extension is deactivated, users login as before using their most
  recent password (since passwords are also stored in original manner).

*Installation Instructions:*

1. Clone the forked FA repository:

    `> git clone https://github.com/genebarker/FA.git`

2. Switch to the 'fixes' branch:

    `> git checkout fixes`

3. Install FA as usual. Notes:

    - See the official [FA Wiki instructions][5]
    - `fixes` is a hardened version of FA 2.3
    - Use MySQL v5.6 to avoid open issues with FA 2.3

4. Clone the extension into FA `modules` folder:

    `> cd webroot/modules`  
    `> git clone https://github.com/genebarker/fa_password.git password`

5. Install and activate the extension:

    - Go to `Setup` -> `Install/Activate Extensions`
    - Click the install button next to `password`
    - On the Extensions dropdown box, select `Activated for..`
    - Check the Active checkbox next to `password`
    - Press the `Update` button

6. Give admin user access to the extension:

    - Go to `Setup` -> `Access Setup`
    - Select `System Administrator` role
    - Check `Password security:`
    - Check `Configure password security`

7. Tune extension settings to your liking:

    - Go to Setup -> Password Security Setup
    - Adjust settings to your liking
    - Press the `Update` button

8. Enjoy!


[1]:https://github.com/dropbox/zxcvbn
[2]:https://github.com/bjeavons/zxcvbn-php
[3]:https://github.com/symfony/polyfill-mbstring
[4]:https://github.com/genebarker/FA/wiki
[5]:https://frontaccounting.com/fawiki/index.php?n=Main.Installation