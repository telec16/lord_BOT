#https://rocket.chat/docs/developer-guides/rest-api/

from rocketchat_API.rocketchat import RocketChat
from datetime import datetime as dt
from pprint import pprint
from pathlib import Path
import secrets
import pickle

import ids

        

############################
# ROCKETCHAT COMMUNICATION #
############################
    
def _connect(config):
    rocket = RocketChat(config['user'], config['pass'], config['type'] + '://' + config['host'])
    return rocket

def _retrieve_msgs(rocket, config, allOfThem=False, since=None):
    if allOfThem:
        hist = rocket.channels_history(config['channel'], count=10000).json()
        msgs = hist['messages']
    else:
        old = dt.strftime(since, config["parseDate"])
        hist = rocket.channels_history(config['channel'], count=10000, oldest=old).json()
        msgs = hist['messages']

    return msgs

def _print_scores(rocket, config, scores, imgSet, rektSet, toChat=False):

    #Choose random avatar/name from all available images
    url = secrets.choice(imgSet)
    alias = url[(url.rfind("/")+1):]

    print("avatar: " + url + "  alias: "+alias)
    pprint(scores)

    #Make the scoreboard and send it if required
    out = "Tableau des scores : \n"
    for name in scores:
        out += name + " a totalisé " + str(scores[name]['score']) + " points\n"
    print (out)
    if toChat:
        rocket.chat_post_message(out, room_id=config['channel'], avatar=url, alias=alias)

    #Make the rektboard and send it if required
    if len(rektSet) != 0:
        out = "Tableau des rekts : \n"
        for rekts in rektSet:
            out += rekts[1] + " a rekt " + rekts[0] + " le " + str(rekts[2])
        print (out)
        if toChat:
            rocket.chat_post_message(out, room_id=config['channel'], avatar=url, alias="Rektor")


##########################
# SCORE SAVING FUNCTIONS #
##########################

def _save_scores(scores, imgSet, rektSet):
    scores_file  = open("scores.bin" , "wb")
    pickle.dump(scores , scores_file , pickle.HIGHEST_PROTOCOL)
        
    imgSet_file  = open("imgSet.bin" , "wb")
    pickle.dump(imgSet , imgSet_file , pickle.HIGHEST_PROTOCOL)
    
    rektSet_file = open("rektSet.bin", "wb")
    pickle.dump(rektSet, rektSet_file, pickle.HIGHEST_PROTOCOL)

def _retrieve_scores():
    scores = {}
    imgSet = []
    rektSet = []

    if Path("scores.bin").is_file():
        scores_file  = open("scores.bin" , "rb")
        scores  = pickle.load(scores_file)
    
    if Path("imgSet.bin").is_file():
        imgSet_file  = open("imgSet.bin" , "rb")
        imgSet  = pickle.load(imgSet_file)
    
    if Path("rektSet.bin").is_file():
        rektSet_file = open("rektSet.bin", "rb")
        rektSet = pickle.load(rektSet_file)

    return scores, imgSet, rektSet


####################
# SCORE MANAGEMENT #
####################

def _add_score(scores, name, date):
    time = [date.hour, date.minute]
    rekt = None
    
    #Create if new player
    if name not in scores:
        scores[name] = {}
        scores[name]['dates'] = []
        scores[name]['time']  = []
        scores[name]['score'] = 0

    #Append date (to get the last one)
    scores[name]['dates'].append(date)

    #Check time in all last times
    for _name in scores:
        for t in scores[_name]['time']:
            if t == time: #We got a match !
                rekt = _name #sorry :(
                scores[_name]['score'] -= 1
                scores[name] ['score'] -= 1
                scores[_name]['time'].remove(t)

    #Someone get rekt
    if rekt is None:
        scores[name]['time'].append(time)
        scores[name]['score'] += 1

    return scores, rekt;

def _add_scores(config, msgs, scores=None, imgSet=None, rektSet=None):
    if scores is None:
        scores = {}
    if imgSet is None:
        imgSet = []
    if rektSet is None:
        rektSet = []

    for msg in msgs:
        #if this msg contains a picture
        if ("attachments" in msg) and (msg["attachments"] is not None) and (len(msg["attachments"]) > 0):
            url = msg["attachments"][0]["image_url"]
            imgSet.append(url)

            date = dt.strptime(msg["ts"], config["parseDate"])
            name = msg["u"]["username"]
            scores, rekt = _add_score(scores, name, date)
            if rekt is not None:
                rektSet.append([rekt, name, date])

    return scores, imgSet, rektSet;

def _get_last_date(scores):
    ld = None
    
    for name in scores:
        for d in scores[name]['dates']:
            if (ld is None) or (d>ld):
                ld = d

    return ld




#############################
####### MAIN FUNCTION #######
#############################

def manage(sumUp = False, toChat=True):
    config = ids.config
    rocket = _connect(config)
    
    if sumUp:
        msgs = _retrieve_msgs(rocket, config, True)
        scores, imgSet, rektSet = _add_scores(config, msgs)
    else:
        scores, imgSet, rektSet = _retrieve_scores()
        date = _get_last_date(scores)
        msgs = _retrieve_msgs(rocket, config, since=date)
        scores, imgSet, rektSet = _add_scores(config, msgs, scores, imgSet, rektSet)
        
    _print_scores(rocket, config, scores, imgSet, rektSet, toChat)
    _save_scores(scores, imgSet, rektSet)



