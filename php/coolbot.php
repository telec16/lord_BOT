<pre>
<?php
#https{//rocket.chat/docs/developer-guides/rest-api/

include('ids.php');
include_once('rocketchat_API/RocketChat.php');

function remove($array, $obj){
	foreach($array as $key => $value){
		if($value == $obj){
			unset($array[$key]);
		}
	}
	
	return $array;
}

############################
# ROCKETCHAT COMMUNICATION #
############################

function _connect(){
	$rocket = new \RocketChat\Client(USERNAME, PASSWORD);
	$rocket->login();
    return $rocket;
}

function _retrieve_msgs($rocket, $allOfThem=False, $since=null){
    if ($allOfThem){
        $hist = $rocket->channels_history(CHANNEL_ID, 10000);
        $msgs = $hist['messages'];
	}
    else{
        $old = $since->format(PARSE_DATE);
        $hist = $rocket->channels_history(CHANNEL_ID, 10000, $old);
        $msgs = $hist['messages'];
	}

    return $msgs;
}

function _print_scores($rocket, $data, $toChat=False){

    #Choose random avatar/name from all available images
    $url = $data['imgs'][array_rand($data['imgs'])];
	$exp = explode("/", $url);
    $alias = end($exp);

    print("avatar: {$url} alias: {$alias}"); echo "\n</br>";
    var_dump($data); echo "\n</br>";

	$scores = $data['users'];
	$rektSet = $data['rekts'];
    #Make the scoreboard and send it if required
    $out = "Tableau des scores : \n";
    foreach($scores as $name => $values)
        $out .= "{$name} a totalisé {$scores[$name]['score']} points\n";
    print ($out); echo "</br>";
    if ($toChat)
        $rocket->chat_post_message(CHANNEL_ID, $out, $alias, $url);

    #Make the rektboard and send it if required;
    if (count($rektSet) != 0){
        $out = "Tableau des rekts : \n";
        foreach($rektSet as $rekts){
			/*** Hacky stuff ***/
			$h = intval($rekts[2]->format("H")) + TIMEZONE;
			$m = intval($rekts[2]->format("i"));
			$s = intval($rekts[2]->format("s"));
			$rekts[2]->setTime($h,$m,$s);
			/*** Hacky stuff ***/
			
            $out .= "{$rekts[0]} a rekt {$rekts[1]} le {$rekts[2]->format(PARSE_DATE_U)}";
		}
        print ($out); echo "</br>";
        if ($toChat)
			$rocket->chat_post_message(CHANNEL_ID, $out, "Rektor", $url);
	}
}


##########################
# SCORE SAVING FUNCTIONS #
##########################

function _save_data($data){
	file_put_contents("./coolbot/data.bin", serialize($data));
}

function _retrieve_data(){
    $data = [];

	$f = file_get_contents("./coolbot/data.bin");
	if($f !== False)
		$data  = unserialize($f);

    return $data;
}


####################
# SCORE MANAGEMENT #
####################

function _add_score($scores, $name, $date){
    $time = $date->format("Hi");
    $rekt = [];

    #Create if new player
    if(!array_key_exists($name, $scores)){
        $scores[$name]['dates'] = [];
        $scores[$name]['score'] = 0;
	}

    #Check time in all last times
    foreach($scores as $_name => $values){
        foreach($values['dates'] as $d){
			$t = $d->format("Hi");
            if ($t == $time){ #We got a match !

                $scores[$_name]['dates'] = remove($scores[$_name]['dates'], $d);
				if($d>$date){
					$rekt = [$_name, $name, $d]; //$_name rekt $name on $d
					$scores[$_name]['score'] -= 2;
					$scores[$name] ['score'] -= 0;
				}
				else{
					$rekt = [$name, $_name, $date]; //$name rekt $_name on $date
					$scores[$_name]['score'] -= 1;
					$scores[$name] ['score'] -= 1;
				}
			}
		}
	}

    #Nobody got rekt
    if(count($rekt) == 0){
		$scores[$name]['dates'][] = $date;
        $scores[$name]['score'] += 1;
		if($date->format("Hi") == "0000")
			$scores[$name]['score'] += 10;
		if($date->format("His") == "000000")
			$scores[$name]['score'] += 100;
	}

    return [$scores, $rekt];
}

function _add_scores($msgs, $data=null){
    if($data == null){
        $data = [];
        $data['users'] = [];
        $data['lastCheck'] = null;
        $data['imgs'] = [];
        $data['rekts'] = [];
	}
	$newThing = False;

    foreach($msgs as $msg){
        #if this msg contains a picture
        if (array_key_exists("attachments", $msg) //If we have an attachment
			and ($msg["attachments"] != null) //And it is not null
			and (count($msg["attachments"]) > 0) //Or empty
			and array_key_exists("image_url", $msg["attachments"][0])) //And contains an image...
		{
            $url = $msg["attachments"][0]["image_url"];
            $data['imgs'][] = $url;

            $date = date_create_from_format(PARSE_DATE, $msg["ts"]);
			if(($data['lastCheck'] == null) or ($date > $data['lastCheck']))
				$data['lastCheck'] = $date;
			
            $name = $msg["u"]["username"];
			
            [$scores, $rekt] = _add_score($data['users'], $name, $date);
			
			$data['users'] = $scores;
            if(count($rekt) != 0)
                $data['rekts'][] = $rekt;

			$newThing = True;
		}
	}

    return [$newThing, $data];
}



#############################
####### MAIN FUNCTION #######
#############################

function coolbot_manage($sumUp = False, $toChat=True){
    $rocket = _connect();
	$newThing = False;

    if($sumUp) {
        $msgs = _retrieve_msgs($rocket, True);
        [$newThing, $data] = _add_scores($msgs);
	}
    else{
        $data = _retrieve_data();
        $msgs = _retrieve_msgs($rocket, False, $data['lastCheck']);
        [$newThing, $data] = _add_scores($msgs, $data);
	}
	if($newThing)
		_print_scores($rocket, $data, $toChat);
	else
		print("Up to date");
    _save_data($data);
}


?>
</pre>
