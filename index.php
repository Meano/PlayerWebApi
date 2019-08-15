<?php
require("PlayerDB.php");

header('Content-Type:application/json;charset=utf-8');  

function get($name)
{
    return ! empty($_GET[$name]) ? $_GET[$name] : "";
}

function post($name)
{
    return ! empty($_POST[$name]) ? $_POST[$name] : "";
}

function response($status, $message = '', $data = array()) {
    if (! is_bool ( $status )) {
        return '';
    }
    $result = array (
        'status' => $status,
        'message' => $message,
        'data' => $data 
    );
    echo json_encode($result,JSON_UNESCAPED_UNICODE);
}

function lib_replace_end_tag($str) 
{ 
    if (empty($str)) return false;
    $str = htmlspecialchars($str);
    $str = str_replace('/', "", $str);
    $str = str_replace("\\", "", $str);
    $str = str_replace(">", "", $str);
    $str = str_replace("<", "", $str);
    $str = str_replace("<SCRIPT>", "", $str);
    $str = str_replace("</SCRIPT>", "", $str);
    $str = str_replace("<script>", "", $str);
    $str = str_replace("</script>", "", $str);
    $str = str_replace("select","select",$str);
    $str = str_replace("join","join",$str);
    $str = str_replace("union","union",$str);
    $str = str_replace("where","where",$str);
    $str = str_replace("insert","insert",$str);
    $str = str_replace("delete","delete",$str);
    $str = str_replace("update","update",$str);
    $str = str_replace("like","like",$str);
    $str = str_replace("drop","drop",$str);
    $str = str_replace("create","create",$str);
    $str = str_replace("modify","modify",$str);
    $str = str_replace("rename","rename",$str);
    $str = str_replace("alter","alter",$str);
    $str = str_replace("cas","cast",$str);
    $str = str_replace("&","&",$str);
    $str = str_replace(">",">",$str);
    $str = str_replace("<","<",$str);
    $str = str_replace(" ",chr(32),$str);
    $str = str_replace(" ",chr(9),$str);
    $str = str_replace(" ",chr(9),$str);
    $str = str_replace("&",chr(34),$str);
    $str = str_replace("'",chr(39),$str);
    $str = str_replace("<br />",chr(13),$str);
    $str = str_replace("''","'",$str);
    $str = str_replace("css","'",$str);
    $str = str_replace("CSS","'",$str);
    return $str;
}

$action = get('action');
//var_dump($_POST);
$playerdb = new playerDB();
$result = array();
switch ($action) {
    case "verify":
        $result = $playerdb->VerifyMail(post("mail"));
        break;
    case "register":
        $result = $playerdb->Register(post("mail"), post("mailcode"), post("password"), post("playername"));
        break;
    case "login":
        $result = $playerdb->Login(post("mail"), post("password"), post("uuid"), post("token"), post("skinenable"));
        break;
    case "heartbeat":
        $result = $playerdb->Heartbeat(post("uuid"), post("session"), post("status"));
        break;
    // Server API
    case "check":
        $result = $playerdb->Check(post("playeruuid"));
        break;
    default:
        break;
}
echo json_encode($result, JSON_UNESCAPED_UNICODE);
echo "\n";
?>