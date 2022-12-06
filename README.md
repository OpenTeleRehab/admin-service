# Overview

This repository is where OpenTeleRehab is implemented in Laravel and using docker-compose and single sign on with keycloak.

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

# Platform Label Translation 

* Add the translation content to json file at `~/dev/docker-projects/hiv/admin-service/storage/app/translation/`
* Run importing command in the `admin_service` container
    ```bash
    php artisan hi:import-default-translation
    ```

# How to run Feature test

* Go to project directory, and run test command inside the `admin_service` container
    ```bash
    cd ~/dev/docker-projects/hiv/admin-service
    ```
  > Note: Ensure it has `admin_service_test_db` database. If not, please run command
  >
  > `docker exec -i distribution_admin_service_db_1 sh -c 'exec mysql -uroot -p"$MYSQL_ROOT_PASSWORD"' < ../Distribution/config/mysql/admin_service_db/create_test_db.sql`

* Run all Feature tests
    ```bash
    php artisan test --testsuite Feature
    ```
* Run specific Feature test
    ```bash
    php artisan test --testsuite Feature --group={GroupName}
    ```
* Run Feature test stop on failure
    ```bash
    php artisan test --testsuite=Feature --stop-on-failure 
    ```

# API Documents
* Please visit [L5-Swagger](https://github.com/DarkaOnLine/L5-Swagger) more detail.
* Browse [http://localhost:8082/api/documentation](http://localhost:8082/api/documentation)
