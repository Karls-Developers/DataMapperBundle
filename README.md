
# Karls Data Mapper Bundle for unite-cms

Mapping old data JSON to new data JSON in unite-cms

## Installation

via composer
```
composer require karls/data-mapper-bundle
```

in config/bundles.php

```
Karls\DataMapperBundle\KarlsDataMapperBundle::class => ['all' => true],
```


## Usage


#### Terminal

```
php bin/console karls:data-mapping
```