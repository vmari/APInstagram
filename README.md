# APInstagram
Upload images to Instagram w/o official API

  
## Usage

```php
namespace MyProject;
use VM\Instagram;

try {
    $api = new Instagram($user,$pass);

    $api->uploadPhoto('test.jpg','Hello world from PHP!');
    echo 'Ok!';
} catch (Exception $e) {
    echo 'Error';
}
```
