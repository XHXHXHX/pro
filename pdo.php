<?php

class Mysql{

    private $con;
    private $db_option = array(PDO::ATTR_PERSISTENT=>true,PDO::ATTR_ERRMODE=>2,PDO::MYSQL_ATTR_INIT_COMMAND=>'SET NAMES utf8');
    private $stmt_arr = [];
    private $unique_prefix_length = 6;      // 唯一性前缀长度
    private $unique_string = '0123456789~!@#$%^&*()-=_+[]{},.M<>?abcdefghijklmnopqrstuvwxyzABCDEFGHIGKLMNOPQRSTUVWXYZ';
    private $could_allow_function = [
        'errorCode',             // 获取错误号
        'getRow',                   // 获取结果集
        'executeSql',            // 获取执行sql
        'errorMessage',             // 获取错误信息
        'rowCount',                 // 获取影响行数
        'releaseStmt',              // 释放预处理语句对象
    ];

    public static $instance;


    public function __construct($config_filename = '')
    {
        $config = $this->getConfig($config_filename);
        $this->con = $this->connect($config);

        self::$instance = $this;
    }

    /*获取mysql配置信息*/
    protected function getConfig($config_filename)
    {
        if($config_filename && is_file($config_filename)) {
            $config = require_once($config_filename);
            return $config;
        }

        if(defined('DB_CONFIG_PATH')) {
            if(strpos(DB_CONFIG_PATH, '.php'))
                $config = require_once(DB_CONFIG_PATH);
            else
                $config = require_once(DB_CONFIG_PATH . '.php');
            return $config;
        }
    }

    /*数据库连接*/
    private function connect($config)
    {
        try{
            return new PDO("mysql:host=".$config['DB_HOST'].";dbname=".$config['DB_DATABASE'],
                        $config['DB_USERNAME'],
                        $config['DB_PASSWORD'],
                        $this->db_option);
        }catch(Exception $e) {
            die('mysql contect fail');
        }
    }

    public static function instance($config = '')
    {
        return self::$instance ?? new self($config);
    }

    /*
     * 调用could_allow_function成员内的方法
     * */
    public function __call($name, $arguments)
    {
        $key = $arguments[0] ?? false;
        if(!in_array($name, $this->could_allow_function) || !$key || !array_key_exists($key, $this->stmt_arr))
            return false;

        return $this->$name($this->stmt_arr[$key]);
    }

    /*适合查询*/
    public function query($sql){

        try{
            $result = $this->con->query($sql, PDO::FETCH_ASSOC);
            return $result;
        } catch (Exception $e) {
            $this->errorMessage($this->con->errorInfo(), $e);
        }

        return [$this->con->errorInfo(), $e];
    }

    /*适合查询数量*/
    public function exec(){}

    /*适合update及insert*/
    public function execute($sql, $params = [])
    {
        $stmt = $this->con->prepare($sql);
        $this->bindParams($stmt, $params);

        try {
            $stmt->execute();

        } catch (Exception $e) {

        }

        $unique_key = $this->makeUniqid();
        $this->stmt_arr[$unique_key] = $stmt;

        return $unique_key;
    }

    protected function bindParams($stmt, $params)
    {
        if(empty($params) || !is_array($params))
            return;

        foreach($params as $key => $value) {
            if(is_array($value))
                $value = implode(',', $value);
            $stmt->bindParam(':'.$key, $value);
        }
    }

    # 获取影响行数
    protected function rowCount($stmt)
    {
        return $stmt->rowCount();
    }

    # 获取错误码
    protected function errorCode($stmt)
    {
        return $stmt->errorCode();
    }

    # 获取查询结果集
    protected function getRow($stmt)
    {
        while ($row[] = $stmt->fetch(PDO::FETCH_ASSOC)){}
        array_pop($row);

        return $row;
    }

    # 获取执行sql
    protected function executeSql($stmt)
    {
        ob_start();
        $stmt->debugDumpParams();
        $debug_info = ob_get_contents();
        ob_end_clean();
        ob_flush();
        flush();
        preg_match('/Sent SQL:[\s\S]*\]([\s\S]*?)P/', $debug_info, $match);
        $match[1] ?? preg_match('/SQL:[\s\S]*\]([\s\S]*?)P/', $debug_info, $match);
        $sql = $match[1] ?? '';
        return trim($sql);
    }

    # 获取错误信息
    protected function errorMessage($stmt)
    {
        return $stmt->errorInfo();

    }

    # 开启事务
    protected function beginTransaction()
    {
        $this->con->beginTransaction();
    }

    # 提交事务
    protected function commit()
    {
        if(PDO::inTransaction())
            $this->con->commit();
    }

    # 回滚事务
    protected function rollBack()
    {
        if(PDO::inTransaction())
            $this->con->rollBack();
    }

    # 释放语句对象
    protected function releaseStmt($key)
    {
        unset($this->stmt_arr[$key]);
    }

    /*
     * 唯一性ID
     *
     * @return string
     * */
    protected function makeUniqid()
    {
        $prefix = $this->getUniqueString();
        return uniqid($prefix, true);
    }

    /*
     * 唯一性字符串
     *
     * @return string
     * */
    private function getUniqueString()
    {
        $string = '';
        for($i = 0; $i < $this->unique_prefix_length; $i++) {
            $string .= $this->unique_string[mt_rand(0, strlen($this->unique_string) - 1)];
        }

        return $string;
    }
}