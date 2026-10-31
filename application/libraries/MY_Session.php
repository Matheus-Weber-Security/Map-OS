<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class MY_Session extends CI_Session {

    public function __construct(array $params = array()) {
        parent::__construct($params);
    }

    public function userdata($key = NULL) {
        if ($key === 'session_id') {
            return session_id();
        }
        return parent::userdata($key);
    }
}
