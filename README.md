# Magento 2 products export, via SQL

Export products from Magento 2 database to Elastic Search(or whatever).

## Performance

Magento is quite slow itself. So if you try to export via magento REST or Programmatically, it would take a lot of time.

This script works via SQL queries, to improve performance. 

Tested on Magento 2.2.2, Core i7 2.5.
Exported 8653 products per minute

Update Mike / yacms
Rewrote Databasenames, droped Magento 2 2.2.x support, Added Magento 2.3.x support.

## Install, usage

1 . Clone repository

```
https://github.com/yacms/magento2-products-export.git
```

2 . Set database connection and base url

```
cp _config.php.dist _config.php
nano _config.php
```

3 . Run

```
php export.php
```

4 . Multi thread

```
./multitread.sh
```

kill all scripts:

```
./multitread_stop.sh
```
