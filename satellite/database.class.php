<?php

  class database {
    private $_link = false;
    private $_server = DB_SERVER;
    private $_database = DB_DATABASE;
    private $_username = DB_USERNAME;
    private $_password = DB_PASSWORD;
    public $table_prefix = DB_PREFIX;
    private $_type = null;
    
    public function connect() {
      if (function_exists('mysqli_connect')) {
        $this->_type = 'mysqli';
        $this->_link = mysqli_connect($this->_server, $this->_username, $this->_password, $this->_database);
      } else {
        $this->_type = 'mysql';
        $this->_link = mysql_connect($this->_server, $this->_username, $this->_password);
        mysql_select_db($this->_database);
      }
    }
    
    public function disconnect() {
      if ($this->_type == 'mysqli') {
        mysqli_close($this->_link);
      } else {
        mysql_close($this->_link);
      }
    }
    
    public function query($query) {
      if (empty($this->_link) || !is_resource($this->_link)) $this->connect();
      if ($this->_type == 'mysqli') {
        $result = mysqli_query($this->_link, $query);
      } else {
        $result = mysql_query($query, $this->_link);
      }
      return $result;
    }
    
    public function fetch($result) {
      if ($this->_type == 'mysqli') {
        return mysqli_fetch_assoc($result);
      } else {
        return mysql_fetch_assoc($result);
      }
    }
    
    public function seek($result, $offset) {
      if ($this->_type == 'mysqli') {
        return mysqli_data_seek($result, $offset);
      } else {
        return mysql_data_seek($result, $offset);
      }
    }
    
    public function num_rows($result) {
      if ($this->_type == 'mysqli') {
        return mysqli_num_rows($result);
      } else {
        return mysql_num_rows($result);
      }
    }

    public function free($result) {
      if ($this->_type == 'mysqli') {
        return mysqli_free_result($result);
      } else {
        return mysql_free_result($result);
      }
    }
    
    public function insert_id($link='default') {
      if ($this->_type == 'mysqli') {
        return mysqli_insert_id($this->_links[$link]);
      } else {
        return mysql_insert_id($this->_links[$link]);
      }
    }
    
    public function affected_rows($link='default') {
      if ($this->_type == 'mysqli') {
        return mysqli_affected_rows($this->_links[$link]);
      } else {
        return mysql_affected_rows($this->_links[$link]);
      }
    }
    
    public function info($link='default') {
      if ($this->_type == 'mysqli') {
        return mysqli_info($this->_links[$link]);
      } else {
        return mysql_info($this->_links[$link]);
      }
    }
    
    public function input($string, $allowable_tags=false) {
      
      if (is_bool($allowable_tags) === true && $allowable_tags !== true) {
        $string = strip_tags($string, $allowable_tags);
      }
      
      if ($this->_type == 'mysqli') {
        return mysqli_real_escape_string($this->_links[$link], $string);
      } else {
        if (function_exists('mysql_real_escape_string')) {
          if (!isset($this->_link)) $this->connect();
          return mysql_real_escape_string($string, $this->_link);
        } elseif (function_exists('mysql_escape_string')) {
          return mysql_escape_string($string);
        } else {
          return addslashes($string);
        }
      }
    }
    
    function get_server_info() {
      if (empty($this->_link) || !is_resource($this->_link)) $this->connect();
      if ($this->_type == 'mysqli') {
        return mysqli_get_server_info($this->_link);
      } else {
        return mysql_get_server_info($this->_link);
      }
    }
    
    private function _error($query, $errno, $error) {
      trigger_error('MySQL error code '. $errno .': '. $error . (!empty($query) ? ' Query: '. $query : ''), E_USER_ERROR);
    }
  }
  
?>