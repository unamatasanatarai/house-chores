<?php

class Response {
    public static function success($data = [], $meta = [], $status = 200) {
        $payload = self::buildSuccess($data, $meta);
        self::send($payload, $status);
    }

    public static function buildSuccess($data = [], $meta = []) {
        return [
            'success' => true,
            'data' => $data,
            'meta' => $meta
        ];
    }

    public static function error($code, $message, $details = [], $status = 400) {
        $payload = self::buildError($code, $message, $details);
        self::send($payload, $status);
    }

    public static function buildError($code, $message, $details = []) {
        return [
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details
            ]
        ];
    }

    private static function send($payload, $status) {
        if (defined('PHPUNIT_RUNNING')) return; // Don't exit or send headers during tests
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($payload);
        exit;
    }
}
