<?php

namespace dentsucreativeuk\citrus\helpers;

class ResponseHelper
{
    public const CODE_OK = 0;
    public const CODE_ERROR_GENERAL = -1;
    public const CODE_ERROR_CURL = -2;

    public $response;
    public $code;
    public $message;
    public $data;

    public function __construct($code, $message = '', $data = null)
    {
        $this->code = $code;
        $this->message = $message;
        $this->data = ($data !== null ? $data : array());
    }

    public function __toString()
    {
        return $this->code . ' ' . $this->message;
    }

    public function writeData($return = false)
    {
        $str = '';

        foreach ($this->data as $key => $value) {
            $str .= sprintf("%s: %s\r\n", $key, $value);
        }

        if ($return) {
            return $str;
        }

        echo $str;
    }
}
