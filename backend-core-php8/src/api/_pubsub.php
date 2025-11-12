<?php

/**
 * _pubsub testing
 *  gcloud pubsub topics create cloudframework-pubsub-test --project={your-project}
 *  gcloud pubsub subscriptions create cloudframework-pubsub-test-sub --topic cloudframework-pubsub-test  --project={your-project}
 *  https://console.cloud.google.com/cloudpubsub/topic/list?folder=&organizationId=&project={your-project}
 *  It requires: Pub/Sub Admin
 */
class API extends RESTful
{

    var $topicName = 'cloudframework-pubsub-test';
    var $subscriptionName = 'cloudframework-pubsub-test-sub';
    function main()
    {
        /** @var PubSub $pubsub */
        $pubsub = $this->core->loadClass('PubSub',['projectId'=>$this->core->gc_project_id]);
        if($pubsub->error) return($this->setErrorFromCodelib('system-error',$pubsub->errorMsg));

        $topics='';
        $message_id=null;
        $subcription=null;
        $subcriptions=[];

        $topics = $pubsub->getTopics();
        if($pubsub->error) return $this->setErrorFromCodelib('system-error',$pubsub->errorMsg);

        $message_id = $pubsub->pushMessage('Test message '.uniqid('pubsub'),['fieldTest'=>'FieldValue'],$this->topicName);
        if($pubsub->error) return $this->setErrorFromCodelib('system-error',$pubsub->errorMsg);

        $pubsub->subscribeTo($this->subscriptionName,$this->topicName);
        if($pubsub->error) return $this->setErrorFromCodelib('system-error',$pubsub->errorMsg);

        $subcriptions = $pubsub->getSubscriptions();
        if($pubsub->error) return $this->setErrorFromCodelib('system-error',$pubsub->errorMsg);


        $pullMessages = $pubsub->pullMessages($this->subscriptionName,$this->topicName);
        if($pubsub->error) return $this->setErrorFromCodelib('system-error',$pubsub->errorMsg);

        $pubsub->acknowledgeLastMessages();
        if($pubsub->error) return $this->setErrorFromCodelib('system-error',$pubsub->errorMsg);

        return $this->addReturnData(['topics'=>$topics,'subscriptions'=>$subcriptions, 'message_id' =>$message_id,'messages'=>$pullMessages,]);



        // Create a Subscription over a topic
        //if(!($subscription = $pubsub->getSubscription('testsubscription','testtopic'))) return($this->setErrorFromCodelib('system-error',$pubsub->errorMsg));



        return;
        // Create a Topic
        if(!($topic = $pubsub->getTopic('testtopic'))) return($this->setErrorFromCodelib('system-error',$pubsub->errorMsg));



        //$this->addReturnData([$topic->info(),$pubsub->getSubscriptions(),$pubsub->getSubscriptionMessages($subscription)]);
        $this->addReturnData([$topic->info(),$subscription->info(),$pubsub->getSubscriptions(),$message_id]);

    }
}
