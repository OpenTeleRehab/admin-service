# Overview

This repository is where Telerehabilitation App implemented in Laravel and using docker-compose and single sign on with keycloak.

# Maintainer

* Web Essentials Co., Ltd

# Run code check style with `phpcs`

* make sure you are using `phpcs` version `>=3.3.1`. If not, run `~/dotfiles/setup.sh`

    ```bash
    cd ~/dev/docker-projects/hiv/admin-service
    phpcs
    ```

* References

    > [https://github.com/standard/eslint-config-standard](https://github.com/standard/eslint-config-standard)

* Configuration with IDE

    > [https://eslint.org/docs/user-guide/integrations](https://eslint.org/docs/user-guide/integrations)

## Import default translation file to database
### Add the translation content to json file in 
```bash
    /dev/docker-projects/hiv/admin-service/storage/app/translation
```
### Run the import command
```bash
php artisan hi:import-default-translation
```
