# XMLParser

## About

[XMLReader](https://www.php.net/manual/ru/book.xmlreader.php) for PHP extension. 


## 1. Installation ##

Add the `tmitry/xmlreader-extension` package to your `require` section in the `composer.json` file.

``` bash
$ composer require tmitry/xmlreader-extension
```

## 2. Usage ##

```php
use tmitry\XMLReaderExtension\XMLParser;
```
You can use existing instance of XMLReader
```php
$xmlReader = new XMLReader();
$xmlReader->open($fileName);
// ... some actions with instance of XMLReader
$parser = new XMLParser($xmlReader);
```
or init new
```php
$parser = new XMLParser();
$parser->open($fileName);
```

Use extended or standart interface of XMLReader 
```php
// extended interface
if ($parser->moveTo('root/products') && $parser->moveIn()) {
    do {
        if ('root/products/product' == $parser->getPath()) {
            // standart XMLReader interface
            echo $parser->readOuterXml();
        }
    } while ($parser->moveToNextSibling());
}
```
