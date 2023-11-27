<?php
/**
 * PubSub CloudFramework Class

 * last-update: 2021-03
 */
use Google\Cloud\PubSub\PubSubClient;
use Google\Cloud\PubSub\Subscription;
/**
 * [$pubsub = $this->core->loadClass('PubSub');]  Class to handle Pub/Sub of GCP
 *
 * https://cloud.google.com/pubsub/docs/quickstart-client-libraries
 * It requires to execute for testing:
 * gcloud pubsub topics create cloudframework-pubsub-test --project={your-project}
 * gcloud pubsub subscriptions create cloudframework-pubsub-test-sub --topic cloudframework-pubsub-test --project={your-project}
 * https://console.cloud.google.com/cloudpubsub/topic/list?folder=&organizationId=&project={your-project}
 * It requires: Pub/Sub Admin
 * @package CoreClasses
 */
class PubSub
{

    private $core = null;
    /** @var $client PubSubClient|null  */
    var $client = null;
    /** @var $topic \Google\Cloud\PubSub\Topic|null  */
    var $topic = null;
    var $topicName = '';
    /** @var $subscription \Google\Cloud\PubSub\Subscription|null  */

    var $lastMessages = null;
    /** @var Subscription $lastSubscription */
    var $lastSubscription = null;

    var $subscription = null;
    var $error = false;
    var $errorMsg = null;

    /**
     * DataSQL constructor.
     * @param Core $core
     */
    function __construct(Core7 &$core, $options=[])
    {


        // Get core function
        $this->core = $core;
        $projectId = $this->core->gc_project_id;
        if(isset($options['projectId'])) $projectId = $options['projectId'];

        if(!$projectId) return($this->addError('Missing GoogleProjectId config var'));
        try {
            $this->core->__p->add('init PubSub',__CLASS__,'note');
            $this->client = new PubSubClient([
                'projectId' => $projectId,
            ]);
            $this->core->__p->add('init PubSub',__CLASS__,'endnote');
        } catch (Exception $error) {
            $this->core->__p->add('init PubSub',__CLASS__,'endnote');
            return($this->addError($error->getCode().': '.$error->getMessage()));

        }
    }

    /**
     * Return the current subscriptions for the Application
     * @return array|void
     */
    public function getTopics() {
        if(!is_object($this->client)) return($this->addError('missing pubsub client'));

        //region SET $ret from $topics = $this->client->topics();
        try {
            $this->core->__p->add('getTopics','PubSub','note');
            /** @var Google\Cloud\Core\Iterator\ItemIterator $topics */
            $topics = $this->client->topics();
            $ret = [];
            if(is_object($topics))
                foreach ($topics as $topic) {
                    $ret[] = $topic->info();
                }
        } catch(Exception $e) {
            $this->core->__p->add('getTopics','PubSub','endnote');
            return $this->addError($e->getCode().': '.$e->getMessage());
        }
        //endregion

        $this->core->__p->add('getTopics','PubSub','endnote');
        return $ret;
    }

    /**
     * Return the current subscriptions for the Application
     * @return array|void
     */
    public function getSubscriptions() {
        if(!is_object($this->client)) return($this->addError('missing pubsub client'));
        $this->core->__p->add('getSubscriptions','PubSub','note');
        //region SET $ret from  $subscriptions = $this->client->subscriptions();
        try {
            $subscriptions = $this->client->subscriptions();
            $ret = [];
            if(is_object($subscriptions))
                foreach ($subscriptions as $subscription) {
                    $ret[] = $subscription->info();
                }

        } catch(Exception $e) {
            $this->core->__p->add('getSubscriptions','PubSub','endnote');
            return $this->addError($e->getCode().': '.$e->getMessage());
        }
        //endregion

        $this->core->__p->add('getSubscriptions','PubSub','endnote');
        return $ret;
    }

    /**
     * Return the $subscription subscriptions for the Application
     * @params $subscription string subscription to be returned
     * @params $topic string optionally $topic
     * @return array|void
     */
    public function getSubscription($subscription,$topic=null) {
        if(!is_object($this->client)) return($this->addError('missing pubsub client'));
        $this->core->__p->add('getSubscription','PubSub','note');
        //region SET $ret from  $subscriptions = $this->client->subscriptions();
        try {
            $subscription = $this->client->subscription($subscription,$topic);
            _printe($subscription);
            $ret = [];
            if(is_object($subscriptions))
                foreach ($subscriptions as $subscription) {
                    $ret[] = $subscription->info();
                }

        } catch(Exception $e) {
            $this->core->__p->add('getSubscription','PubSub','endnote');
            return $this->addError($e->getCode().': '.$e->getMessage());
        }
        //endregion

        $this->core->__p->add('getSubscription','PubSub','endnote');
        return $ret;
    }

    /**
     * Create a subscripion
     * https://cloud.google.com/pubsub/docs/samples/pubsub-create-pull-subscription
     * @param $subscription
     * @param $topic
     * @return \Google\Cloud\PubSub\Subscription|null
     */
    public function subscribeTo($subscriptionName,$topicName) {
        if(!is_object($this->client)) return;

        try {
            $this->core->__p->add('subscribeTo','PubSub','note');
            $subscription = $this->client->subscription($subscriptionName,$topicName);
            if(!$subscription->exists()) {
                $subscription = $this->client->subscribe($subscriptionName,$topicName);
            }
        } catch(Exception $e) {
            $this->core->__p->add('subscribeTo','PubSub','endnote');
            return $this->addError($e->getCode().': '.$e->getMessage());
        }

        $this->core->__p->add('subscribeTo','PubSub','endnote');
        return $subscription->info();

    }

    /**
     * DELETE a subscripion
     * https://cloud.google.com/pubsub/docs/samples/pubsub-delete-subscription
     * @param $subscription
     * @param $topic
     * @return \Google\Cloud\PubSub\Subscription|null
     */
    public function unsubscribeTo($subscriptionName,$topicName) {
        if(!is_object($this->client)) return;
        $info = ['name'=>$subscriptionName,'topic'=>$topicName,'status'=>'does-not-exist'];
        try {
            $this->core->__p->add('unsubscribeTo','PubSub','note');
            $subscription = $this->client->subscription($subscriptionName,$topicName);
            if($subscription->exists()) {
                $info = $subscription->info();
                $subscription->delete();
            }
        } catch(Exception $e) {
            $this->core->__p->add('unsubscribeTo','PubSub','endnote');
            return $this->addError($e->getCode().': '.$e->getMessage());
        }

        $this->core->__p->add('unsubscribeTo','PubSub','endnote');
        return $info;
    }

    /**
     * Publish a message in a TopicName
     * @param $message
     * @param array $attributes
     * @param null $topicName
     * @return array|void
     */
    public function pushMessage($message,$attributes=[],$topicName=null) {

        if(!is_object($this->client)) return;
        $this->core->__p->add('pushMessage','PubSub','note');
        //region SET $this->topic. If error return;
        if($topicName && $topicName != $this->topicName) {
            try {
                $this->topic = $this->client->topic($topicName);
                $this->topicName = $topicName;
            } catch (Exception $e) {
                $this->core->__p->add('pushMessage','PubSub','endnote');
                return $this->addError($e->getCode().': '.$e->getMessage());
            }
        }
        if(!is_object($this->topic)) {
            $this->core->__p->add('pushMessage','PubSub','endnote');
            return $this->addError('Missing topic: '.$this->topicName);
        }
        //endregion

        //region SET $message_ids  = publishing $message and $attributes in $this->topic
        $topicData = ['data'=>$message];
        if($attributes) {
            $topicData['attributes'] = $attributes;
        }

        try {
            $message_ids = $this->topic->publish($topicData);
        } catch (Exception $e) {
            $this->core->__p->add('pushMessage','PubSub','endnote');
            return $this->addError($e->getCode().': '.$e->getMessage());
        }
        //endregion

        $this->core->__p->add('pushMessage','PubSub','endnote');
        return($message_ids);
    }

    /**
     * @param $topic
     * @return \Google\Cloud\PubSub\Topic|null
     */
    public function pullMessages($subscriptionName,$topicName=null,$acknowledge=false) {

        if(!is_object($this->client)) return($this->addError('missing pubsub client'));
        $ret = [];
        try {
            $this->core->__p->add('pullMessages','PubSub','note');
            $subscription = $this->client->subscription($subscriptionName,$topicName);
            $this->lastMessages = [];
            $this->lastSubscription = ($acknowledge)?null:$subscription;
            foreach ($subscription->pull() as $message) {
                $info =$message->info();
                $ret[]=$info['message'];
                // $acknowledge or keepit to acknowledge in $this->acknowledgeLastMessages
                if($acknowledge) {
                    $subscription->acknowledge($message);
                } else {
                    $this->lastMessages[$info['message']['messageId']] = $message;
                }
            }
        } catch(Exception $e) {
            $this->core->__p->add('pullMessages','PubSub','endnote');
            return $this->addError($e->getCode().': '.$e->getMessage());
        }

        $this->core->__p->add('pullMessages','PubSub','endnote');
        return $ret;
    }

    /**
     * @param $id null optional param to acknowledge a specific message
     * @return \Google\Cloud\PubSub\Topic|null
     */
    public function acknowledgeLastMessages($id=null) {
        if(!$this->lastMessages) return true;
        if(!is_object($this->lastSubscription)) return $this->addError('missing lastSubscription object');
        try {
            $this->core->__p->add('acknowledgeLastMessages','PubSub','note');
            if($id) {
                if(!isset($this->lastMessages[$id])) return($this->addError('acknowledgeLastMessages($id=null) has received a $id that does not exist: '.$id));
                $this->lastSubscription->acknowledge($this->lastMessages[$id]);
            } else {
                $this->lastSubscription->acknowledgeBatch(array_values($this->lastMessages));
            }
            $this->lastMessages=[];
            $this->lastSubscription=null;
        } catch(Exception $e) {
            $this->core->__p->add('acknowledgeLastMessages','PubSub','endnote');
            return $this->addError($e->getCode().': '.$e->getMessage());
        }

        $this->core->__p->add('acknowledgeLastMessages','PubSub','endnote');
        return true;
    }

    /**
     * Add Error message
     * @param $err
     */
    private function addError($err) {
        $this->error = true;
        $this->errorMsg[] = $err;
    }

}
