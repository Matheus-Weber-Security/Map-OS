<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class MY_Form_validation extends CI_Form_validation {

    public function __construct($rules = array()) {
        parent::__construct($rules);
    }

    public function xss_clean($str) {
        return $this->CI->security->xss_clean($str);
    }
}
