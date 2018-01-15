# mp4 proxy

## Install

```shell
composer require crlt/mp4-proxy
```

## Usage
```php
<?php
require_once "vendor/autoload.php";
use Crlt_\Mp4Proxy\mp4Proxy;
$url = $_GET['url'];
$myProxy=new mp4Proxy();
$myProxy->actionNormal($url);
```

## Test

```
curl "http://localhost:1024/mp4-proxy/index.php?url=http://localhost:1024/mp4-proxy/test.mp4" > test.mp4
```

## License
MIT
