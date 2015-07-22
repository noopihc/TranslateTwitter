#!/usr/bin/python
# -*- coding: utf-8 -*-
# get list of tweets for translation

import pymysql, datetime, time, beanstalkc, json, urllib, requests, config, gc, sys
from twitter import *

# garbage collection
gc.enable()

db = pymysql.connect(host=config.MYSQL_HOST,user=config.MYSQL_USER,passwd=config.MYSQL_PASS,db=config.MYSQL_NAME,charset='utf8')

# microsoft translation function
def translator (twext,twlang):
  try:
    # get the access token from the microsoft translation api
    args = {
      'client_id': '*',
      'client_secret': '*',
      'scope': 'http://api.microsofttranslator.com',
      'grant_type': 'client_credentials'
      }

    oauthurl = 'https://datamarket.accesscontrol.windows.net/v2/OAuth2-13'
    oauth = json.loads(requests.post(oauthurl,data=urllib.urlencode(args)).content)

    # send the tweet for translation
    transargs = {
      'text': twext,
      'to': twlang,
      'from': ''
      }

    headers = {
      'Authorization': 'Bearer ' + oauth['access_token']}

    transurl = 'http://api.microsofttranslator.com/V2/Ajax.svc/Translate?'
    translated = requests.get(transurl + urllib.urlencode(transargs), headers=headers)

    return translated.content

  # something broke in translation
  except Exception, e:
    with open('/home/translatetweet/python/logs/translatorerrors.log', 'a') as f1:
      print >>f1, str(datetime.datetime.now()) + " err: " + str(e)

    return "TRANSLATIONFAILED"


# setup beanstalk queue system
beanstalk = beanstalkc.Connection(host=config.BEAN_HOST, port=11303)
beanstalk.use('translatetweet')
beanstalk.watch('translatetweet')

# continously monitor queue of tweets that need to be translated
try:
  #print beanstalk.stats_tube('translatetweet')['current-jobs-ready']
  if beanstalk.stats_tube('translatetweet')['current-jobs-ready'] > 0: #if job is None:
    # found a tweet in queue
    job = beanstalk.reserve()
    data = json.loads(job.body)

    tweet = data[0]      # tweet text
    tlang = data[1]      # translate to language
    userid = data[2]     # twitter id

    # get user details for active following users
    c0 = db.cursor()
    query = "select TWITTERTOKEN, TWITTERSECRET from twttrtrnslt.USERS where TWITTERID = '%s' and ACTIVE = 1" % (userid)
    c0.execute(query)

    isactive = c0.rowcount
    result = c0.fetchone()
    c0.close()

    # this is a active user that follows @NoUpload
    if isactive > 0:      
      twittoken = result[0]
      twitsecret = result[1]

      # call translator, get rid of quotes returned around tweet
      tweet = tweet.encode('utf-8')
      translated = translator(tweet,tlang)
      #translated = translated.decode('utf-8-sig')
      #translated = translated.encode('utf-8')
      translated = translated.strip('\xef\xbb\xbf')
      translated = translated.strip('"')
      translated = translated.replace("\/", "/")

      # update user in db
      c1 = db.cursor()
      query = "update twttrtrnslt.USERS set TOTALTWEET = TOTALTWEET + 1 where TWITTERID = '%s'" % (userid)
      c1.execute(query)
      db.commit()
      c1.close()

      t = Twitter(auth=OAuth(twittoken,twitsecret,config.APIKEY,config.APISECRET))

      if translated != "TRANSLATIONFAILED":
        # post translated tweet to users timeline
        t.statuses.update(status=translated)
      else:
        t.statuses.update(status="sorry translation failed")

    # delete job from translatetweet tube
    job.delete()

    db.close()

  sys.exit()

except Exception, e:
  with open('/home/translatetweet/python/logs/tweeterrors.log', 'a') as f1:
    print >>f1, str(datetime.datetime.now()) + " err: " + str(e)
    # delete job from translatetweet tube
    job.delete()

