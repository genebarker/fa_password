<?php

spl_autoload_register(
    function ($full_class_name) {
        $class_dir = [
            '/src',
            '/zxcvbn-php/src/',
            '/zxcvbn-php/src/Matchers',
        ];
        foreach ($class_dir as $dir) {
            if (class_exists($full_class_name)) {
                return;
            }
            preg_match('/\w+$/', $full_class_name, $match);
            $file = __DIR__ . $dir . '/' . $match[0] . '.php';
            if (file_exists($file)) {
                require_once($file);
                return;
            }
        }
    }
);
