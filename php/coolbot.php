<pre>
<?php
#https{//rocket.chat/docs/developer-guides/rest-api/

include('./ids.php');
include('./rocketchat_API/RocketChat.php');


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
        $old = $since.format(PARSE_DATE);
        $hist = $rocket->channels_history(CHANNEL_ID, 10000, $old);
        $msgs = $hist['messages'];
	}
	
    return $msgs;
}

function _print_scores($rocket, $scores, $imgSet, $rektSet, $toChat=False){

    #Choose random avatar/name from all available images
    $url = $imgSet[array_rand($imgSet)];
	$exp = explode("/", $url);
    $alias = end($exp);

    print("avatar: {$url} alias: {$alias}"); echo "</br>";
    var_dump($scores); echo "</br>";

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
        foreach($rektSet as $rekts)
            $out .= "{$rekts[1]} a rekt {$rekts[0]} le {$rekts[2]}";
        print ($out); echo "</br>";
        if ($toChat)
			$rocket->chat_post_message(CHANNEL_ID, $out, "Rektor", $url);
	}
}

/*
##########################
# SCORE SAVING FUNCTIONS #
##########################

function _save_scores(scores, imgSet, rektSet){
    scores_file  = open("scores.bin" , "wb");
    pickle.dump(scores , scores_file , pickle.HIGHEST_PROTOCOL);

    imgSet_file  = open("imgSet.bin" , "wb");
    pickle.dump(imgSet , imgSet_file , pickle.HIGHEST_PROTOCOL);

    rektSet_file = open("rektSet.bin", "wb");
    pickle.dump(rektSet, rektSet_file, pickle.HIGHEST_PROTOCOL);
}
	
function _retrieve_scores(){
    scores = {};
    imgSet = [];
    rektSet = [];

    if (Path("scores.bin").is_file()){
        scores_file  = open("scores.bin" , "rb");
        scores  = pickle.load(scores_file);
	}

    if (Path("imgSet.bin").is_file()){
        imgSet_file  = open("imgSet.bin" , "rb");
        imgSet  = pickle.load(imgSet_file)
	}

    if (Path("rektSet.bin").is_file()){
        rektSet_file = open("rektSet.bin", "rb");
        rektSet = pickle.load(rektSet_file);
	}

    return scores, imgSet, rektSet;
}
*/
####################
# SCORE MANAGEMENT #
####################

function _add_score($scores, $name, $date){
    $time = [$date->format("H"), $date->format("i")];
    $rekt = "";
    
    #Create if new player
    if(!array_key_exists($name, $scores)){
        $scores[$name]['dates'] = [];
        $scores[$name]['time']  = [];
        $scores[$name]['score'] = 0;
	}

    #Append date (to get the last one)
    $scores[$name]['dates'][] = $date;

    #Check time in all last times
    foreach($scores as $_name => $values){
        foreach($values['time'] as $t){
            if ($t == $time){ #We got a match !
                $rekt = $_name; #sorry :(
                $scores[$_name]['score'] -= 1;
                $scores[$name] ['score'] -= 1;
                $scores[$_name]['time'] = array_diff($scores[$_name]['time'], $t);
			}
		}
	}

    #Nobody got rekt
    if($rekt == ""){
        $scores[$name]['time'][] = $time;
        $scores[$name]['score'] += 1;
	}

    return [$scores, $rekt];
}

function _add_scores($msgs, $scores=null, $imgSet=null, $rektSet=null){
    if($scores == null)
        $scores = [];
    if($imgSet == null)
        $imgSet = [];
    if($rektSet == null)
        $rektSet = [];

    foreach($msgs as $msg){
        #if this msg contains a picture
        if (array_key_exists("attachments", $msg) and ($msg["attachments"] != null) and (count($msg["attachments"]) > 0)){
            $url = $msg["attachments"][0]["image_url"];
            $imgSet[] = $url;

            $date = date_create_from_format(PARSE_DATE, $msg["ts"]);
            $name = $msg["u"]["username"];
            [$scores, $rekt] = _add_score($scores, $name, $date);
            if($rekt != null)
                $rektSet[] = [$rekt, $name, $date];
		}
	}
	
    return [$scores, $imgSet, $rektSet];
}

function _get_last_date($scores){
    $ld = null;

    foreach($scores as $name => $values)
        foreach($values['dates'] as $d)
            if (($ld == null) or ($d>$ld))
                $ld = $d;

    return $ld;
}



#############################
####### MAIN FUNCTION #######
#############################

function manage($sumUp = False, $toChat=True){
    $rocket = _connect();
    
    if($sumUp) {
        $msgs = _retrieve_msgs($rocket, True);
        [$scores, $imgSet, $rektSet] = _add_scores($msgs);
	}
    else{
        //[$scores, $imgSet, $rektSet] = _retrieve_scores();
        $date = _get_last_date($scores);
        $msgs = _retrieve_msgs($rocket, $since=date);
        [$scores, $imgSet, $rektSet] = _add_scores($msgs, $scores, $imgSet, $rektSet);
	}
    _print_scores($rocket, $scores, $imgSet, $rektSet, $toChat);
    //_save_scores($scores, $imgSet, $rektSet);
}

manage(True, True);

?>
</pre>
