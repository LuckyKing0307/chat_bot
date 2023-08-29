<?php 
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/config.php';
use Longman\TelegramBot\Telegram;
use Longman\TelegramBot\Request;
use function Amp\async;
use function Amp\delay;
$future = [];
$array_bans = [];
$messages_id = [];
$telegram = new Telegram($bot_api_key, $bot_username);
$telegram->useGetUpdatesWithoutDatabase();

function insertBd($bot_id,$user_id,$group_id,$group=null){
	global $channel_id,$bd;
    if ($group) {
        $query = "INSERT INTO `message`(`bot_id`, `chat_id`, `user_id`,`message`) VALUES ('".$bot_id."','".$group_id."','".$user_id."','group')";
    }else{
        $query = "INSERT INTO `message`(`bot_id`, `chat_id`, `user_id`) VALUES ('".$bot_id."','".$group_id."','".$user_id."')";
    }
	$data = mysqli_query($bd,$query);
}
function sendText($from,$text,$bot_msg_id,$reply=null,$to=null){
	global $channel_id,$bd;
	if ($to===null) {
		$to=$channel_id;
	}
	$result = Request::sendMessage([
	    'chat_id' => $to,
	    'text'    => $text,
	]);
	$chat_id = $result->getResult()->getMessageId();
	if ($reply) {
		insertBd($bot_msg_id,$from,$chat_id,'group');
	}else{
		insertBd($bot_msg_id,$from,$chat_id);
	}
	return $result;
}
function selectBd($id,$type){
	global $channel_id,$bd;
    $user_data = array();
    if ($type=='chat_id') {
        $query = "SELECT * FROM `message` WHERE chat_id=".$id;
    }else{
        $query = "SELECT * FROM `message` WHERE chat_id=".$id." and message='group'";
    }
	$data = mysqli_query($bd,$query);
	$row = mysqli_fetch_assoc($data);
    return $row;
}
function userban($id,$type){
	global $channel_id,$bd;
    if ($type=='ban') {
    	$query = "INSERT INTO `bans`(`telegram_id`) VALUES ('".$id."')";
    }if ($type=='unban') {
    	$query = "DELETE FROM `bans` WHERE telegram_id=".$id;
    }
	$data = mysqli_query($bd,$query);
}
function selectBan($id){
		global $channel_id,$bd;
        $result = "SELECT * FROM `bans` WHERE telegram_id=".$id;
		$data = mysqli_query($bd,$result);
		$numrows = mysqli_num_rows($data);
        return $numrows;
}
while (true) {
	try {
		delay(5);
	    $server_response = $telegram->handleGetUpdates();
	    if ($server_response->isOk()) {
	        $update_count = count($server_response->getResult());
	    	if ($update_count>0) {
	    		foreach ($server_response->getResult() as $result) {
	    			$content = $result->getUpdateContent();
	    			$type = $content->getType();
	    			$from = $content->getFrom()->getId();
	    			$chat = $content->getChat()->getId();
                	$bot_msg_id = $content->getMessageId();
	    			$text = $content->getText();
	    			if (selectBan($chat)===0) {
		    			if ($type=='text') {
		    				if ($content->getReplyToMessage()) {
			    				$reply = $content->getReplyToMessage();
		    					$id = $reply->getMessageId();
		    					$chat_id = $content->getChat()->getId();
			    				$message_bot_data = selectBd($id,'chat_id');
			    				sendText($chat_id,$text,$bot_msg_id,$message_bot_data['bot_id'],$message_bot_data['user_id']);
			    			}else{
		                		sendText($from,$text,$bot_msg_id);
			    			}
		    			}
	    			}
	    			if ($type=='command') {
		    			if ($content->getReplyToMessage()) {
			    			$reply = $content->getReplyToMessage();
		    				var_dump($chat);
		    				var_dump($channel_id);
		    				if ($chat==$channel_id) {
		    					$id = $reply->getMessageId();
		    					if ($text=='/ban') {
		                            $data = selectBd($id,'chat_id');
		                            userban($data['user_id'],'ban');
		                        }if ($text=='/unban') {
		                            $data = selectBd($id,'chat_id');
		                            userban($data['user_id'],'unban');
		                        }
		    				}
		    			}
		    		}
	    	}
	    }}
	} catch (Longman\TelegramBot\Exception\TelegramException $e) {
	    // log telegram errors
	     echo $e->getMessage();
	}
}