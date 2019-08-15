<?php

class DBManager
{
    private $serveraddr;
    private $username;
    private $password;
    private $dbname;
    private $dbconnect;
    private $tablename;

    public function __construct(string $serveraddr, string $username, string $password)
    {
        $this->serveraddr = $serveraddr;
        $this->username   = $username;
        $this->password   = $password;
    }

    public function IsConnected()
    {
        if($this->dbconnect == null)
            return false;
        else
            return true;
    }

    public function Connect(string $dbname)
    {
        try{
            $this->dbconnect  = new PDO("mysql:host=" . $this->serveraddr . ";dbname=" . $dbname, $this->username, $this->password, array(PDO::MYSQL_ATTR_INIT_COMMAND => "set names utf8"));
            $this->dbconnect->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->dbname     = $dbname;
            return true;
        }
        catch(PDOException $e)
        {
            #echo $e->getMessage();
            $this->dbconnect = null;
            return false;
        }
    }

    public function CreateDatabase($dbname, $dbcmd)
    {
        try{
            $this->dbconnect  = new PDO("mysql:host=" . $this->serveraddr, $this->username, $this->password, array(PDO::MYSQL_ATTR_INIT_COMMAND => "set names utf8"));
            $this->dbconnect->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $sqlcmd = "CREATE DATABASE IF NOT EXISTS $dbname $dbcmd;";
            $this->dbconnect->exec($sqlcmd);
            $this->dbconnect->exec("use $dbname;");
            $this->dbname = $dbname;
            return true;
        }
        catch(PDOException $e)
        {
            // echo $e->getMessage();
            $dbconnect = null;
            return false;
        }
    }

    public function CreateTable($tablename, $tablecmd)
    {
        try{
            $sqlcmd = "CREATE TABLE IF NOT EXISTS " 
                . $tablename    
                . $tablecmd;

            if($this->ExecCmd($sqlcmd))
                return true;
            else
                return false;
        }
        catch(PDOException $e)
        {
            echo $e->getMessage();
            $this->dbconnect = null;
            return false;
        }
    }

    public function ExecCmd($cmd)
    {
        try{
            if(!$this->IsConnected())
                return false;
            $this->dbconnect->beginTransaction();
            $this->dbconnect->exec($cmd);
            $this->dbconnect->commit();
            return true;
        }
        catch(PDOException $e)
        {
            $this->dbconnect->rollback();
            echo $cmd . "<br>" . $e->getMessage();
            return false;
        }
    }

    public function Select(string $table, string $where = "", bool $countonly = true)
    {
        try{
            if(!$this->IsConnected())
                return false;
            $cmd = "select * from $table where $where";
            $results = $this->dbconnect->query($cmd);
            if($countonly){
                $count = $results->rowCount();
                $results->closeCursor();
                return $count;
            }
            $resultarray = [];
            foreach ($results as $row) {
                array_push($resultarray, $row);
            }
            return $resultarray;
        }
        catch(PDOException $e)
        {
            $this->dbconnect->rollback();
            echo $cmd . "<br>" . $e->getMessage();
            return false;
        }
    }

    public function Insert(string $table, array $keypairs)
    {
        try{
            if(!$this->IsConnected())
                return false;
            if(count($keypairs) < 1)
                return false;
            $keys = "";
            $vals = "";
            foreach($keypairs as $key => $val){
                $vallen = strlen($val);
                if(!($vallen > 2 && substr($val, -2) === "()"))
                    $val = "'$val'";
                $keys = "$keys $key,";
                $vals = "$vals $val,";
            }
            $keys = ltrim(rtrim($keys, ","));
            $vals = ltrim(rtrim($vals, ","));
            $cmd = "insert into $table ($keys) VALUES ($vals);";
            $ret = $this->dbconnect->exec($cmd);
            return $ret;
        }
        catch(PDOException $e)
        {
            echo $cmd . "\n" . $e->getMessage() . "\n";
            return false;
        }
    }

    public function Update(string $table, string $where, array $keypairs)
    {
        try{
            if(!$this->IsConnected())
                return false;
            if(count($keypairs) < 1)
                return false;
            $updatepairs = "";
            foreach($keypairs as $key => $val) {
                $updatepairs = "$updatepairs $key = '$val',";
            }
            $updatepairs = rtrim($updatepairs, ",");
            $cmd = "update $table set $updatepairs where $where;";
            $ret = $this->dbconnect->exec($cmd);
            return $ret;
        }
        catch(PDOException $e)
        {
            echo $cmd . "\n" . $e->getMessage() . "\n";
            return false;
        }
    }
}
