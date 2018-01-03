#https://rocket.chat/docs/developer-guides/rest-api/

from pprint import pprint
from rocketchat_API.rocketchat import RocketChat
from datetime import datetime as dt
from datetime import time as tm
import secrets
import ids

config = ids.config

scores = {}
imgSet = []
rektSet = []

def _add_score(scores, name, date):
    time = [date.hour, date.minute]
    rekt = None
    
    
    if name not in scores:
        scores[name] = {}
        scores[name]['dates'] = []
        scores[name]['time']  = []
        scores[name]['score'] = 0

    scores[name]['dates'].append(date)

    #check score
    for _name in scores:
        for t in scores[_name]['time']:
            if t == time:
                rekt = _name
                scores[_name]['score'] -= 1
                scores[name] ['score'] -= 1
                scores[_name]['time'].remove(t)

    if rekt is None:
        scores[name]['time'].append(time)
        scores[name]['score'] += 1

    return rekt;

    
def _connect(config):
    rocket = RocketChat(config['user'], config['pass'], config['type'] + '://' + config['host'])
    return rocket


def _sum_up(rocket, config, scores, imgSet, rektSet):
    hist = rocket.channels_history(config['channel'], count=100).json()

    msgs = hist['messages']

    for msg in msgs:
        if ("attachments" in msg) and (msg["attachments"] is not None):
            url = msg["attachments"][0]["image_url"]
            imgSet.append(url)

            date = dt.strptime(msg["ts"], config["parseDate"])
            name = msg["u"]["username"]
            rekt = _add_score(scores, name, date)
            if rekt is not None:
                rektSet.append([rekt, name, date])

def _print_scores(rocket, config, scores, imgSet, rektSet, toChat=False):

    url = secrets.choice(imgSet)
    alias = url[(url.rfind("/")+1):]

    print("avatar: " + url + "  alias: "+alias)
    pprint(scores)
    
    out = "Tableau des scores : \n"
    for name in scores:
        out += name + " a totalisé " + str(scores[name]['score']) + " points\n"
    print (out)
    if toChat:
        rocket.chat_post_message(out, room_id=config['channel'], avatar=url, alias=alias)

    if len(rektSet) != 0:
        out = "Tableau des rekts : \n"
        for rekts in rektSet:
            out += rekts[1] + " a rekt " + rekts[0] + " le " + str(rekts[2])
        print (out)
        if toChat:
            rocket.chat_post_message(out, room_id=config['channel'], avatar=url, alias="Rektor")

    




rocket = _connect(config)
_sum_up(rocket, config, scores, imgSet, rektSet)
_print_scores(rocket, config, scores, imgSet, rektSet, False)



