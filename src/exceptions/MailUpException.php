<?php
/**
 * Created by PhpStorm.
 * User: michele.lafrancesca
 * Date: 23/10/2018
 * Time: 10:54
 */

namespace amos\newsletter\exceptions;


class MailUpException extends \Exception {

    var $statusCode;

    function __construct($inStatusCode, $inMessage) {
        parent::__construct($inMessage);
        $this->statusCode = $inStatusCode;
    }

    function getStatusCode() {
        return $this->statusCode;
    }

    function setStatusCode($inStatusCode) {
        $this->statusCode = $inStatusCode;
    }
}