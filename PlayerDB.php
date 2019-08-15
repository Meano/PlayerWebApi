<?php

require("DBManager.php");

class playerDB extends DBManager
{
    private $serveraddr = "localhost";
    private $username = "root";
    private $password = "password";
    private $mcdatabase = "database";
    private $mctable = "PMPlayers";
    private $mctablestruct =  "table struct";

    function __construct()
    {
        parent::__construct($this->serveraddr, $this->username, $this->password);
    }

    function ConnectDB()
    {
        if($this->IsConnected()){
            return true;
        }

        if(!$this->Connect($this->mcdatabase))
        {
            if(!$this->CreateDatabase($this->mcdatabase, "DEFAULT CHARSET utf8 COLLATE utf8_general_ci;"))
            {
                echo "数据库连接失败!<br>";
                return false;
            }
        }
    
        if(!$this->CreateTable($this->mctable, $this->mctablestruct))
        {
            echo "数据表创建失败!<br>";
            return false;
        }
        return true;
    }

    function HasPlayer(string $playername, string $mail)
    {
        $result = $this->Select($this->mctable, "PlayerName='$playername' or Mail='$mail'");
        return $result > 0;
    }

    function HasMail(string $mail)
    {
        $result = $this->Select($this->mctable, "Mail='$mail'");
        return $result == 1;
    }

    function GetPlayer(string $playerinfo){
        if(strpos($playerinfo, "@") !== false)
            $infoname = "Mail";
        else
            $infoname = "UUID";
        $result = $this->Select($this->mctable, "$infoname='$playerinfo'", false);
        if($result != false && count($result) == 1)
            return $result[0];
       
        return false;
    }

    function SetPlayer(string $uuid, array $infoarray){
        return $this->Update($this->mctable, "UUID='$uuid'", $infoarray);
    }

    function ReturnStatus(bool $status, string $message = "", array $data = array())
    {
        return
        [
            "Status" => $status,
            "Message" => $message,
            "Data" => $data,
        ];
    }

    function CanRequest(string $key, int $timeout = 60, int $limitcount = 1){
        $redis = new redis();

        if(!$redis->connect('redisAddr', 'redisPort'))
            return false;

        if(!$redis->auth('redisAuth'))
            return false;
        $key = $key . (empty($_SERVER['REMOTE_ADDR']) ? "127.0.0.1" : $_SERVER['REMOTE_ADDR']);

        $check = $redis->exists($key);
        if($check){
            $redis->incr($key);
            $count = $redis->get($key);
            if($count > $limitcount){
                return false;
            }
        }else{
            $redis->incr($key);
            $redis->expire($key, $timeout);
        }
        return true;
    }

    function SetRedis(string $key, $value, int $timeout = 60)
    {
        $redis = new redis();

        if(!$redis->connect('redisAddr', 'redisPort'))
            return false;

        if(!$redis->auth('redisAuth'))
            return false;
        
        $redis->set($key, $value);
        $redis->expire($key, $timeout);
        return true;
    }

    function GetRedis(string $key)
    {
        $redis = new redis();

        if(!$redis->connect('redisAddr', 'redisPort'))
            return false;

        if(!$redis->auth('redisAuth'))
            return false;

        return $redis->get($key);
    }

    function DelRedis(string $key)
    {
        $redis = new redis();

        if(!$redis->connect('redisAddr', 'redisPort'))
            return false;

        if(!$redis->auth('redisAuth'))
            return false;

        return $redis->set($key, null);
    }

    function VerifyMail(string $mail)
    {
        if(!$this->CanRequest("player.verifymail.", 60, 10))
            return $this->ReturnStatus(false, "请求过快，请稍后重试！");

        $mail = strtolower($mail);
        if(!filter_var($mail, FILTER_VALIDATE_EMAIL))
            return $this->ReturnStatus(false, "邮箱格式不正确！");

        $maildomain = explode('@', $mail)[1];
        if(
            $maildomain != 'qq.com' &&
            $maildomain != 'foxmail.com' &&
            $maildomain != 'meano.net'
        )
            return $this->ReturnStatus(false, "当前仅支持QQ/Foxmail邮箱注册！");

        $result = $this->ConnectDB();
        if(!$result) 
            return $this->ReturnStatus($result, "服务器数据库连接失败！");

        if($this->HasMail($mail))
            return $this->ReturnStatus(false, "邮箱地址 $mail 已被注册！");

        if(!$this->CanRequest("player.sendmail.", 180, 1))
            return $this->ReturnStatus(false, "请在三分钟后再次请求验证！");

        $mailcode = sprintf("%06d", mt_rand(0, 999999));

        if(!$this->SetRedis("player.mailcode." . $mail, $mailcode, 180))
            return $this->ReturnStatus(false, "服务器对象存储错误！");
        
        $sendmailcmd = "sendmailCmd";
        exec($sendmailcmd);
        return $this->ReturnStatus(true, "校验码发送成功！");
    }

    function Register(string $mail, string $mailcode, string $password, string $playername)
    {
        if(!$this->CanRequest("player.register.", 60, 10))
            return $this->ReturnStatus(false, "请求过快，请稍后重试！");

        if(!preg_match("/^[\w\x{4e00}-\x{9fa5}]{3,15}$/u", $playername, $matches))
        {
            return $this->ReturnStatus(false, "游戏ID $playername 不可用！限定1-15个汉字、英文大小写字母、\n数字0-9以及英文下划线符号\"_\"的任意组合。");
        }

        if(
            !(
            preg_match("/[\[\]~!@#$%^&*()\-_=+{};:<,.>?]/", $password) > 0 &&
            preg_match("/[A-Z]/", $password) > 0 &&
            preg_match("/[a-z]/", $password) > 0 &&
            preg_match("/[0-9]/", $password) > 0 &&
            strlen($password) >= 8 &&
            strlen($password) <= 20
            )
        )
        {
            return $this->ReturnStatus(false, "密码必须为8~20位的字母(同时含有大小写)、数字\n以及特殊符号[~!@#$%^&*()\-_=+{};:<,.>?]三者的组合。");
        }

        if(!$this->ConnectDB()) 
            return $this->ReturnStatus(false, "服务器数据库连接失败！");

        if($this->HasPlayer($playername, $mail))
            return $this->ReturnStatus(false, "邮箱地址或玩家名称已被注册！");

        $mailcodeverify = $this->GetRedis("player.mailcode." . $mail);
        if($mailcodeverify == false || empty($mailcodeverify) || $mailcodeverify != $mailcode)
        {
            return $this->ReturnStatus(false, "邮箱验证码不正确！");
        }
        $this->DelRedis("player.mailcode." . $mail);

        $playerarray = [
            "UUID" => "uuid()",
            "Mail" => "$mail",
            "PlayerName" => "$playername",
            "Password" => password_hash("$password", PASSWORD_DEFAULT),
            "RegisterDate" => "now()",
        ];
        if($this->Insert($this->mctable, $playerarray) != 1)
            return $this->ReturnStatus(true, "服务器数据库添加失败！");

        return $this->ReturnStatus(true, "注册成功！");
    }

    private function GenerateSession(string $playername, string $uuid = "", string $skinenable, string $tokennew = "")
    {
        $currenttime = new DateTime("now");
        $sessionnew = md5("session.$uuid" . sprintf("%06d", mt_rand(0, 999999)) . $currenttime->format("YmdHis"));
        if(!$this->SetRedis("player.session.$uuid", $sessionnew, 36000))
            return $this->ReturnStatus(false, "服务器对象存储错误！");

        switch($skinenable){
            case "off":
                $skinenable = 0;
                break;
            case "default":
                $skinenable = 1;
                break;
            case "random":
                $skinenable = 2;
                break;
            default:
                $skinenable = 0;
                break;
        }
        $this->SetPlayer($uuid, ["SkinEnable" => $skinenable]);
        if($tokennew === "")
            return $this->ReturnStatus(true, "登录成功！$skinenable", [ "PlayerName" => $playername, "Session" => $sessionnew , "SkinEnable" => $skinenable]);
        else
            return $this->ReturnStatus(true, "登录成功！", [ "UUID" => $uuid, "Token" => $tokennew, "PlayerName" => $playername, "Session" => $sessionnew ]);
    }

    function Login(string $mail = "", string $password = "", string $uuid = "", string $token = "", string $skinenable = "")
    {
        if(!$this->CanRequest("player.login.", 60, 10))
            return $this->ReturnStatus(false, "请求过快，请稍后重试！");

        if(strlen($token) == 32 && strlen($uuid) == 36 && $password === "" && $mail === "")
        {
            $tokenverify = $this->GetRedis("player.logintoken.$uuid");
            if(!(strlen($tokenverify) == 32 && $tokenverify == $token))
            {
                return $this->ReturnStatus(false, "验证过期，请重新登陆！");
            }
            else
            {
                if(!$this->ConnectDB()) 
                    return $this->ReturnStatus(false, "服务器数据库连接失败！");

                    $player = $this->GetPlayer($uuid);
                if($player == false)
                    return $this->ReturnStatus(false, "验证过期！$uuid");
                return $this->GenerateSession($player["PlayerName"], $uuid, $skinenable);
            }
        }

        if(
            !(
            preg_match("/[\[\]~!@#$%^&*()\-_=+{};:<,.>?]/", $password) > 0 &&
            preg_match("/[A-Z]/", $password) > 0 &&
            preg_match("/[a-z]/", $password) > 0 &&
            preg_match("/[0-9]/", $password) > 0 &&
            strlen($password) >= 8 &&
            strlen($password) <= 20
            )
        )
        {
            return $this->ReturnStatus(false, "密码格式错误！");
        }
        if(!$this->ConnectDB()) 
            return $this->ReturnStatus(false, "服务器数据库连接失败！");
        
        $player = $this->GetPlayer($mail);
        if($player == false || !password_verify($password, $player['Password']))
            return $this->ReturnStatus(false, "密码错误！");

        $uuid = $player["UUID"];
        $playername = $player["PlayerName"];

        $currenttime = new DateTime("now");
        $tokennew = md5("token.$uuid" . sprintf("%06d", mt_rand(0, 999999)) . $currenttime->format("YmdHis"));
        if(!$this->SetRedis("player.logintoken.$uuid", $tokennew, 86400 * 5))
            return $this->ReturnStatus(false, "服务器对象存储错误！");

        return $this->GenerateSession($playername, $uuid, $skinenable, $tokennew);
    }

    function Heartbeat($uuid, $session, $status)
    {
        if(!$this->CanRequest("player.heartbeat.", 60, 20))
            return $this->ReturnStatus(false, "too fast");
        if(strlen($session) == 32)
        {
            $sessionverify = $this->GetRedis("player.session.$uuid");
            if(strlen($sessionverify) == 32 && $sessionverify == $session)
            {
                if($status === "join"){
                    //$this->SetRedis("player.session.$uuid", $session, 300);
                    $this->SetRedis("player.status.$uuid", $status, 300);
                    return $this->ReturnStatus(true);
                }
                else if($status === "keep")
                {
                    //$this->SetRedis("player.session.$uuid", $session, 300);
                    return $this->ReturnStatus(true);
                }
                else{
                    $this->DelRedis("player.session.$uuid");
                    $this->DelRedis("player.status.$uuid");
                    return $this->ReturnStatus(false);
                }
            }
        }
        return $this->ReturnStatus(false, "Session error!");
    }

    // Server Apis
    function Check($uuid)
    {
        $fromip = empty($_SERVER['REMOTE_ADDR']) ? "0.0.0.0" : $_SERVER['REMOTE_ADDR'];
        if($fromip != "127.0.0.1")
            return $this->ReturnStatus(false);
        $player = $this->GetPlayer($uuid);
        $uuid = $player["UUID"];
        $session = $this->GetRedis("player.session.$uuid");
        $status = $this->GetRedis("player.status.$uuid");
        if (strlen($session) == 32 && $status == "join"){
            return $this->ReturnStatus(true);
        }
        return $this->ReturnStatus(false);
    }
}
?>
