<?php

namespace Knp\Bundle\LastTweetsBundle\Twitter\LastTweetsFetcher;

use Knp\Bundle\LastTweetsBundle\Twitter\Tweet;

class RedisCacheFetcher implements FetcherCacheableInterface
{
    protected $fetcher;
    protected $redisClient;

    public function __construct(FetcherInterface $fetcher, $redisClient)
    {
        $this->fetcher = $fetcher;
        $this->redisClient = $redisClient;
    }
    
    public function fetch($username, $limit = 10, $forceRefresh = false)
    {
        if (!is_array($username)) {
            $username = array((string) $username);
        }

        $cacheId = 'knp_last_tweets_' . strtolower(implode('_', $username)) . '_' . $limit;

        $encodedTweets = $this->redisClient->get($cacheId);
        
        if ($forceRefresh || is_null($encodedTweets)) {
            $tweets = $this->fetcher->fetch($username, $limit);
            $this->redisClient->set($cacheId, $this->encodeTweets($tweets));
        } else {
            $tweets = $this->decodeTweets($encodedTweets);
        }
        
        return $tweets;
    }

    protected function encodeTweets($tweetObjects)
    {
        $tweets = array();

        foreach ($tweetObjects as $tweetObject) {
            $tweet = array(
                'id' => $tweetObject->getId(),
                'created_at' => $tweetObject->getCreatedAt()->format('D M d H:i:s O Y'),
                'text' => $tweetObject->getText(),
                'username' => $tweetObject->getUsername()
            );
            if ($tweetObject->isReply()) {
                $tweet['in_reply_to_screen_name'] = true;
            }
            if ($tweetObject->isRetweet()) {
                $tweet['retweeted_status'] = true;
            }
            $tweets[] = $tweet;
        }

        return json_encode($tweets);
    }

    protected function decodeTweets($encodedTweets)
    {
        $tweets = array();
        $tweetsArray = json_decode($encodedTweets, true);

        foreach ($tweetsArray as $tweetArray) {
            $tweets[] = new Tweet($tweetArray);
        }

        return $tweets;
    }
    
    public function forceFetch($username, $limit = 10)
    {
        return $this->fetch($username, $limit, true);
    }
}
