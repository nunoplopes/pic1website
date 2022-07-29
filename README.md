# PIC1 website

## Setup
0) Copy config.php.in to config.php and modify apropriately

1) Install Composer: https://getcomposer.org/download/

2) Install dependencies
```shell
php composer.phar update
```

2) Create DB
```shell
php doctrine.php orm:schema-tool:create
```

3) Setup cronjob (cron.php)


## Management
Delete DB:
```shell
php doctrine.php orm:schema-tool:drop --force
```
