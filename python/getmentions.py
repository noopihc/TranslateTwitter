#!/usr/bin/python
# -*- coding: utf-8 -*-
# get list of @NoUpload mentions for translations

import pymysql, time, datetime, beanstalkc, json, config, re, gc, sys
from twitter import *

# garbage collection
gc.enable()

# setup beanstalk queue system
beanstalk = beanstalkc.Connection(host=config.BEAN_HOST,port=11303)

try:
  # get last mentionid for @NoUpload
  db = pymysql.connect(host=config.MYSQL_HOST,user=config.MYSQL_USER,passwd=config.MYSQL_PASS,db=config.MYSQL_NAME,charset='utf8')
  c0 = db.cursor()
  query = "select LASTTWEET from twttrtrnslt.USERS where TWITTERID = '%s'" % (config.OWNERID)
  c0.execute(query)
  lastmentionid = c0.fetchone()[0]
  c0.close()
  db.close()

  # if no lastmentionid start from whatever
  if lastmentionid is None:
    lastmentionid = '12345'

  # get mentions of @noupload
  t = Twitter(auth=OAuth(config.OWNERTOKEN,config.OWNERSECRET,config.APIKEY,config.APISECRET))
  dm = t.statuses.mentions_timeline(count=50,since_id=lastmentionid)

except Exception, e:
  dm = []

try:
  # reverse the list because twitter returns the newest tweet first
  for item in reversed(dm):
    mentionid = item['id']
    userid = item['user']['id']
    tweet = item['text']

    # remove @NoUpload from tweet ignoring case
    renoupload = re.compile(re.escape('@NoUpload'), re.IGNORECASE)
    tweet = renoupload.sub('', tweet)

    lowtwt = tweet.lower()
    languages = ['ar','bg','ca','chs','cht','cs','da','nl','en','et','fi','fr','de','el','ht','he','hi','mww','hu','id','it','ja','tlh','ko','lv','lt','ms','mt','no','fa','pl','pt','ro','ru','sk','sl','es','sv','th','tr','uk','ur','vi','cy']
    langlist = []

    # splits tweet and creates list of all hashtags in tweet
    hashlist = [i[1:] for i in tweet.split() if i.startswith("#")]

    # loop through list of hashtags from tweet
    for hashtag in hashlist:
      hashtag = hashtag.lower()

      if hashtag == "all":
        langlist = languages
      elif hashtag in languages:
        # check if hashtag is in the languages list for translation
        langlist.append(hashtag)

      hashtag = "#" + hashtag
      # 1. remove hashtags ignoring case, 2. remove and join multiple whitespaces
      tweet = ' '.join((re.sub(hashtag, '', tweet, flags=re.IGNORECASE)).split())

    beanstalk.use('translatetweet')

    # loop through list of languages to translate tweet into
    for tranlang in langlist:
 
      # using my own shorter codes for chinese
      if tranlang == "chs":
        tranlang = "zh-CHS"
      elif tranlang == "cht":
        tranlang = "zh-CHT"

      data = [tweet,tranlang,userid]
 
      # format in json and add to translation queue
      beanstalk.put(json.dumps(data))

    # set @NoUpload LASTWEET to the mentionid. Only new mentions are translated. mentionid will be the since_id parameter in the Twitter api so we do not translate old mentions.		
    db = pymysql.connect(host=config.MYSQL_HOST,user=config.MYSQL_USER,passwd=config.MYSQL_PASS,db=config.MYSQL_NAME,charset='utf8')
    c0 = db.cursor()
    query = "update twttrtrnslt.USERS set LASTTWEET = '%s' where TWITTERID = '%s'" % (mentionid,config.OWNERID)
    c0.execute(query)
    db.commit()
    c0.close()
    db.close()

  sys.exit()

except Exception, e:
  with open('/home/translatetweet/python/logs/mentionerrors.log', 'a') as f1:
    print >>f1, str(datetime.datetime.now()) + " err: " + str(e)


