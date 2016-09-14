# amoCRM Core2

### Синхронизация данных из amoCRM в базу данных mongoDB
Функционал нужен для построения отчетов и панелей мониторинга на основе данных из amoCRM. Позволяет загружать данные не заботясь о лимите запросов amoCRM.

### Требования
Обязательно наличие расширения MongoDB и PHP 5.4+

###  Использование
Создайте файл sync.php

```php
<?php
require_once __DIR__.'/vendor/autoload.php';
$core = new \solohin\yadro\yadro('doffler', 'test@gmail.com', '41a17271a1f57817271a1f5194978', true);
$yadro->updateAll();
```

### Установка через composer
```
composer require "solohin/amocrm-yadro2-php"
```

### Автор модуля
[Солохин Илья](https://vk.com/solohin_ilya) из команды [DATA5](http://data5.ru)
