
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
#### Image Mapping (S3 UUID fix)
Fixes S3 Storage problem, where the image is not save with an UUID.
```console
karls@local:~/unitecms$ php bin/console karls:image-mapping
```
When you run this command you will be prompted to select the `content_type` where the images are located. You can use your arrow keys or simply typing in the content type name.
```console
Please select the content_type: :
  [1] Formulare
  [3] Test
  [4] Bilder
  [5] Reference
 > Bilder
```
After submitting the `content_type` you will be asked which of the following types should be searched and refactored. You can use your arrow keys or type in multiple types at once (`,` comma separates the types).
```console
Please select the type/s which to refactor: 
  [0] mediaimage
  [1] text
  [2] image
 > mediaimage,image
```
Now the Command should execute and search for images that have no UUID and fixes them.

---
#### Data Mapping
Fixes Problems in content, where some data was changed and now needs to be renamed or inserted.
```
php bin/console karls:data-mapping
```
