<?php

include('Instagram.php');

try {
    $api = new Instagram('user','pass');

    $api->uploadPhoto('test.jpg','Hola!');
    echo 'Ok!';
} catch (Exception $e) {
    echo $e;
}