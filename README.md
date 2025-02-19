# PIC1 website

## Setup
0) Copy config.php.in to config.php and modify appropriately

1) Install Composer: https://getcomposer.org/download/

2) Install dependencies
```shell
php composer.phar update
```

2) Create DB
```shell
php doctrine.php orm:schema-tool:create
```

3) Generate Proxies (this step must be repeated on update)
```shell
php doctrine.php orm:generate-proxies
```

4) Setup cronjob (cron.php)

5) Update bundled assets (optional)
```shell
npm update
npx encore production
```

## Development
Spawn a web server:
```shell
php -S localhost:8080 &
```

## Management
Delete DB:
```shell
php doctrine.php orm:schema-tool:drop --force --full-database
```

Update DB schema:
```shell
php doctrine.php orm:schema-tool:update --complete --force --dump-sql
```
