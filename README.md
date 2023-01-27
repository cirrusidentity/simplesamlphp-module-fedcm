# FedCM "proof-of-concept" simpleSAMLphp module

This module adds support for to SSP 2.0 for the [FedCM Identity Provider endpoints](https://fedidcg.github.io/FedCM/#idp-api) described in the [FedCM unofficial draft specification](https://fedidcg.github.io/FedCM/).


## Install

**NOTE**: this module has not been published to packagist.org yet, so the following won't work.

Install with composer

```bash
    composer require cirrusidentity/simplesamlphp-module-fedcm
```

## Configuration

  1. Enable the module: in `config.php`
  Search for the `module.enable` key and set `fedcm` to true:
     ```php
     'module.enable' => [
        'fedcm' => true,
        ...
     ],
     ```
  2. Copy `config-templates/module_fedcm.php` to your config directory and adjust settings accordingly. See the file for description of parameters.
  3. Ensure you have at least one hosted IdP defined.

## Testing locally

If you are testing locally, so you can ignore the [Install](#install) and [Configuration](#configuration) sections above. All the configuration files needed are included in the `samples/idp` directory (and can be tweaked if desired).

#### Prerequisites on your local system:

  1. [Docker Desktop](https://www.docker.com/products/docker-desktop/)
  2. [ngrok](https://ngrok.com/)

#### Steps to test locally are as follows:

  1. Clone the `ssp2` branch from https://github.com/cirrusidentity/docker-simplesamlphp (clone into a separate directory outside this project):
     ```bash
     git clone --branch ssp2 --single-branch https://github.com/cirrusidentity/docker-simplesamlphp.git
     ```
  2. `cd` into the `docker-simplesamlphp` project directory.
  3. 
     ```bash
     cd docker-simplesamlphp/docker
     SSP_IMAGE_TAG=v2.0.0-rc2
     docker build -t cirrusid/simplesamlphp:$SSP_IMAGE_TAG -f Dockerfile .
     docker tag cirrusid/simplesamlphp:$SSP_IMAGE_TAG
     docker tag cirrusid/simplesamlphp:$SSP_IMAGE_TAG cirrusid/simplesamlphp:latest
     ```
  4. Now use [ngrok](https://ngrok.com/) to create a hosted HTTPS proxy for your local SSP instance. In a shell: `ngrok http https://localhost:8443`. Record the forwarding URL (e.g., `https://2c1c-69-137-176-246.ngrok.io`) for later.
  5. `cd` back into this project directory.
  6. Run the following command in a shell in the top-level of this project directory. Replace `https://2c1c-69-137-176-246.ngrok.io` with whatever ngrok returned for your forwarding URL in step 4, above. If your ngrok forwarding URL changes in the future, you can re-run this command to update it (just use the previous ngrok URL value instead of `https://your-forwarding-url.ngrok.io`).
     ```bash
     scripts/set-ngrok-url.sh https://your-forwarding-url.ngrok.io https://2c1c-69-137-176-246.ngrok.io
     ```
  7. Run the following to launch the `docker-simplesamlphp` container, using the local `simplesamlphp-module-fedcm` module and configuration files from the `samples` directory:
     ```bash
     docker run --name ssp-idp \    
     --mount type=bind,source="$(pwd)/samples/cert",target=/var/simplesamlphp/cert,readonly \
     --mount type=bind,source="$(pwd)/samples/idp/authsources.php",target=/var/simplesamlphp/config/authsources.php,readonly \
     --mount type=bind,source="$(pwd)/samples/idp/config-override.php",target=/var/simplesamlphp/config/config-override.php,readonly \
     --mount type=bind,source="$(pwd)/samples/idp/module_fedcm.php",target=/var/simplesamlphp/config/module_fedcm.php,readonly \
     --mount type=bind,source="$(pwd)/samples/idp/saml20-idp-hosted.php",target=/var/simplesamlphp/metadata/saml20-idp-hosted.php,readonly \
     --mount type=bind,source="$(pwd)/samples/idp/saml20-sp-remote.php",target=/var/simplesamlphp/metadata/saml20-sp-remote.php,readonly \
     --mount type=bind,source="$(pwd)/samples/sp/saml20-idp-remote.php",target=/var/simplesamlphp/metadata/saml20-idp-remote.php,readonly \
     --mount type=bind,source="$(pwd)/docker/apache-override.cf",target=/etc/apache2/sites-enabled/ssp-override.cf,readonly \
     --mount type=bind,source="$(pwd)/samples/sp_test/fedcmtest.php",target=/var/www/fedcmtest.php,readonly \
     --mount type=bind,source="$(pwd)",target=/var/simplesamlphp/staging-modules/fedcm,readonly \
     -e STAGINGCOMPOSERREPOS=fedcm \
     -e COMPOSER_REQUIRE="cirrusidentity/simplesamlphp-module-fedcm:dev-main" \
     -e SSP_ADMIN_PASSWORD=secret1 \
     -e SSP_SECRET_SALT=mysalt \
     -e SSP_APACHE_ALIAS=sample-idp/ \
     -e SSP_LOG_LEVEL=7 \
     -p 8443:443 cirrusid/simplesamlphp:latest
     ```
  8.  In a Chrome browser that supports FedCM (Chrome 108 or later), and with the `#fedcm` flag (in `chrome://flags`) set to `Enabled`, do the following:
      - go to https://your-forwarding-url.ngrok.io/sample-idp/module.php/admin/test and login as `admin` with password `secret1` (or whatever you set `SSP_ADMIN_PASSWORD` to in step 5. above)
      - select the `example-userpass` test link
      - login with username `student` and password `studentpass` (assuming you haven't changed the credentials in `samples/idp/authsources.php`)
      - you'll see confirmation of authentication, including attributes
      - now, in a separate tab/window in your browser, navigate to https://your-forwarding-url.ngrok.io/fedcmtest.php.
      - you should be presented with a FedCM dialog for account `student@college.edu` asking if you wish to "continue as Firsty" (the account's first name attribute). Links to the privacy and TOS pages for the RP should be displayed as well.
      - If you click the "Continue" button, the [FedCM identity assertion endpoint](https://fedidcg.github.io/FedCM/#idp-api-id-assertion-endpoint) will be hit, returning a base64-encoded SAML response in the `token` property of the API response. The `fedcmtest.php` script will POST this to the default SP's Assertion Consumer Service endpoint, with a relay state pointing to the `default-sp` test page. You will be presented with a page similar to the one you saw earlier (after authenticating as `student`), but including SAML subject information as well.
      - If you wish to test multiple times, make sure to clear your browser state completely, then repeat all the steps in item 8 again.
 