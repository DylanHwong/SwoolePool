<?php

if (!defined('ROOT_DIR') && !defined('ROOT')) {
    exit('请定义项目根目录路径为ROOT_DIR或者ROOT常量' . PHP_EOL);
}

//引用文件时需要定义项目根目录为ROOT_DIR或者ROOT常量，同时类需要定义命名空间，否则无法自动加载
//自动加载文件
spl_autoload_register(function($class_name) {
    $class_path = str_replace('\\', DIRECTORY_SEPARATOR, $class_name);

    //基本自动加载文件，带命名空间
    $class_file =  (defined('ROOT_DIR') ? ROOT_DIR : (ROOT . '/')) . $class_path . '.php';
    if (file_exists($class_file)) {
        require($class_file);
    }
if(false !== strpos($class_file, 'RedisPool')){
echo $class_file;die;
}
/**
    //自动加载dal文件
    $dal_camel_file       = (defined('ROOT_DIR') ? ROOT_DIR : (ROOT . '/')) . 'dal/' . $class_path . '.class.php';//使用驼峰命名法文件
    $dal_under_score_file = (defined('ROOT_DIR') ? ROOT_DIR : (ROOT . '/')) . 'dal/' . toUnderScoreCase($class_path) . '.class.php';//使用下划线命名法文件
    $dal_lower_file       = (defined('ROOT_DIR') ? ROOT_DIR : (ROOT . '/')) . 'dal/' . strtolower($class_path) . '.class.php';//使用完全小写的命名文件
    if (file_exists($dal_camel_file)) {
        require($dal_camel_file);
    } elseif (file_exists($dal_under_score_file)) {
        require($dal_under_score_file);
    } elseif (file_exists($dal_lower_file)) {
        require($dal_lower_file);
    }
*/
});
