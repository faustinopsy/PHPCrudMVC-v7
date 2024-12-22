<?php
namespace Fast\Back\Http;

class HttpHeader {
    private static $allowedOrigins = [
        '*',
    ];

    public static function setDefaultHeaders() {

        $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
        if (in_array($origin, self::$allowedOrigins)) {
            header("Access-Control-Allow-Origin: $origin");
        } else {
            header("Access-Control-Allow-Origin: " . self::$allowedOrigins[0]); 
        }

        header("Content-Type: application/json; charset=UTF-8");
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
        header("Access-Control-Max-Age: 3600");
        header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
        header("Access-Control-Allow-Credentials: true");
    }

    public static function sendNotAllowedMethod() {
        http_response_code(405);
        echo json_encode(["error" => "Método não permitido."]);
        exit;
    }
}
