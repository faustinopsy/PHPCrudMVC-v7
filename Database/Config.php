<?php

namespace Fast\Back\Database;

class Config
{
    public static function get()
    {
        return [
            'database' => array (
            'driver' => 'mysql',
                    'mysql' => 
                          array (
                            'host' => 'localhost',
                            'db_name' => 'kipedreiro',
                            'username' => 'root',
                            'password' => NULL,
                            'charset' => 'utf8',
                            'port' => NULL,
                          ),
                  )
          ];
    }
}
