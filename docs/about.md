# mii
  
### Request flow
  
Запуск web приложения выглядит примерно так:
  
``` (new mii\web\App($config))->run(); ```
  
Перед запуском необходимо подключить Mii.php и автолоадер (если не используется composer).
    
App сохраняется в Mii::$app
Создается web\Request
На основе роутов вычисляется необходимый контролер и управление передается методу контролера execute.

Далее, дефолтный web/controller действует так:
$this->access_control();
$this->before();
$this->{action}();
$this->after();

...


### Требования

Php 7.0+
APCu
Gmagick


### Установка

Через composer: levmorozov/mii.

В index.php обычно лежит что-то вроде этого:
```php

require(__DIR__ . '/../vendor/autoload.php');
require(__DIR__ . '/vendor/levmorozov/mii/src/Mii.php');

$config = require(__DIR__'/../app/config/main.php');

$app = new \mii\web\App($config);

$app->run();

```


Структура сайта обычно такова:
```
project
 ..app
 ....controllers
 ....models
 ....blocks
 ..vendor
 ..public
 ....assets
 ....res
 ....index.php
```

Но, на самом деле, жестких зависимостей почти нет. Так что все может быть иначе.

### Mii

Класс Mii является глобальным и, пожалуй, единственная его заслуга в том, что он всегда хранит в себе экземпляр 
нашего приложения (/mii/web/Application, например) в Mii::$app



 




 
  